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

# Send both supported header forms. On a normal WordPress Apache install the
# rewrite rules preserve Authorization; this disposable lane deliberately uses
# ?rest_route= to avoid rewrite coupling, so X-WPVibe-Direct-Token also proves
# the documented header fallback without weakening token validation.
auth_headers=(
  -H "Authorization: Bearer ${DIRECT_TOKEN}"
  -H "X-WPVibe-Direct-Token: ${DIRECT_TOKEN}"
)

mcp_legacy() {
  curl --fail-with-body --silent --show-error \
    "${auth_headers[@]}" \
    -H 'Content-Type: application/json' \
    -H 'MCP-Protocol-Version: 2025-11-25' \
    --data-binary @- \
    "$MCP_URL"
}

mcp_modern() {
  local method="$1"
  curl --fail-with-body --silent --show-error \
    "${auth_headers[@]}" \
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

run_readonly_ability() {
  local ability="$1"
  local input_json="$2"
  local args result
  args="$(jq -cn --arg ability "$ability" --argjson input "$input_json" '{ability_name:$ability,input:$input}')"
  result="$(tool_call run_ability "$args")"
  assert_jq "$result" '(.result.isError // false) == false' "$ability readonly call failed"
  printf '%s' "$result"
}

tool_error_code() {
  jq -r '.result.content[0].text | fromjson | .code // empty' <<<"$1"
}

resolved_element_id() {
  local xml="$1"
  local configuration_id="$2"
  printf '%s' "$xml" | php -r '
    $xml = stream_get_contents(STDIN);
    $configuration_id = $argv[1] ?? "";
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadXML("<root>" . $xml . "</root>")) { exit(2); }
    foreach ($dom->getElementsByTagName("*") as $node) {
      if ($node->getAttribute("configuration-id") === $configuration_id) {
        echo $node->getAttribute("id");
        exit(0);
      }
    }
    exit(3);
  ' "$configuration_id"
}

elementor_document_settings() {
  local post_id="$1"
  [[ "$post_id" =~ ^[0-9]+$ ]] || fail 'Elementor document settings requested for invalid post id'
  "$WP_BIN" --path="$WP_ROOT" eval "\$d = Elementor\\Plugin::\$instance->documents->get((int) ${post_id}); if (!\$d) { exit(2); } echo wp_json_encode(array('margin'=>\$d->get_settings('margin'),'padding'=>\$d->get_settings('padding')));"
}

elementor_global_class_json() {
  local class_id="$1"
  [[ "$class_id" =~ ^[A-Za-z0-9_-]+$ ]] || fail 'Elementor global class id contained unexpected characters'
  "$WP_BIN" --path="$WP_ROOT" eval "\$repo = Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository::make(Elementor\\Plugin::\$instance->kits_manager->get_active_kit()); echo wp_json_encode(\$repo->get('${class_id}'));"
}

elementor_post_css() {
  local post_id="$1"
  local html href
  [[ "$post_id" =~ ^[0-9]+$ ]] || fail 'Elementor CSS requested for invalid post id'
  html="$(curl --fail-with-body --silent --show-error "${BASE_URL}/?page_id=${post_id}")"
  href="$(printf '%s' "$html" | php -r '
    $html = stream_get_contents(STDIN);
    $post_id = $argv[1] ?? "";
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadHTML($html)) { exit(2); }
    $node = $dom->getElementById("elementor-post-" . $post_id . "-css");
    if (!$node) { exit(3); }
    echo html_entity_decode($node->getAttribute("href"), ENT_QUOTES | ENT_HTML5);
  ' "$post_id")"
  [[ -n "$href" ]] || fail "Elementor post stylesheet link was not present for page ${post_id}"
  curl --fail-with-body --silent --show-error "$href"
}

note 'Checking authenticated Direct MCP health endpoint'
health="$(curl --fail-with-body --silent --show-error "${auth_headers[@]}" "$HEALTH_URL")"
assert_jq "$health" '.name == "WPVibe Direct MCP" and .wpvibe_active == true' 'Direct MCP health did not report an active WPVibe bridge'

note 'Checking MCP 2026-07-28 server discovery and routing headers'
modern_discover="$(jq -cn '{jsonrpc:"2.0",id:1,method:"server/discover",params:{}}' | mcp_modern 'server/discover')"
assert_jq "$modern_discover" '.result.resultType == "complete" and (.result.supportedVersions | index("2026-07-28")) != null' 'Modern server/discover failed'

note 'Checking real site tool discovery'
tools="$(jq -cn '{jsonrpc:"2.0",id:2,method:"tools/list",params:{}}' | mcp_legacy)"
assert_jq "$tools" '([.result.tools[].name] | index("discover_abilities")) != null' 'discover_abilities is not exposed on WordPress 6.9+'
assert_jq "$tools" '([.result.tools[].name] | index("run_ability")) != null' 'run_ability is not exposed on WordPress 6.9+'
assert_jq "$tools" '([.result.tools[].name] | index("elementor_save_page")) != null' 'WPVibe native Elementor save fallback is not exposed'

