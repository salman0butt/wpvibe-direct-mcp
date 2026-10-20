#!/usr/bin/env bash
# Disposable WPCode Lite + official WPVibe 1.20.5 integration acceptance.
set -euo pipefail
: "${WP_ROOT:?}"
: "${MCP_URL:?}"
: "${DIRECT_TOKEN:?}"
: "${ADMIN_ID:?}"

fail() { echo "::error::$*" >&2; exit 1; }
call() {
  local tool="$1" args="$2"
  jq -cn --arg tool "$tool" --argjson arguments "$args" '{jsonrpc:"2.0",id:734,method:"tools/call",params:{name:$tool,arguments:$arguments}}' |
    curl --silent --show-error --fail-with-body \
      -H "Authorization: Bearer ${DIRECT_TOKEN}" \
      -H "X-WPVibe-Direct-Token: ${DIRECT_TOKEN}" \
      -H 'Content-Type: application/json' \
      -H 'MCP-Protocol-Version: 2025-11-25' --data-binary @- "$MCP_URL"
}
assert() { jq -e "$2" >/dev/null <<<"$1" || { jq . <<<"$1" >&2; fail "$3"; }; }

echo 'WPCode: create a non-executing CSS snippet through the Direct safety bridge'
create="$(call code_snippet '{"action":"create","title":"WPVibe Direct disposable retirement test","code_type":"css","location":"site_wide_header","code":"body { --wpvdmcp-retirement-check: 1; }"}')"
assert "$create" '(.result.isError // false) == false and .result.structuredContent.status == 200' 'WPCode dormant create failed'
id="$(jq -r '.result.structuredContent.data.id // empty' <<<"$create")"
[[ "$id" =~ ^[1-9][0-9]*$ ]] || fail "No new WPCode snippet ID in Direct create response"

echo 'WPCode: deactivate through the new upstream no-approval action'
off="$(call code_snippet "$(jq -cn --argjson id "$id" '{action:"deactivate",id:$id}')")"
assert "$off" '.result.structuredContent.data.action == "deactivate" and .result.structuredContent.data.active == false and (.result.isError // false) == false' 'WPCode deactivate failed'

echo 'WPCode: deleting a WPVibe-created snippet requires separate administrator approval'
request="$(call code_snippet "$(jq -cn --argjson id "$id" '{action:"delete",id:$id}')")"
assert "$request" '.result.structuredContent.status == "approval_required" and .result.structuredContent.reason == "retire"' 'Delete was not approval gated'
approval="$(jq -r '.result.structuredContent.approval_id // empty' <<<"$request")"
[[ "$approval" =~ ^[A-Za-z0-9_-]+$ ]] || fail 'Delete approval_id invalid'
wp --path="$WP_ROOT" eval "\$ok=WPVDMCP_Approvals::approve('$approval',(int)$ADMIN_ID);if(is_wp_error(\$ok)){fwrite(STDERR,\$ok->get_error_message());exit(1);}" >/dev/null
approved_args="$(jq -cn --argjson id "$id" --arg approval_id "$approval" '{action:"delete",id:$id,approval_id:$approval_id}')"
deleted="$(call code_snippet "$approved_args")"
assert "$deleted" '.result.structuredContent.data.action == "delete" and .result.structuredContent.data.active == false and (.result.isError // false) == false' 'Approved WPCode deletion failed'
status="$(wp post get "$id" --field=post_status --path="$WP_ROOT")"
[[ "$status" == "trash" ]] || fail "Expected WPCode snippet in trash; got $status"
replay="$(call code_snippet "$approved_args")"
assert "$replay" '.result.isError == true' 'Delete approval replay unexpectedly succeeded'
echo "WPCode disposable retire workflow passed (snippet $id in WPCode trash)."
