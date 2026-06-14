#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/docs/mevonpay-fern"

if [[ -z "${FERN_TOKEN:-}" ]]; then
  echo "No FERN_TOKEN set. Run: fern login --device-code"
  echo "Or export FERN_TOKEN from https://dashboard.buildwithfern.com"
  exit 1
fi

echo "Checking Fern project..."
npx fern-api check

MODE="${1:-preview}"
if [[ "$MODE" == "production" ]]; then
  echo "Publishing to mevonpay-209643.docs.buildwithfern.com ..."
  npx fern-api generate --docs --no-prompt
else
  echo "Creating preview deployment..."
  npx fern-api generate --docs --preview --no-prompt
fi

echo "Done."
