#!/usr/bin/env bash
set -euo pipefail

# Safe deploy script for rahasiaemas.id.
# Run from the repository root:
#   bash deploy/deploy.sh

BRANCH="${DEPLOY_BRANCH:-main}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$REPO_DIR"

if [ ! -d ".git" ]; then
  echo "ERROR: deploy must run inside a Git repository."
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  echo "ERROR: working tree is dirty. Commit, stash, or clean server-side changes first."
  git status --short
  exit 1
fi

git fetch origin "$BRANCH" --quiet

LOCAL_COMMIT="$(git rev-parse HEAD)"
REMOTE_COMMIT="$(git rev-parse "origin/$BRANCH")"

if [ "$LOCAL_COMMIT" = "$REMOTE_COMMIT" ]; then
  echo "Already up to date: $LOCAL_COMMIT"
  exit 0
fi

echo "Deploying $BRANCH: $LOCAL_COMMIT -> $REMOTE_COMMIT"
git checkout "$BRANCH" --quiet
git pull --ff-only origin "$BRANCH"

if command -v php >/dev/null 2>&1; then
  php -l index.php
  php -l buat-link.php
  php -l admin/dashboard.php
  php -l admin/events.php
  php -l challenge/index.php
fi

echo "Deploy complete: $(git rev-parse HEAD)"

