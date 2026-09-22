# Changelog

Entries in the commit-message format (`X.Y.Z - description in English`, see
[docs/versioning.md](docs/versioning.md)), newest first. **Each `##` heading is
literally the commit subject.**

Bodies are narrative: what changed, why, and what was measured. This file is
never rewritten.

## 0.1.13 - give CI an APP_KEY, and build the test operator the way this repo does

The first CI run came back **51 failed**, and both causes were the environment rather
than the app — which is the whole reason the previous entry said that run would be
reporting something already true and unmeasured.

| failures | cause |
|---|---|
| **47** | `MissingAppKeyException` — a clean checkout has no `.env`, so nothing set `APP_KEY` and every test that boots the encrypter died |
| **4** | `Call to undefined method App\Models\User::factory()` — this repository has **no** `database/factories`; the sibling suites build the operator with `User::create([...])` and the test added in `0.1.12` did not |

⚠️ **The `APP_KEY` failure is the more interesting one, because the error does not name
its own cause.** It says "No application encryption key has been specified" and points at
`EncryptionServiceProvider`; nothing in it says *"there is no `.env` here"*. Locally the
suite passed because the developer's `.env` supplied the key — so this is a defect that
only exists in a clean checkout, and until this week there was no clean checkout to find
it in.

The key is generated per run instead of committed to `phpunit.xml`: a key-shaped literal
in a repository is the kind of thing that gets copied somewhere it matters.

🔬 **Still true and not fixed here:** a fresh clone with no `.env` cannot run
`php artisan test` locally either, for exactly the same reason. CI now supplies the key;
the clone does not. That is a separate delivery.

## 0.1.12 - run the suite in CI, and refuse a skip nobody declared

`f197`. Two halves of the same defect: a divergence between this app and its twin, and a
suite that was not measuring most of itself.

### The fork, measured

`f197` claimed *39 shared files, 37 divergent, 2 byte-identical* between this repository
and `samirhv-site`. **That count does not reproduce**, and the paths it named
(`app/Http/Controllers/Admin`, `resources/views/admin/ai-memory`) do not exist here — this
app flattened them. Measured by sha256 against the real counterparts:

| here | in samirhv-site | shared | byte-identical |
|---|---|---|---|
| `app/Services/AiMemory` | `app/Services/AiMemory` | 11 | **0** |
| `resources/views/ai-memory` | `resources/views/admin/ai-memory` | 16 | **0** |
| `AiMemoryController` (274 lines) | `Admin/AiMemoryController` (279 lines) | 1 | 0 |

So: **28 file pairs, none identical** — the duplication is real and the divergence is
total, but the finding's numbers were not. Saying which part reproduced is the point; a
finding list that rounds its own evidence up stops being worth reading.

### The functional divergence, which did reproduce

`samirhv-site` validates the search term (`'q' => ['nullable', 'string', 'max:200']`) and
this app did not — while its three other filtered actions (`pages`, `sessions`,
`handoffs`) all do. Ported here, so the four answer the same way.

🔬 **Honest severity: consistency, not a vulnerability.** `SearchRepository::toMatch()`
already caps the token COUNT at 10, so a long query never became a long `MATCH` expression
— unless it is one long token. Measured: 2 MB of input costs **2.52 ms** in the tokenising
regex and yields a 2 MB term. Small. The value is that nobody has to remember which of the
four actions is the exception.

### 🔴 The larger half: this repository had no CI

`.github/workflows/` held only `release.yml`, which publishes a Release when `version.md`
moves and never runs a test. So `php artisan test` ran only where somebody typed it — and
on the machine where that was measured it reported **"36 passed"** while skipping **62 of
98**, because `ext-pdo_sqlite` (a *declared* requirement in `composer.json`;
`composer check-platform-reqs` says `missing`) is not installed there.

Sixty-two tests that skip locally and run nowhere else are not coverage. `ci.yml` now
installs the extension and runs `pint --test` plus the suite on every push and pull
request, with both actions pinned by SHA.

### A skip is not a pass

The last CI step reads the junit of the run above — it does not re-run the suite — and
fails on any skip **not named in `tests/expected-skips.txt`**. An allowlist, not a count:
a count lets a deliberate skip be traded for an accidental one without the number moving.
It exits **2** when it cannot read the junit, because *"did not measure"* must never be
readable as *"clean"*.

One entry today: the schema canary, which needs a real ai-memory index and is armed by
hand with `AI_MEMORY_CANARY_PATH`. The root-only skip in `AiMemoryDatabaseTest` is
deliberately **not** listed — GitHub's runner is not root, so if CI ever moves into a root
container the guard should complain rather than quietly stop measuring eight tests.

