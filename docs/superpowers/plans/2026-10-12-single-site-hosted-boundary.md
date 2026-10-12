# Single-Site Hosted Boundary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the repository explicitly and testably single-site: each Direct MCP installation manages only its own WordPress site, while hosted site/account/fleet names remain external-provider-backed compatibility surfaces.

**Architecture:** Preserve all current runtime architecture. Do not add a multi-site registry, remote credential vault, cross-site transport, or fleet control plane. Add a narrow characterization regression for the hosted site-registry names, then align the user-facing documentation with the approved single-site deployment model.

**Tech Stack:** WordPress/PHP 7.4+, existing PHP reference test harness, Markdown documentation, GitHub Actions PHP matrix and disposable WordPress/Elementor E2E.

**Spec:** `docs/superpowers/specs/2026-10-12-single-site-hosted-boundary-design.md`

## Global Constraints

- Direct MCP remains site-local: one manually installed Direct MCP instance per WordPress site.
- Do not add a built-in multi-site registry, remote credential persistence, cross-site HTTP execution, or local fleet scheduler/dashboard.
- `connect_site`, `list_sites`, `remove_site`, `get_profile`, `start_fleet_job`, `show_fleet_dashboard`, and `use_usage_reset` remain external-provider-backed exact compatibility names.
- Missing providers continue to fail closed with `provider_unavailable`; never synthesize account/site/fleet/plan/quota state.
- `$GLOBALS['wpvdmcp_reference_providers']` and `wpvdmcp_reference_<tool>` remain backward compatible.
- Do not weaken authentication, approvals, Worker/control-plane boundaries, provider-output hardening, readiness metadata, or release gates.
- Release `v1.4.1` is immutable: do not rebuild, retag, replace, or republish it.
- Use single-agent/native execution only; do not spawn subagents or parallel reviewers unless the user explicitly changes that preference.

## Review Focus

- No-provider execution of `connect_site`, `list_sites`, and `remove_site` must return `provider_unavailable`, never an empty/fake local registry.
- Explicit external providers must remain authoritative; the single-site decision must not disable provider callback/filter execution.
- Discovery/readiness must continue to expose exact hosted names without executing providers or leaking provider credentials/results.
- Documentation must not imply that Direct MCP can natively manage multiple WordPress sites, while still honestly documenting exact-name parity.
- No change in this plan may alter release/tag behavior or mutate the immutable `v1.4.1` artifact.

---

### Task 1: Lock the single-site hosted-site boundary with a characterization regression

**Files:**
- Modify: `tests/reference/cases-19.php`
- Modify: `docs/TESTING.md`

**Interfaces:**
- Consumes: `WPVDMCP_Parity::execute( string $name, array $args )`, existing provider globals/filters, existing `WP_Error` helpers.
- Produces: one explicit reference regression proving that the three hosted site-registry names are provider-only and fail closed when no provider exists.

- [ ] **Step 1: Add the hosted site-registry characterization test**

Append one `parity_test()` to `tests/reference/cases-19.php` named:

```php
'hosted site registry names remain external-provider-only in single-site Direct MCP'
```

Assertions:

```php
unset(
    $GLOBALS['wpvdmcp_reference_providers'],
    $GLOBALS['wpvdmcp_test_filter_callbacks'],
    $GLOBALS['wpvdmcp_test_filter_values']
);

foreach ( array(
    'connect_site' => array( 'site_url' => 'https://remote.example.test/' ),
    'list_sites'   => array(),
    'remove_site'  => array( 'site_id' => 'remote-1' ),
) as $tool => $args ) {
    $result = WPVDMCP_Parity::execute( $tool, $args );
    assert_true( is_wp_error( $result ), $tool . ' fails closed without provider' );
    assert_same( 'provider_unavailable', $result->get_error_code(), $tool . ' provider-only boundary' );
}
```

Then configure `$GLOBALS['wpvdmcp_reference_providers']['list_sites']` with a synthetic callback returning safe test metadata and assert `WPVDMCP_Parity::execute( 'list_sites', array() )` returns that provider-backed result. Remove the global afterward.

