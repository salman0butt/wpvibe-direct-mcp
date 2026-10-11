# Disposable WordPress + Elementor E2E Implementation Plan

**Spec:** `docs/superpowers/specs/2026-10-11-disposable-wordpress-elementor-e2e-design.md`

## Global constraints

- Work only on `feat/disposable-wordpress-elementor-e2e-20261011` until merge gates pass.
- Never point the E2E harness at an externally supplied or production URL.
- Pin WordPress 6.9, WPVibe `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`, and Elementor Core 4.3.4.
- Run all Elementor operations through the Direct MCP HTTP endpoint; WP-CLI may only prepare the disposable site and approve pending Direct MCP operations.
- Use TDD: get a failing E2E signal before any production runtime change.
- Keep existing PHP parity CI green.
- Do not claim Pro/provider/other-builder coverage from this lane.

## Task 1 — Add the real acceptance script

**Files:**
- Create `tests/e2e/wordpress-elementor-smoke.sh`

Implement reusable MCP JSON-RPC helpers and assertions for:

1. authenticated health;
2. modern `server/discover`;
3. `tools/list`;
4. runtime Elementor ability discovery;
5. Atomic ON create-page approval + execution;
6. Atomic ON build-composition approval + execution;
7. readonly structure verification;
8. publish-document approval + execution;
9. live frontend verification;
10. approval replay rejection;
11. Atomic OFF predictable failure + native fallback discovery.

The script must print actionable failure messages without printing secrets.

## Task 2 — Add the disposable GitHub Actions environment

**Files:**
- Create `.github/workflows/wordpress-elementor-e2e.yml`

Provision MySQL, PHP 8.2, WP-CLI, WordPress 6.9, exact WPVibe upstream SHA, Elementor 4.3.4, and the current Direct MCP checkout. Configure synthetic credentials, enable WordPress debug logging, enable Atomic V4 through Elementor's own experiment option keys, launch a local WordPress server, and run Task 1.

Upload redacted logs only on failure.

**RED gate:** open/update the PR and inspect the first real workflow run. Do not patch production code until the E2E job has failed for a concrete behavioral reason.

## Task 3 — Diagnose and fix only proven runtime gaps

For each failure:

1. use systematic debugging to establish root cause;
2. if Direct MCP behavior is wrong, first add a focused regression test to the existing PHP suite and observe RED;
3. make the smallest runtime fix;
4. run focused CI and the full PHP parity matrix;
5. rerun the disposable E2E lane.

Environment/setup failures are fixed in the harness, not disguised as product fixes.

## Task 4 — Record verified evidence

**Files:**
- Update `docs/TESTING.md` only after a green disposable run.
- Update `docs/PARITY.md` or compatibility docs only if the live run changes a previously documented status.

Record exact WordPress, WPVibe SHA/version, Elementor version, operations actually exercised, and remaining external boundaries. Do not mark unexecuted checklist items passed.

## Task 5 — Review, merge, and release decision

1. Run/inspect exact-head PHP parity CI.
2. Run/inspect exact-head disposable WordPress + Elementor E2E CI.
3. Review the complete branch diff and security impact.
4. Merge with expected-head protection only when both gates are green.
5. Verify `main` after merge.
6. If no runtime plugin behavior changed, keep release `1.3.2` current.
7. If runtime behavior changed, prepare the next patch release through the existing gated release workflow, then verify tag, ZIP, checksum, and release page.