<?php

namespace App\Console\Commands;

use App\Models\AiMemoryStatSnapshot;
use App\Services\AiMemory\AiMemoryDatabase;
use App\Services\AiMemory\StatsRepository;
use Illuminate\Console\Command;
use Throwable;

/**
 * Writes the daily snapshot of ai-memory statistics into the durable
 * `ai_memory_stat_snapshots` table (this app's own database). Idempotent per
 * day (updateOrCreate on captured_on). Scheduled in routes/console.php.
 *
 * If ai-memory is unreachable (app moved off the host, layout or permission
 * changed), it writes NOTHING and exits successfully — the existing history is
 * preserved. Writing a row of zeros would be worse than writing nothing: the
 * charts would show a cliff that never happened.
 */
class SnapshotAiMemoryStats extends Command
{
    protected $signature = 'aimemory:snapshot';

    protected $description = 'Write a daily snapshot of the ai-memory statistics (durable history)';

    public function handle(AiMemoryDatabase $db, StatsRepository $stats): int
    {
        if (! $db->isAvailable()) {
            $this->warn("ai-memory unavailable at [{$db->path()}]: {$db->unavailableReason()} — snapshot skipped, history preserved.");

            return self::SUCCESS;
        }

        try {
            $counts = $stats->counts();
        } catch (Throwable $e) {
            // The probe passed and the queries still failed (permission changed
            // between the two, a lock, an ai-memory upgrade mid-run). Same rule
            // as above: preserve the history and report the reason.
            report($e);

            $this->warn("ai-memory unavailable at [{$db->path()}]: {$db->unavailableReason()} — snapshot skipped, history preserved.");

            return self::SUCCESS;
        }

        $snapshot = AiMemoryStatSnapshot::updateOrCreate(
            ['captured_on' => today()],
            [...$counts, 'raw_json' => $counts],
        );

        $this->info('Snapshot for '.$snapshot->captured_on->format('Y-m-d').': '
            .$counts['pages'].' pages, '
            .$counts['sessions'].' sessions, '
            .$counts['observations'].' observations.');

        return self::SUCCESS;
    }
}