note 'Checking live WPVibe route registry against parity classification'
if ! route_inventory="$("$WP_BIN" --path="$WP_ROOT" eval '
$server = rest_get_server();
if ( ! did_action( "rest_api_init" ) ) {
    do_action( "rest_api_init", $server );
}
$manifest = WPVDMCP_Parity::execute( "reference_parity_manifest", array() );
$model = isset( $manifest["model_route_tools"] ) && is_array( $manifest["model_route_tools"] ) ? array_keys( $manifest["model_route_tools"] ) : array();
$internal = isset( $manifest["internal_not_tools"] ) && is_array( $manifest["internal_not_tools"] ) ? $manifest["internal_not_tools"] : array();
$known = array_fill_keys( array_merge( $model, $internal ), true );
$routes = array_keys( $server->get_routes( "wpvibe/v1" ) );
$unknown = array();
foreach ( $routes as $route ) {
    $normalized = rtrim( $route, "/" );
    if ( "/wpvibe/v1" === $normalized ) {
        continue;
    }
    $normalized = preg_replace_callback(
        "/\\(\\?P<([^>]+)>[^)]+\\)/",
        static function ( $match ) { return "{" . $match[1] . "}"; },
        $normalized
    );
    if ( ! isset( $known[ $normalized ] ) ) {
        $unknown[] = $route;
    }
}
if ( $unknown ) {
    fwrite( STDERR, "Unclassified WPVibe runtime routes: " . wp_json_encode( $unknown ) . PHP_EOL );
    exit( 1 );
}
echo wp_json_encode( array( "count" => count( $routes ) ) );
' 2>&1)"; then
  fail "$route_inventory"
fi
assert_jq "$route_inventory" '.count > 0' 'WPVibe runtime route inventory was unexpectedly empty'

note 'Discovering installed Elementor abilities at runtime'
abilities="$(tool_call discover_abilities '{"page":1,"per_page":100,"category":"elementor"}')"
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/create-page"))' 'Elementor create-page ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/build-composition"))' 'Elementor build-composition ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/get-page-structure"))' 'Elementor get-page-structure ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/publish-document"))' 'Elementor publish-document ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/list-widget-schemas"))' 'Elementor list-widget-schemas ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/get-widget-schema"))' 'Elementor get-widget-schema ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/manage-elements"))' 'Elementor manage-elements ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/update-page-settings"))' 'Elementor update-page-settings ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/read-resource"))' 'Elementor read-resource ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/manage-global-variable"))' 'Elementor manage-global-variable ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/manage-classes"))' 'Elementor manage-classes ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/get-default-styles"))' 'Elementor get-default-styles ability was not discovered'
assert_jq "$abilities" '(.result.structuredContent | tostring | contains("elementor/manage-default-styles"))' 'Elementor manage-default-styles ability was not discovered'

note 'Verifying live ability annotations used by Direct MCP routing'
build_info="$(tool_call get_ability_info '{"ability_name":"elementor/build-composition"}')"
assert_jq "$build_info" '.result.structuredContent.data.meta.annotations.destructive == true and .result.structuredContent.data.meta.annotations.idempotent == false and .result.structuredContent.data.meta.annotations.readonly == false' 'build-composition annotations changed or were not returned'
publish_info="$(tool_call get_ability_info '{"ability_name":"elementor/publish-document"}')"
assert_jq "$publish_info" '.result.structuredContent.data.meta.annotations.destructive == true and .result.structuredContent.data.meta.annotations.idempotent == true and .result.structuredContent.data.meta.annotations.readonly == false' 'publish-document annotations changed or were not returned'

note 'Creating a draft Elementor page through Direct MCP approval flow'
create_result="$(run_approved_ability 'elementor/create-page' '{"title":"WPVibe Direct MCP E2E","post_type":"page"}')"
PAGE_ID="$(jq -r '.result.structuredContent.data.id // empty' <<<"$create_result")"
[[ "$PAGE_ID" =~ ^[0-9]+$ ]] || fail 'create-page did not return a numeric page id'

note 'Verifying Elementor page-settings partial merge semantics with reversible document settings'
initial_page_settings="$(elementor_document_settings "$PAGE_ID")"
assert_jq "$initial_page_settings" 'has("margin") and has("padding")' 'Could not snapshot initial Elementor margin/padding settings'

