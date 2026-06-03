#!/usr/bin/env bash
# =============================================================
# deploy.sh — Gokul Bhavan IONOS deployment script
# Uses lftp over SFTP (IONOS restricts rsync — sftp only)
#
# Usage:
#   ./deploy.sh            — build frontend + deploy all
#   ./deploy.sh --api      — deploy /api only (no build)
#   ./deploy.sh --no-build — deploy frontend dist without rebuilding
#
# Credentials are read from .deploy.env (git-ignored).
# Requires: brew install lftp
# =============================================================

set -euo pipefail

# ── Load credentials ─────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.deploy.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "❌  .deploy.env not found."
  echo "    Expected at: $ENV_FILE"
  exit 1
fi

# Parse .deploy.env explicitly — immune to Windows line endings and
# unquoted special characters (# in passwords etc.)
_get_env() {
  grep -m1 "^${1}=" "$ENV_FILE" \
    | tr -d '\r' \
    | sed "s/^${1}=//" \
    | sed "s/^['\"]//;s/['\"]$//"
}

SFTP_HOST="$(_get_env SFTP_HOST)"
SFTP_USER="$(_get_env SFTP_USER)"
SFTP_PASS="$(_get_env SFTP_PASS)"
SFTP_PORT="$(_get_env SFTP_PORT)"
REMOTE_ROOT="$(_get_env REMOTE_ROOT)"

# Apply defaults for optional fields
[ -z "$SFTP_PORT" ]    && SFTP_PORT=22
[ -z "$REMOTE_ROOT" ]  && REMOTE_ROOT=/

# Validate required fields
[ -z "$SFTP_HOST" ] && { echo "❌  SFTP_HOST not set in .deploy.env"; exit 1; }
[ -z "$SFTP_USER" ] && { echo "❌  SFTP_USER not set in .deploy.env"; exit 1; }
[ -z "$SFTP_PASS" ] && { echo "❌  SFTP_PASS not set in .deploy.env"; exit 1; }

# ── Check lftp ───────────────────────────────────────────────
if ! command -v lftp &>/dev/null; then
  echo "❌  lftp not found. Install it with:"
  echo "    brew install lftp"
  exit 1
fi

# ── Parse flags ──────────────────────────────────────────────
API_ONLY=false
NO_BUILD=false

for arg in "$@"; do
  case $arg in
    --api)      API_ONLY=true ;;
    --no-build) NO_BUILD=true ;;
    *)          echo "Unknown flag: $arg"; exit 1 ;;
  esac
done

# ── Colours ──────────────────────────────────────────────────
GREEN='\033[0;32m'; CYAN='\033[0;36m'; NC='\033[0m'
log() { echo -e "${CYAN}▶ $1${NC}"; }
ok()  { echo -e "${GREEN}✔ $1${NC}"; }

# ── lftp mirror helper ───────────────────────────────────────
# mirror --reverse uploads local→remote
# --delete removes remote files that no longer exist locally
# --only-newer skips files that haven't changed
# --parallel=4 uploads 4 files simultaneously

lftp_mirror() {
  local local_src="$1"
  local remote_dst="$2"
  local label="$3"

  log "Uploading $label → $SFTP_HOST:$remote_dst"

  lftp -u "$SFTP_USER","$SFTP_PASS" \
       -p "$SFTP_PORT" \
       "sftp://$SFTP_HOST" <<EOF
set sftp:auto-confirm yes
set net:reconnect-interval-base 5
set net:max-retries 3
mirror --reverse --delete --only-newer --parallel=4 --verbose=1 \
  --exclude-glob api/ \
  --exclude-glob logs/ \
  --exclude-glob media/ \
  --exclude config.php \
  "$local_src" "$remote_dst"
bye
EOF

  ok "$label deployed"
}

# ── API-only deploy ───────────────────────────────────────────
if [ "$API_ONLY" = true ]; then
  log "API-only deploy (skipping frontend build)"
  lftp_mirror "$SCRIPT_DIR/api/" "${REMOTE_ROOT}api/" "PHP API"
  echo ""
  ok "Done. API deployed to $SFTP_HOST"
  exit 0
fi

# ── Build frontend ────────────────────────────────────────────
if [ "$NO_BUILD" = false ]; then
  log "Building Astro frontend..."
  cd "$SCRIPT_DIR/frontend"
  npm run build
  cd "$SCRIPT_DIR"
  ok "Build complete"
fi

DIST_DIR="$SCRIPT_DIR/frontend/dist/"

if [ ! -d "$DIST_DIR" ]; then
  echo "❌  dist/ folder not found. Run npm run build first."
  exit 1
fi

# ── Deploy ────────────────────────────────────────────────────
echo ""
log "Deploying to $SFTP_HOST..."
echo ""

lftp_mirror "$DIST_DIR"          "$REMOTE_ROOT"        "Frontend (dist → web root)"
lftp_mirror "$SCRIPT_DIR/api/"   "${REMOTE_ROOT}api/"  "PHP API"

echo ""
ok "═══════════════════════════════════════════"
ok " Deployment complete → https://gokulbhavan.org"
ok "═══════════════════════════════════════════"