Rehearsed locally against the real junit of a 66-skip run: the canary is recognised, the
other 65 are named, exit 1; with the file absent, exit 2.

⚠️ **This is the first CI run this repository has ever had.** If it comes back red, that
is the guard reporting something that was already true and unmeasured, not a regression
introduced here.

### Correction to `0.1.11`

That entry names the reversal target `plaintext_200_FAILS_the_deploy`. The method is
`plaintext_200_fails_the_deploy` — `pint`'s `php_unit_method_casing` renamed it, and this
delivery is what ran `pint` over that file for the first time. The changelog is never
rewritten, so the correction lives here.

## 0.1.11 - fail the deploy when the login page is served over plaintext HTTP

`f196`. `tools/deploy_mem.sh` smoke-tests the login page after reloading PHP-FPM. With no
TLS vhost it asks `:80`, and the `200` branch printed a warning and **fell through**:

```
200) echo "login page: 200 — but this is PLAINTEXT HTTP and the panel has a"
     echo "            login form. Get the certificate in place." ;;
```

Only `*)` called `die`. Under `set -euo pipefail` a fall-through is still exit 0, so the
script printed `Done.` — a successful deploy — immediately after publishing a login form
in the clear.

⚠️ **That is the ordinary outcome, not an edge case.** On a host whose `:80` vhost serves
the app — the Apache default almost everywhere — 200 is exactly what the probe gets. The
403 the surrounding comment describes depends on `:80` being deliberately shut, which is
true of one host today. The operator's password is this panel's entire perimeter: it
renders, in plain text, everything the agents remember about every project on the host.

### What changed

`200` now fails the deploy. The refusal names its own escape hatch, because a refusal that
does not say how to proceed gets worked around by commenting the check out:

```bash
sudo -u b3sys AIMWEB_ALLOW_PLAINTEXT=1 tools/deploy_mem.sh
```

The `403` tolerance is untouched, and every other code still fails — including `000`,
which is what curl reports when it could not connect at all.

### The verdict is a function so that it can be RUN

`smoke_verdict` was extracted from the `case` and the script gained an `AIMWEB_SOURCE_ONLY=1`
early return, so `tests/Feature/DeployRefusesPlaintextLoginTest.php` sources the real file
and calls the real function with the real shell, reading the real exit code. A ruler that
grepped for `die` would pass on the word appearing in a comment and fail on a rename; this
one measures the only thing the deploy depends on.

| reversal | what went red |
|---|---|
| `200` goes back to a warning that passes | `plaintext_200_FAILS_the_deploy`, `the_refusal_names_its_own_escape_hatch` |
| any other code passes | `anything_else_still_fails`, all five data sets |
| the escape hatch is inverted | `plaintext_200_FAILS_the_deploy`, `the_escape_hatch_lets_a_deliberate_operator_through` |

### 🔴 Measured while verifying this, and NOT fixed here

`php artisan test` on this machine reports **36 passed of 98 — and 62 skipped**, because
`pdo_sqlite` is not installed. This repository has **no CI workflow that runs the suite**
(`.github/workflows/` holds only `release.yml`, which publishes Releases). So those 62
tests do not run here and do not run anywhere. The nine tests added by this delivery are
among the ones that do run: they shell out and need no database.

## 0.1.10 - showcase the interface in the repository README

Lead the README with a dashboard capture and an expandable visual tour so
visitors can see the memory panel before installing it. Turn the screenshot
directory into a captioned gallery and identify the supplied captures as the
Portuguese site integration from which the standalone English app was extracted.
Verify that every embedded image resolves to a tracked file.

## 0.1.9 - prepare the screenshot directory

Add a tracked screenshot directory with naming and caption guidance so owners
can pull the repository and contribute interface captures. Link the directory
from the documentation index without embedding images that do not exist yet.

## 0.1.8 - the panel is verified against the real index, and queue item 1 closes

The app was extracted, built and tested on a machine with no `pdo_sqlite`, so
until now 62 of its 89 tests had never executed and no screen had rendered a
real row. Both happened, on the host that has the index — `blue3site`, PHP
8.4.24 with the driver already present, reading
`/opt/ai-memory/data/db/memory.sqlite` (a symlink to `/srv/…`, the same file the
runbook names).

**The suite.** With the driver, 61 of the 62 gated tests run and pass; the 62nd
is the canary, which arms separately. Armed, the whole suite is **89 passed, 0
skipped** — against 27 passed / 62 skipped on a driverless box. Nothing failed,
which is the part worth stating plainly: the hand-written fixture in
`SeedsAiMemoryIndex` still matches what upstream actually ships.