initial_settings_input="$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id,settings:{margin:{unit:"px",top:"23",right:"23",bottom:"23",left:"23",isLinked:true},padding:{unit:"px",top:"13",right:"13",bottom:"13",left:"13",isLinked:true}}}')"
initial_settings_result="$(run_approved_ability 'elementor/update-page-settings' "$initial_settings_input")"
assert_jq "$initial_settings_result" '.result.structuredContent.data.success == true' 'Initial Elementor page-settings update failed'
settings_after_pair="$(elementor_document_settings "$PAGE_ID")"
assert_jq "$settings_after_pair" '.margin.top == "23" and .margin.unit == "px" and .padding.top == "13" and .padding.unit == "px"' 'Elementor did not persist the paired margin/padding document settings'

margin_only_input="$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id,settings:{margin:{unit:"px",top:"29",right:"29",bottom:"29",left:"29",isLinked:true}}}')"
margin_only_result="$(run_approved_ability 'elementor/update-page-settings' "$margin_only_input")"
assert_jq "$margin_only_result" '.result.structuredContent.data.success == true' 'Elementor margin-only page-settings update failed'
settings_after_partial="$(elementor_document_settings "$PAGE_ID")"
assert_jq "$settings_after_partial" '.margin.top == "29" and .padding.top == "13" and .padding.unit == "px"' 'Elementor partial page-settings update did not preserve untouched padding'

note 'Discovering live Elementor widget schemas before page composition'
widget_summary="$(run_readonly_ability 'elementor/list-widget-schemas' '{"summary":true}')"
for widget_type in e-flexbox e-heading e-paragraph e-button; do
  assert_jq "$widget_summary" "([.result.structuredContent.data.widgets[].type] | index(\"${widget_type}\")) != null" "Elementor widget schema summary did not expose ${widget_type}"
done

heading_schema="$(run_readonly_ability 'elementor/get-widget-schema' '{"widget_type":"e-heading"}')"
assert_jq "$heading_schema" '.result.structuredContent.data.properties.title != null and .result.structuredContent.data.properties.tag != null' 'e-heading schema did not expose canonical title/tag props'
paragraph_schema="$(run_readonly_ability 'elementor/get-widget-schema' '{"widget_type":"e-paragraph"}')"
assert_jq "$paragraph_schema" '.result.structuredContent.data.properties.paragraph != null' 'e-paragraph schema did not expose canonical paragraph prop'
button_schema="$(run_readonly_ability 'elementor/get-widget-schema' '{"widget_type":"e-button"}')"
assert_jq "$button_schema" '.result.structuredContent.data.properties.text != null' 'e-button schema did not expose canonical text prop'
flexbox_schema="$(run_readonly_ability 'elementor/get-widget-schema' '{"widget_type":"e-flexbox"}')"
assert_jq "$flexbox_schema" '.result.structuredContent.data.properties.tag != null' 'e-flexbox schema did not expose canonical tag prop'

note 'Proving strict composition preflight runs only after approval and rejects a lossy build before persistence'
bad_build_input="$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id,xml_structure:"<e-heading configuration-id=\"invalid-heading\"></e-heading>",element_config:{"invalid-heading":{definitely_not_a_real_prop:"This must never persist"}}}')"
bad_build_args="$(jq -cn --argjson input "$bad_build_input" '{ability_name:"elementor/build-composition",input:$input}')"
bad_build_pending="$(tool_call run_ability "$bad_build_args")"
assert_jq "$bad_build_pending" '.result.structuredContent.status == "approval_required"' 'Lossy Elementor composition did not request approval before the compiler preflight'
bad_build_approval="$(approval_id_from "$bad_build_pending")"
approve_operation "$bad_build_approval"
bad_build_approved_args="$(jq -cn --argjson input "$bad_build_input" --arg approval "$bad_build_approval" '{ability_name:"elementor/build-composition",input:$input,approval_id:$approval}')"
bad_build_result="$(tool_call run_ability "$bad_build_approved_args")"
assert_jq "$bad_build_result" '.result.isError == true' 'Lossy Elementor composition unexpectedly persisted or succeeded after approval'
[[ "$(tool_error_code "$bad_build_result")" == 'elementor_composition_preflight_failed' ]] || fail 'Lossy Elementor composition did not return strict preflight error'
bad_build_replay="$(tool_call run_ability "$bad_build_approved_args")"
assert_jq "$bad_build_replay" '.result.isError == true' 'Consumed lossy-build approval was unexpectedly reusable'
[[ "$(tool_error_code "$bad_build_replay")" == 'approval_not_approved' ]] || fail 'Lossy-build approval replay did not fail before another Elementor compiler call'
prebuild_structure="$(run_readonly_ability 'elementor/get-page-structure' "$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id}')")"
assert_jq "$prebuild_structure" '(.result.structuredContent.data.elements | tostring | contains("invalid-heading")) == false' 'Rejected lossy Elementor composition still persisted content'

