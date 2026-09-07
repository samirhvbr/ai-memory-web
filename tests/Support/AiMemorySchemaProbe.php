<?php

namespace Tests\Support;

use App\Services\AiMemory\AiMemoryDatabase;
use App\Services\AiMemory\HandoffRepository;
use App\Services\AiMemory\ObservationRepository;
use App\Services\AiMemory\PageRepository;
use App\Services\AiMemory\ProjectRepository;
use App\Services\AiMemory\SearchRepository;
use App\Services\AiMemory\SessionRepository;
use App\Services\AiMemory\StatsRepository;
use App\Services\AiMemory\WorkspaceRepository;
use Throwable;

/**
 * Runs every read this panel performs and reports which ones the schema no
 * longer answers.
 *
 * Why this exists: the fixture in SeedsAiMemoryIndex is a hand-written copy of
 * ai-memory's schema. Hand-written copies drift silently — upstream renames a
 * column, the fixture keeps the old name, the suite stays green and the panel
 * breaks in production. This probe is the canary against that: point it at a
 * REAL index and every repository query runs against the real schema.
 *
 * It reports rather than throws, so one broken query does not hide the next.
 * An empty index is not a failure: a query that legitimately finds no rows
 * still proves the schema answered it.
 */
class AiMemorySchemaProbe
{
    /** @return array<string, string> label => failure message, empty when the schema answers everything */
    public static function failures(): array
    {
        $db = new AiMemoryDatabase;
        $failures = [];

        $run = static function (string $label, callable $query) use (&$failures) {
            try {
                return $query();
            } catch (Throwable $e) {
                $failures[$label] = $e->getMessage();

                return null;
            }
        };

        $stats = new StatsRepository($db);
        $run('StatsRepository::counts', fn () => $stats->counts());
        $run('StatsRepository::observationsByDay', fn () => $stats->observationsByDay(7));
        $run('StatsRepository::sessionsByDay', fn () => $stats->sessionsByDay(7));

        $projects = new ProjectRepository($db);
        $allProjects = $run('ProjectRepository::all', fn () => $projects->all());
        $run('ProjectRepository::options', fn () => $projects->options());
        if (! empty($allProjects)) {
            $run('ProjectRepository::find', fn () => $projects->find($allProjects[0]->id_hex));
        }

        $workspaces = new WorkspaceRepository($db);
        $run('WorkspaceRepository::all', fn () => $workspaces->all());

        $pages = new PageRepository($db);
        $pageList = $run('PageRepository::paginate', fn () => $pages->paginate(null, 5));
        if ($pageList !== null && $pageList->count() > 0) {
            $page = $run('PageRepository::find', fn () => $pages->find($pageList->first()->id_hex));
            if ($page !== null) {
                $run('PageRepository::history', fn () => $pages->history($page));
            }
        }

        $sessions = new SessionRepository($db);
        $sessionList = $run('SessionRepository::paginate', fn () => $sessions->paginate([], 5));
        $run('SessionRepository::agentKinds', fn () => $sessions->agentKinds());
        if ($sessionList !== null && $sessionList->count() > 0) {
            $hex = $sessionList->first()->id_hex;
            $run('SessionRepository::find', fn () => $sessions->find($hex));
            $run('SessionRepository::observations', fn () => $sessions->observations($hex));
        }

        $observations = new ObservationRepository($db);
        $observationList = $run('ObservationRepository::paginate', fn () => $observations->paginate([], 5));
        $run('ObservationRepository::kinds', fn () => $observations->kinds());
        if ($observationList !== null && $observationList->count() > 0) {
            $run('ObservationRepository::find', fn () => $observations->find($observationList->first()->id_hex));
        }

        $handoffs = new HandoffRepository($db);
        $handoffList = $run('HandoffRepository::paginate', fn () => $handoffs->paginate(null, 5));
        if ($handoffList !== null && $handoffList->count() > 0) {
            $run('HandoffRepository::find', fn () => $handoffs->find($handoffList->first()->id_hex));
        }

        $search = new SearchRepository($db);
        $run('SearchRepository::search', fn () => $search->search('fixture', 3));

        return $failures;
    }

    /** One-line rendering of failures(), for a test failure message. */
    public static function describe(array $failures): string
    {
        $lines = [];
        foreach ($failures as $label => $message) {
            $lines[] = "  {$label}: {$message}";
        }

        return implode("\n", $lines);
    }
}
