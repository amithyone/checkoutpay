#!/usr/bin/env bash
# Reminder: keep checkoutpay private on GitHub after pushing.

REPO_URL="${CHECKOUT_GITHUB_REPO_URL:-https://github.com/amithyone/checkoutpay/settings}"

cat <<EOF

================================================================================
  REMINDER: Make sure checkoutpay is PRIVATE on GitHub
  Settings → General → Danger zone → Change repository visibility → Private
  $REPO_URL
================================================================================

EOF