note "Building a styled multi-section Atomic page on ${PAGE_ID}; this proves real page-design fidelity"
build_input="$(jq -cn --argjson post_id "$PAGE_ID" '
  {
    post_id:$post_id,
    xml_structure:"<e-flexbox configuration-id=\"page-shell\"><e-flexbox configuration-id=\"hero-section\"><e-heading configuration-id=\"hero-title\"></e-heading><e-paragraph configuration-id=\"hero-copy\"></e-paragraph><e-button configuration-id=\"hero-cta\"></e-button></e-flexbox><e-flexbox configuration-id=\"proof-section\"><e-heading configuration-id=\"proof-title\"></e-heading><e-paragraph configuration-id=\"proof-copy\"></e-paragraph></e-flexbox></e-flexbox>",
    element_config:{
      "page-shell":{tag:"main"},
      "hero-section":{tag:"section"},
      "hero-title":{tag:"h1",title:"Build Elementor pages reliably"},
      "hero-copy":{paragraph:"Direct MCP now validates Elementor schemas before saving so requested content is not silently dropped."},
      "hero-cta":{text:"Verified Elementor design"},
      "proof-section":{tag:"section"},
      "proof-title":{tag:"h2",title:"Schema-first page building"},
      "proof-copy":{paragraph:"This second section proves a realistic multi-section Elementor composition survives structure, styling, updates, and publishing."}
    },
    style:{
      "page-shell":"display: flex; flex-direction: column; gap: 0px;",
      "hero-section":"background-color: #111827; padding: 96px 32px; flex-direction: column; align-items: center; gap: 20px; @media (--mobile) { padding: 56px 20px; }",
      "hero-title":"color: #ffffff; font-size: 56px; font-weight: 700; line-height: 1.1em; text-align: center; max-width: 900px; @media (--mobile) { font-size: 38px; }",
      "hero-copy":"color: #d1d5db; font-size: 20px; line-height: 1.6em; text-align: center; max-width: 760px;",
      "hero-cta":"background-color: #2563eb; color: #ffffff; padding: 14px 24px; border-radius: 8px;",
      "proof-section":"padding: 72px 32px; flex-direction: column; align-items: center; gap: 16px;",
      "proof-title":"color: #111827; font-size: 36px; font-weight: 700; text-align: center;",
      "proof-copy":"color: #374151; font-size: 18px; line-height: 1.6em; text-align: center; max-width: 760px;"
    }
  }')"
build_result="$(run_approved_ability 'elementor/build-composition' "$build_input")"
assert_jq "$build_result" '.result.structuredContent.data.success == true' 'Elementor build-composition did not report success'
assert_jq "$build_result" '((.result.structuredContent.data.warnings // []) | length) == 0' 'Elementor build-composition returned warnings for the schema-derived page design'
assert_jq "$build_result" '(.result.structuredContent.data.resolved_xml | contains("hero-title")) and (.result.structuredContent.data.resolved_xml | contains("proof-section"))' 'Elementor build-composition did not return resolved ids for the designed sections'

resolved_xml="$(jq -r '.result.structuredContent.data.resolved_xml // empty' <<<"$build_result")"
HERO_SECTION_ID="$(resolved_element_id "$resolved_xml" 'hero-section')"
HERO_TITLE_ID="$(resolved_element_id "$resolved_xml" 'hero-title')"
PROOF_SECTION_ID="$(resolved_element_id "$resolved_xml" 'proof-section')"
[[ "$HERO_SECTION_ID" =~ ^[a-f0-9]+$ ]] || fail 'Could not resolve hero-section server element id from build-composition resolved_xml'
[[ "$HERO_TITLE_ID" =~ ^[a-f0-9]+$ ]] || fail 'Could not resolve hero-title server element id from build-composition resolved_xml'
[[ "$PROOF_SECTION_ID" =~ ^[a-f0-9]+$ ]] || fail 'Could not resolve proof-section server element id from build-composition resolved_xml'

note 'Reading Elementor page structure through readonly GET ability'
structure_args="$(jq -cn --argjson post_id "$PAGE_ID" '{ability_name:"elementor/get-page-structure",input:{post_id:$post_id}}')"
structure="$(tool_call run_ability "$structure_args")"
assert_jq "$structure" '(.result.isError // false) == false' 'get-page-structure failed'
assert_jq "$structure" '(.result.structuredContent.data.elements | tostring | contains("e-heading"))' 'Created Atomic heading was not present in page structure'
assert_jq "$structure" '(.result.structuredContent.data.elements | tostring | contains("e-paragraph")) and (.result.structuredContent.data.elements | tostring | contains("e-button"))' 'Designed Elementor page was missing paragraph/button structure'

hero_content_input="$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_SECTION_ID" '{post_id:$post_id,element_id:$element_id,include_content:true}')"
hero_content="$(run_readonly_ability 'elementor/get-page-structure' "$hero_content_input")"
assert_jq "$hero_content" '(.result.structuredContent.data.elements[0].styles.css // "") | contains("background")' 'Hero section styling did not survive into Elementor structure'
assert_jq "$hero_content" '(.result.structuredContent.data.elements | tostring | contains("Build Elementor pages reliably")) and (.result.structuredContent.data.elements | tostring | contains("Verified Elementor design"))' 'Hero content did not survive into Elementor structure'

