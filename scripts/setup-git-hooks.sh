#!/usr/bin/env bash
# One-time setup: use repo git hooks + remind after every successful git push.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

chmod +x .githooks/pre-push .githooks/remind-repo-private.sh

git config core.hooksPath .githooks

# Real push + privacy reminder (uses "command git" so hooks still run).
git config alias.push '!f(){ command git push "$@" && "'"$ROOT"'/.githooks/remind-repo-private.sh"; }; f'

echo "Installed git hooks for $ROOT"
echo "  core.hooksPath = .githooks"
echo "  git push       = push + private-repo reminder"
echo ""
echo "Run this once per clone. Re-run after cloning on a new machine."
