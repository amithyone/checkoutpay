#!/usr/bin/env bash
# After a successful git push in Cursor, nudge the agent to remind the user about repo privacy.

input="$(cat)"
command=""
exit_code=0

if command -v jq >/dev/null 2>&1; then
  command="$(echo "$input" | jq -r '.command // empty')"
  exit_code="$(echo "$input" | jq -r '.exit_code // 0')"
else
  command="$(echo "$input" | grep -o '"command"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | sed 's/.*"\([^"]*\)"$/\1/')"
  exit_code="$(echo "$input" | grep -o '"exit_code"[[:space:]]*:[[:space:]]*[0-9]*' | head -1 | grep -o '[0-9]*$')"
  exit_code="${exit_code:-0}"
fi

if [[ "$exit_code" != "0" ]]; then
  exit 0
fi

if [[ "$command" =~ git[[:space:]]+push ]]; then
  cat <<'EOF'
{
  "additional_context": "A git push just succeeded for checkoutpay (amithyone/checkoutpay). Remind the user to confirm the GitHub repo visibility is set to Private: https://github.com/amithyone/checkoutpay/settings → General → Danger zone → Change repository visibility."
}
EOF
fi

exit 0