note 'Updating one element using only the fresh id returned by build-composition'
update_input="$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_TITLE_ID" '{post_id:$post_id,operations:[{action:"update",element_id:$element_id,settings:{title:"Build Elementor pages reliably — verified"}}]}')"
update_result="$(run_approved_ability 'elementor/manage-elements' "$update_input")"
assert_jq "$update_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor manage-elements did not apply the fresh-id update cleanly'

note 'Duplicating, moving, and deleting a real Atomic element with current server IDs'
duplicate_input="$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_TITLE_ID" '{post_id:$post_id,operations:[{action:"duplicate",element_id:$element_id}]}')"
duplicate_result="$(run_approved_ability 'elementor/manage-elements' "$duplicate_input")"
assert_jq "$duplicate_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].action == "duplicate" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor duplicate operation did not succeed'

hero_after_duplicate="$(run_readonly_ability 'elementor/get-page-structure' "$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_SECTION_ID" '{post_id:$post_id,element_id:$element_id}')")"
DUP_TITLE_ID="$(jq -r --arg original "$HERO_TITLE_ID" '[.result.structuredContent.data.elements[] | .. | objects | select(.widgetType? == "e-heading") | .id | select(. != $original)] | last // empty' <<<"$hero_after_duplicate")"
[[ "$DUP_TITLE_ID" =~ ^[a-f0-9]+$ ]] || fail 'Could not identify fresh duplicate heading id from current Elementor structure'

move_input="$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$DUP_TITLE_ID" --arg parent_id "$PROOF_SECTION_ID" '{post_id:$post_id,operations:[{action:"move",element_id:$element_id,new_parent_id:$parent_id,index:0}]}')"
move_result="$(run_approved_ability 'elementor/manage-elements' "$move_input")"
assert_jq "$move_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].action == "move" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor move operation did not succeed'

hero_after_move="$(run_readonly_ability 'elementor/get-page-structure' "$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_SECTION_ID" '{post_id:$post_id,element_id:$element_id}')")"
assert_jq "$hero_after_move" "([.result.structuredContent.data.elements[] | .. | objects | .id?] | index(\"${DUP_TITLE_ID}\")) == null" 'Moved Elementor duplicate still appeared under its old parent'
proof_after_move="$(run_readonly_ability 'elementor/get-page-structure' "$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$PROOF_SECTION_ID" '{post_id:$post_id,element_id:$element_id}')")"
assert_jq "$proof_after_move" "([.result.structuredContent.data.elements[] | .. | objects | .id?] | index(\"${DUP_TITLE_ID}\")) != null" 'Moved Elementor duplicate did not appear under its new parent'

delete_input="$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$DUP_TITLE_ID" '{post_id:$post_id,operations:[{action:"delete",element_id:$element_id}]}')"
delete_result="$(run_approved_ability 'elementor/manage-elements' "$delete_input")"
assert_jq "$delete_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].action == "delete" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor delete operation did not succeed'
proof_after_delete="$(run_readonly_ability 'elementor/get-page-structure' "$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$PROOF_SECTION_ID" '{post_id:$post_id,element_id:$element_id}')")"
assert_jq "$proof_after_delete" "([.result.structuredContent.data.elements[] | .. | objects | .id?] | index(\"${DUP_TITLE_ID}\")) == null" 'Deleted Elementor duplicate still appeared in current page structure'

note 'Proving partial Elementor bulk mutations surface as errors instead of false success'
partial_input="$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_TITLE_ID" '{post_id:$post_id,operations:[{action:"update",element_id:$element_id,settings:{title:"Partial write applied — repair required"}},{action:"update",element_id:"deadbeef",settings:{title:"Must fail"}}]}')"
partial_pending_args="$(jq -cn --argjson input "$partial_input" '{ability_name:"elementor/manage-elements",input:$input}')"
partial_pending="$(tool_call run_ability "$partial_pending_args")"
assert_jq "$partial_pending" '.result.structuredContent.status == "approval_required"' 'Partial manage-elements test did not request approval'
partial_approval="$(approval_id_from "$partial_pending")"
approve_operation "$partial_approval"
partial_approved_args="$(jq -cn --argjson input "$partial_input" --arg approval "$partial_approval" '{ability_name:"elementor/manage-elements",input:$input,approval_id:$approval}')"
partial_result="$(tool_call run_ability "$partial_approved_args")"
assert_jq "$partial_result" '.result.isError == true' 'Partial Elementor mutation was incorrectly reported as MCP success'
[[ "$(tool_error_code "$partial_result")" == 'elementor_manage_elements_partial_failure' ]] || fail 'Partial Elementor mutation did not expose the expected error code'
assert_jq "$partial_result" '(.result.content[0].text | fromjson | .data.partial_applied) == true' 'Partial Elementor mutation did not disclose that a sibling operation had already applied'

