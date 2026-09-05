<?php

/*
|--------------------------------------------------------------------------
| ai-memory-web — configuration and host coupling
|--------------------------------------------------------------------------
|
| This app READS (read-only) the database of `ai-memory`
| (github.com/akitaonrails/ai-memory) — the long-term memory of the coding
| agents (Claude Code, Codex, etc.).
|
| >>> READ THIS BEFORE MOVING THE APP TO ANOTHER SERVER <<<
|
| ai-memory keeps its index in a WAL-mode SQLite file, and this app opens that
| file straight off the filesystem as a second, read-only reader. The layout
| depends on how ai-memory was installed:
|
|     2.x native   /opt/ai-memory/data/db/memory.sqlite
|     upstream     /var/lib/ai-memory/db/memory.sqlite
|     1.x Docker   /var/lib/docker/volumes/ai-memory-data/_data/db/memory.sqlite
|
| That makes this app HOST-COUPLED: it has to run on the same machine as
| ai-memory. Move it elsewhere, change the ai-memory layout, or take away the
| web user's access and every screen stops returning data and shows the
| degradation notice instead.
|
| >>> WHAT "ACCESS" MEANS FOR A WAL DATABASE <<<
|
| Read permission on memory.sqlite is NOT enough. A WAL reader also needs WRITE
| permission on the DIRECTORY that holds the file, because whenever ai-memory
| has checkpointed and closed its last connection the sidecar files `-shm` and
| `-wal` are gone and the reader is the one that has to create them. Without
| that, the first SELECT fails with SQLITE_READONLY_DIRECTORY, which PDO reports
| as the misleading "General error: 8 attempt to write a readonly database".
| The full recipe (groups, permissions, diagnosis) is in docs/permissions.md.
|
*/

return [

    // Absolute path of memory.sqlite ON THIS HOST. Set it in production via
    // AI_MEMORY_SQLITE_PATH in .env. Empty/missing => the app degrades.
    'path' => env('AI_MEMORY_SQLITE_PATH', '/opt/ai-memory/data/db/memory.sqlite'),

    // Name of the connection declared in config/database.php (read-only).
    'connection' => 'aimemory',

    // Timezone used to display timestamps (ai-memory stores UTC microseconds).
    'timezone' => env('AI_MEMORY_TIMEZONE', 'UTC'),

    // How many days of history the dashboard charts show.
    'chart_days' => (int) env('AI_MEMORY_CHART_DAYS', 30),

    // Row cap per page in the listings (sessions/observations/pages).
    'per_page' => (int) env('AI_MEMORY_PER_PAGE', 50),

    // Name shown in the header. Handy when several installs are open at once.
    'brand' => env('AI_MEMORY_BRAND', 'ai-memory'),
];
