#!/usr/bin/env bash
#
# CI smoke test for the Payments Central PHP example (no secrets required).
#
# Boots a recorded mock of the core API (smoke/mock-router.php) that asserts
# every outgoing request matches core's contract, then drives the real demo
# app (public/index.php) through: charge -> list -> get -> refund -> checkout.
# Exits non-zero if any demo route errors or any request shape drifts.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

APP_PORT=4020
MOCK_PORT=4021
export MOCK_PORT

FAIL_FILE="$(mktemp)"
export SMOKE_FAIL_FILE="$FAIL_FILE"
: > "$FAIL_FILE"

# Generate the PSR-4 autoloader the front controller requires.
composer install --no-interaction --no-progress >/dev/null 2>&1 || composer dump-autoload >/dev/null 2>&1

# Minimal .env so the demo app has (placeholder) credentials and points at the mock.
cat > .env <<EOF
PAYMENTS_CENTRAL_API_KEY=sk_sandbox_smoke
PAYMENTS_CENTRAL_MERCHANT_ID=mer_smoke
PAYMENTS_CENTRAL_BASE_URL=http://127.0.0.1:$MOCK_PORT
EOF

php -S "127.0.0.1:$MOCK_PORT" "$ROOT/smoke/mock-router.php" >/tmp/php-mock.log 2>&1 &
MOCK_PID=$!
php -S "127.0.0.1:$APP_PORT" -t public "$ROOT/public/index.php" >/tmp/php-app.log 2>&1 &
APP_PID=$!

cleanup() {
  kill "$MOCK_PID" "$APP_PID" >/dev/null 2>&1 || true
  rm -f .env "$FAIL_FILE"
}
trap cleanup EXIT

# Wait for both servers.
for _ in $(seq 1 50); do curl -sf "http://127.0.0.1:$APP_PORT/" >/dev/null 2>&1 && break; sleep 0.2; done
for _ in $(seq 1 50); do
  curl -sf "http://127.0.0.1:$MOCK_PORT/api/v1/transactions?page=1&limit=1" \
    -H 'Authorization: Bearer x' -H 'x-merchant-id: m' >/dev/null 2>&1 && break
  sleep 0.2
done

rc=0
hit() { # method path label
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' -X "$1" "http://127.0.0.1:$APP_PORT$2")
  if [ "$code" -lt 200 ] || [ "$code" -ge 400 ]; then
    echo "FAIL $3: $1 $2 -> HTTP $code"
    rc=1
  else
    echo "ok   $3: $1 $2 -> HTTP $code"
  fi
}

hit POST "/demo/charge"                        charge
hit GET  "/demo/transactions"                  list
hit GET  "/demo/transaction?id=txn_smoke_1"    get
hit POST "/demo/refund?id=txn_smoke_1"         refund
hit POST "/demo/checkout"                      checkout

if [ -s "$FAIL_FILE" ]; then
  echo ""
  echo "SMOKE FAILED — request shape drift detected:"
  sed 's/^/  - /' "$FAIL_FILE"
  rc=1
fi

if [ "$rc" -eq 0 ]; then
  echo ""
  echo "SMOKE PASSED: charge -> list -> get -> refund -> checkout all match the core API contract."
fi

exit "$rc"
