# Permissions — the WAL trap, and the recipe that fixes it

> **Status:** `ACTIVE` · The single most important operational page in this
> repository. **Read permission on `memory.sqlite` is not enough**, and the
> error you get when you stop at read permission points at the wrong problem.

## 1. The coupling

```
┌───────────────────────────── one host ──────────────────────────────┐
│                                                                      │
│   PHP-FPM (ai-memory-web)  ──reads (RO)──►  /opt/ai-memory/data/db/  │
│        [www-data]                           memory.sqlite            │
│                                             (+ -wal, -shm)           │
│                                                   ▲                  │
│   ai-memory service  ──writes (sole writer)───────┘                  │
│        [systemd or Docker]                                           │
└──────────────────────────────────────────────────────────────────────┘
```

This app opens the file straight off the filesystem, as a second reader. It has
to run on the same machine. Where the file is depends on the install:

| Install | Data directory | memory.sqlite |
|---|---|---|
| 2.x native (`--data-dir`) | `/opt/ai-memory/data` | `/opt/ai-memory/data/db/memory.sqlite` |
| upstream systemd default | `/var/lib/ai-memory` | `/var/lib/ai-memory/db/memory.sqlite` |
| 1.x Docker volume | `ai-memory-data` | `/var/lib/docker/volumes/ai-memory-data/_data/db/memory.sqlite` |

Find the real path:

```bash
ls -l /opt/ai-memory/data/db/memory.sqlite                     # 2.x native install
systemctl cat ai-memory | grep -- --data-dir                   # whatever the unit says
docker volume inspect ai-memory-data -f '{{ .Mountpoint }}'    # 1.x, then + /db/memory.sqlite
```

Put it in `.env` as `AI_MEMORY_SQLITE_PATH`. If the deploy runs
`php artisan config:cache`, a change in `.env` only takes effect after the cache
is rebuilt.

## 2. Why read permission is not enough

A WAL database is read through two sidecar files next to it: `-wal` (the log)
and `-shm` (the shared-memory index). When ai-memory has checkpointed and closed
its last connection, **those files do not exist**, and the *reader* is the one
that has to create them — which requires **write permission on the directory**
holding the database.

Without it the very first `SELECT` fails with `SQLITE_READONLY_DIRECTORY`, which
PDO surfaces as the thoroughly misleading:

```
SQLSTATE[HY000]: General error: 8 attempt to write a readonly database
```

...on a plain `SELECT`. Two corollaries, before you try to be clever:

- **`?mode=ro` does not fix it.** A read-only handle cannot create the sidecars
  either. That is why the connection is a plain path plus `PRAGMA query_only`.
- **`chmod` on `-wal`/`-shm` does not stick.** SQLite recreates them with the
  mode of the *main* database file (measured: exactly its mode, regardless of
  the creating process's umask), and `wal_checkpoint(TRUNCATE)` / `VACUUM` /
  last-close delete them. The modes you actually have to fix are the ones on
  `memory.sqlite` and on its **directory**.

**Why an upgrade triggers this.** Since 1.27.0 ai-memory creates its data
directories `0700` and `memory.sqlite` `0600`, owned by the service user,
independently of umask, and leaves *existing* installations alone. An old Docker
volume predates that hardening; a fresh `/opt/ai-memory/data/db` does not.

## 3. Granting access — the shared-group recipe

Run on the host, as root. A **dedicated group** is used instead of the service's
own group, so it works whether ai-memory runs as `root` or as its own system
user: the files stay owned by the service (owner keeps `rw` regardless of
group), and the group only carries the reader in.

```bash
DB=/opt/ai-memory/data/db/memory.sqlite     # adjust if the data dir differs
DBDIR=$(dirname "$DB")

# 1. a group whose only purpose is "may read the ai-memory index"
groupadd -f aimemory-read
usermod -aG aimemory-read www-data

# 2. traverse-only on the way in: the group gets x, not r (no listing)
chgrp aimemory-read /opt/ai-memory /opt/ai-memory/data
chmod 0710          /opt/ai-memory /opt/ai-memory/data

# 3. the db directory: group rwx + SETGID, so every file created in it — by
#    either process — inherits the group
chgrp aimemory-read "$DBDIR"
chmod 2770          "$DBDIR"

# 4. the database itself: group rw. SQLite creates -wal/-shm with this exact
#    mode, which is what lets both processes maintain them.
chgrp aimemory-read "$DB"
chmod 0660          "$DB"
for f in "$DB"-wal "$DB"-shm; do [ -e "$f" ] && chgrp aimemory-read "$f" && chmod 0660 "$f"; done

# 5. php-fpm only picks up the new group membership on restart
systemctl restart php8.4-fpm
```

If ai-memory runs under systemd with `ProtectSystem=strict`, make sure its unit
has a `ReadWritePaths=` covering the data dir (`systemctl cat ai-memory`); the
upstream template only lists `/var/lib/ai-memory`.

### The trade this makes

Step 3 hands the web user real **write** capability over that directory — a
deliberate departure from ai-memory's declared threat model ("single-tenant
service, we rely on filesystem permissions"). This app's side of the bargain is
`PRAGMA query_only = 1` plus SELECT-only repositories
([read-only.md](read-only.md)). If that trade ever stops being acceptable,
[decisions.md ADR-001](decisions.md#adr-001) lists the two decoupled
alternatives.

## 4. Diagnosis, from the web user's point of view

```bash
P=/opt/ai-memory/data/db/memory.sqlite
sudo -u www-data test -r "$P"              && echo "read: OK"      || echo "read: DENIED"
sudo -u www-data test -w "$(dirname "$P")" && echo "dir write: OK" || echo "dir write: DENIED  <-- the 500"
ls -la "$(dirname "$P")"; id www-data
sudo -u www-data php /path/to/ai-memory-web/artisan aimemory:snapshot   # must write a snapshot
```

A one-liner that reproduces exactly what the app does — it fails the same way
the panel did, and succeeds once the permissions are right:

```bash
sudo -u www-data php -r '$d=new PDO("sqlite:/opt/ai-memory/data/db/memory.sqlite");
  $d->exec("pragma query_only=1");
  var_dump($d->query("select count(*) from sqlite_master")->fetchColumn());'
```

## 5. When it is not permissions

| Symptom in the notice | Cause |
|---|---|
| "The file […] does not exist on this host." | wrong `AI_MEMORY_SQLITE_PATH`, or the app is not on the ai-memory host |
| "The PHP extension `pdo_sqlite` is not installed" | `apt install php8.4-sqlite3`, then restart PHP-FPM |
| "locked […] longer than `busy_timeout`" | ai-memory is in a long write cycle; transient |
| "missing a table/column this screen queries" | ai-memory version change — check upstream's migrations |
| "The database path […] is empty" | `AI_MEMORY_SQLITE_PATH` unset **and** `config:cache` stale |

## 6. Regression tests

`tests/Unit/AiMemory/AiMemoryDatabaseTest.php` builds a real WAL database in a
temporary directory, deletes its sidecars, drops the directory to `0555`, and
asserts that the guard sees the failure — including the assertion that
`SELECT 1` would *not* have caught it, which is what fails if anyone
"simplifies" the probe back to a constant.

Those tests **skip** where `pdo_sqlite` is absent or the process is root (root
ignores permission bits, so the failure cannot be simulated).
