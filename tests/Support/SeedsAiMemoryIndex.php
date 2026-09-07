<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * A seeded, WAL-mode stand-in for the ai-memory index, built in a temporary
 * directory so the screens can be exercised without a production server.
 *
 * ── SCHEMA VERSION — READ THIS BEFORE CHANGING THE DDL ──────────────────────
 *
 * The DDL below was written against ai-memory **2.0.2**, read from the source
 * checkout at `~/x/SHVIA/ai-memory` (migrations V01..V58) on 2026-09-07. It
 * reproduces only the tables and columns this panel actually queries, plus the
 * NOT NULL columns SQLite would otherwise reject on INSERT.
 *
 * The ai-memory server this panel is meant to read reports **2.0.0**, and the
 * equivalence between 2.0.0 and 2.0.2 **has not been verified** — no query has
 * ever run against the production index from this repository. Closing that gap
 * is not this file's job: it is `AiMemorySchemaCanaryTest`, which re-runs every
 * repository query against a real index when one is pointed at it.
 *
 * So: a green suite here proves the panel is consistent with the schema as
 * transcribed, not that the transcription matches production.
 *
 * ── WHAT IT IS NOT ─────────────────────────────────────────────────────────
 *
 * `AiMemoryDatabaseTest::seedWalDatabase()` also builds a WAL database, but it
 * is a *permission* fixture: six tables with the three or four columns the
 * availability guard touches. Nothing renders from it. This trait is the
 * *content* fixture, and the two are deliberately separate — the guard tests
 * must keep working on a database with no rows worth reading.
 */
trait SeedsAiMemoryIndex
{
    private ?string $aiMemoryDir = null;

    private ?string $aiMemoryDbPath = null;

    /** Microseconds UTC, the clock every seeded timestamp is relative to. */
    private int $aiMemoryNow = 0;

