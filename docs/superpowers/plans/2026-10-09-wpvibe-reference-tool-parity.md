# WPVibe Reference Tool Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close every remaining audited WPVibe public MCP tool-name gap without faking hosted state.

**Architecture:** Add one reference-parity facade that supplies exact-name local aliases, provider-backed hosted operations, a parity manifest, and safe live-reload inspection. Reuse existing REST, approvals, saved-skills, and provider infrastructure.

**Tech Stack:** PHP 7.4+, WordPress REST APIs, existing dependency-free PHP test harness.

**Spec:** `docs/superpowers/specs/2026-10-09-wpvibe-reference-tool-parity-design.md`

## Global Constraints
- WordPress 6.0+; PHP 7.4+.
- Audited official WPVibe plugin baseline stays 1.20.3 / `8f303926...`.
- Never embed WPVibe/Unsplash/cloud credentials or fabricate account/fleet state.
- Consequential writes keep one-time approvals or upstream WPVibe safety gates.
- Existing Direct MCP tool names remain backward compatible.

## Review Focus
- Hosted provider absent: exact hosted tool names must return honest `provider_unavailable`, not fake success.
- `rest_api_write` must reject GET and preserve blocked credential/self routes.
- `save_skill` must update an existing local skill, create a missing one, and never overwrite built-ins.
- The audited reference manifest and exposed definitions must stay in sync.
- Existing route-backed tools must remain conditionally exposed only when their routes exist.

---

### Task 1: Reference manifest and failing coverage test
**Files:** Create `includes/class-wpvdmcp-reference-parity.php`; modify `tests/parity-run.php`.
**Produces:** `WPVDMCP_Reference_Parity::reference_tools()`, `definitions()`, `merge_definitions()`.
- [x] Add tests asserting all audited reference tool names are exposed.
- [x] Run `php tests/parity-run.php` and confirm RED on missing names.
- [x] Implement minimal definitions + manifest.
- [x] Run parity suite GREEN.

### Task 2: Exact-name local aliases
**Files:** Modify `includes/class-wpvdmcp-reference-parity.php`, `includes/class-wpvdmcp-server.php`, `tests/parity-run.php`.
**Produces:** `audit_page`, `rest_api_write`, `save_skill`, `reference_parity_manifest`, `get_last_change` execution.
- [x] Add behavior tests first.
- [x] Confirm RED.
- [x] Implement using existing `WPVDMCP_Parity_Insights`, `WPVDMCP_Tools`, and `WPVDMCP_Parity_Skills` primitives.
- [x] Run full suites GREEN.

### Task 3: Hosted-boundary provider adapters
**Files:** Modify `includes/class-wpvdmcp-reference-parity.php`, `docs/PROVIDERS.md`, `docs/PARITY.md`, `tests/parity-run.php`.
**Produces:** provider-backed `connect_site`, `list_sites`, `remove_site`, `get_profile`, `start_fleet_job`, `show_fleet_dashboard`, `use_usage_reset`.
- [x] Add absent-provider and configured-provider tests first.
- [x] Confirm RED.
- [x] Implement one filter/provider namespace per tool with structured hosted-boundary errors.
- [x] Run full suites GREEN.

### Task 4: Release hardening
**Files:** bump release metadata/changelog/readme; update testing docs.
- [x] Bump Direct MCP to 1.3.0.
- [x] Run legacy suite, parity suite, PHP lint, whitespace/secret checks.
- [ ] Build ZIP and run both suites/lint from extracted ZIP.
- [ ] Compare source/archive manifests and publish one fast-forward GitHub commit only if identical.
