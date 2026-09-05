<?php

namespace App\Services\AiMemory;

use Illuminate\Support\Carbon;

/**
 * Formatting of ai-memory timestamps for the views. ai-memory stores time as
 * INTEGER = microseconds since the epoch (UTC); these helpers convert to the
 * display timezone (config `aimemory.timezone`) and format it. Static on
 * purpose, so Blade can call it directly.
 *
 * Formats are ISO-ish (Y-m-d) rather than locale-specific: this panel is read
 * by operators, and 03-04 must never be ambiguous between March and April.
 */
class AiMemoryTime
{
    public static function toCarbon(int|float|null $micros): ?Carbon
    {
        if (! $micros || $micros <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) ($micros / 1_000_000))
            ->timezone((string) config('aimemory.timezone', 'UTC'));
    }

    /** Formatted date/time, or "—" when empty. */
    public static function format(int|float|null $micros, string $format = 'Y-m-d H:i'): string
    {
        return self::toCarbon($micros)?->format($format) ?? '—';
    }

    /** "3 days ago", or "—". */
    public static function human(int|float|null $micros): string
    {
        return self::toCarbon($micros)?->diffForHumans() ?? '—';
    }

    /** Duration between start and end (a session), tolerating a null end. */
    public static function duration(int|float|null $start, int|float|null $end): string
    {
        $a = self::toCarbon($start);
        if ($a === null) {
            return '—';
        }
        $b = self::toCarbon($end);
        if ($b === null) {
            return 'still open';
        }

        $seconds = abs($b->getTimestamp() - $a->getTimestamp());
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? "{$h}h {$m}min" : ($m > 0 ? "{$m}min" : "{$seconds}s");
    }
}
