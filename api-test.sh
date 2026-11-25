#!/usr/bin/env bash
set -euo pipefail

base_url="${API_BASE_URL:-http://127.0.0.1:8080}"

status="$(curl -sSf -o /tmp/ernesto-health.json -w '%{http_code}' "$base_url/api/health")"

test "$status" = "200"
php -r '$body=json_decode(file_get_contents("/tmp/ernesto-health.json"), true, 512, JSON_THROW_ON_ERROR); exit(($body["status"] ?? null) === "ok" ? 0 : 1);'

unauth_status="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/customers")"
test "$unauth_status" = "401"

session_status="$(curl -sS -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -o /tmp/ernesto-session.json -w '%{http_code}' "$base_url/api/auth/session")"
test "$session_status" = "200"

csrf_token="$(php -r '$body=json_decode(file_get_contents("/tmp/ernesto-session.json"), true, 512, JSON_THROW_ON_ERROR); echo $body["csrf_token"];')"
test -n "$csrf_token"

login_status="$(curl -sS -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -o /tmp/ernesto-login.json -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" \
  -d "{\"username\":\"${AUTH_TEST_USERNAME:-admin}\",\"password\":\"${AUTH_TEST_PASSWORD:-test-only-password}\"}" \
  "$base_url/api/auth/login")"
test "$login_status" = "200"

create_status="$(curl -sS -o /tmp/ernesto-customer.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" \
  -d '{"name":"API Test Customer","email":"api-test@example.invalid","duration_months":3,"start_date":"2026-01-01"}' \
  "$base_url/api/customers")"
test "$create_status" = "201"

customer_id="$(php -r '$body=json_decode(file_get_contents("/tmp/ernesto-customer.json"), true, 512, JSON_THROW_ON_ERROR); echo $body["customer"]["id"];')"
test -n "$customer_id"

list_status="$(curl -sS -o /tmp/ernesto-customers.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt "$base_url/api/customers")"
test "$list_status" = "200"

get_status="$(curl -sS -o /tmp/ernesto-customer-get.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt "$base_url/api/customers/$customer_id")"
test "$get_status" = "200"

update_status="$(curl -sS -o /tmp/ernesto-customer-updated.json -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -X PUT \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" \
  -d '{"name":"API Test Updated","email":"api-test@example.invalid","duration_months":12,"start_date":"2026-02-01"}' \
  "$base_url/api/customers/$customer_id")"
test "$update_status" = "200"

delete_status="$(curl -sS -o /dev/null -w '%{http_code}' \
  -c /tmp/ernesto-cookies.txt -b /tmp/ernesto-cookies.txt \
  -X DELETE -H "X-CSRF-Token: $csrf_token" "$base_url/api/customers/$customer_id")"
test "$delete_status" = "204"
