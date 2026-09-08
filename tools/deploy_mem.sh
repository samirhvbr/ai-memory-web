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
# It needs exactly one sudo rule to exist, and nothing more — reloading PHP-FPM
# is its last action and the only thing it cannot do as the checkout owner:
#
#     /etc/sudoers.d/b3sys-deploy
#     b3sys ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
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

# WHICH FRONT DOOR — and why a 403 is currently a pass.
#
# The TLS vhost is the real front door. While it does not exist, the :80 vhost
# is deliberately CLOSED: admin.shvia.org has no DNS record yet, so certbot
# cannot answer an HTTP-01 challenge, and a login form served over plaintext
# HTTP would put the operator's password on the wire in the clear. :80 answers
# 403 to everything except the ACME path, so a 403 here means exactly what we
# want it to mean: Apache is up, the vhost is loaded, and the door is shut on
# purpose.
#
# That tolerance is TEMPORARY and it expires by itself. The moment the TLS
# vhost is enabled, this script tests THAT instead and demands a 200 — where a
# 403 would be a real failure, not a closed door.
TLS_VHOST="${TLS_VHOST:-/etc/apache2/sites-enabled/${SMOKE_HOST}-le-ssl.conf}"

if [ -e "$TLS_VHOST" ]; then
    say "Smoke test — https (the TLS vhost is enabled)"
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 \
        --resolve "${SMOKE_HOST}:443:127.0.0.1" "https://${SMOKE_HOST}/login" || true)"
    [ "$code" = "200" ] || die "https://${SMOKE_HOST}/login answered $code, expected 200.
       The deploy is live but the login page is not."
    echo "login page over TLS: 200"
else
    say "Smoke test — http (no TLS vhost yet)"
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 -H "Host: $SMOKE_HOST" "$SMOKE_URL" || true)"
    case "$code" in
        403) echo "login page: 403 — :80 is closed on purpose (no DNS, no certificate)."
             echo "            Reopen it only to measure, and close it again." ;;
        200) echo "login page: 200 — but this is PLAINTEXT HTTP and the panel has a"
             echo "            login form. Get the certificate in place." ;;
        *)   die "$SMOKE_URL answered $code, expected 200 (open) or 403 (closed on purpose)." ;;
    esac
fi

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
