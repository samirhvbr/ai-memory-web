# Read-only — the contract, and the guard that keeps a failure out of the log

> **Status:** `ACTIVE` · Why this app may never write, how that is enforced at
> the engine level, and why an unreachable database renders a notice instead of
> an HTTP 500.

## 1. ai-memory is the only legitimate writer

The index is not a shared database. ai-memory serialises every write through a
**writer actor** and maintains, inside SQLite:

- **FTS5 triggers** that keep `pages_fts` in step with `pages`;
- **workspace × project invariant triggers**.

Writing from outside bypasses both and corrupts the index. Upstream is explicit
about it: even ai-memory's own CLI never opens the SQLite file — it talks to the
server.

## 2. Three layers say the same thing

1. **The connection.** `config/database.php` declares the `aimemory` connection
   with `'pragmas' => ['query_only' => 1]`. Laravel's SQLite connector applies
   that at connect time, so it holds **for every consumer of the connection and
   across reconnects**. Any `INSERT`/`UPDATE`/`DELETE`/DDL fails at the engine.
2. **The repositories.** Every method in `app/Services/AiMemory/` issues
   `SELECT`. `AiMemoryDatabase::connection()` is `private` on purpose: nobody
   can grab the raw connection out of the service and write with it.
3. **The test.** `AiMemoryDatabaseTest::test_the_connection_stays_read_only`
   deliberately attempts an `INSERT` and asserts that it throws.

There is no write path to add later, either. Approving an Auto Improve proposal
or generating embeddings are **writes**, and they belong to ai-memory's HTTP API
or MCP — never to this file. See [decisions.md](decisions.md#adr-001).

## 3. Untrusted content

The data this app renders was written by coding agents, not by an operator:

- **Page bodies** are Markdown, rendered with `html_input => 'escape'` and
  `allow_unsafe_links => false`. Raw HTML inside a page is shown as text.
- **Observation bodies, handoff summaries and list items** are printed with
  Blade's escaping (`{{ }}`), never `{!! !!}`.
- **Search snippets** are the one place raw HTML is emitted, and the order
  matters: the text is escaped **first**, and only then are ai-memory's `<<<`
  / `>>>` sentinels replaced by `<mark>`. Reversing those two steps would be an
  injection.

## 4. The availability guard — two layers, because one is not enough

The app is host-coupled by design (see [permissions.md](permissions.md)). When
the index is not reachable, the contract is: **every screen renders the notice,
and none of them returns a 500.**

### Layer 1 — the probe

`AiMemoryDatabase::isAvailable()` checks, in order: the path is configured, the
file exists, the file is readable, and then runs

```sql
SELECT count(*) FROM sqlite_master
```

**Why not `SELECT 1`.** SQLite answers a constant expression *without ever
opening the database file*. `SELECT 1` therefore stayed green in exactly the
broken scenario — and that false positive is what produced the 500s in
production. Reading the schema touches page 1, which forces the WAL index setup
that actually fails.

The result is memoised on the instance, and the service is a **singleton**, so a
screen with six repositories probes once.

### Layer 2 — the catch

`AiMemoryController::screen()` also wraps each screen's queries in `try/catch`.
The probe can pass and a query still fail: a permission changed mid-request, a
lock outlived `busy_timeout`, an upgrade renamed a table. Then the screen
degrades to the notice, `report()` logs the exception, and
`markUnavailable()` short-circuits the rest of the request.

**`HttpExceptionInterface` is rethrown**, so an `abort_if(..., 404)` inside a
screen's closure stays a 404 rather than turning into "ai-memory unavailable".

### The reason is part of the UI

`unavailableReason()` turns a driver exception into something an operator can
act on, and the notice prints it. The readonly-directory case gets the longest
explanation because its PDO message —
*"attempt to write a readonly database"*, on a `SELECT` — points at the wrong
problem entirely.

| Driver message | What the notice says |
|---|---|
| `attempt to write a readonly database` | no **write** permission on the directory; a WAL reader has to create `-shm`/`-wal` |
| `unable to open database file` | missing read permission, or traverse (`x`) on the directory |
| `could not find driver` | `pdo_sqlite` is not installed |
| `database is locked` | held longer than `busy_timeout`; try again |
| `no such table/column` | ai-memory version mismatch |

### The same rule in the scheduled job

`aimemory:snapshot` follows it too: if ai-memory is unreachable it writes
**nothing** and exits successfully. A row of zeros would draw a cliff in the
history that never happened — see [durable-history.md](durable-history.md).

## 5. What is deliberately tolerated, and what is not

`StatsRepository` swallows *"no such table/column"* — and only that — for the
`page_embeddings` and `auto_improve_proposals` counts, because an older
ai-memory does not have them. Every other failure (permission, lock, IO) is
rethrown so the controller guard can degrade the whole screen with an
explanation. A permission error must never be shown as a zero.
