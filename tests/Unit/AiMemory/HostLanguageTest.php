<?php

namespace Tests\Unit\AiMemory;

use App\Services\AiMemory\AiMemoryDatabase;
use App\Services\AiMemory\AiMemoryTime;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The reader classes are copied byte-for-byte into samirhv-site (ADR-006),
 * which renders Portuguese and d/m/Y. Whatever differs between the two apps
 * therefore has to come from the host's config and translation files. These
 * tests hold that from this side: a language named in `aimemory.locale` reaches
 * the text, placeholders survive translation, and the default date format is
 * config.
 */
class HostLanguageTest extends TestCase
{
    /** 2026-09-05 12:34:56 UTC, in microseconds. */
    private const MICROS = 1_788_611_696_000_000;

    private string $langDir;

    protected function setUp(): void
    {
        parent::setUp();

        // The same mechanism samirhv-site uses: a JSON file keyed by the English sentence.
        $this->langDir = sys_get_temp_dir().'/aimw-lang-'.bin2hex(random_bytes(4));
        mkdir($this->langDir);
        file_put_contents($this->langDir.'/pt_BR.json', json_encode([
            'still open' => 'em aberto',
            'The file [:path] does not exist on this host.' => 'O arquivo [:path] não existe neste servidor.',
        ]));
        $this->app['translator']->getLoader()->addJsonPath($this->langDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langDir);

        parent::tearDown();
    }

    public function test_with_no_host_locale_the_text_is_the_english_key(): void
    {
        config(['aimemory.locale' => null]);

        $this->assertSame('still open', AiMemoryTime::duration(self::MICROS, null));
    }

    public function test_the_host_locale_wins_over_the_request_locale(): void
    {
        // samirhv-site's admin routes render in the bare (English) locale while
        // their text is Portuguese, so the reader's language cannot follow the request.
        app()->setLocale('en');
        config(['aimemory.locale' => 'pt_BR']);

        $this->assertSame('em aberto', AiMemoryTime::duration(self::MICROS, null));
    }

    public function test_a_translated_notice_keeps_its_placeholders_filled(): void
    {
        config(['aimemory.locale' => 'pt_BR', 'aimemory.path' => '/nowhere/memory.sqlite']);

        $db = new AiMemoryDatabase;

        $this->assertFalse($db->isAvailable());
        $this->assertSame('O arquivo [/nowhere/memory.sqlite] não existe neste servidor.', $db->unavailableReason());
    }

    public function test_the_default_date_format_is_config(): void
    {
        config(['aimemory.timezone' => 'UTC', 'aimemory.date_format' => 'd/m/Y H:i']);

        $this->assertSame('05/09/2026 12:34', AiMemoryTime::format(self::MICROS));
        $this->assertSame('2026-09-05', AiMemoryTime::format(self::MICROS, 'Y-m-d'), 'an explicit format still wins');
    }
}
