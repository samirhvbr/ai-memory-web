# Decisions (ADR log)

> **Status:** `ACTIVE` · The single, chronological record of decisions taken for
> this app, and the reason each was taken, so the next session does not
> re-litigate them.

Numbering is sequential and never reused. A superseded ADR is not deleted: its
status becomes `SUPERSEDED` and it names the ADR that replaced it.

**Provenance.** ADR-001 to ADR-004 were taken while this was the `AI-MEMORY`
module of [samirhvbr/samirhv-site](https://github.com/samirhvbr/samirhv-site).
They are recorded here on 05/09/2026, when the code moved into its own
repository (ADR-005); the dates given are the dates of the decisions, not of
this file.

---

## ADR-001 — Read `memory.sqlite` directly, as a second reader on the same host

**Status:** `ACCEPTED` · 03/07/2026

**Context.** ai-memory keeps a derived SQLite index next to its Markdown wiki.
The panel needed sessions, observations, pages, handoffs and full-text search.
Three ways to get them: open the file, call ai-memory's read-only HTTP API, or
copy the file periodically. The panel was going to run on the same host as
ai-memory anyway.

**Decision.** Open `memory.sqlite` from the filesystem, read-only, as a second
SQLite reader.

**Consequences.** It is the shortest path: no API key to provision, no network
hop, no staleness, and FTS5 search comes for free through `pages_fts`. The cost
is a hard **host coupling** — the app has to live on the machine where ai-memory
runs, and it needs a filesystem permission that ai-memory's own threat model
does not anticipate ([permissions.md](permissions.md#3-granting-access--the-shared-group-recipe)).
It also puts a ceiling on the feature set: everything this app can ever do is a
`SELECT`.

The two decoupled alternatives stay on the table, and this ADR is what to
supersede if either is taken:

- **ai-memory's read-only HTTP API** — `/api/v1/*` is read-only by construction
  (workspaces, projects, pages, search, recent, briefing, overview, handoffs,
  graph, sessions, observations), authenticated with a User-level `aim_` key
  (`ai-memory api-key add --username <u> --label ai-memory-web`). This is the
  path upstream supports for exactly this use case, and it is what any *write*
  feature would need anyway.
- **A periodic consistent snapshot** — `ai-memory backup --to <file>` (SQLite's
  online backup API) into a directory the web user owns, with
  `AI_MEMORY_SQLITE_PATH` pointing at the copy. It costs freshness, needs zero
  permission on the ai-memory data directory, and the copy has no WAL sidecars
  at all.

---

## ADR-002 — Never write; pin `PRAGMA query_only` at the connection

**Status:** `ACCEPTED` · 03/07/2026

**Context.** ai-memory serialises its writes through a writer actor and keeps
FTS5 and invariant triggers in SQLite. A second writer would corrupt all of it.
"We only ever call SELECT" is a convention, and a convention is one careless
`DB::connection('aimemory')->statement(...)` away from being false.

**Decision.** Declare the guard at the engine, in the connection itself:
`'pragmas' => ['query_only' => 1]` on the `aimemory` connection. Keep the raw
connection private inside `AiMemoryDatabase`. Assert it in a test that attempts
a real write.

**Consequences.** Any write fails at the driver, for every consumer of the
connection and across reconnects — including code nobody has written yet. In
exchange, no feature of this app can ever be a write: approving an Auto Improve
proposal or generating embeddings must go through ai-memory's API or MCP, and
would be a new ADR.

---

## ADR-003 — Probe with `SELECT count(*) FROM sqlite_master`, not `SELECT 1`

**Status:** `ACCEPTED` · 02/09/2026

**Context.** ai-memory 2.0 was installed into a fresh `/opt/ai-memory/data/db`,
which it creates `0700` with the database `0600`. The web user could read the
file but could not write the **directory**, so the first `SELECT` against a real
table failed with `SQLITE_READONLY_DIRECTORY` — a WAL reader has to create the
`-shm`/`-wal` sidecars when the writer is not holding them
([permissions.md](permissions.md#2-why-read-permission-is-not-enough)).

The availability probe used `SELECT 1`. SQLite answers a constant expression
without ever opening the database file, so the probe stayed **green** while
every screen 500'd.

**Decision.** The probe must be a query that touches the file. Read
`sqlite_master`. And because a probe can pass and a later query still fail, add
a second layer: the controller wraps each screen's queries in `try/catch`,
degrades to the notice, and logs.

**Consequences.** A failure now costs one extra real query per request instead
of a constant, which is nothing next to what it prevents. The contract became
explicit and testable: **an unreachable index renders a notice that names the
failure, never an HTTP 500** — with `HttpExceptionInterface` rethrown so a 404
stays a 404. The regression test asserts that `SELECT 1` would not have caught
it, which is what fails if anyone simplifies the probe back.

---

## ADR-004 — "Live" is browser polling; the durable history is our own table

**Status:** `ACCEPTED` · 03/07/2026

**Context.** Two problems that look unrelated and share a root: **this app owns
none of the data it shows.** The index is written by the agents, and it is
*derived* — it can be rebuilt or wiped.

**Decision.** Two answers:

- For freshness: **short browser polling** of a JSON endpoint (15s, pausable,
  paused on a hidden tab, exponential backoff), not broadcasting. There is no
  event of ours to emit, so a WebSocket daemon would end up polling the file
  itself ([live-dashboard.md](live-dashboard.md)).
- For history: a **daily snapshot** of the totals into this app's own database,
  and the job writes **nothing** when ai-memory is unreachable
  ([durable-history.md](durable-history.md)).

**Consequences.** No daemon to keep alive, and the panel degrades to a plain
server-rendered page with JS off. The cost is a 15-second granularity and one
duplicated `niceMax()` between PHP and JS, which must stay in step. The history
starts the day the job first runs — there is nothing to backfill from — and a
gap in the snapshots is visible in the delta, which states the real number of
days it spans rather than a nominal seven.

---

## ADR-005 — Extract the module into its own repository, as a standalone app

**Status:** `ACCEPTED` · 05/09/2026

**Context.** The panel lived as the `AI-MEMORY` module inside samirhv-site: an
admin area of a personal downloads site, coupled to that app's layout, auth,
route names and `admin.*` view namespace. Nothing about reading the ai-memory
index has anything to do with that site, and nobody else could run it.

**Decision.** Make it a standalone Laravel application in its own public
repository — its own layout, its own login, its own database, English URLs and
UI. samirhv-site keeps its module for now; the two are separate codebases, not a
shared package.

**Consequences.** Anyone can clone it, point `AI_MEMORY_SQLITE_PATH` at their
own install and get a panel. It also drops the dependencies the module had on
its host app: the Canvas icon font became a 14-glyph inline-SVG component, and
the `admin-*` CSS vocabulary became `card`/`table`/`btn`.

The cost is a **fork**: the same code now exists in two repositories and will
drift. That is accepted deliberately — a shared Composer package was considered
and rejected as more coupling than a personal site and a public tool are worth.
If samirhv-site's module is retired in favour of this app, that is a new ADR.

**Language.** Everything here is English (US), including the UI. The fleet rule
carves out end-user-facing strings as product i18n for a Brazilian audience;
that carve-out does not apply — this is a developer tool published publicly,
whose operator is also its reader.
