# Provider Availability Metadata Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add side-effect-free, non-secret provider readiness metadata to provider-backed MCP tool definitions while preserving exact WPVibe tool-name parity and existing runtime behavior.

**Architecture:** A focused `WPVDMCP_Provider_Availability` decorator owns tool→provider mapping and readiness detection. `WPVDMCP_Parity::merge_definitions()` applies it before MCP Apps decoration so both metadata layers compose. No execution provider is called during discovery.

**Tech Stack:** PHP 7.4+, WordPress filters/globals, MCP tool `_meta`, existing PHP test harness.

**Spec:** `docs/superpowers/specs/2026-10-12-provider-availability-metadata-design.md`

## Global Constraints

- Keep all audited provider-backed public tool names visible.
- Never invoke provider callbacks or filters from `tools/list`.
- Never include credentials/provider output in metadata.
- Preserve `provider_unavailable`, native fallback, approval, Worker-proof, auth, and release behavior.
- Do not mutate `v1.4.1`.

## Review Focus

- Provider callbacks with side effects are not invoked by discovery.
- WordPress filter registration is detected without executing the filter.
- Existing MCP Apps `_meta` survives provider decoration.
- Hybrid `get_page_html` does not claim complete unavailability when browser provider is absent.
- Non-provider tools do not receive misleading provider metadata.

---

### Task 1: Provider metadata decorator

**Files:**
- Create: `includes/class-wpvdmcp-provider-availability.php`
- Modify: `includes/class-wpvdmcp-parity.php`
- Create: `tests/reference/cases-19.php`
- Modify: `tests/reference-run.php`

**Interfaces:**
- Produces: `WPVDMCP_Provider_Availability::decorate_tools(array $tools): array`
- Consumes: existing callback globals and `has_filter()` registration state only.

- [ ] **Step 1: Write RED tests** for absent providers, callback/filter configuration, non-invocation, hybrid fallback metadata, `_meta` preservation, exact-name visibility, and non-provider omission.
- [ ] **Step 2: Run `php tests/reference-run.php`** and verify the new cases fail for missing metadata.
- [ ] **Step 3: Implement the decorator and wire it into `WPVDMCP_Parity::merge_definitions()`** before `WPVDMCP_MCP_Apps::decorate_tools()`.
- [ ] **Step 4: Run the focused reference suite** and verify GREEN.
- [ ] **Step 5: Update README/security/parity/testing/changelog docs** for metadata-only readiness signaling.
- [ ] **Step 6: Run all PHP suites, Abilities regression, full PHP syntax scan, and `git diff --check`.**

### Task 2: Review and exact-head gates

**Files:** No behavioral files beyond review-driven fixes.

**Interfaces:** Exact PR head and CI evidence.

- [ ] **Step 1: Commit/push one review head and open PR.**
- [ ] **Step 2: Require exact-head PHP 7.4/8.2/8.4 and disposable WordPress/Elementor E2E green.**
- [ ] **Step 3: Require independent exact-head review with no Critical/Important findings.**
- [ ] **Step 4: If head changes, invalidate all old evidence and repeat.**
- [ ] **Step 5: Merge with expected-head lock, then verify exact-main CI and release guard skips `v1.4.1`.**
