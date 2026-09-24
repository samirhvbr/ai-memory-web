<?php

namespace Tests\Unit\AiMemory;

use Tests\TestCase;

/**
 * app/Services/AiMemory/ runs in two apps: here, and byte-for-byte in
 * samirhv-site, whose CI fails as soon as its copy differs from this directory
 * (ADR-006). Two kinds of line written here would work in this app and break
 * only in the other one: an import of a class samirhv-site does not have, and a
 * config key it does not declare. Both are checked here, where the line is
 * written, rather than discovered there after a sync.
 */
class SharedReaderIsPortableTest extends TestCase
{
    /**
     * Imports outside the framework and this namespace that every host app must
     * provide. Adding one is a change to ADR-006, not only to this list.
     */
    private const HOST_CONTRACT = [
        'App\Models\AiMemoryStatSnapshot', // each app owns its snapshot table
    ];

    public function test_the_classes_import_only_the_framework_and_the_host_contract(): void
    {
        foreach ($this->sources() as $file => $source) {
            preg_match_all('/^use\s+([^;\s]+)\s*;/m', $source, $m);

            foreach ($m[1] as $import) {
                $portable = str_starts_with($import, 'Illuminate\\')
                    || str_starts_with($import, 'Carbon\\')
                    || str_starts_with($import, 'App\\Services\\AiMemory\\')
                    || ! str_contains($import, '\\') // a global class: Throwable, PDO
                    || in_array($import, self::HOST_CONTRACT, true);

                $this->assertTrue($portable, "{$file} imports {$import}. samirhv-site receives this file "
                    .'byte-for-byte and may not have that class. If both apps provide it, add it to '
                    .'HOST_CONTRACT and to ADR-006.');
            }
        }
    }

    public function test_every_config_key_they_read_is_declared_in_the_aimemory_file(): void
    {
        $declared = array_keys(require config_path('aimemory.php'));

        foreach ($this->sources() as $file => $source) {
            preg_match_all("/config\\(\\s*'([^']+)'/", $source, $m);

            foreach ($m[1] as $key) {
                $this->assertStringStartsWith('aimemory.', $key, "{$file} reads config('{$key}'). What may "
                    .'differ between the two apps lives under aimemory.*, which both declare (ADR-006).');
                $this->assertContains(substr($key, strlen('aimemory.')), $declared,
                    "{$file} reads config('{$key}'), which config/aimemory.php does not declare. "
                    .'samirhv-site has to declare it too before its copy is synced.');
            }
        }
    }

    /** @return array<string, string> file name => source */
    private function sources(): array
    {
        $files = glob(app_path('Services/AiMemory/*.php'));
        $this->assertNotEmpty($files, 'no reader class found: the check would pass on nothing');

        $out = [];
        foreach ($files as $path) {
            $out[basename($path)] = (string) file_get_contents($path);
        }

        return $out;
    }
}
