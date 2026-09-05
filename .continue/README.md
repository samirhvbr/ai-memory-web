# Queue — ai-memory-web

> **Status:** `ACTIVE` · What is **still open** here. Anything already done lives
> in [`../docs/`](../docs/README.md) (the record) or in
> [`../CHANGELOG.md`](../CHANGELOG.md) (the history). A finished item leaves
> this file — an item sitting here that is already done costs more than a
> missing one, because the next session redoes it.

## What is left

### 1. Run it against a real `memory.sqlite` — **not yet done**

The app was extracted, built and tested on a machine whose PHP has **no
`pdo_sqlite`**, so every test that needs the driver **skips** there and the
panel has never rendered a real row in this repository. What *has* been checked:
routes resolve, every Blade template compiles, the pure logic
(`DashboardSummary`, `AiMemoryTime`) passes, and Pint is clean.

To close this item, on the ai-memory host:

```bash
apt install php8.4-sqlite3          # then restart PHP-FPM
php artisan test                    # the 28 skipped tests must run and pass
php artisan aimemory:snapshot       # must write a snapshot
```

Then open each of the nine screens once. Until that has happened, treat the
first deploy as the real test.

### 2. Whether samirhv-site keeps its module — **owner's call**

[ADR-005](../docs/decisions.md#adr-005) accepted a fork: the same code now
exists in `samirhvbr/samirhv-site` and here, and the two will drift. The panel
is already reachable at `samirhv.com.br/admin/ai-memory`.

The decision that is open is not technical: whether that admin tab is retired in
favour of this app, or both are kept on purpose. Either way it becomes a new
ADR, and retiring it is a change to *that* repository, not this one.

> Note, if it is retired: the module's CSS was moved out of the Blade templates
> into `public/css/admin/` there on 05/09/2026, **after** this extraction was
> copied. The two are the same rules, differently packaged.

## Not queue — deliberately out of scope

These are ideas, not pending work. They are here so nobody mistakes their
absence for an oversight. All of them are blocked on the same thing: this app
can only ever `SELECT` ([ADR-002](../docs/decisions.md#adr-002)).

| Idea | Why it is not queued |
|---|---|
| Approve / reject Auto Improve proposals | A **write**. Would have to go through ai-memory's HTTP API or MCP — a different app, or a new ADR |
| Generate embeddings | Same: a write |
| A visual knowledge graph from the `links` table | Readable, so it is possible — but nobody has asked for it, and an unasked-for screen is not queue |
| A dedicated embeddings / ai-memory audit screen | Same |
| Multi-host: read several ai-memory installs | Would drop the host coupling that [ADR-001](../docs/decisions.md#adr-001) chose on purpose. It needs the HTTP API path, and therefore an ADR that supersedes ADR-001 |

## Where the record is

| Question | Document |
|---|---|
| What ai-memory collects, table by table | [docs/what-ai-memory-collects.md](../docs/what-ai-memory-collects.md) |
| Why this app may never write, and the availability guard | [docs/read-only.md](../docs/read-only.md) |
| Why it says "not reachable", and how to fix it | [docs/permissions.md](../docs/permissions.md) |
| What was decided, and why | [docs/decisions.md](../docs/decisions.md) |
| Install, deploy, troubleshoot | [docs/runbook.md](../docs/runbook.md) |
