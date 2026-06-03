#!/usr/bin/env bash
# dev.sh — start local dev environment
# Usage: ./dev.sh
# Starts PHP API server on :8000 and Astro dev server on :4321.
# Ctrl+C stops both.

set -e

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
PHP_PORT=8000
PHP_PID=""

cleanup() {
  echo ""
  echo "Stopping PHP server..."
  [[ -n "$PHP_PID" ]] && kill "$PHP_PID" 2>/dev/null
  exit 0
}
trap cleanup SIGINT SIGTERM

# Kill any stale PHP server already on the port
lsof -ti tcp:$PHP_PORT | xargs kill -9 2>/dev/null || true

echo "Starting PHP API server on http://localhost:$PHP_PORT ..."
php -S "localhost:$PHP_PORT" -t "$PROJECT_ROOT" > /tmp/php-dev.log 2>&1 &
PHP_PID=$!

echo "Starting Astro dev server on http://localhost:4321 ..."
cd "$PROJECT_ROOT/frontend"
npm run dev
