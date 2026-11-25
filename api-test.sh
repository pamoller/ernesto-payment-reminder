#!/usr/bin/env bash
set -euo pipefail

base_url="${API_BASE_URL:-http://127.0.0.1:8080}"

status="$(curl -sSf -o /tmp/ernesto-health.json -w '%{http_code}' "$base_url/api/health")"

test "$status" = "200"
php -r '$body=json_decode(file_get_contents("/tmp/ernesto-health.json"), true, 512, JSON_THROW_ON_ERROR); exit(($body["status"] ?? null) === "ok" ? 0 : 1);'