**The canary, second arming.** 70.4 s against a 39.5 GiB index. The first arming
recorded in the runbook was 27 s against 18.4 GiB — the index has roughly
doubled and the probe's cost went with it. It is the same walk of a page's full
version history, so this is the number to watch, not a regression.

**Every screen, once.** All fifteen routes answered 200 — `/login` 302s to the
dashboard when already authenticated — and **not one rendered the degradation
notice**. The dashboard came back in 267 ms with 30 rows; `/projects` 116 ms and
**116 rows**; `/observations` 285 ms; `/pages`, `/sessions` and `/handoffs` 50
rows each; `/workspaces` 3. `/live` answered `available: true` with real counts.
`aimemory:snapshot` wrote **3,170 pages · 2,658 sessions · 371,670
observations**.

Two things that looked like findings and were not, recorded so nobody re-opens
them: a two-word search returning nothing is correct — `purge` has zero hits in
`pages_fts`, so `purge session` cannot have any; and the `30,000` on the
dashboard is rendered data, not a clipped axis — `niceMax(91556)` is 95,000, and
the JS twin is present, as an arrow function at `dashboard.js:45`.

The queue item leaves `.continue/`. What replaced it there is nothing: it is
done.

## 0.1.7 - the queue says how many tests are gated, counted rather than remembered

Queue item 1 promised that installing the driver would make "the 28 skipped
tests" run. The real number is **62**, measured on this checkout. Twenty-eight
was written when the suite was smaller and was never recounted, and a closing
procedure whose acceptance criterion is a stale number is one somebody can
satisfy without noticing they did not.

The same item now says out loud what the attempt to close it ran into: the
driver and the index have to be on the *same* machine. A development box has
neither, and a host that has one does not necessarily have the other, so the
step is not "run these three commands somewhere" — it is "run them where the
index actually is".

The item stays open. Nothing here closes it; it is only worth less time to the
next reader.

## 0.1.7 - the schema-probe tests skip instead of erroring where the driver is absent

`AiMemorySchemaProbeTest` called `markTestSkipped()` *before* `parent::setUp()`.
PHPUnit still runs `tearDown()` after a skip, and this class's `tearDown()`
calls `cleanUpAiMemoryIndex()`, whose first statement is `DB::purge('aimemory')`
— a container lookup. With the application never booted, that resolved to
`Target class [db] does not exist.` and the three tests reported as **errors**,
not skips.

The consequence was bigger than three tests. On any machine without
`pdo_sqlite` — which is every machine this repository has been developed on, and
the reason queue item 1 exists — `php artisan test` came back
`"result":"failed"`. A suite that is merely ungated on this host looked broken
on it, and the one signal that would tell the two apart was the thing being
swallowed.

The fix is the order its sibling already uses: `AiMemoryDatabaseTest` boots the
application first and guards second, and skips cleanly because of it. Measured
on PHP 8.4.24 with no `pdo_sqlite`, whole suite, before and after:

    before   "result":"failed"   tests=92  passed=27  skipped=62  errors=3
    after    "result":"passed"   tests=89  passed=27  skipped=62  errors=0

Pint stays clean. Nothing about what the tests assert changed — only whether a
machine that cannot run them says so honestly.

## 0.1.6 - the deploy script survives a closed front door and an unprivileged owner

Three things the first real deploy found, fixed together because they are the
same story: the script was written against a host that did not exist yet.

**It could not reload PHP-FPM.** The script runs as the checkout owner and
refuses to run as root, so the reload is the one thing it cannot do on its own.
The owner is in `sudo`, but with a password, and a deploy script cannot answer a
prompt. The fix is one line in `/etc/sudoers.d/b3sys-deploy` granting NOPASSWD
for exactly `systemctl reload php8.4-fpm` and nothing else — `restart` still
asks, everything else still asks. It does not widen what that user may do; it
removes the prompt for one command. Documented in
[runbook.md](docs/runbook.md) §4.1, with the `visudo -c` check.

**Its smoke test demanded a 200 from a door that is shut on purpose.** The panel
now runs on a host where `admin.shvia.org` has no DNS record, so certbot cannot
issue a certificate, so there is no TLS — and a login form over plaintext HTTP
puts a password on the wire. The :80 vhost therefore answers 403 to everything
but the ACME path. The script now reads that correctly: while no TLS vhost is
enabled it accepts **403 as "up and closed on purpose"**, warns loudly on a 200,
and fails on anything else. The tolerance expires by itself — the moment
`sites-enabled/<host>-le-ssl.conf` exists, the smoke test moves to https and
demands a 200, where a 403 would be a genuine failure.

