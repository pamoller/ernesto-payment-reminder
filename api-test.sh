#!/usr/bin/env bash
set -euo pipefail

base_url="${API_BASE_URL:-http://127.0.0.1:8080}"

auth_test_username="${AUTH_TEST_USERNAME:-}"
auth_test_password="${AUTH_TEST_PASSWORD:-}"
if [[ -z "$auth_test_username" || -z "$auth_test_password" ]]; then
  read -r auth_test_username auth_test_password < <(
    cd backend
    php -r 'require "vendor/autoload.php"; App\Infrastructure\Database\PdoFactory::loadEnv(); echo (getenv("AUTH_TEST_USERNAME") ?: ""), " ", (getenv("AUTH_TEST_PASSWORD") ?: ""), PHP_EOL;'
  )
fi

if [[ -z "$auth_test_username" || -z "$auth_test_password" ]]; then
  echo "Set AUTH_TEST_USERNAME and AUTH_TEST_PASSWORD in backend/.env or the environment." >&2
  exit 2
fi

require_status() {
  local check="$1"
  local expected="$2"
  local actual="$3"
  if [[ "$actual" != "$expected" ]]; then
    printf 'Smoke check failed (%s): expected HTTP %s, got %s\n' "$check" "$expected" "$actual" >&2
    exit 1
  fi
}

status="$(curl -sSf -o /tmp/ernesto-health.json -w '%{http_code}' "$base_url/api/health")"

require_status health 200 "$status"
php -r '$body=json_decode(file_get_contents("/tmp/ernesto-health.json"), true, 512, JSON_THROW_ON_ERROR); exit(($body["status"] ?? null) === "ok" ? 0 : 1);'

unauth_status="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/customers")"
require_status unauthenticated-customers 401 "$unauth_status"

session_status="$(curl -sS -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -o /tmp/ernesto-session.json -w '%{http_code}' "$base_url/api/auth/session")"
require_status auth-session 200 "$session_status"

csrf_token="$(php -r '$body=json_decode(file_get_contents("/tmp/ernesto-session.json"), true, 512, JSON_THROW_ON_ERROR); echo $body["csrf_token"];')"
test -n "$csrf_token"

login_status="$(curl -sS -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -o /tmp/ernesto-login.json -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" \
  -d "$(AUTH_TEST_USERNAME="$auth_test_username" AUTH_TEST_PASSWORD="$auth_test_password" php -r 'echo json_encode(["username" => getenv("AUTH_TEST_USERNAME"), "password" => getenv("AUTH_TEST_PASSWORD")], JSON_THROW_ON_ERROR);')" \
  "$base_url/api/auth/login")"
require_status login 200 "$login_status"

create_status="$(curl -sS -o /tmp/ernesto-customer.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" \
  -d '{"name":"API Test Customer","email":"api-test@example.invalid","duration_months":3,"start_date":"2026-01-01"}' \
  "$base_url/api/customers")"
require_status customer-create 201 "$create_status"

customer_id="$(php -r '$body=json_decode(file_get_contents("/tmp/ernesto-customer.json"), true, 512, JSON_THROW_ON_ERROR); echo $body["customer"]["id"];')"
test -n "$customer_id"

list_status="$(curl -sS -o /tmp/ernesto-customers.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt "$base_url/api/customers")"
require_status customer-list 200 "$list_status"

get_status="$(curl -sS -o /tmp/ernesto-customer-get.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt "$base_url/api/customers/$customer_id")"
require_status customer-get 200 "$get_status"

update_status="$(curl -sS -o /tmp/ernesto-customer-updated.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -X PUT \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" \
  -d '{"name":"API Test Updated","email":"api-test@example.invalid","duration_months":12,"start_date":"2026-02-01"}' \
  "$base_url/api/customers/$customer_id")"
require_status customer-update 200 "$update_status"

delete_status="$(curl -sS -o /dev/null -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -X DELETE -H "X-CSRF-Token: $csrf_token" "$base_url/api/customers/$customer_id")"
require_status customer-delete 204 "$delete_status"
