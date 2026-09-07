# Runbook — ai-memory-web

> **Status:** `ACTIVE` · From a clean machine to a running environment, and the
> checklists that gate a release.

## 1. Requirements

| | |
|---|---|
| **PHP** | 8.3+ with `pdo_sqlite` (**mandatory** — it is how the ai-memory index is read), `mbstring`, `openssl`. On Debian/Ubuntu: `apt install php8.4-cli php8.4-fpm php8.4-sqlite3 php8.4-mbstring` |
| **Composer** | 2.x |
| **ai-memory** | running **on the same host**, with its `memory.sqlite` readable by the web user — this is the part that actually bites, see [permissions.md](permissions.md) |
| **A database for the app itself** | SQLite by default (a file in `database/`). MySQL/MariaDB works too; nothing here needs it |
| **Node / npm** | not used. There is no build step: two plain stylesheets and one JS file are served straight from `public/`, cache-busted by mtime |

## 2. From a clean machine to running

```bash
git clone git@github.com:samirhvbr/ai-memory-web.git
cd ai-memory-web
git config core.hooksPath tools/git-hooks   # once per clone — see §6

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# point it at the ai-memory index on this host
${EDITOR:-nano} .env          # AI_MEMORY_SQLITE_PATH=...

# create the one account (there is no sign-up page)
php artisan aimemory:user you@example.com --name="Your Name"

php artisan serve             # http://127.0.0.1:8000
```

