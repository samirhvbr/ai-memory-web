<?php

namespace Tests\Unit\AiMemory;

use App\Models\AiMemoryStatSnapshot;
use App\Services\AiMemory\DashboardSummary;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The presentation maths behind the dashboard. No database is involved — that
 * is the point of the class, and it is why these tests run everywhere, including
 * on a machine with no pdo_sqlite.
 */
class DashboardSummaryTest extends TestCase
{
    private DashboardSummary $summary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->summary = new DashboardSummary;
    }

    public static function niceMaxCases(): array
    {
        return [
            'zero stays drawable' => [0, 1],
            'tiny values are left alone' => [3, 3],
            'five is the boundary' => [5, 5],
            'six needs no rounding: the step is 1 below ten' => [6, 6],
            'twelve rounds to the half-decade' => [12, 15],
            'seventy-eight' => [78, 80],
            'nine hundred and fifty-nine' => [959, 1000],
            'sixty-five thousand' => [65453, 70000],
        ];
    }

    #[DataProvider('niceMaxCases')]
    public function test_nice_max_rounds_the_axis_ceiling(int $max, int $expected): void
    {
        $this->assertSame($expected, $this->summary->niceMax($max));
    }

    public function test_nice_max_matches_the_javascript_copy(): void
    {
        // dashboard.js carries the same function, because the live refresh has
        // to land on the ceiling the server drew. If this list ever disagrees
        // with public/js/dashboard.js, the axis jumps on the first poll.
        $js = file_get_contents(base_path('public/js/dashboard.js'));

        $this->assertStringContainsString('const niceMax = (max) => {', $js);
        $this->assertStringContainsString('if (max <= 5) return Math.max(max, 1);', $js);
        $this->assertStringContainsString('10 ** Math.floor(Math.log10(max)) / 2', $js);
    }

    public function test_series_summarises_a_daily_series(): void
    {
        $series = $this->summary->series([
            '2026-09-01' => 4,
            '2026-09-02' => 0,
            '2026-09-03' => 12,
            '2026-09-04' => 8,
        ]);

        $this->assertSame(24, $series['total']);
        $this->assertSame(6, $series['avg']);
        $this->assertSame(12, $series['max']);
        $this->assertSame(15, $series['top']);
        $this->assertSame('2026-09-03', $series['peak_day']);
        $this->assertSame(8, $series['today']);
        $this->assertSame(4, $series['days']);
    }

    public function test_series_reports_the_most_recent_peak_on_a_tie(): void
    {
        $series = $this->summary->series([
            '2026-09-01' => 7,
            '2026-09-02' => 3,
            '2026-09-03' => 7,
        ]);

        $this->assertSame('2026-09-03', $series['peak_day']);
    }

    public function test_series_of_only_zeros_has_no_peak_day(): void
    {
        $series = $this->summary->series(['2026-09-01' => 0, '2026-09-02' => 0]);

        $this->assertNull($series['peak_day']);
        $this->assertSame(1, $series['top'], 'the axis still needs a drawable ceiling');
    }

    public function test_series_of_no_days_does_not_divide_by_zero(): void
    {
        $series = $this->summary->series([]);

        $this->assertSame(0, $series['total']);
        $this->assertSame(0, $series['avg']);
        $this->assertSame(0, $series['days']);
    }

    public function test_delta_reports_the_real_number_of_days_it_spans(): void
    {
        Carbon::setTestNow('2026-09-05 10:00:00');

        // The 7-day window would like a snapshot from 08-29; the oldest one
        // inside it is from 08-25. The UI must say 11 days, not 7.
        $history = collect([
            $this->snapshot('2026-08-25', 100),
            $this->snapshot('2026-09-04', 180),
        ]);

        $delta = $this->summary->delta($history, 'observations', 200);

        $this->assertSame(100, $delta['value']);
        $this->assertSame(11, $delta['days']);

        Carbon::setTestNow();
    }

    public function test_delta_is_null_when_only_todays_snapshot_exists(): void
    {
        Carbon::setTestNow('2026-09-05 10:00:00');

        $history = collect([$this->snapshot('2026-09-05', 190)]);

        $this->assertNull($this->summary->delta($history, 'observations', 200));

        Carbon::setTestNow();
    }

    public function test_delta_is_null_without_any_snapshot(): void
    {
        $this->assertNull($this->summary->delta(collect(), 'observations', 200));
    }

    public function test_area_path_is_zero_based_and_uses_the_given_ceiling(): void
    {
        $path = $this->summary->areaPath([0, 50, 100], 100, 1000, 220);

        // zero sits on the baseline, the ceiling on top, and the area closes
        // back along the bottom edge
        $this->assertSame('M0,220 L500,110 L1000,0', $path['line']);
        $this->assertStringEndsWith('L1000,220 L0,220 Z', $path['area']);
        $this->assertSame(0.0, $path['last_y']);
    }

    public function test_sparkline_scales_to_the_shape_not_to_zero(): void
    {
        // 100→104 is a flat line on a zero-based scale; the sparkline is about
        // the SHAPE, so the lowest point sits at the bottom of the padded box.
        $path = $this->summary->sparkline([100, 102, 104], 100, 30, 3);

        $this->assertSame('M0,27 L50,15 L100,3', $path['line']);
    }

    public function test_a_single_value_still_draws_a_line(): void
    {
        $path = $this->summary->sparkline([42]);

        $this->assertNotSame('', $path['line']);
    }

    public function test_an_empty_series_draws_nothing_instead_of_failing(): void
    {
        $path = $this->summary->areaPath([], 10);

        $this->assertSame('', $path['line']);
        $this->assertSame('', $path['area']);
    }

    public function test_history_series_extracts_one_array_per_metric(): void
    {
        $history = collect([
            $this->snapshot('2026-09-03', 10, 2),
            $this->snapshot('2026-09-04', 20, 3),
        ]);

        $this->assertSame(
            ['observations' => [10, 20], 'pages' => [2, 3]],
            $this->summary->historySeries($history, ['observations', 'pages'])
        );
    }

    private function snapshot(string $day, int $observations, int $pages = 0): AiMemoryStatSnapshot
    {
        return new AiMemoryStatSnapshot([
            'captured_on' => $day,
            'observations' => $observations,
            'pages' => $pages,
        ]);
    }
}
