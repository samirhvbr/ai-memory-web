# Durable history — why the numbers outlive an ai-memory reset

> **Status:** `ACTIVE` · `memory.sqlite` is a **derived** index: it can be
> rebuilt from the wiki, or wiped, at any time. This app copies one snapshot of
> the totals per day into its **own** database so the usage history is not lost
> when that happens.

## 1. The table

`ai_memory_stat_snapshots`, one row per day (`captured_on` is unique):

| Column | |
|---|---|
| `captured_on` | the day — the uniqueness key, which makes the job idempotent |
| `workspaces`, `projects`, `pages`, `sessions`, `observations`, `embeddings`, `handoffs_open`, `proposals_pending` | the eight totals at that moment |
| `raw_json` | the whole counts array, so a new metric does not need a migration before it can be recorded |

It lives in **this app's** database (`DB_CONNECTION` — SQLite by default, MySQL
if you point it there), never in `memory.sqlite`. This app does not write to
ai-memory's index at all ([read-only.md](read-only.md)).

## 2. The job

```bash
php artisan aimemory:snapshot
```

- **Idempotent per day** — `updateOrCreate` on `captured_on`. Running it five
  times in an afternoon overwrites the same row with fresher numbers.
- **Scheduled** in `routes/console.php` at 03:10 daily. That requires Laravel's
  scheduler on the host:
  ```cron
  * * * * * cd /path/to/ai-memory-web && php artisan schedule:run >> /dev/null 2>&1
  ```
- **It writes nothing when ai-memory is unreachable**, and exits `0`. This is
  the important part: a row of zeros would draw a cliff in the chart that never
  happened, and the chart would then be lying about the product rather than
  about the panel. The reason is printed as a warning instead.

## 3. What the dashboard does with it

The dashboard shows **two different things**, and says which is which:

- **Live totals** — read from ai-memory right now.
- **Historical evolution** — the area chart, from this table. It survives a
  reset, so it is the only place a long trend can be read.

Two derived readings also come from the snapshots:

**The sparklines** next to the three lede numbers — the shape of the last N
snapshots, scaled min→max (the shape is the point, not the zero).

**The delta** ("+N in X days"). It compares the **live** number now against the
oldest snapshot inside the window, and then states **how many days that interval
really spans**. Snapshots are daily but one can be missing; the UI shows the real
figure rather than a "7 days" that did not happen. With only today's snapshot,
there is nothing to compare against and the delta is omitted — the screen says
so instead of showing a zero.

## 4. Consequences worth knowing

- **The history starts the day you first run the job.** Deploying the panel does
  not backfill anything; there is nothing to backfill from.
- **The curve appears from the second snapshot on.** With one row the dashboard
  says exactly that, and names the date it has.
- **A wiped ai-memory shows as a live number below the historical curve.** That
  is correct and is the whole point: the drop is real in the index and did not
  happen in the record.