Purpose: pin both sides of the contract—no local fallback exists, while explicit external provider compatibility remains intact.

- [ ] **Step 2: Run the focused reference suite**

Run:

```bash
php tests/reference-run.php
```

Expected: PASS. This is a characterization regression for already-approved behavior, so it is expected to pass on current production code. If it fails, stop and investigate the existing contract; do not invent a local multi-site implementation to make the test pass.

- [ ] **Step 3: Update the unreleased source test count**

In `docs/TESTING.md`, update only the current unreleased source count from:

```text
39 core/legacy + 14 parity + 94 reference cases (147 suite cases)
```

to:

```text
39 core/legacy + 14 parity + 95 reference cases (148 suite cases)
```

Keep the published `v1.4.1` release count unchanged at 119 suite cases.

In the hosted/provider-backed contracts section, add an explicit bullet that `connect_site`, `list_sites`, and `remove_site` are provider-only compatibility names in the single-site architecture and must never fall back to a local registry.

- [ ] **Step 4: Re-run the reference suite after documentation/count update**

Run:

```bash
php tests/reference-run.php
```

Expected:

```text
95 reference parity tests, 0 failures
```

- [ ] **Step 5: Commit Task 1**

```bash
git add tests/reference/cases-19.php docs/TESTING.md
git commit -m "test: lock single-site hosted boundary"
```

---

### Task 2: Align user-facing compatibility documentation with the approved deployment model

**Files:**
- Modify: `README.md`
- Modify: `docs/COMPATIBILITY.md`
- Modify: `docs/PARITY.md`
- Modify: `docs/PROVIDERS.md`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: the approved single-site spec and existing provider-backed hosted contract.
- Produces: consistent documentation that a user can follow without inferring native multi-site support.

- [ ] **Step 1: Clarify installation/deployment behavior in `README.md`**

Under **Install** or **Hosted-service boundary**, add this meaning without changing the published-release history:

```text
Install Direct MCP separately on each WordPress site you want to manage. A Direct MCP instance natively manages only the WordPress installation on which it is installed.
```

In **Hosted-service boundary**, explicitly state that `connect_site`, `list_sites`, and `remove_site` remain exact-name compatibility adapters only; they do not create a Direct-managed remote-site registry.

- [ ] **Step 2: Tighten the architectural statement in `docs/COMPATIBILITY.md`**

In **Hosted vs direct**, preserve the existing statement that Direct replaces the transport/account dependency for one site and add:

```text
The intended deployment model is one Direct MCP installation per WordPress site. Direct MCP does not use connect_site/list_sites/remove_site to build a local cross-site control plane.
```

Do not reclassify the hosted account/fleet names as native capabilities.

- [ ] **Step 3: Clarify parity wording in `docs/PARITY.md`**

Keep the exact hosted names in the parity table, but make the three site-registry entries explicit:

- `connect_site`: external provider only; no Direct local registry is created.
- `list_sites`: external provider only; Direct does not fabricate the current site as a one-item hosted registry.
- `remove_site`: external provider only; no Direct local remote-site record exists to remove.

In the **Every Site You Manage** feature-parity row, state that the phrase is satisfied only through an external provider/hosted integration; the native Direct deployment model is separate installation per site.

Do not claim WPVibe private cloud/account/fleet parity.

- [ ] **Step 4: Clarify provider responsibility in `docs/PROVIDERS.md`**

Add one short paragraph before **Exact hosted-tool adapters**:

```text
Direct MCP is intentionally single-site. These hosted adapters are optional external integrations, not a built-in remote-site registry. Install Direct MCP independently on each site for native site-local management.
```

Keep provider credential/network/SSRF/rate-limit/retention responsibilities unchanged.

- [ ] **Step 5: Record the documentation decision in `CHANGELOG.md`**

Under `## Unreleased`, add one documentation/architecture bullet stating that Direct MCP is explicitly single-site, that each site gets its own installation, and hosted site/account/fleet names remain external-provider-backed with no local remote credential registry.

