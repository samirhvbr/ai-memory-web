# What ai-memory collects, and what this panel reads

> **Status:** `ACTIVE` · The inventory of the data. Every table and column named
> here is one this app actually queries — nothing is listed because the upstream
> schema happens to have it.

## 1. Two stores, one source of truth

[ai-memory](https://github.com/akitaonrails/ai-memory) is a Rust server that
gives long-term memory to coding agents (Claude Code, Codex, and others). It
keeps two things on disk:

| Store | Path | What it is |
|---|---|---|
| The **Markdown wiki** | `<data>/wiki/` | The **source of truth**. Human-readable pages. |
| The **SQLite index** | `<data>/db/memory.sqlite` | **Derived.** WAL mode. Sessions, observations, pages, handoffs, embeddings, audit rows and an FTS5 search index. |

This app reads the **index**, never the wiki. That is deliberate: the index is
what carries the relations (which session produced which observation, which page
supersedes which) and the search. It is also *derived*, which is why the usage
history is copied out daily — see [durable-history.md](durable-history.md).

## 2. The five things ai-memory records

### Workspaces

The outermost scope. One workspace can be a company, a client, or a shared body
of practice (`practice/unit-testing`).

Columns read: `id`, `name`. Counts of projects, pages, sessions and observations
are derived by `WorkspaceRepository` — note that `pages` and `sessions` carry
`workspace_id` **directly**, not only through `project_id`, which is what those
sums use.

### Projects

One repository / working directory inside a workspace. A project appears the
first time an agent opens a session inside it.

Columns read: `id`, `workspace_id`, `name`, `repo_path`, `created_at`.

### Sessions — *one agent, working*

Opened by the agent's lifecycle hooks when work starts, closed when it ends.

| Column | What it holds |
|---|---|
| `agent_kind` | which agent (`claude-code`, `codex`, …) |
| `cwd` | the directory the agent was working in |
| `started_at` / `ended_at` | microseconds UTC; `ended_at` null means still open |
| `summary_page_id` | the page consolidation produced for this session, if any |
| `workspace_id`, `project_id` | scope |

### Observations — *one fact the agent learned*

The atoms of the memory. Lifecycle hooks capture them automatically, sanitized
and bounded; an agent can also write one deliberately.

| Column | What it holds |
|---|---|
| `kind` | the category of the fact (free-form; the filter select is built from `DISTINCT kind`) |
| `title` / `body` | the fact itself |
| `importance` | 0–10, the agent's own weighting |
| `session_id`, `project_id` | where it came from |
| `created_at` | microseconds UTC |

### Pages — *consolidated knowledge (the wiki)*

What survives a session: a Markdown document with frontmatter.

| Column | What it holds |
|---|---|
| `title`, `path`, `body`, `frontmatter_json` | the document |
| `tier` | `working` / `episodic` / `semantic` — how settled the knowledge is |
| `is_latest` | `1` on the current version; older versions stay in the table |
| `supersedes` | the previous version's id — the version chain |
| `pinned` | kept at the top of listings |
| `author_id` | joins ai-memory's own `users` table for a username |

The **history** screen does not walk `supersedes` one link at a time: it lists
every row sharing the same `(workspace_id, project_id, path)`, newest first,
which is the same set and survives a broken chain.

### Handoffs — *the baton between sessions*

The note one session leaves for the next.

| Column | What it holds |
|---|---|
| `from_agent` / `to_agent` | who wrote it, and who it is addressed to (`null` = any agent) |
| `state` | `open` / `accepted` / `expired` |
| `summary` | prose: where we stopped |
| `open_questions`, `next_steps`, `files_touched` | JSON arrays (listings count them with `json_array_length`, the detail screen decodes them) |
| `accepted_by`, `accepted_at` | who picked the baton up |

### Two counters, and nothing more

`page_embeddings` and `auto_improve_proposals` are only ever counted, for the
dashboard. Both **may not exist** in an older ai-memory: `StatsRepository`
tolerates *"no such table/column"* for these — and for nothing else, because a
fabricated zero would also poison the durable history.

## 3. Three facts that bite

**Timestamps are microseconds since the epoch, in UTC.** Not seconds, not
milliseconds. `AiMemoryTime` divides by 1,000,000 and converts to
`aimemory.timezone`; the dashboard's daily buckets are grouped in **UTC**, and
the screen says so.

**Ids are BLOB (UUIDv7).** A URL cannot carry a BLOB, so every id crosses the
boundary as `lower(hex(id))` — 32 hex characters. The route constraint
`[0-9a-fA-F]{32}` is what stops a malformed id from ever reaching a query.

**`rowid` is not stable across ai-memory versions.** `pages`, `sessions`,
`links` and `users` have been rebuilt by upstream migrations (`DROP` + `RENAME`)
before. `rowid` is used *inside* one query (joining `pages` to `pages_fts`) and
must never be persisted anywhere.

## 4. Search

The panel does not build its own index. It queries **ai-memory's own
`pages_fts`** (FTS5 over `title` + `body`), ordered by `bm25`, with
`snippet(...)` for the highlighted excerpt.

The user's query is not passed through raw. Each token is quoted and given a `*`
suffix, so `oauth` finds `oauth2` and the FTS5 operators (`AND`, `OR`, `NOT`,
`NEAR`, quotes) inside a user's text become literals instead of a syntax error.
At most 10 tokens; they combine with an implicit AND.

Because the index is literal, the empty-result screen says so — a term that does
not appear in the text returns nothing, and no amount of rephrasing changes that.

## 5. Screen → query map

| Screen | Repository | What it asks the index |
|---|---|---|
| Dashboard | `StatsRepository`, `ProjectRepository` | eight `COUNT`s + two daily series + the project ranking |
| Projects | `ProjectRepository` | projects with per-project counts and last activity |
| Workspaces | `WorkspaceRepository` | workspaces with aggregated counts |
| Pages | `PageRepository` | `is_latest = 1`, pinned first, optionally by project |
| Page | `PageRepository` | one version + every version of the same path |
| Sessions | `SessionRepository` | filterable by agent/project/window, sortable by start or duration |
| Session | `SessionRepository` | one session + its observations, chronological |
| Observations | `ObservationRepository` | filterable by kind/importance/project/window |
| Observation | `ObservationRepository` | one fact, with its session |
| Handoffs | `HandoffRepository` | filterable by state |
| Handoff | `HandoffRepository` | one baton, with its JSON lists decoded |
| Search | `SearchRepository` | `pages_fts MATCH`, ordered by `bm25` |

Every one of those is a `SELECT`. Why that is enforced rather than merely
intended: [read-only.md](read-only.md).