    /**
     * Build the index, point the `aimemory` connection at it, return the path.
     *
     * $breakColumn is for the canary's own test: 'pages.path' renames that
     * column so a query that reads it fails, which is how we prove the canary
     * actually rings. Anything else is ignored.
     */
    protected function seedAiMemoryIndex(?string $breakColumn = null): string
    {
        $this->aiMemoryNow = time() * 1_000_000;
        $this->aiMemoryDir = sys_get_temp_dir().'/aimemory-fixture-'.bin2hex(random_bytes(6));
        mkdir($this->aiMemoryDir.'/db', 0o755, true);
        $this->aiMemoryDbPath = $this->aiMemoryDir.'/db/memory.sqlite';

        $pathColumn = $breakColumn === 'pages.path' ? 'path_renamed_on_purpose' : 'path';

        $pdo = new PDO('sqlite:'.$this->aiMemoryDbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');

        $this->createAiMemorySchema($pdo, $pathColumn);
        $this->insertAiMemoryRows($pdo, $pathColumn);

        $pdo = null;

        $this->pointAppAtAiMemoryIndex($this->aiMemoryDbPath);

        return $this->aiMemoryDbPath;
    }

    /** Point config + connection at a path, the way production `.env` does. */
    protected function pointAppAtAiMemoryIndex(string $path): void
    {
        config([
            'aimemory.path' => $path,
            'database.connections.aimemory.database' => $path !== '' ? $path : ':memory:',
        ]);

        DB::purge('aimemory');
    }

    /** Remove the temporary index. Call from the test class's tearDown(). */
    protected function cleanUpAiMemoryIndex(): void
    {
        DB::purge('aimemory');

        if ($this->aiMemoryDir === null) {
            return;
        }

        foreach (glob($this->aiMemoryDir.'/db/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->aiMemoryDir.'/db');
        @rmdir($this->aiMemoryDir);

        $this->aiMemoryDir = null;
        $this->aiMemoryDbPath = null;
    }

    // ── Ids ────────────────────────────────────────────────────────────────
    //
    // 16 bytes, so `lower(hex(id))` is 32 hex characters — which is exactly
    // what the `[0-9a-fA-F]{32}` route constraint accepts. A shorter BLOB
    // would make every detail route 404 and the failure would look like a
    // missing row rather than a malformed id.

    protected function wsDefaultHex(): string
    {
        return '0191f0a1000170008000000000000001';
    }

    protected function wsPracticeHex(): string
    {
        return '0191f0a1000170008000000000000002';
    }

    protected function projectAlphaHex(): string
    {
        return '0191f0a1000170008000000000000011';
    }

    protected function projectBetaHex(): string
    {
        return '0191f0a1000170008000000000000012';
    }

    protected function projectSharedHex(): string
    {
        return '0191f0a1000170008000000000000013';
    }

    /** The current version of the three-version page. */
    protected function pageCurrentHex(): string
    {
        return '0191f0a1000170008000000000000023';
    }

    /** The oldest version of the same path. */
    protected function pageOldestHex(): string
    {
        return '0191f0a1000170008000000000000021';
    }

    protected function pagePinnedHex(): string
    {
        return '0191f0a1000170008000000000000024';
    }

    protected function pageEmptyBodyHex(): string
    {
        return '0191f0a1000170008000000000000026';
    }

    /** The session with observations, a summary page and an end. */
    protected function sessionClosedHex(): string
    {
        return '0191f0a1000170008000000000000031';
    }

    /** The session still running (ended_at IS NULL). */
    protected function sessionOpenHex(): string
    {
        return '0191f0a1000170008000000000000033';
    }

    protected function observationHex(): string
    {
        return '0191f0a1000170008000000000000041';
    }

    /** The open handoff, with all three JSON arrays populated. */
    protected function handoffOpenHex(): string
    {
        return '0191f0a1000170008000000000000051';
    }

    protected function handoffAcceptedHex(): string
    {
        return '0191f0a1000170008000000000000052';
    }

    // ── Schema ─────────────────────────────────────────────────────────────

    private function createAiMemorySchema(PDO $pdo, string $pathColumn): void
    {
        $pdo->exec(
            'CREATE TABLE workspaces (
                id         BLOB PRIMARY KEY NOT NULL,
                name       TEXT NOT NULL UNIQUE,
                created_at INTEGER NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE projects (
                id           BLOB PRIMARY KEY NOT NULL,
                workspace_id BLOB NOT NULL,
                name         TEXT NOT NULL,
                repo_path    TEXT,
                created_at   INTEGER NOT NULL,
                UNIQUE (workspace_id, name)
            )'
        );

        // V14 added ai-memory's own users table; the panel only joins it for a
        // username on pages and (in a future phase) audit rows.
        $pdo->exec(
            'CREATE TABLE users (
                id       BLOB PRIMARY KEY NOT NULL,
                username TEXT NOT NULL
            )'
        );

        // `path_search` (V17), `body_sha256` and `access_count` are NOT NULL
        // upstream; they are here because SQLite rejects the INSERT without
        // them, not because any screen reads them.
        $pdo->exec(
            "CREATE TABLE pages (
                id               BLOB PRIMARY KEY NOT NULL,
                workspace_id     BLOB NOT NULL,
                project_id       BLOB NOT NULL,
                {$pathColumn}    TEXT NOT NULL,
                path_search      TEXT NOT NULL DEFAULT '',
                title            TEXT NOT NULL,
                tier             TEXT NOT NULL CHECK (tier IN ('working','episodic','semantic','procedural')),
                body             TEXT NOT NULL,
                body_sha256      BLOB NOT NULL,
                frontmatter_json TEXT NOT NULL DEFAULT '{}',
                is_latest        INTEGER NOT NULL DEFAULT 1 CHECK (is_latest IN (0,1)),
                supersedes       BLOB,
                pinned           INTEGER NOT NULL DEFAULT 0 CHECK (pinned IN (0,1)),
                author_id        BLOB,
                access_count     INTEGER NOT NULL DEFAULT 0,
                created_at       INTEGER NOT NULL,
                updated_at       INTEGER NOT NULL
            )"
        );

        // External-content FTS5, three columns since V17. The panel's snippet()
        // call addresses column 1 (body) by index, so the ORDER of these
        // columns is load-bearing.
        $pdo->exec(
            'CREATE VIRTUAL TABLE pages_fts USING fts5(
                title, body, path_search,
                content=\'pages\',
                content_rowid=\'rowid\',
                tokenize="unicode61 remove_diacritics 2 tokenchars \'/_-\'"
            )'
        );
        $pdo->exec(
            'CREATE TRIGGER pages_fts_ai AFTER INSERT ON pages BEGIN
                INSERT INTO pages_fts(rowid, title, body, path_search)
                    VALUES (new.rowid, new.title, new.body, new.path_search);
            END'
        );

        // The agent_kind CHECK has grown with every new harness (V09, V11, V20,
        // V50, V51...). The fixture only seeds kinds that existed from V01, so
        // it does not have to track that list.
        $pdo->exec(
            'CREATE TABLE sessions (
                id              BLOB PRIMARY KEY NOT NULL,
                workspace_id    BLOB NOT NULL,
                project_id      BLOB NOT NULL,
                agent_kind      TEXT NOT NULL,
                cwd             TEXT,
                started_at      INTEGER NOT NULL,
                ended_at        INTEGER,
                summary_page_id BLOB
            )'
        );

