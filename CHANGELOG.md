# Changelog

Entries in the commit-message format (`X.Y.Z - description in English`, see
[docs/versioning.md](docs/versioning.md)), newest first. **Each `##` heading is
literally the commit subject.**

Bodies are narrative: what changed, why, and what was measured. This file is
never rewritten.

## 0.1.1 - the git hooks are regenerated from repodocs

Both hooks of the standard now run here: `commit-msg`, which checks the shape of
the subject (`X.Y.Z - description`), refuses a Conventional Commits prefix and a
vague message, **and checks that the subject's `X.Y.Z` is the version this commit
carries in `version.md`**; and `pre-push`, which compares the local `version.md`
against the remote default branch for a repeated version and for one that moves
backwards.

The hook does **not** check the language and could not: what it measures is the
shape and the number.

Until now the commit rule lived here only as prose in `CLAUDE.md`, and prose is
what gets forgotten at the end of a long session. On 07/09/2026 the hooks were
enabled in 3 clones out of 58, and two repositories of the fleet were measurably
off the norm with nothing to say so.

Escape hatch, declared in both: `AIMWEB_NO_HOOK=1`. It exists so the hooks stay installed —
a guard with no declared bypass gets bypassed with `--no-verify`, which switches
off every guard at once. In a fresh clone, enable them with
`git config core.hooksPath tools/git-hooks`.

## 0.1.0 - the ai-memory panel becomes an application of its own

First commit of **ai-memory-web**: a read-only web panel over the SQLite index
of [ai-memory](https://github.com/akitaonrails/ai-memory) — what the coding
agents remember, and how it was collected.

The code was the `AI-MEMORY` module of
[samirhvbr/samirhv-site](https://github.com/samirhvbr/samirhv-site), where it
was an admin area of a personal downloads site: coupled to that app's layout,
its authentication, its `admin.*` route and view namespaces, and its Canvas icon
font. Nothing about reading the ai-memory index has anything to do with that
site, and nobody else could run it. Extracting it is
[ADR-005](docs/decisions.md#adr-005).

What came across unchanged is the substance: the read-only access layer
(`PRAGMA query_only` pinned at the connection), the nine query repositories, the
two-layer availability guard, the daily durable snapshot, the polling dashboard
and its accessibility affordances, and the regression tests that lock down the
September 2026 outage — the one where a WAL reader could not create the
`-shm`/`-wal` sidecars and `SELECT 1` kept the probe green while every screen
returned a 500.

What changed is everything the host app used to provide. The app now has its own
layout and design tokens, its own single-operator login (`aimemory:user`, no
sign-up route), its own database for the snapshot history, English URLs
(`/pages`, `/sessions`, `/observations`) and an English UI. The 14 glyphs the
screens needed became an inline-SVG component, so the panel renders on a host
with no outbound network and no icon font; the `admin-*` CSS vocabulary became
`card` / `table` / `btn`. There is no build step at all — no Node, no Vite: two stylesheets and one script are served straight from `public/`, cache-busted by mtime.

The documentation was reorganised around the two questions the title asks. What
ai-memory collects is [docs/what-ai-memory-collects.md](docs/what-ai-memory-collects.md),
table by table, listing only what this app actually queries. How it is collected
is split into [docs/read-only.md](docs/read-only.md) (the invariant and the
guard), [docs/permissions.md](docs/permissions.md) (the WAL trap and the
shared-group recipe), [docs/durable-history.md](docs/durable-history.md) and
[docs/live-dashboard.md](docs/live-dashboard.md). The four decisions that had
only ever been prose are now ADRs.

samirhv-site keeps its module for now. The two are separate codebases and will
drift; that cost is named in ADR-005.