note 'Re-reading and repairing after partial mutation using current page state'
after_partial_content="$(run_readonly_ability 'elementor/get-page-structure' "$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_TITLE_ID" '{post_id:$post_id,element_id:$element_id,include_content:true}')")"
assert_jq "$after_partial_content" '.result.structuredContent.data.elements[0].settings.title == "Partial write applied — repair required"' 'Expected valid sibling from partial Elementor mutation was not persisted'
repair_input="$(jq -cn --argjson post_id "$PAGE_ID" --arg element_id "$HERO_TITLE_ID" '{post_id:$post_id,operations:[{action:"update",element_id:$element_id,settings:{title:"Build Elementor pages reliably — verified"}}]}')"
repair_result="$(run_approved_ability 'elementor/manage-elements' "$repair_input")"
assert_jq "$repair_result" '.result.structuredContent.data.status == "ok"' 'Elementor repair after partial mutation did not succeed'

note 'Creating, reading, updating, and deleting a real Elementor global variable'
VAR_LABEL="WPVDMCP_E2E_Brand_${PAGE_ID}"
var_create_input="$(jq -cn --arg label "$VAR_LABEL" '{operations:[{action:"create",type:"global-color-variable",label:$label,value:"#123456"}]}')"
var_create_result="$(run_approved_ability 'elementor/manage-global-variable' "$var_create_input")"
VAR_ID="$(jq -r '.result.structuredContent.data.results[0].id // empty' <<<"$var_create_result")"
[[ "$VAR_ID" =~ ^[A-Za-z0-9_-]+$ ]] || fail 'Elementor global variable create did not return a safe id'
vars_after_create="$(run_readonly_ability 'elementor/read-resource' '{"uri":"elementor://global-variables"}')"
assert_jq "$vars_after_create" "(.result.structuredContent.data.content | fromjson | .variables | to_entries | any(.key == \"${VAR_ID}\" and .value.label == \"${VAR_LABEL}\" and .value.value == \"#123456\"))" 'Created Elementor global variable was not visible through the public resource'

var_update_input="$(jq -cn --arg id "$VAR_ID" --arg label "$VAR_LABEL" '{operations:[{action:"update",id:$id,label:$label,value:"#654321"}]}')"
var_update_result="$(run_approved_ability 'elementor/manage-global-variable' "$var_update_input")"
assert_jq "$var_update_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor global variable update failed'
vars_after_update="$(run_readonly_ability 'elementor/read-resource' '{"uri":"elementor://global-variables"}')"
assert_jq "$vars_after_update" "(.result.structuredContent.data.content | fromjson | .variables | to_entries | any(.key == \"${VAR_ID}\" and .value.label == \"${VAR_LABEL}\" and .value.value == \"#654321\"))" 'Updated Elementor global variable value was not visible through the public resource'

var_delete_input="$(jq -cn --arg id "$VAR_ID" '{operations:[{action:"delete",id:$id}]}')"
var_delete_result="$(run_approved_ability 'elementor/manage-global-variable' "$var_delete_input")"
assert_jq "$var_delete_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor global variable cleanup failed'
vars_after_delete="$(run_readonly_ability 'elementor/read-resource' '{"uri":"elementor://global-variables"}')"
assert_jq "$vars_after_delete" "(.result.structuredContent.data.content | fromjson | .variables[\"${VAR_ID}\"].deleted) == true" 'Disposable Elementor global variable was not soft-deleted after cleanup'
assert_jq "$vars_after_delete" "(.result.structuredContent.data.content | fromjson | .variables | to_entries | any(.key == \"${VAR_ID}\" and (.value.deleted // false) == false)) == false" 'Soft-deleted Elementor global variable remained active after cleanup'

note 'Creating, reading, updating, and deleting a real Elementor global class'
CLASS_LABEL="WPVDMCP_E2E_Class_${PAGE_ID}"
class_create_input="$(jq -cn --arg label "$CLASS_LABEL" '{operations:[{action:"create",label:$label,css:"color: #112233; font-weight: 600;"}]}')"
class_create_result="$(run_approved_ability 'elementor/manage-classes' "$class_create_input")"
CLASS_ID="$(jq -r '.result.structuredContent.data.results[0].id // empty' <<<"$class_create_result")"
[[ "$CLASS_ID" =~ ^[A-Za-z0-9_-]+$ ]] || fail 'Elementor global class create did not return a safe id'
classes_after_create="$(run_readonly_ability 'elementor/read-resource' '{"uri":"elementor://global-classes"}')"
assert_jq "$classes_after_create" "(.result.structuredContent.data.content | fromjson | .classes | any(.id == \"${CLASS_ID}\" and .label == \"${CLASS_LABEL}\"))" 'Created Elementor global class was not visible through the public resource'
class_state_after_create="$(elementor_global_class_json "$CLASS_ID")"
grep -Fq '112233' <<<"$class_state_after_create" || fail 'Created Elementor global class CSS was not persisted in Elementor repository state'

