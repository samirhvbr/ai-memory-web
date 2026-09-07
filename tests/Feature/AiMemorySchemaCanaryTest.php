<?php

namespace Tests\Feature;

use Tests\Support\AiMemorySchemaProbe;
use Tests\Support\SeedsAiMemoryIndex;
use Tests\TestCase;

/**
 * The canary: every repository query, run against a REAL ai-memory index.
 *
 * It is DISARMED by default, and deliberately so. Arming it needs a real index
 * on this host, which is exactly what is still open — which ai-memory install
 * this panel reads (D0) and the filesystem access to it (F0). Until that is
 * settled there is nothing to point it at.
 *
 * Arm it by exporting the path of a real memory.sqlite:
 *
 *     AI_MEMORY_CANARY_PATH=/opt/ai-memory/data/db/memory.sqlite php artisan test
 *
 * What it catches that the rest of the suite cannot: the fixture in
 * SeedsAiMemoryIndex is a hand-written transcription of ai-memory's schema. If
 * upstream renames a column, the fixture keeps the old name and every other
 * test stays green while production breaks. This one fails instead.
 *
 * That the alarm actually rings is itself tested — see
 * Tests\Unit\AiMemory\AiMemorySchemaProbeTest.
 */
class AiMemorySchemaCanaryTest extends TestCase
{
    use SeedsAiMemoryIndex;

    protected function tearDown(): void
    {
        $this->cleanUpAiMemoryIndex();

        parent::tearDown();
    }

    public function test_a_real_index_still_answers_every_query_this_panel_makes(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to read an ai-memory index.');
        }

        $path = (string) env('AI_MEMORY_CANARY_PATH', '');

        if ($path === '') {
            $this->markTestSkipped(
                'Canary disarmed: set AI_MEMORY_CANARY_PATH to a real memory.sqlite. '
                .'Which index this panel reads is still open (D0/F0).'
            );
        }

        if (! is_file($path)) {
            $this->fail("AI_MEMORY_CANARY_PATH is set but there is no file at {$path}.");
        }

        $this->pointAppAtAiMemoryIndex($path);

        $failures = AiMemorySchemaProbe::failures();

        $this->assertSame(
            [],
            $failures,
            "The real ai-memory schema no longer answers these reads:\n".AiMemorySchemaProbe::describe($failures)
        );
    }
}
