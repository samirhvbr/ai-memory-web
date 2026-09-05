<?php

use Illuminate\Support\Facades\Schedule;

/*
| A daily snapshot of the ai-memory statistics into this app's own database,
| so the usage history survives an ai-memory reset (memory.sqlite is a DERIVED
| index and can be rebuilt from the wiki at any time).
|
| Requires Laravel's scheduler on the host: `* * * * * php artisan schedule:run`.
| See app/Console/Commands/SnapshotAiMemoryStats and docs/durable-history.md.
*/
Schedule::command('aimemory:snapshot')->dailyAt('03:10')->withoutOverlapping();
