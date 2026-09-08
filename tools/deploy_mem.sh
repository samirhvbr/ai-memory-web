#!/usr/bin/env bash
#
# Deploy ai-memory-web on the host that serves it (admin.shvia.org, blue3site).
#
# The panel is HOST-COUPLED: it reads the ai-memory index straight off the
# filesystem, so it is deployed on the machine ai-memory runs on, and there is
# no build step to run — no Node, no Vite, just PHP.
#
# Run it as the user that OWNS the checkout, not as root:
#
#     sudo -u b3sys tools/deploy_mem.sh
#
# What it deliberately does NOT do, and why:
#
#   * It does not run migrations. It REFUSES to continue when a release adds
#     one, and tells you to run it by hand — a deploy script that silently
#     migrates a production database is a deploy script that eventually
#     silently loses one.
#   * It does not enable the `aimemory:snapshot` schedule. That cron writes a
#     daily row into the panel's own database; pointed at an unreachable index
#     it writes zeros, and two weeks later those zeros look like history.
#   * It does not touch the ai-memory index. This app only ever SELECTs
#     (docs/read-only.md), and deploying it must not be the exception.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FPM_SERVICE="${FPM_SERVICE:-php8.4-fpm}"
SMOKE_URL="${SMOKE_URL:-http://127.0.0.1/login}"
SMOKE_HOST="${SMOKE_HOST:-admin.shvia.org}"

cd "$APP_DIR"

say() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
die() { printf '\n\033[31mFAILED: %s\033[0m\n' "$*" >&2; exit 1; }

[ -f artisan ] || die "no artisan in $APP_DIR — is this the application root?"
[ -f .env ]    || die "no .env in $APP_DIR — this is a deploy, not a first install"

if [ "$(id -u)" -eq 0 ]; then
    die "running as root would leave root-owned files in the checkout. Use: sudo -u <owner> $0"
fi

say "Version before"
git rev-parse --short HEAD
cat version.md

say "Pulling"
git pull --ff-only

say "Version after"
git rev-parse --short HEAD
cat version.md

say "Dependencies (production only)"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# A release that adds a migration has to be applied deliberately. `migrate:status`
# exits non-zero on a pending migration, so the guard is the exit code itself.
say "Checking for pending migrations"
if ! php artisan migrate:status --pending >/dev/null 2>&1; then
    php artisan migrate:status || true
    die "there are pending migrations. Apply them yourself, then re-run:
       php artisan migrate --force"
fi
echo "none pending."

# config:cache freezes .env into the cache. Any .env change from here on needs
# this script (or `php artisan config:clear`) to take effect — the symptom of
# forgetting is a correct .env that the app appears to ignore.
say "Caching config, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

say "Reloading $FPM_SERVICE"
sudo systemctl reload "$FPM_SERVICE"

say "Smoke test"
code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $SMOKE_HOST" "$SMOKE_URL" || true)"
[ "$code" = "200" ] || die "$SMOKE_URL answered $code, expected 200. The deploy is live but the login page is not."
echo "login page: 200"

# The panel answers 200 with an explanatory notice when the index is unreachable
# — never a 500 — so a green smoke test does NOT prove it can read the index.
# That is what this last line is for.
say "Index reachability, from the web user's point of view"
php artisan tinker --execute='
    $db = app(\App\Services\AiMemory\AiMemoryDatabase::class);
    echo $db->isAvailable()
        ? "ai-memory index: reachable (".$db->path().")\n"
        : "ai-memory index: UNREACHABLE — ".$db->unavailableReason()."\n";
'

say "Done"
