#!/bin/bash
# ─────────────────────────────────────────────────────────────
#  Sherwood Events — Deploy Script
#  Run this on the IONOS server to pull the latest main branch.
#  Invoked manually (`./deploy.sh`) or automatically by the
#  GitHub Action in .github/workflows/deploy.yml.
#
#  First-time setup on the server (once):
#    chmod +x <SITE_DIR>/deploy.sh
#
#  Then:
#    <SITE_DIR>/deploy.sh
# ─────────────────────────────────────────────────────────────

set -euo pipefail

# ─── Path resolution ───────────────────────────────────────
# Honors SHERWOOD_EVENTS_DIR if set (the GitHub Action sets it from a
# repo secret); otherwise falls back to the production IONOS path.
SITE_DIR="${SHERWOOD_EVENTS_DIR:-/kunden/homepages/40/d493077416/htdocs/events}"
# ───────────────────────────────────────────────────────────

echo ""
echo "==> Sherwood Events deploy"
echo "==> $(date)"
echo ""

cd "$SITE_DIR" || { echo "ERROR: Could not cd to $SITE_DIR"; exit 1; }

echo "--> Pulling latest from GitHub..."
git fetch --all --prune
# Make sure we're on main before the hard reset — protects anyone who
# checked out a feature branch on the server (e.g., to test something)
# from silently losing their checkout.
current_branch="$(git rev-parse --abbrev-ref HEAD)"
if [ "$current_branch" != "main" ]; then
    echo "--> Switching from '$current_branch' to main..."
    git checkout main
fi
git reset --hard origin/main

echo "--> Setting file permissions..."
# Directories 755, regular files 644
find . -type d -not -path "./.git/*" -exec chmod 755 {} \;
find . -type f -not -path "./.git/*" \
    \( -name "*.php" -o -name "*.html" -o -name "*.css" -o -name "*.js" \
       -o -name "*.svg" -o -name "*.png" -o -name "*.jpg" -o -name "*.webp" \
       -o -name "*.ico" -o -name "*.txt" -o -name "*.md" -o -name "*.sql" \
       -o -name ".htaccess" -o -name ".gitkeep" \) \
    -exec chmod 644 {} \;

# Uploads directory must be writable by the web user
if [ -d "public/uploads" ]; then
    chmod 775 public/uploads
fi

# Config file (if present) should be readable only by owner
if [ -f "config/config.php" ]; then
    chmod 600 config/config.php
fi

# Deploy script itself stays executable
chmod +x "$SITE_DIR/deploy.sh"

echo ""
echo "==> Done! Site is live."
echo "==> HEAD: $(git log -1 --pretty=format:'%h %s')"
echo ""