Do not move the entry into the immutable `1.4.1` section.

- [ ] **Step 6: Verify documentation consistency**

Search the repository for misleading native multi-site claims:

```bash
grep -RInE 'built-in multi-site|local site registry|local fleet|native connect_site|native list_sites|native remove_site|Direct-managed remote' README.md docs CHANGELOG.md || true
```

Then manually inspect each match. Expected: no text claims that Direct MCP natively stores/manages remote sites; references describing the rejected/out-of-scope architecture in the approved spec are allowed.

Also confirm exact hosted names are still documented as provider-backed rather than removed from parity.

- [ ] **Step 7: Commit Task 2**

```bash
git add README.md docs/COMPATIBILITY.md docs/PARITY.md docs/PROVIDERS.md CHANGELOG.md
git commit -m "docs: clarify single-site deployment boundary"
```

---

### Task 3: Full verification and branch readiness

**Files:**
- Verify only; no planned production source changes.

**Interfaces:**
- Consumes: Tasks 1-2.
- Produces: exact branch-head verification evidence suitable for opening/reviewing a PR.

- [ ] **Step 1: Run all PHP suites**

Run:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
php tests/abilities-json-body-run.php
```

Expected:

```text
39 tests, 0 failures
14 parity tests, 0 failures
95 reference parity tests, 0 failures
PASS: Abilities compatibility bridge sends a core-style raw JSON body.
```

- [ ] **Step 2: Run PHP syntax validation**

Run:

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

Expected: every PHP file reports no syntax errors.

- [ ] **Step 3: Run whitespace/diff validation**

Run:

```bash
git diff --check origin/main...HEAD
```

Expected: no output.

- [ ] **Step 4: Inspect the exact branch diff**

Run:

```bash
git diff --stat origin/main...HEAD
git diff origin/main...HEAD -- tests/reference/cases-19.php README.md docs/COMPATIBILITY.md docs/PARITY.md docs/PROVIDERS.md docs/TESTING.md CHANGELOG.md docs/superpowers/specs/2026-10-12-single-site-hosted-boundary-design.md docs/superpowers/plans/2026-10-12-single-site-hosted-boundary.md
```

Expected: only the approved design/plan, one hosted-boundary characterization test, test-count documentation, and boundary wording changes. No `includes/` production PHP changes, dependency changes, release archive changes, or tag/version changes.

- [ ] **Step 5: Confirm release metadata remains untouched**

Verify plugin header/version/stable-tag files still identify the existing release exactly as before, and inspect:

```bash
git diff origin/main...HEAD -- wpvibe-direct-mcp.php readme.txt .github/workflows/release.yml
```

Expected: no release metadata/workflow diff from this work.

- [ ] **Step 6: Commit only if verification required a documentation/test correction**

If Task 3 discovers a genuine issue in the planned files, fix only that issue, rerun the affected checks, and commit with a narrow message. Otherwise create no extra commit.

- [ ] **Step 7: Open a PR only after the exact local head is green**

PR scope should state:

- Direct MCP is intentionally single-site;
- no multi-site registry/credential vault/fleet subsystem is added;
- exact hosted names remain external-provider-backed;
- one characterization regression pins fail-closed/provider behavior;
- `v1.4.1` is untouched.

After the PR exists, require exact-head PHP parity and disposable WordPress/Elementor E2E success before merge. In accordance with the user's execution preference, perform review in this same agent/session rather than spawning parallel reviewers.

---

## Plan self-review checklist

- Spec coverage: every accepted single-site boundary is either pinned by Task 1, documented by Task 2, or protected by Task 3 verification.
- No production-code task exists because current runtime semantics already match the approved architecture.
- External provider callbacks/filters remain supported; no task removes exact hosted names.
- The new characterization test intentionally passes on current behavior; it is a contract-lock test, not justification for behavior changes.
- Test-count changes affect unreleased source only; immutable `v1.4.1` counts/history remain unchanged.
- No task creates remote credential storage, cross-site networking, fleet execution, or private-cloud emulation.
- Single-agent/native execution is preserved throughout.