CLASS_LABEL_UPDATED="${CLASS_LABEL}_Updated"
class_update_input="$(jq -cn --arg id "$CLASS_ID" --arg label "$CLASS_LABEL_UPDATED" '{operations:[{action:"update",id:$id,label:$label,css:"color: #334455; font-weight: 700;",mode:"replace"}]}')"
class_update_result="$(run_approved_ability 'elementor/manage-classes' "$class_update_input")"
assert_jq "$class_update_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor global class update failed'
classes_after_update="$(run_readonly_ability 'elementor/read-resource' '{"uri":"elementor://global-classes"}')"
assert_jq "$classes_after_update" "(.result.structuredContent.data.content | fromjson | .classes | any(.id == \"${CLASS_ID}\" and .label == \"${CLASS_LABEL_UPDATED}\"))" 'Updated Elementor global class label was not visible through the public resource'
class_state_after_update="$(elementor_global_class_json "$CLASS_ID")"
grep -Fq '334455' <<<"$class_state_after_update" || fail 'Updated Elementor global class CSS was not persisted in Elementor repository state'

class_delete_input="$(jq -cn --arg id "$CLASS_ID" '{operations:[{action:"delete",id:$id}]}')"
class_delete_result="$(run_approved_ability 'elementor/manage-classes' "$class_delete_input")"
assert_jq "$class_delete_result" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].status == "ok"' 'Elementor global class cleanup failed'
classes_after_delete="$(run_readonly_ability 'elementor/read-resource' '{"uri":"elementor://global-classes"}')"
assert_jq "$classes_after_delete" "(.result.structuredContent.data.content | fromjson | .classes | any(.id == \"${CLASS_ID}\")) == false" 'Disposable Elementor global class remained after cleanup'

note 'Proving a site-wide default-style partial batch is surfaced as an error and its applied sibling remains inspectable'
initial_h6_style="$(run_readonly_ability 'elementor/get-default-styles' '{"tag":"h6"}')"
assert_jq "$initial_h6_style" '(.result.isError // false) == false' 'Could not read initial Elementor h6 default style'
default_style_input='{"operations":[{"action":"update","tag":"h6","css":"color: #123456; font-size: 31px;"},{"action":"update","tag":"definitely-not-a-tag","css":"font-size: 10px;"}]}'
default_style_pending_args="$(jq -cn --argjson input "$default_style_input" '{ability_name:"elementor/manage-default-styles",input:$input}')"
default_style_pending="$(tool_call run_ability "$default_style_pending_args")"
assert_jq "$default_style_pending" '.result.structuredContent.status == "approval_required"' 'Default-style partial batch did not request approval'
default_style_approval="$(approval_id_from "$default_style_pending")"
approve_operation "$default_style_approval"
default_style_approved_args="$(jq -cn --argjson input "$default_style_input" --arg approval "$default_style_approval" '{ability_name:"elementor/manage-default-styles",input:$input,approval_id:$approval}')"
default_style_result="$(tool_call run_ability "$default_style_approved_args")"
assert_jq "$default_style_result" '.result.isError == true' 'Partial Elementor default-style batch was incorrectly reported as MCP success'
[[ "$(tool_error_code "$default_style_result")" == 'elementor_manage_default_styles_partial_failure' ]] || fail 'Partial Elementor default-style batch did not expose the expected error code'
assert_jq "$default_style_result" '(.result.content[0].text | fromjson | .data.partial_applied) == true' 'Partial Elementor default-style batch did not disclose its applied sibling'

h6_after_partial="$(run_readonly_ability 'elementor/get-default-styles' '{"tag":"h6"}')"
assert_jq "$h6_after_partial" '(.result.structuredContent.data.default_styles // "") | length > 0' 'Valid sibling of partial default-style batch was not persisted'
cleanup_default_style="$(run_approved_ability 'elementor/manage-default-styles' '{"operations":[{"action":"delete","tag":"h6"}]}')"
assert_jq "$cleanup_default_style" '.result.structuredContent.data.status == "ok" and .result.structuredContent.data.results[0].status == "ok"' 'Disposable default-style cleanup did not succeed'
h6_after_cleanup="$(run_readonly_ability 'elementor/get-default-styles' '{"tag":"h6"}')"
assert_jq "$h6_after_cleanup" '(.result.structuredContent.data.default_styles // "") == ""' 'Disposable h6 default style remained after cleanup'

