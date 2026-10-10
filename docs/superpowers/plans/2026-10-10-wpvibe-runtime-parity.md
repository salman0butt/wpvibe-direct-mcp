# WPVibe Runtime Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the runtime/safety gaps between WPVibe Direct MCP and the current WPVibe 1.20.3 + wpvibe.ai public surface, with production-grade Elementor Abilities support.

**Architecture:** Keep the installed WPVibe plugin as the native executor. Add narrowly scoped Direct worker adapters only where the hosted WPVibe Worker normally supplies approval/proof orchestration, and route every decoded/approved operation back through WPVibe/WordPress native implementations. Hosted account/browser/audit providers remain explicit user-owned providers.

**Tech Stack:** WordPress/PHP 7.4+, WordPress REST API, WordPress Abilities API 6.9+, MCP JSON-RPC/Streamable HTTP, GitHub Actions PHP 7.4/8.2/8.4.

**Spec:** `docs/superpowers/specs/2026-10-10-wpvibe-runtime-parity-design.md`

## Global Constraints

- Upstream WordPress plugin baseline is WPVibe 1.20.3 commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`.
- Do not copy or fabricate WPVibe private hosted account/fleet credentials/state.
- Do not expose proof keys, builder-login credentials, authorization routes, detached worker state, or other control-plane primitives as model tools.
- Preserve PHP 7.4 compatibility.
- Use Direct browser approvals unless the installed site's WPVibe `Dangerously bypass approvals` setting is enabled.
- Use TDD: regression test must fail for the intended reason before production-code implementation.
- Real builder/host acceptance remains a disposable-staging E2E requirement; automated tests must not claim it occurred.

## Review Focus

- Destructive but non-idempotent Abilities must remain POST, not DELETE.
- A changed REST body/CLI command after approval must fail payload binding rather than execute.
- Generic REST must never reach WPVibe/Direct control-plane routes even with an administrator token.
- Direct WP-CLI approved execution must preserve WPVibe's original dry-run snapshot/drift checks rather than bypassing the classifier.
- Armored payloads must decode only into ordinary allowed tool arguments; recursive/internal execution must remain impossible.

---

### Task 1: Correct WordPress Abilities / Elementor execution semantics

**Files:**
- Modify: `tests/parity-run.php`
- Modify: `includes/class-wpvdmcp-tools.php`
- Modify: `skills/elementor.md`
- Modify: `skills/elementor-atomic.md`

**Interfaces:**
- Consumes: `WPVDMCP_Tools::get_ability_info()`, `WPVDMCP_Approvals`.
- Produces: correct `run_ability` HTTP method selection and current Elementor read/write/verify workflow guidance.

- [ ] Add failing tests proving readonly -> GET, destructive+idempotent -> DELETE, destructive+non-idempotent -> POST, and write approval binding.
- [ ] Run branch CI and confirm the new non-idempotent test fails because current code sends DELETE.
- [ ] Implement method selection using `readonly`, `destructive`, and `idempotent` annotations.
- [ ] Expand Elementor skills to require Abilities-first discovery, Atomic Editor awareness, whole-settings read/modify/write, native page-template handling, global-style caution, and live post-write verification.
- [ ] Run all suites and PHP lint.

### Task 2: Approval-gate generic REST writes and seal control-plane routes

**Files:**
- Modify: `tests/parity-run.php`
- Modify: `tests/reference/cases-1.php`
- Modify: `includes/class-wpvdmcp-tools.php`
- Modify: `includes/class-wpvdmcp-reference-parity.php`

**Interfaces:**
- Consumes: `WPVDMCP_Approvals::request/consume/bypass_enabled`.
- Produces: `rest_api` / `rest_api_write` with optional `approval_id`; normalized approval payload; expanded sensitive-path denylist.

- [ ] Add failing tests: GET immediate; POST/PUT/PATCH/DELETE require approval; approved request executes once; replay/mutated body fails; bypass executes; each control-plane route family is blocked.
- [ ] Confirm CI fails on current ungated writes.
- [ ] Add `approval_id` to public schemas and gate non-GET requests after method/path/body normalization but before dispatch.
- [ ] Deny Direct MCP auth endpoints and WPVibe authorization/proof/approved-execution/builder-login/detached/self-update/audit-writer/connection-challenge route families.
- [ ] Verify exact existing safe REST reads/writes still preserve destination WordPress capability checks.

### Task 3: Recreate WPVibe Worker approval handoff for destructive WP-CLI/SQL

**Files:**
- Create: `includes/class-wpvdmcp-worker-bridge.php`
- Modify: `wpvibe-direct-mcp.php`
- Modify: `includes/class-wpvdmcp-tools.php`
- Modify: `tests/bootstrap.php`
- Modify: `tests/parity-run.php`

**Interfaces:**
- Produces: `WPVDMCP_Worker_Bridge::run_wp_cli(array $args)` returning native result, Direct approval request, or `WP_Error`.
- Uses installed `WPVibe_CLI::run_approved($command, $confirm_write, $approved_state)` only after Direct approval/bypass and baseline `manage_options` authorization.

- [ ] Add test doubles for upstream `approval_required` response and native `WPVibe_CLI::run_approved`.
- [ ] Add failing tests for destructive approval conversion, exact snapshot binding, changed-command rejection, one-time consumption, and bypass execution.
- [ ] Implement read/ordinary path through `/wpvibe/v1/cli/run`; on upstream approval_required, create Direct approval from upstream operation/dry-run snapshot.
- [ ] After approval (or bypass), call native `run_approved` locally with the approved snapshot; never call proof-protected `/cli/run-approved` as if Direct MCP possessed a hosted proof key.
- [ ] Verify all legacy/parity/reference tests and lint.

### Task 4: Make dormant WPCode writes work without forging hosted proof

**Files:**
- Modify: `includes/class-wpvdmcp-worker-bridge.php`
- Modify: `includes/class-wpvdmcp-tools.php`
- Modify: `tests/bootstrap.php`
- Modify: `tests/parity-run.php`

**Interfaces:**
- Produces: `WPVDMCP_Worker_Bridge::code_snippet(array $args)`.
- Uses installed `WPVibe_Code_Snippet::handle(WP_REST_Request $request, true)` when available.

- [ ] Add a failing test showing Direct MCP must not depend on the hosted Worker's op-proof for dormant snippets.
- [ ] Add capability and availability tests (`WPCode_Snippet`, `wpcode_edit_snippets`).
- [ ] Implement local dormant handler invocation while preserving WPVibe validation and inactive-state invariant; safe compatibility fallback only for older WPVibe without the local class/worker-proof boundary.
- [ ] Verify no `active` input is introduced and all existing code-snippet tests remain green.

### Task 5: Add bounded WAF-safe armored tool calls

**Files:**
- Modify: `includes/class-wpvdmcp-tools.php` or create focused `includes/class-wpvdmcp-armored-call.php`
- Modify: `wpvibe-direct-mcp.php` if a new class is created
- Modify: `tests/parity-run.php`
- Modify: `docs/PARITY.md`

**Interfaces:**
- Produces tool `call_armored` with `{tool_name:string, arguments_base64:string}`.
- Dispatches only through existing normal tool handlers after strict base64 + UTF-8 JSON-object decoding.

- [ ] Add failing tests for valid decoding/dispatch, invalid base64, non-object JSON, recursive `call_armored`, app-only approval helper, and unknown/internal target rejection.
- [ ] Implement a strict allowlist based on exposed model-facing tools and explicitly forbid recursion/control-plane/app-only helpers.
- [ ] Verify decoded requests still hit the same approvals and capability checks as unarmored calls.

### Task 6: Complete upstream route/feature manifest and current Elementor docs

**Files:**
- Modify: `includes/class-wpvdmcp-reference-manifest.php`
- Modify: `tests/reference/cases-1.php`
- Modify: `docs/PARITY.md`
- Modify: `docs/TESTING.md`
- Modify: `README.md`

**Interfaces:**
- Produces machine-readable classification for all current upstream helper/control-plane routes and human-readable feature matrix.

- [ ] Add failing manifest tests for `/classic-theme-safety`, `/draft-theme/compile-sources`, proof/approved routes, and current Direct worker-replacement classification.
- [ ] Update manifest without exposing internal routes as model tools.
- [ ] Document current wpvibe.ai feature surface, runtime WP-CLI discovery (not a brittle marketing count), provider boundaries, Direct approvals, and Elementor 4.3+ acceptance flow.
- [ ] Expand disposable WordPress checklist to cover Elementor Atomic on/off, Pro templates when installed, live/autosave/cache verification, WP-CLI destructive approval, WPCode dormant flow, REST approval and WAF retry.

### Task 7: Fix post-1.3.0 release automation

**Files:**
- Modify: `.github/workflows/release.yml`
- Modify: `docs/TESTING.md` if needed

**Interfaces:**
- Produces historical `v1.3.0` release workflow that cannot automatically fail future successful `main` builds.

- [ ] Change the historical 1.3.0 publisher to explicit/manual dispatch only (or otherwise gate it so an existing immutable tag at a historical SHA is a successful no-op).
- [ ] Preserve release verification when intentionally invoked.
- [ ] Validate YAML by GitHub Actions parsing on the branch and ensure normal parity CI remains independent.

### Task 8: Whole-branch verification, review, and integration

**Files:**
- Review all branch changes.

**Interfaces:**
- Produces a merge-ready branch; no claim of live WordPress E2E without a disposable target.

- [ ] Run exact-head GitHub Actions on PHP 7.4, 8.2, 8.4 and require every legacy/parity/reference suite plus syntax validation to pass.
- [ ] Review branch diff for secrets, duplicate tools, accidentally exposed internal routes, version/release inconsistencies, and provider-boundary regressions.
- [ ] Open/update the PR with the audited feature matrix and explicit staging-E2E boundary.
- [ ] Merge only on green exact-head CI and no unresolved review blockers.
- [ ] Verify post-merge `main` exact SHA and CI. Do not publish a new version/tag unless the release gate is explicitly invoked separately.