**The first deploy on a host cannot use the script**, because the checkout has
to already contain it. Named in §4.1 with the two-line sequence, along with the
consequence that a first run reports the version it moved *from* as though its
own pull had done the work.

Not fixed here: `composer.lock` is out of date against `composer.json`, which
the deploy surfaces as a warning on every run. That is a separate commit —
`composer update --lock`, no version changes, hash only.

## 0.1.5 - the schema canary runs as the web user, and the runbook says why

`docs/runbook.md` gains section 9.1. Arming `AiMemorySchemaCanaryTest` against a
real index has an identity requirement that is not obvious and fails in a
misleading way: it must run as **`www-data`**, not as the user that owns the
checkout.

Access to the index is carried by a dedicated group and the data directory is
`0710` — group traverse only ([permissions.md](docs/permissions.md) §3).
`www-data` is in that group because it has to read the index to render a screen.
The checkout owner is deliberately not: owning the code is not a reason to be
able to read every agent memory on the host. Run the canary as the owner and it
dies in `is_file()` before reaching a query, reporting a missing file rather
than a missing group — which is exactly the wrong thing to go looking for.

The section also records the two incidental-looking details that are not
(`HOME=/tmp`, because `www-data` has no writable home; and the
`.phpunit.result.cache` permission warning, which is correct and not a failure),
and that the canary needs dev dependencies a deploy has removed.

