#!/usr/bin/env bash
set -euo pipefail

: "${WP_ROOT:?WP_ROOT is required}"
: "${MCP_URL:?MCP_URL is required}"
: "${DIRECT_TOKEN:?DIRECT_TOKEN is required}"
: "${ADMIN_ID:?ADMIN_ID is required}"
WP_BIN="${WP_BIN:-wp}"
BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
HEALTH_URL="${HEALTH_URL:-${BASE_URL}/?rest_route=/wpvibe-direct/v1/health}"

fail() {
  printf '::error::%s\n' "$*" >&2
  exit 1
}

note() {
  printf '==> %s\n' "$*"
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Required command missing: $1"
}

require_command curl
require_command jq
require_command "$WP_BIN"

mcp_legacy() {
  curl --fail-with-body --silent --show-error \
    -H "Authorization: Bearer ${DIRECT_TOKEN}" \
    -H 'Content-Type: application/json' \
    -H 'MCP-Protocol-Version: 2025-11-25' \
    --data-binary @- \
    "$MCP_URL"
}

mcp_modern() {
  local method="$1"
  curl --fail-with-body --silent --show-error \
    -H "Authorization: Bearer ${DIRECT_TOKEN}" \
    -H 'Content-Type: application/json' \
    -H 'MCP-Protocol-Version: 2026-07-28' \
    -H "Mcp-Method: ${method}" \
    --data-binary @- \
    "$MCP_URL"
}

tool_call() {
  local name="$1"
  local args_json="$2"
  jq -cn --arg name "$name" --argjson args "$args_json" \
    '{jsonrpc:"2.0",id:41,method:"tools/call",params:{name:$name,arguments:$args}}' \
    | mcp_legacy
}

assert_jq() {
  local json="$1"
  local filter="$2"
  local message="$3"
  if ! jq -e "$filter" >/dev/null 2>&1 <<<"$json"; then
    printf '%s\n' "$json" | jq . >&2 || true
    fail "$message"
  fi
}

approval_id_from() {
  jq -r '.result.structuredContent.approval_id // empty' <<<"$1"
}

approve_operation() {
  local approval_id="$1"
  [[ "$approval_id" =~ ^[A-Za-z0-9_-]+$ ]] || fail 'Approval id contained unexpected characters.'
  "$WP_BIN" --path="$WP_ROOT" eval "\$r = WPVDMCP_Approvals::approve('${approval_id}', (int) ${ADMIN_ID}); if ( is_wp_error( \$r ) ) { fwrite( STDERR, \$r->get_error_code() . ': ' . \$r->get_error_message() . PHP_EOL ); exit( 1 ); } echo 'approved';" >/dev/null
}

run_approved_ability() {
  local ability="$1"
  local input_json="$2"
  local pending_args pending approval_id approved_args result

  pending_args="$(jq -cn --arg ability "$ability" --argjson input "$input_json" '{ability_name:$ability,input:$input}')"
  pending="$(tool_call run_ability "$pending_args")"
  assert_jq "$pending" '.result.structuredContent.status == "approval_required"' "$ability did not request Direct MCP approval"
  approval_id="$(approval_id_from "$pending")"
  [[ -n "$approval_id" ]] || fail "$ability approval response did not contain approval_id"

  approve_operation "$approval_id"
  approved_args="$(jq -cn --arg ability "$ability" --argjson input "$input_json" --arg approval "$approval_id" '{ability_name:$ability,input:$input,approval_id:$approval}')"
  result="$(tool_call run_ability "$approved_args")"
  assert_jq "$result" '(.result.isError // false) == false' "$ability failed after approval"

  # A Direct approval is single-use. A second attempt with the same id must fail.
  local replay
  replay="$(tool_call run_ability "$approved_args")"
  assert_jq "$replay" '.result.isError == true' "$ability approval could be replayed"

  printf '%s' "$result"
}

note 'Checking authenticated Direct MCP health endpoint'
health="$(curl --fail-with-body --silent --show-error -H "Authorization: Bearer ${DIRECT_TOKEN}" "$HEALTH_URL")"
assert_jq "$health" '.name == "WPVibe Direct MCP" and .wpvibe_active == true' 'Direct MCP health did not report an active WPVibe bridge'

note 'Checking MCP 2026-07-28 server discovery and routing headers'
modern_discover="$(jq -cn '{jsonrpc:"2.0",id:1,method:"server/discover",params:{}}' | mcp_modern 'server/discover')"
assert_jq "$modern_discover" '.result.resultType == "complete" and (.result.supportedVersions | index("2026-07-28")) != null' 'Modern server/discover failed'

note 'Checking real site tool discovery'
tools="$(jq -cn '{jsonrpc:"2.0",id:2,method:"tools/list",params:{}}' | mcp_legacy)"
assert_jq "$tools" '([.result.tools[].name] | index("discover_abilities")) != null' 'discover_abilities is not exposed on WordPress 6.9+'
assert_jq "$tools" '([.result.tools[].name] | index("run_ability")) != null' 'run_ability is not exposed on WordPress 6.9+'
assert_jq "$tools" '([.result.tools[].name] | index("elementor_save_page")) != null' 'WPVibe native Elementor save fallback is not exposed'

note 'Discovering installed Elementor abilities at runtime'
abilities="$(tool_call discover_abilities '{"page":1,"per_page":100,"category":"elementor"}')"
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/create-page"))' 'Elementor create-page ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/build-composition"))' 'Elementor build-composition ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/get-page-structure"))' 'Elementor get-page-structure ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/publish-document"))' 'Elementor publish-document ability was not discovered'