note "Publishing page ${PAGE_ID}; this proves destructive+idempotent routes as DELETE"
publish_input="$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id}')"
publish_result="$(run_approved_ability 'elementor/publish-document' "$publish_input")"
assert_jq "$publish_result" '.result.structuredContent.data.status == "publish"' 'Elementor publish-document did not publish the page'

note 'Verifying rendered frontend output, not only mutation responses'
# Use WordPress's plain page query so frontend verification does not depend on
# Apache permalink rewrite rules in the disposable runner.
PAGE_URL="${BASE_URL}/?page_id=${PAGE_ID}"
frontend="$(curl --fail-with-body --silent --show-error "$PAGE_URL")"
grep -Fq 'Build Elementor pages reliably — verified' <<<"$frontend" || fail 'Published frontend did not contain the repaired Elementor hero heading'
grep -Fq 'Direct MCP now validates Elementor schemas before saving' <<<"$frontend" || fail 'Published frontend did not contain the hero paragraph'
grep -Fq 'Verified Elementor design' <<<"$frontend" || fail 'Published frontend did not contain the Elementor CTA button'
grep -Fq 'Schema-first page building' <<<"$frontend" || fail 'Published frontend did not contain the second-section heading'
grep -Fq 'realistic multi-section Elementor composition' <<<"$frontend" || fail 'Published frontend did not contain the second-section paragraph'
if grep -Fq 'This must never persist' <<<"$frontend"; then
  fail 'Rejected invalid Elementor content leaked into the published page'
fi

note 'Verifying Elementor generated CSS/cache refresh after an approved page-style mutation'
"$WP_BIN" --path="$WP_ROOT" option update elementor_css_print_method external >/dev/null
css_before_refresh="$(elementor_post_css "$PAGE_ID")"
grep -Eq 'margin:[^;}]*29px' <<<"$css_before_refresh" || fail 'Generated Elementor post CSS did not contain the current 29px page margin'
grep -Eq 'padding:[^;}]*13px' <<<"$css_before_refresh" || fail 'Generated Elementor post CSS did not contain the preserved 13px page padding'

post_publish_margin_input="$(jq -cn --argjson post_id "$PAGE_ID" '{post_id:$post_id,settings:{margin:{unit:"px",top:"37",right:"37",bottom:"37",left:"37",isLinked:true}}}')"
post_publish_margin_result="$(run_approved_ability 'elementor/update-page-settings' "$post_publish_margin_input")"
assert_jq "$post_publish_margin_result" '.result.structuredContent.data.success == true' 'Published Elementor page margin update failed'
css_after_refresh="$(elementor_post_css "$PAGE_ID")"
grep -Eq 'margin:[^;}]*37px' <<<"$css_after_refresh" || fail 'Generated Elementor CSS/cache did not refresh to the approved 37px page margin'
grep -Eq 'padding:[^;}]*13px' <<<"$css_after_refresh" || fail 'Generated Elementor CSS refresh lost the untouched 13px page padding'
[[ "$css_before_refresh" != "$css_after_refresh" ]] || fail 'Elementor post stylesheet content did not change after approved page-style mutation'

note 'Restoring the disposable page settings snapshot after merge/cache verification'
restore_page_settings_input="$(jq -cn --argjson post_id "$PAGE_ID" --argjson original "$initial_page_settings" '{post_id:$post_id,settings:{margin:$original.margin,padding:$original.padding}}')"
restore_page_settings_result="$(run_approved_ability 'elementor/update-page-settings' "$restore_page_settings_input")"
assert_jq "$restore_page_settings_result" '.result.structuredContent.data.success == true' 'Elementor page-settings restoration failed'
settings_after_restore="$(elementor_document_settings "$PAGE_ID")"
if ! jq -e --argjson original "$initial_page_settings" '.margin == $original.margin and .padding == $original.padding' >/dev/null 2>&1 <<<"$settings_after_restore"; then
  printf '%s\n' "$settings_after_restore" | jq . >&2 || true
  fail 'Elementor page margin/padding did not return to the initial snapshot'
fi

note 'Turning Elementor Atomic Editor off and checking failure/fallback behavior'
"$WP_BIN" --path="$WP_ROOT" eval 'foreach ( array( "e_opt_in_v4", "e_atomic_elements" ) as $feature ) { $key = Elementor\Plugin::$instance->experiments->get_feature_option_key( $feature ); update_option( $key, Elementor\Core\Experiments\Manager::STATE_INACTIVE ); }' >/dev/null

# With Atomic off, an Atomic-only composition must not silently succeed. Depending on
# Elementor registration timing/version it can disappear at metadata lookup or return
# a capability/403-style tool error; either outcome is acceptable, success is not.
off_args="$(jq -cn --argjson post_id "$PAGE_ID" '{ability_name:"elementor/build-composition",input:{post_id:$post_id,xml_structure:"<e-heading configuration-id=\"off-test\"></e-heading>",element_config:{"off-test":{tag:"h2",title:"should not persist"}}}}')"
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