        // importance is CHECK (importance BETWEEN 1 AND 10) upstream — NOT
        // 0..10 as docs/what-ai-memory-collects.md says. Seeding 0 would be
        // rejected here, which is the point of keeping the CHECK.
        $pdo->exec(
            'CREATE TABLE observations (
                id           BLOB PRIMARY KEY NOT NULL,
                session_id   BLOB NOT NULL,
                workspace_id BLOB NOT NULL,
                project_id   BLOB NOT NULL,
                kind         TEXT NOT NULL,
                title        TEXT NOT NULL,
                body         TEXT NOT NULL,
                importance   INTEGER NOT NULL DEFAULT 5 CHECK (importance BETWEEN 1 AND 10),
                created_at   INTEGER NOT NULL
            )'
        );

        $pdo->exec(
            "CREATE TABLE handoffs (
                id              BLOB PRIMARY KEY NOT NULL,
                workspace_id    BLOB NOT NULL,
                project_id      BLOB NOT NULL,
                from_session_id BLOB,
                from_agent      TEXT NOT NULL,
                to_agent        TEXT,
                cwd             TEXT,
                summary         TEXT NOT NULL,
                open_questions  TEXT NOT NULL DEFAULT '[]',
                next_steps      TEXT NOT NULL DEFAULT '[]',
                files_touched   TEXT NOT NULL DEFAULT '[]',
                state           TEXT NOT NULL DEFAULT 'open' CHECK (state IN ('open','accepted','expired')),
                created_at      INTEGER NOT NULL,
                accepted_by     TEXT,
                accepted_at     INTEGER
            )"
        );

        // V13 shape: the scope columns are NULL for a link inside the source
        // page's own project, and to_page_id is NULL while unresolved.
        $pdo->exec(
            "CREATE TABLE links (
                from_page_id BLOB NOT NULL,
                to_page_id   BLOB,
                to_workspace TEXT,
                to_project   TEXT,
                to_path      TEXT NOT NULL,
                link_type    TEXT NOT NULL DEFAULT 'references',
                PRIMARY KEY (from_page_id, to_workspace, to_project, to_path, link_type)
            )"
        );

        // V01 + V16 (author_id). No agent column and no session_id — that is
        // the schema, not an omission in this fixture.
        $pdo->exec(
            "CREATE TABLE audit_log (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                at           INTEGER NOT NULL,
                op           TEXT NOT NULL,
                workspace_id BLOB,
                project_id   BLOB,
                page_id      BLOB,
                detail       TEXT NOT NULL DEFAULT '{}',
                author_id    BLOB
            )"
        );

        // V46. `day` is UTC DAYS since the epoch, not microseconds — it must
        // never be handed to AiMemoryTime, which divides by 1_000_000.
        $pdo->exec(
            'CREATE TABLE client_activity (
                client TEXT NOT NULL CHECK (length(client) BETWEEN 1 AND 64),
                day    INTEGER NOT NULL,
                reads  INTEGER NOT NULL DEFAULT 0 CHECK (reads >= 0),
                writes INTEGER NOT NULL DEFAULT 0 CHECK (writes >= 0),
                PRIMARY KEY (client, day)
            ) WITHOUT ROWID'
        );

        // Only ever counted by the dashboard, and both may be absent on an
        // older ai-memory — see AiMemoryDatabaseTest, which asserts the
        // tolerance by leaving them out.
        $pdo->exec('CREATE TABLE page_embeddings (page_id BLOB PRIMARY KEY NOT NULL)');
        $pdo->exec('CREATE TABLE auto_improve_proposals (id BLOB PRIMARY KEY NOT NULL, status TEXT NOT NULL)');
    }

    // ── Rows ───────────────────────────────────────────────────────────────

    private function insertAiMemoryRows(PDO $pdo, string $pathColumn): void
    {
        $now = $this->aiMemoryNow;
        $hour = 3_600_000_000;
        $day = 86_400_000_000;

        $x = static fn (string $hex): string => "x'{$hex}'";

        $ws = $x($this->wsDefaultHex());
        $wsPractice = $x($this->wsPracticeHex());
        $alpha = $x($this->projectAlphaHex());
        $beta = $x($this->projectBetaHex());
        $shared = $x($this->projectSharedHex());

        $pdo->exec("INSERT INTO workspaces (id, name, created_at) VALUES
            ({$ws}, 'fixture-default', ".($now - 30 * $day).'),
            ('.$wsPractice.", 'fixture-practice', ".($now - 20 * $day).')');

        $pdo->exec("INSERT INTO projects (id, workspace_id, name, repo_path, created_at) VALUES
            ({$alpha}, {$ws}, 'fixture-alpha', '/srv/fixture/alpha', ".($now - 25 * $day)."),
            ({$beta}, {$ws}, 'fixture-beta', NULL, ".($now - 15 * $day)."),
            ({$shared}, {$wsPractice}, 'fixture-shared', '/srv/fixture/shared', ".($now - 10 * $day).')');

        $pdo->exec("INSERT INTO users (id, username) VALUES
            (x'0191f0a1000170008000000000000061', 'fixture-alice'),
            (x'0191f0a1000170008000000000000062', 'fixture-bob')");

        // Eight rows, six logical pages: the first three are versions of one
        // path, which is what the page screen's history list walks.
        $pages = [
            // [hex, project, path, title, tier, body, is_latest, supersedes, pinned, author, frontmatter]
            ['...21', $alpha, 'notes/alpha-history.md', 'Fixture History Page v1', 'working', 'First version of the fixture history page.', 0, 'NULL', 0, 'NULL', '{}'],
            ['...22', $alpha, 'notes/alpha-history.md', 'Fixture History Page v2', 'episodic', 'Second version of the fixture history page.', 0, $x('0191f0a1000170008000000000000021'), 0, 'NULL', '{}'],
            ['...23', $alpha, 'notes/alpha-history.md', 'Fixture History Page v3', 'semantic', 'Third and current version. It contains the word zorbulax so full-text search has something unmistakable to find.', 1, $x('0191f0a1000170008000000000000022'), 0, $x('0191f0a1000170008000000000000061'), '{}'],
            ['...24', $alpha, 'notes/alpha-pinned.md', 'Fixture Pinned Page', 'semantic', 'A pinned page, which listings sort to the top.', 1, 'NULL', 1, $x('0191f0a1000170008000000000000061'), '{}'],
            ['...25', $alpha, 'runbooks/procedural.md', 'Fixture Procedural Page', 'procedural', 'A procedural page. The tier chip map in the views only knows working/episodic/semantic.', 1, 'NULL', 0, 'NULL', '{}'],
            ['...26', $beta, 'notes/empty.md', 'Fixture Empty Body Page', 'working', '', 1, 'NULL', 0, 'NULL', '{}'],
            ['...27', $beta, 'notes/with-frontmatter.md', 'Fixture Frontmatter Page', 'episodic', 'A page whose frontmatter is not the empty object.', 1, 'NULL', 0, $x('0191f0a1000170008000000000000062'), '{"audience":"fixture","tags":["seeded"]}'],
            ['...28', $shared, 'practice/unit-testing.md', 'Fixture Shared Practice Page', 'semantic', 'Shared practice knowledge, in another workspace.', 1, 'NULL', 0, 'NULL', '{}'],
        ];

        $suffix = ['...21' => '21', '...22' => '22', '...23' => '23', '...24' => '24', '...25' => '25', '...26' => '26', '...27' => '27', '...28' => '28'];
        $i = 0;
        foreach ($pages as $page) {
            [$tag, $project, $path, $title, $tier, $body, $latest, $supersedes, $pinned, $author, $frontmatter] = $page;
            $id = $x('0191f0a10001700080000000000000'.$suffix[$tag]);
            $workspace = $project === $shared ? $wsPractice : $ws;
            $created = $now - (12 - $i) * $day;
            $pathSearch = str_replace(['/', '.'], ' ', $path).' '.str_replace(['/', '.', '-', '_'], ' ', $path);

            $stmt = $pdo->prepare(
                "INSERT INTO pages (id, workspace_id, project_id, {$pathColumn}, path_search, title, tier, body,
                                    body_sha256, frontmatter_json, is_latest, supersedes, pinned, author_id,
                                    created_at, updated_at)
                 VALUES ({$id}, {$workspace}, {$project}, ?, ?, ?, ?, ?, ?, ?, {$latest}, {$supersedes}, {$pinned}, {$author}, ?, ?)"
            );
            $stmt->execute([
                $path,
                $pathSearch,
                $title,
                $tier,
                $body,
                hash('sha256', $body, true),
                $frontmatter,
                $created,
                $created + $hour,
            ]);
            $i++;
        }

        $pdo->exec("INSERT INTO sessions (id, workspace_id, project_id, agent_kind, cwd, started_at, ended_at, summary_page_id) VALUES
            (x'0191f0a1000170008000000000000031', {$ws}, {$alpha}, 'claude-code', '/srv/fixture/alpha', ".($now - 3 * $day).', '.($now - 3 * $day + 2 * $hour).", x'0191f0a1000170008000000000000023'),
            (x'0191f0a1000170008000000000000032', {$ws}, {$alpha}, 'codex', '/srv/fixture/alpha', ".($now - 2 * $day).', '.($now - 2 * $day + $hour).", NULL),
            (x'0191f0a1000170008000000000000033', {$ws}, {$beta}, 'claude-code', '/srv/fixture/beta', ".($now - $hour).", NULL, NULL),
            (x'0191f0a1000170008000000000000034', {$wsPractice}, {$shared}, 'claude-code', '/srv/fixture/shared', ".($now - 5 * $day).', '.($now - 5 * $day + $hour).', NULL)');

        // Twelve observations. importance touches both ends of the CHECK (1 and
        // 10) and never 0, which the upstream constraint would reject.
        $observations = [
            ['41', '31', $alpha, $ws, 'decision', 'Fixture Observation One', 'The body of the first fixture observation.', 10, 3 * $day],
            ['42', '31', $alpha, $ws, 'decision', 'Fixture Observation Two', 'Another decision recorded in the same session.', 8, 3 * $day],
            ['43', '31', $alpha, $ws, 'gotcha', 'Fixture Observation Three', 'A gotcha worth remembering.', 7, 3 * $day],
            ['44', '31', $alpha, $ws, 'fact', 'Fixture Observation Four', 'A plain fact.', 5, 3 * $day],
            ['45', '31', $alpha, $ws, 'preference', 'Fixture Observation Five', 'A stated preference.', 1, 3 * $day],
            ['46', '32', $alpha, $ws, 'fact', 'Fixture Observation Six', 'Recorded by the codex session.', 6, 2 * $day],
            ['47', '32', $alpha, $ws, 'gotcha', 'Fixture Observation Seven', 'Second one from the codex session.', 4, 2 * $day],
            ['48', '32', $alpha, $ws, 'decision', 'Fixture Observation Eight', 'Third one from the codex session.', 9, 2 * $day],
            ['49', '33', $beta, $ws, 'fact', 'Fixture Observation Nine', 'From the session that is still open.', 5, 0],
            ['4a', '33', $beta, $ws, 'preference', 'Fixture Observation Ten', 'Also from the open session.', 2, 0],
            ['4b', '34', $shared, $wsPractice, 'fact', 'Fixture Observation Eleven', 'From the shared practice project.', 5, 5 * $day],
            ['4c', '34', $shared, $wsPractice, 'decision', 'Fixture Observation Twelve', 'Also from the shared project.', 3, 5 * $day],
        ];

        foreach ($observations as [$id, $session, $project, $workspace, $kind, $title, $body, $importance, $ago]) {
            $stmt = $pdo->prepare(
                "INSERT INTO observations (id, session_id, workspace_id, project_id, kind, title, body, importance, created_at)
                 VALUES (x'0191f0a10001700080000000000000{$id}', x'0191f0a10001700080000000000000{$session}', {$workspace}, {$project}, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$kind, $title, $body, $importance, $now - $ago - 60_000_000]);
        }

        $openQuestions = json_encode(['Fixture open question one', 'Fixture open question two']);
        $nextSteps = json_encode(['Fixture next step one', 'Fixture next step two', 'Fixture next step three']);
        $filesTouched = json_encode(['src/fixture/one.php', 'src/fixture/two.php']);

        $stmt = $pdo->prepare(
            "INSERT INTO handoffs (id, workspace_id, project_id, from_session_id, from_agent, to_agent, cwd, summary,
                                   open_questions, next_steps, files_touched, state, created_at, accepted_by, accepted_at)
             VALUES (x'0191f0a1000170008000000000000051', {$ws}, {$alpha}, x'0191f0a1000170008000000000000031',
                     'claude-code', 'codex', '/srv/fixture/alpha', ?, ?, ?, ?, 'open', ?, NULL, NULL)"
        );
        $stmt->execute([
            'Fixture handoff summary for the open baton.',
            $openQuestions,
            $nextSteps,
            $filesTouched,
            $now - 2 * $day,
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO handoffs (id, workspace_id, project_id, from_session_id, from_agent, to_agent, cwd, summary,
                                   open_questions, next_steps, files_touched, state, created_at, accepted_by, accepted_at)
             VALUES (x'0191f0a1000170008000000000000052', {$ws}, {$alpha}, NULL,
                     'codex', NULL, NULL, ?, ?, ?, ?, 'accepted', ?, 'fixture-claude-code', ?)"
        );
        $stmt->execute([
            'Fixture handoff summary for the accepted baton.',
            json_encode(['Fixture accepted question']),
            json_encode(['Fixture accepted step']),
            json_encode([]),
            $now - 4 * $day,
            $now - 3 * $day,
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO handoffs (id, workspace_id, project_id, from_session_id, from_agent, to_agent, cwd, summary,
                                   open_questions, next_steps, files_touched, state, created_at, accepted_by, accepted_at)
             VALUES (x'0191f0a1000170008000000000000053', {$wsPractice}, {$shared}, NULL,
                     'claude-code', NULL, NULL, ?, '[]', '[]', '[]', 'expired', ?, NULL, NULL)"
        );
        $stmt->execute(['Fixture handoff summary for the expired baton.', $now - 9 * $day]);

        // Four links: resolved in-project, resolved cross-project, unresolved
        // (to_page_id IS NULL — the broken-link signal), and one pointing back
        // at the current page so both directions of the footer have a row.
        $current = $x($this->pageCurrentHex());
        $pinnedPage = $x($this->pagePinnedHex());
        $sharedPage = $x('0191f0a1000170008000000000000028');

        $pdo->exec("INSERT INTO links (from_page_id, to_page_id, to_workspace, to_project, to_path, link_type) VALUES
            ({$current}, {$pinnedPage}, NULL, NULL, 'notes/alpha-pinned.md', 'references'),
            ({$current}, {$sharedPage}, 'fixture-practice', 'fixture-shared', 'practice/unit-testing.md', 'references'),
            ({$current}, NULL, NULL, NULL, 'notes/does-not-exist.md', 'references'),
            ({$pinnedPage}, {$current}, NULL, NULL, 'notes/alpha-history.md', 'references')");

        // Six audit rows: three attributed, three anonymous. A screen that reads
        // this must omit the author rather than print null.
        $pdo->exec('INSERT INTO audit_log (at, op, workspace_id, project_id, page_id, detail, author_id) VALUES
            ('.($now - 5 * $day).", 'page.upsert', {$ws}, {$alpha}, {$current}, '{\"path\":\"notes/alpha-history.md\"}', x'0191f0a1000170008000000000000061'),
            (".($now - 4 * $day).", 'page.upsert', {$ws}, {$alpha}, {$pinnedPage}, '{\"path\":\"notes/alpha-pinned.md\"}', x'0191f0a1000170008000000000000061'),
            (".($now - 3 * $day).", 'page.supersede', {$ws}, {$alpha}, {$current}, '{\"supersedes\":\"v2\"}', x'0191f0a1000170008000000000000062'),
            (".($now - 2 * $day).", 'retention.soft_delete', {$ws}, {$beta}, NULL, '{\"reason\":\"expired\"}', NULL),
            (".($now - $day).", 'page.upsert', {$wsPractice}, {$shared}, {$sharedPage}, '{\"path\":\"practice/unit-testing.md\"}', NULL),
            (".($now - $hour).", 'session.finalize', {$ws}, {$alpha}, NULL, '{\"agent\":\"claude-code\"}', NULL)");

        // Per (client, day) counters. `day` is days since the epoch: the same
        // arithmetic the writer uses, never a microsecond timestamp.
        $today = intdiv($now, $day);
        $pdo->exec("INSERT INTO client_activity (client, day, reads, writes) VALUES
            ('fixture-claude-desktop', {$today}, 120, 4),
            ('fixture-claude-desktop', ".($today - 1).", 90, 2),
            ('fixture-vscode-copilot', {$today}, 45, 0),
            ('fixture-vscode-copilot', ".($today - 1).", 30, 1),
            ('other', {$today}, 12, 0),
            ('other', ".($today - 1).', 8, 0)');

        $pdo->exec("INSERT INTO page_embeddings (page_id) VALUES ({$current}), ({$pinnedPage})");
        $pdo->exec("INSERT INTO auto_improve_proposals (id, status) VALUES
            (x'0191f0a1000170008000000000000071', 'pending'),
            (x'0191f0a1000170008000000000000072', 'approved')");
    }
}