note 'Verifying live ability annotations used by Direct MCP routing'
build_info="$(tool_call get_ability_info '{"ability_name":"elementor/build-composition"}')"
assert_jq "$build_info" '.result.structuredContent.data.meta.annotations.destructive == true and .result.structuredContent.data.meta.annotations.idempotent == false and .result.structuredContent.data.meta.annotations.readonly == false' 'build-composition annotations changed or were not returned'
publish_info="$(tool_call get_ability_info '{"ability_name":"elementor/publish-document"}')"
assert_jq "$publish_info" '.result.structuredContent.data.meta.annotations.destructive == true and .result.structuredContent.data.meta.annotations.idempotent == true and .result.structuredContent.data.meta.annotations.readonly == false' 'publish-document annotations changed or were not returned'

note 'Creating a draft Elementor page through Direct MCP approval flow'
create_result="$(run_approved_ability 'elementor/create-page' '{"title":"WPVibe Direct MCP E2E","post_type":"page"}')"
PAGE_ID="$(jq -r '.result.structuredContent.data.id // empty' <<<"$create_result")"
[[ "$PAGE_ID" =~ ^[0-9]+$ ]] || fail 'create-page did not return a numeric page id'

note "Building Atomic composition on page ${PAGE_ID}; this proves destructive+non-idempotent routes as POST"
build_input="$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id,xml_structure:"<e-flexbox configuration-id=\"hero-section\"><e-heading configuration-id=\"hero-title\"></e-heading></e-flexbox>",element_config:{"hero-title":{tag:"h2",title:"WPVibe Direct MCP E2E Heading"}}}')"
build_result="$(run_approved_ability 'elementor/build-composition' "$build_input")"
assert_jq "$build_result" '.result.structuredContent.data.success == true' 'Elementor build-composition did not report success'

note 'Reading Elementor page structure through readonly GET ability'
structure_args="$(jq -cn --argjson post_id "$PAGE_ID" '{ability_name:"elementor/get-page-structure",input:{post_id:$post_id}}')"
structure="$(tool_call run_ability "$structure_args")"
assert_jq "$structure" '(.result.isError // false) == false' 'get-page-structure failed'
assert_jq "$structure" '(.result.structuredContent.data.elements | tostring | contains("e-heading"))' 'Created Atomic heading was not present in page structure'

note "Publishing page ${PAGE_ID}; this proves destructive+idempotent routes as DELETE"
publish_input="$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id}')"
publish_result="$(run_approved_ability 'elementor/publish-document' "$publish_input")"
assert_jq "$publish_result" '.result.structuredContent.data.status == "publish"' 'Elementor publish-document did not publish the page'

note 'Verifying rendered frontend output, not only mutation responses'
# Use WordPress's plain page query so frontend verification does not depend on
# Apache permalink rewrite rules in the disposable runner.
PAGE_URL="${BASE_URL}/?page_id=${PAGE_ID}"
frontend="$(curl --fail-with-body --silent --show-error "$PAGE_URL")"
grep -Fq 'WPVibe Direct MCP E2E Heading' <<<"$frontend" || fail 'Published frontend did not contain the expected Elementor heading'

note 'Turning Elementor Atomic Editor off and checking failure/fallback behavior'
"$WP_BIN" --path="$WP_ROOT" eval 'foreach ( array( "e_opt_in_v4", "e_atomic_elements" ) as $feature ) { $key = Elementor\Plugin::$instance->experiments->get_feature_option_key( $feature ); update_option( $key, Elementor\Core\Experiments\Manager::STATE_INACTIVE ); }' >/dev/null

# With Atomic off, an Atomic-only composition must not silently succeed. Depending on
# Elementor registration timing/version it can disappear at metadata lookup or return
# a capability/403-style tool error; either outcome is acceptable, success is not.
off_args="$(jq -cn --argjson post_id "$PAGE_ID" '{ability_name:"elementor/build-composition",input:{post_id:$post_id,xml_structure:"<e-heading configuration-id=\"off-test\"></e-heading>",element_config:{"off-test":{tag:"h2",title:"should not persist"}}}')"
off_result="$(tool_call run_ability "$off_args")"
if jq -e '(.result.isError // false) == false and (.result.structuredContent.status // "") == "approval_required"' >/dev/null 2>&1 <<<"$off_result"; then
  off_approval="$(approval_id_from "$off_result")"
  approve_operation "$off_approval"
  off_retry_args="$(jq -cn --argjson post_id "$PAGE_ID" --arg approval "$off_approval" '{ability_name:"elementor/build-composition",input:{post_id:$post_id,xml_structure:"<e-heading configuration-id=\"off-test\"></e-heading>",element_config:{"off-test":{tag:"h2",title:"should not persist"}}},approval_id:$approval}')"
  off_result="$(tool_call run_ability "$off_retry_args")"
fi
assert_jq "$off_result" '.result.isError == true' 'Atomic build unexpectedly succeeded after Elementor Atomic Editor was disabled'

tools_after_off="$(jq -cn '{jsonrpc:"2.0",id:3,method:"tools/list",params:{}}' | mcp_legacy)"
assert_jq "$tools_after_off" '([.result.tools[].name] | index("elementor_save_page")) != null' 'Native Elementor fallback disappeared when Atomic Editor was disabled'

note 'Disposable WordPress + WPVibe Direct MCP + Elementor E2E acceptance passed'