If the panel comes up showing the **"ai-memory is not reachable"** notice, that
is the app working as designed — read what the notice says, then
[permissions.md §4](permissions.md#4-diagnosis-from-the-web-users-point-of-view).

## 3. Configuration

Everything lives in `.env`. The ones that matter:

| Variable | Required | What it is |
|---|---|---|
| `AI_MEMORY_SQLITE_PATH` | **yes** | Absolute path of `memory.sqlite` **on this host**. Default `/opt/ai-memory/data/db/memory.sqlite`. Empty or wrong ⇒ the panel degrades to the notice. |
| `AI_MEMORY_TIMEZONE` | no | Display timezone for timestamps (ai-memory stores UTC microseconds). Default `UTC`. |
| `AI_MEMORY_CHART_DAYS` | no | Days in the activity charts. Default `30`. |
| `AI_MEMORY_PER_PAGE` | no | Rows per page in the listings. Default `50`. |
| `AI_MEMORY_BRAND` | no | Name in the header — useful when two installs are open at once. |
| `APP_KEY` | **yes** | `php artisan key:generate`. **A secret**: never committed. |
| `DB_*` | yes | The app's **own** database (users, sessions, snapshots). Nothing to do with ai-memory. |

`AI_MEMORY_SQLITE_PATH` is not a secret — it is a path, and the notice prints it
on purpose. `APP_KEY` and any `DB_PASSWORD` are
([security.md §5](security.md#5-secrets-and-configuration)).

## 4. Deploy

There is no build step, so a deploy is: pull, install, migrate, warm, restart.

```bash
cd /srv/www/ai-memory-web
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
systemctl reload php8.4-fpm
```

**Two traps, both about caches:**

- **`config:cache` outranks `.env`.** After changing `AI_MEMORY_SQLITE_PATH`,
  the panel keeps reading the old path until `php artisan config:cache` runs
  again. If a path change "did nothing", this is why.
- **The web user's group membership is read at process start.** After
  `usermod -aG aimemory-read www-data` you must **restart** PHP-FPM — a reload
  is not enough.

**The scheduler is part of the deploy**, or the history never starts:

```cron
* * * * * cd /srv/www/ai-memory-web && php artisan schedule:run >> /dev/null 2>&1
```

That is what runs `aimemory:snapshot` daily
([durable-history.md](durable-history.md)). Verify it by hand once:

```bash
sudo -u www-data php artisan aimemory:snapshot
```

**Exposure.** The panel shows, in plain text, everything the agents remember
about every project on the host. Put it behind the same boundary you would put a
staging admin: a private network, a VPN, or at minimum TLS plus IP allow-listing
at the web server. The app's own login is one factor, not a perimeter.

## 5. When it breaks

| Symptom | Where to look |
|---|---|
| Every screen shows the notice | The notice itself names the failure — then [permissions.md](permissions.md) |
| Notice says `pdo_sqlite` is missing | `apt install php8.4-sqlite3 && systemctl restart php8.4-fpm` |
| Dashboard's live dot is red | `/live` is failing; `storage/logs/laravel.log` has the exception |
| "Historical evolution" is empty | The scheduler is not running, or `aimemory:snapshot` has never succeeded |
| An HTTP 500 on a panel screen | **A bug.** The contract is a notice, never a 500 ([read-only.md §4](read-only.md#4-the-availability-guard--two-layers-because-one-is-not-enough)) — the log has the exception |

## 6. The git hooks

Once per clone — yours, and every collaborator's:

```bash
git config core.hooksPath tools/git-hooks
```

Confirm both directions before you rely on them:

```bash
git commit --allow-empty -m "feat: test"                    # must be REJECTED
git commit --allow-empty -m "0.1.0 - first commit of the repo"  # must be accepted
```

The escape hatch in both hooks is `AIMWEB_NO_HOOK=1`.
Rules: [versioning.md](versioning.md).

## 7. Pre-flight before making a repository public

A private repository accumulates content that assumed privacy. Before flipping
visibility:

- [ ] `git log -p | grep -iE 'password|secret|token|api[_-]?key'` — scan the
      **history**, not just the working tree. A secret removed from HEAD is
      still in every clone.
- [ ] Any secret ever committed has been **rotated**, not merely deleted.
- [ ] No internal hostname, private IP range or infrastructure path that should
      not be public.
- [ ] `LICENSE` is present and `NOTICE` agrees with it about the license and the
      copyright holder.
- [ ] `SECURITY.md` names a reporting address that is actually monitored.
- [ ] Every prescriptive document declares its status; nothing reads as current
      while describing something abandoned.
- [ ] `.continue/` holds no finished item and no half-page detail.
- [ ] `README.md` is in English and describes what the project **is**, not what
      it was going to be.
- [ ] No placeholder left over from the skeleton:
      `grep -rn '<[A-Z_]\+>' . --exclude-dir=.git`

## 8. Verifying the repository still conforms

```bash
# the twins are identical below the H1
diff <(tail -n +2 CLAUDE.md) <(tail -n +2 AGENTS.md)

# version.md is a bare X.Y.Z
grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$' version.md && echo ok

# the agent posture parses
python3 -m json.tool .claude/settings.json > /dev/null && echo ok

# every version in history has a tag and a Release (prints what is missing)
./tools/release.sh --backfill --dry-run
```

If the `diff` on the twins reports anything other than the mirroring comment,
an edit was applied to one file and not the other.

## 9. Running the suite where PHP has no `pdo_sqlite`

The whole panel reads SQLite, so **without `pdo_sqlite` the suite does not fail
— it skips**, and a skipped suite reports the same cheerful green as a passing
one. On a workstation without the extension that is 62 of the 89 cases: every
feature test, the availability guard, the seeded fixture and the schema probe.
Only the pure-maths units run.

Check first, and believe the answer:

```bash
php -m | grep -i pdo_sqlite || echo 'MISSING: the suite will skip, not fail'
```

If it is missing and you can install it, that is the better fix
(`apt install php8.4-sqlite3`, then restart PHP-FPM). Where you cannot — a
machine whose PHP is managed elsewhere, or one that deliberately has no SQLite —
run the suite in a container instead. The official `php:8.4-cli` image ships
`pdo_sqlite` and `mbstring` already, so nothing has to be built:

```bash
cd /path/to/ai-memory-web

# the suite
docker run --rm -v "$PWD":/app -w /app --user "$(id -u):$(id -g)" php:8.4-cli \
  sh -c 'php artisan config:clear --ansi >/dev/null && php vendor/bin/phpunit'

# the linter
docker run --rm -v "$PWD":/app -w /app --user "$(id -u):$(id -g)" php:8.4-cli \
  php vendor/bin/pint --test
```

Three things about that command line are load-bearing:

- **`--user "$(id -u):$(id -g)"`** — without it the container runs as root and
  every file it writes (`bootstrap/cache`, `storage/logs`, anything Pint fixes)
  comes back owned by root, inside your working copy.
- **Not root** is also what lets `AiMemoryDatabaseTest` run at all: it drops a
  directory to `0555` to reproduce the WAL permission failure, and root ignores
  permission bits, so those 8 cases skip when the process is root
  ([permissions.md](permissions.md) §6).
- **`vendor/` is mounted, not installed** — it is pure PHP, so the host's
  `composer install` is what the container uses. No `composer` step is needed
  inside it.

A healthy run today is **89 tests, 201 assertions, 1 skipped**. The one skip is
`AiMemorySchemaCanaryTest`, disarmed on purpose until there is a real index to
point it at:

```bash
AI_MEMORY_CANARY_PATH=/opt/ai-memory/data/db/memory.sqlite php artisan test
```

Any other skip means the environment is lying to you about being green.
