<?php

namespace Tests\Unit\AiMemory;

use App\Services\AiMemory\AiMemoryTime;
use Tests\TestCase;

/**
 * ai-memory stores time as INTEGER MICROSECONDS since the epoch, in UTC. Getting
 * that wrong by three orders of magnitude puts every timestamp in 1970 or in the
 * year 56000, so it is worth a test.
 */
class AiMemoryTimeTest extends TestCase
{
    /** 2026-09-05 12:34:56 UTC, in microseconds. */
    private const MICROS = 1_788_611_696_000_000;

    public function test_it_reads_microseconds_not_seconds(): void
    {
        config(['aimemory.timezone' => 'UTC']);

        $this->assertSame('2026-09-05 12:34', AiMemoryTime::format(self::MICROS));
    }

    public function test_it_renders_in_the_configured_timezone(): void
    {
        config(['aimemory.timezone' => 'America/Sao_Paulo']);

        $this->assertSame('2026-09-05 09:34', AiMemoryTime::format(self::MICROS));
    }

    public function test_an_absent_timestamp_is_an_em_dash_not_1970(): void
    {
        $this->assertSame('—', AiMemoryTime::format(null));
        $this->assertSame('—', AiMemoryTime::format(0));
        $this->assertSame('—', AiMemoryTime::human(null));
        $this->assertNull(AiMemoryTime::toCarbon(null));
    }

    public function test_duration_of_an_open_session_says_so(): void
    {
        $this->assertSame('still open', AiMemoryTime::duration(self::MICROS, null));
    }

    public function test_duration_picks_its_unit(): void
    {
        $minute = 60 * 1_000_000;

        $this->assertSame('45s', AiMemoryTime::duration(self::MICROS, self::MICROS + 45 * 1_000_000));
        $this->assertSame('7min', AiMemoryTime::duration(self::MICROS, self::MICROS + 7 * $minute));
        $this->assertSame('2h 5min', AiMemoryTime::duration(self::MICROS, self::MICROS + 125 * $minute));
    }

    public function test_duration_without_a_start_is_an_em_dash(): void
    {
        $this->assertSame('—', AiMemoryTime::duration(null, self::MICROS));
    }
}
