# Documentation — ai-memory-web

> **Status:** `ACTIVE`

Index of the project's **stable** documentation. Work in progress lives in
[`../.continue/`](../.continue/) and migrates here when it matures.

This index is curated, not exhaustive. Keep it that way: a table of contents
that lists everything stops being read.

## Pages

| Document | What it answers |
|---|---|
| [what-ai-memory-collects.md](what-ai-memory-collects.md) | **The data.** What ai-memory records — workspaces, projects, sessions, observations, pages, handoffs — table by table, and which screen reads what. Microsecond timestamps, BLOB ids, FTS5. |
| [read-only.md](read-only.md) | **How it is collected, and the invariant.** Why this app may never write, how `PRAGMA query_only` enforces that, how untrusted agent content is rendered, and the two-layer guard that turns an unreachable index into a notice instead of a 500. |
| [permissions.md](permissions.md) | **The operational page.** Why read permission on `memory.sqlite` is not enough, the shared-group recipe, and how to diagnose it from the web user's point of view. |
| [durable-history.md](durable-history.md) | Why the usage history outlives an ai-memory reset: the daily snapshot, and why the job writes nothing rather than a row of zeros. |
| [live-dashboard.md](live-dashboard.md) | Why "live" is browser polling and not a WebSocket, and what degrades with JS off. |
| [decisions.md](decisions.md) | **ADRs** — the chronological record of what was decided here and why, so it is not re-litigated. |
| [runbook.md](runbook.md) | From a clean machine to a running panel: install, deploy, cron, and what to check when it breaks. |
| [versioning.md](versioning.md) | How a version is set and a commit is written. `version.md` is the sole authority; `X.Y.Z - description in English`; tags and Releases; what the two git hooks check. |
| [security.md](security.md) | The normative security document. In a conflict with any other document, it wins. |

## The norm

The documentation convention this repository follows — queue vs. record vs.
history, filenames that carry state, the status vocabulary, the language rule —
lives once, in the fleet standard:
[samirhvbr/repodocs `docs/conventions.md`](https://github.com/samirhvbr/repodocs/blob/master/docs/conventions.md).
It is deliberately **not** copied here.

## Where a new document goes

| It describes… | It goes to |
|---|---|
| something that already exists — a measurement, a contract, a runbook, an ADR | `docs/` |
| something still to be done, in one line | `.continue/` + a pointer |
| something that happened, with its date and its why | `CHANGELOG.md` |

If a queue item needs half a page, it is in the wrong place: write it here and
leave one line and a pointer in the queue.
