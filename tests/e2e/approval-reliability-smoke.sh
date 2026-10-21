#!/usr/bin/env bash
# Disposable WordPress + MySQL approval durability/credential binding smoke.
# No customer site, persistent secrets, or live destructive MCP tools are used.
set -euo pipefail
: "${WP_ROOT:?}"
: "${ADMIN_ID:?}"

scratch="$(mktemp -d)"
trap 'rm -rf "$scratch"' EXIT
fail() { printf '::error::%s\n' "$*" >&2; exit 1; }

echo 'Approvals: verify real MySQL one-winner one-time consume under competing WP-CLI processes'
approval="$(wp --path="$WP_ROOT" eval '
  $payload = array("name"=>"demo/reliability-claim", "method"=>"DELETE", "input"=>array("id"=>1));
  $created = WPVDMCP_Approvals::request("run_ability", $payload, "Disposable approval concurrency smoke");
  if (is_wp_error($created)) { WP_CLI::error($created->get_error_message()); }
  $ok = WPVDMCP_Approvals::approve($created["approval_id"], (int) get_option("wpvdmcp_user_id", 0));
  if (is_wp_error($ok)) { WP_CLI::error($ok->get_error_message()); }
  echo $created["approval_id"];
')"
[[ "$approval" =~ ^ap_[a-f0-9]{32}$ ]] || fail "Invalid concurrency approval ID"

cat > "$scratch/consume.php" <<'PHP'
<?php
$payload = array('name'=>'demo/reliability-claim', 'method'=>'DELETE', 'input'=>array('id'=>1));
$result = WPVDMCP_Approvals::consume(getenv('WPVDMCP_E2E_APPROVAL'), 'run_ability', $payload);
echo true === $result ? 'consumed' : (is_wp_error($result) ? 'rejected:' . $result->get_error_code() : 'unexpected');
PHP
for n in $(seq 1 8); do
  WPVDMCP_E2E_APPROVAL="$approval" wp --path="$WP_ROOT" eval-file "$scratch/consume.php" > "$scratch/result-$n" 2> "$scratch/stderr-$n" &
done
wait || fail 'A concurrent WP-CLI process failed to complete'
winners="$(grep -l '^consumed$' "$scratch"/result-* | wc -l | tr -d ' ')"
[[ "$winners" == '1' ]] || { cat "$scratch"/result-* >&2; fail "Expected one consumed approval on MySQL; got $winners"; }
if grep -L -E '^(consumed|rejected:approval_(not_approved|in_progress))$' "$scratch"/result-* | grep -q .; then
  cat "$scratch"/result-* >&2
  fail 'Unexpected concurrent approval result'
fi
echo 'MySQL one-winner claim verified across 8 competing WordPress processes'

echo 'Approvals: verify approved receipt cannot cross an Administrator token rotation'
rotated="$(wp --path="$WP_ROOT" eval '
  $payload = array("name"=>"demo/reliability-rotation", "method"=>"DELETE", "input"=>array("id"=>2));
  $created = WPVDMCP_Approvals::request("run_ability", $payload, "Disposable token rotation smoke");
  if (is_wp_error($created)) { WP_CLI::error($created->get_error_message()); }
  $ok = WPVDMCP_Approvals::approve($created["approval_id"], (int) get_option("wpvdmcp_user_id", 0));
  if (is_wp_error($ok)) { WP_CLI::error($ok->get_error_message()); }
  echo $created["approval_id"];
')"
[[ "$rotated" =~ ^ap_[a-f0-9]{32}$ ]] || fail 'Invalid rotation approval ID'
original_hash="$(wp option get wpvdmcp_token_hash --path="$WP_ROOT")"
replacement_hash="$(openssl rand -hex 32)"
wp option update wpvdmcp_token_hash "$replacement_hash" --path="$WP_ROOT" >/dev/null
if ! WPVDMCP_E2E_APPROVAL="$rotated" wp --path="$WP_ROOT" eval '
  $payload = array("name"=>"demo/reliability-rotation", "method"=>"DELETE", "input"=>array("id"=>2));
  $result = WPVDMCP_Approvals::consume(getenv("WPVDMCP_E2E_APPROVAL"), "run_ability", $payload);
  if (!is_wp_error($result) || "approval_credential_changed" !== $result->get_error_code()) {
    WP_CLI::error("An approval outlived its issuing token");
  }
'; then
  fail 'Old approval unexpectedly survived token rotation'
fi
wp option update wpvdmcp_token_hash "$original_hash" --path="$WP_ROOT" >/dev/null
echo 'MySQL-backed approval/credential lifecycle smoke passed'