Measured on the first arming, against ai-memory 2.0.0 (18.4 GiB): passed in 27 s
— slow because the probe walks a page's full version history, and on that index
one path has 16,302 versions (#1).

## 0.1.4 - a deploy script for the host that already has the index

`tools/deploy_mem.sh` is the deploy for `admin.shvia.org` on the machine
ai-memory runs on: pull, production dependencies, cache config/routes/views,
reload PHP-FPM. There is no build step to run — no Node, no Vite — so that is
the whole of it.

What it refuses to do is the part worth writing down.

It **does not migrate**. It runs `migrate:status --pending` and stops when a
release adds a migration, telling the operator to apply it by hand. A deploy
script that silently migrates a production database is one that eventually
silently loses one.

It **does not enable the `aimemory:snapshot` schedule**. That cron writes one
row a day into the panel's own database; pointed at an index it cannot read it
writes zeros, and a fortnight later those zeros are indistinguishable from
history.

It **refuses to run as root**, because a deploy that leaves root-owned files in
the checkout breaks the next one.

And it does not trust its own smoke test. The panel answers 200 with an
explanatory notice when the index is unreachable — never a 500
([read-only.md](docs/read-only.md)) — so a login page returning 200 proves the
app is up and proves nothing about the index. The last step asks
`AiMemoryDatabase::isAvailable()` directly and prints the path it resolved, or
the reason it could not.

Measured on the first deploy, against the real ai-memory 2.0.0 index
(3.071 pages, 345.897 observations, 18,4 GiB): the thirteen screens answer
between 23 ms and 274 ms. The schema canary from 0.1.2, armed with
`AI_MEMORY_CANARY_PATH`, passed — every repository read this panel makes was
answered by the production schema, which is the first evidence that the
hand-written fixture transcribes it correctly.

Two things that deploy found, and that are not this release's to fix. A page
whose path has accumulated 16.302 versions renders in 156 s and 9,9 MB of HTML,
because the page screen lists every version of a path. And the dashboard's
"live" mode has no configuration flag: it polls `/live` every 15 s from every
browser that opens it, and the only in-app switch is a per-browser
`localStorage` toggle. On a WAL database whose sidecar sits at a 191 MiB
high-water mark, that first deploy denies `/live` at the web server instead.

## 0.1.3 - the git hooks are regenerated from repodocs

Both hooks of the standard are rewritten from repodocs, and `tools/release.sh`
with them when it came from there. `commit-msg` checks the shape of the subject
(`X.Y.Z - description`), refuses a Conventional Commits prefix and a vague
message, **and checks that the subject's `X.Y.Z` is the version this commit
carries in `version.md`**. `pre-push` compares the local `version.md` against
the remote default branch for a repeated or a backwards version — **only when
the push actually updates that branch**, so a branch deletion, a tag and a topic
branch pass through.

The hook does **not** check the language and could not: what it measures is the
shape and the number.

Escape hatch, declared in both: `AIMWEB_NO_HOOK=1`. In a fresh clone, enable them with
`git config core.hooksPath tools/git-hooks`.

## 0.1.2 - a seeded index renders every screen in the suite

Until now the only way to know whether a screen rendered was to point the panel
at a production `memory.sqlite`. `AiMemoryDatabaseTest` did build a real WAL
database, but a deliberately minimal one — six tables carrying the three or four
columns the availability guard touches, and no row worth reading. It proves the
guard degrades; it cannot render anything.

`Tests\Support\SeedsAiMemoryIndex` is the other half: a WAL index in a temporary
directory, with the tables and columns the panel actually queries, and rows
chosen for the cases that break on empty data. A page with three versions of the
same `(workspace, project, path)`, so the history list has a chain to walk. A
session with no `ended_at`. A handoff whose `open_questions`, `next_steps` and
`files_touched` are populated, since the listing only counts them and the detail
screen decodes them. A page with an empty body, one `pinned`, one on the
`procedural` tier the view's chip map does not know. Four `links` rows — resolved
in-project, resolved cross-project, unresolved (`to_page_id IS NULL`), and one
pointing back — plus `audit_log` and `client_activity`, none of which any screen
reads yet.

Ids are 16 bytes, because `lower(hex(id))` has to be the 32 characters the
`[0-9a-fA-F]{32}` route constraint accepts; a shorter BLOB makes every detail
route 404 and the failure reads like a missing row.

**Thirteen screens, not nine.** `routes/web.php` serves 8 listings and 5 detail
screens (plus `/live`, which is JSON). `AiMemoryScreensTest` covers all 13, each
asserting a string that could only have come from the fixture, so a screen that
renders its chrome and drops its content fails. A guard test asserts the count,
so screen number 14 cannot arrive without coverage. `PanelAccessTest::panelRoutes()`
went from 8 routes to 13: the five detail routes had no test that a guest is
turned away from them.

**The fixture is a hand-written transcription, and that is a risk with a name.**
The DDL was written against ai-memory 2.0.2; the server this panel is meant to
read reports 2.0.0, and the equivalence has not been verified — no query has
ever run against the production index from this repository. If upstream renames
a column, the fixture keeps the old name and the suite stays green while
production breaks.

**And the dashboard cannot be the thing that notices.** `StatsRepository::counts()`
swallows "no such table/column" on purpose, so that an ai-memory old enough to
lack `page_embeddings` or `auto_improve_proposals` does not take the screen down
— which means a column renamed out from under the panel surfaces there as a
zero, not as an error. That deliberate tolerance is the whole justification for
a canary: something has to fail loudly where the totals are designed to stay
quiet.

`AiMemorySchemaCanaryTest` is that something: point `AI_MEMORY_CANARY_PATH` at a
real index and every repository read runs against the real schema. It is skipped
everywhere today, because which index this panel reads is still an open
question.

An alarm nobody has seen ring is not an alarm, so the canary has its own test.
`AiMemorySchemaProbeTest` builds the same fixture with `pages.path` renamed and
asserts the probe goes red — and, separately, that `StatsRepository::counts()`
stays *quiet* about it, since it swallows "no such table/column" on purpose so
an older ai-memory does not take the dashboard down. That tolerance is exactly
why the dashboard cannot be what notices drift.

Measured: the suite went from 55 cases (28 of them skipped wherever `pdo_sqlite`
is absent) to **89 cases and 201 assertions**, with **1 skip** — the disarmed
canary. Run under PHP 8.4.23 with `pdo_sqlite`; `pint --test` clean.

[docs/runbook.md](docs/runbook.md) gains section 9, because the machine this was
built on has no `pdo_sqlite` and will not get one: it documents running the suite
in a `php:8.4-cli` container with the caller's uid, why `--user` is load-bearing
in both directions (no root-owned files in the working copy, and
`AiMemoryDatabaseTest` needs a non-root process to reproduce a permission
failure at all), and what a healthy run looks like — so the next session does not
rediscover that 62 skips read exactly like green.

`.gitignore` now covers `/.continue/MELHORIAS-*.md`. Planning material for this
panel is written in pt-BR and lives outside the repository; a copy has landed in
`.continue/` twice, and ADR-005 does not have an exception for a file someone
meant to keep local.

Two things this entry does not fix, on purpose. The README still says "Nine
screens" and `docs/what-ai-memory-collects.md` §5 lists 12, missing the Project
detail screen; that same file says `importance` is 0-10 where the upstream
CHECK is `BETWEEN 1 AND 10`. Those are documentation corrections and travel
separately.

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
