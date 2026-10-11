# Disposable WordPress + Elementor E2E Design

## Goal

Add a repeatable, non-production acceptance environment that proves WPVibe Direct MCP against a real WordPress installation, the exact currently audited WPVibe upstream commit, and current Elementor Core. This closes the largest remaining verification gap from 1.3.2 without using a customer site or fabricating hosted/provider state.

## Binding context

This design implements the autonomous completion brief already supplied for this repository. Current durable baseline at design time:

- Direct MCP `main`: `6a3724a9600992c8466d6580a673b65a088465d8` (`1.3.2`).
- Official WPVibe: `1.20.3`, commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`.
- Elementor Core: `4.3.4` (tagged 2026-10-05). WPVibe's public Elementor verification page still names 4.3.3, so the E2E lane intentionally tests the newer compatible patch release while retaining runtime discovery.
- WordPress: pin `6.9`, the minimum core release for the Abilities API used by this workflow.

## Architecture

Use a dedicated GitHub Actions workflow backed by a disposable MySQL service and a fresh `/tmp` WordPress install. The workflow downloads WordPress 6.9, checks out WPVibe at the exact audited SHA, installs Elementor Core 4.3.4, installs the current Direct MCP checkout, creates a synthetic administrator/token, and serves the site only inside the runner.

A shell acceptance script talks to Direct MCP through its real HTTP MCP endpoint. It does not call internal PHP methods for the operation under test. WP-CLI is used only for environment setup and for simulating the browser-admin approval click in this disposable environment; the mutation itself always travels through Direct MCP.

No production/client WordPress site is contacted. No Elementor Pro package or license is assumed. Pro Theme Builder remains a separately documented external acceptance boundary.

## Acceptance tracks

### 1. Transport and capability discovery

- Authenticated health endpoint succeeds.
- Modern `server/discover` works with MCP 2026-07-28 routing headers.
- `tools/list` exposes Abilities and native Elementor fallback tools when the real site registers the required routes.
- `discover_abilities` finds installed `elementor/*` abilities at runtime instead of relying on a hardcoded marketing count.

### 2. Elementor Atomic ON

Enable Elementor V4/Atomic using Elementor's own registered experiment option keys. Then prove through `run_ability`:

1. `elementor/create-page` creates a draft after one-time Direct MCP approval.
2. `elementor/build-composition` creates a real Atomic hierarchy containing a heading. This ability is `destructive=true`, `idempotent=false`; the live call therefore proves Direct MCP uses POST rather than the old incorrect DELETE behavior.
3. `elementor/get-page-structure` reads the resulting tree and exposes the created heading.
4. `elementor/publish-document` publishes the document after a fresh one-time approval. This ability is `destructive=true`, `idempotent=true`; the live call therefore proves Direct MCP uses DELETE.
5. The public page returns HTTP 200 and contains the expected heading text.

Each mutation must demonstrate the full approval-required -> admin approval -> successful retry path. Reusing an approval is forbidden.

### 3. Elementor Atomic OFF / fallback boundary

Disable Elementor V4/Atomic in the disposable site. Re-discover capabilities and verify an Atomic-only mutation fails predictably rather than being reported as a transport failure. Verify the WPVibe-native Elementor fallback tool family remains feature-detected when its routes are registered. Do not invent `_elementor_data` writes merely to force the test green.

### 4. Evidence and diagnostics

On failure upload WordPress debug log, PHP server log, and a compact redacted environment report. Never upload the Direct MCP token, database password, application passwords, or approval payload secrets.

## Safety

- Disposable runner only; no external customer URL is accepted by the workflow.
- Synthetic credentials are created inside the job and die with the runner.
- WPVibe is pinned to the audited commit; Elementor and WordPress versions are explicit.
- Mutations retain Direct MCP approval semantics.
- The workflow must fail closed when an expected ability/route is absent or when live output does not match the requested structure.
- Provider-backed screenshots/Lighthouse/stock images/fleet/account features are out of scope for this local E2E lane and remain fail-closed provider boundaries.

## Testing strategy

The E2E script itself is the acceptance test. Add it and the workflow first, run CI, and treat the first failure as RED. Fix only defects proven by the real environment, using focused regression tests before production-code changes. Keep the existing PHP 7.4/8.2/8.4 parity workflow green throughout.

A successful lane may update `docs/TESTING.md` to record exactly what was executed. It must not claim Elementor Pro, hosted providers, other builders, or production/client validation passed.

## Release policy

Do not publish a new version merely for CI/docs. If real E2E uncovers and fixes runtime behavior, choose the next SemVer patch, rerun both parity and E2E gates, then use the existing gated release workflow. If the harness passes without runtime changes, merge it as verification infrastructure and leave `1.3.2` immutable/current.