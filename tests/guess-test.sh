#!/usr/bin/env bash
# Usage: ./guess-test.sh https://example.com known-protected-file.pdf
# Every request below must return 401/403/404/429 (never 200) for a visitor without a paid order.
set -u
SITE="${1:?site url}"; FILE="${2:?known protected filename}"
FAILED=0
check () {
  code=$(curl -s -o /dev/null -w '%{http_code}' "$1")
  if [ "$code" = "200" ]; then echo "FAIL  $code  $1"; FAILED=1; else echo "ok    $code  $1"; fi
}
check "$SITE/wp-content/uploads/woocommerce_uploads/$FILE"
check "$SITE/wp-content/uploads/woocommerce_uploads/"
check "$SITE/?download_file=1&order=wc_order_abc123&email=a@b.com&key=deadbeef"
check "$SITE/?download_file=1&order=wc_order_abc123&email=a@b.com&key=deadbeef2"
echo "---- hammering endpoint to confirm rate limit (expect 429 at the end) ----"
last=""
for i in $(seq 1 25); do last=$(curl -s -o /dev/null -w '%{http_code}' "$SITE/?download_file=1&order=wc_order_x$i&email=a@b.com&key=k$i"); done
echo "last status: $last"
exit $FAILED
