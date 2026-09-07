<?php

namespace Tests\Unit\AiMemory;

use Tests\Support\AiMemorySchemaProbe;
use Tests\Support\SeedsAiMemoryIndex;
use Tests\TestCase;

/**
 * Tests for the canary itself.
 *
 * An alarm nobody has ever seen ring is not an alarm. AiMemorySchemaCanaryTest
 * is skipped on every machine today (no real index to point it at), so without
 * these two tests its silence would prove nothing: a probe that swallowed every
 * error would look exactly the same.
 *
 * So: it goes green on a healthy schema, and red on one where a single column
 * has been renamed out from under the panel — which is precisely the drift the
 * hand-written fixture cannot notice on its own.
 */
class AiMemorySchemaProbeTest extends TestCase
{
    use SeedsAiMemoryIndex;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to build the ai-memory fixture.');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUpAiMemoryIndex();

        parent::tearDown();
    }

    public function test_the_probe_is_quiet_when_the_schema_answers_everything(): void
    {
        $this->seedAiMemoryIndex();

        $failures = AiMemorySchemaProbe::failures();

        $this->assertSame([], $failures, AiMemorySchemaProbe::describe($failures));
    }

    public function test_the_probe_rings_when_a_column_is_renamed_out_from_under_the_panel(): void
    {
        // Same fixture, with `pages.path` renamed. Nothing else changes: the
        // rows are identical and the table is still called `pages`.
        $this->seedAiMemoryIndex('pages.path');

        $failures = AiMemorySchemaProbe::failures();

        $this->assertNotEmpty($failures, 'A renamed column must not pass the probe.');
        $this->assertArrayHasKey('PageRepository::paginate', $failures);
        $this->assertStringContainsString('no such column', $failures['PageRepository::paginate']);
    }

    public function test_a_renamed_column_is_invisible_to_the_totals_which_is_why_the_probe_exists(): void
    {
        // StatsRepository swallows "no such table/column" on purpose, so an
        // older ai-memory does not take the dashboard down. The cost is that
        // the dashboard cannot be the thing that notices drift.
        $this->seedAiMemoryIndex('pages.path');

        $failures = AiMemorySchemaProbe::failures();

        $this->assertArrayNotHasKey('StatsRepository::counts', $failures);
    }
}
