# WPVibe Direct MCP Runtime Parity Design

**Date:** 2026-10-10

## Goal

Make WPVibe Direct MCP a safe, site-local MCP bridge for the current open-source WPVibe WordPress plugin while matching the public WPVibe feature/tool surface wherever that behavior can honestly be provided without WPVibe's private hosted account infrastructure.

The Direct plugin must support real Elementor development end to end: Elementor 4.3+ WordPress Abilities first, native WPVibe Elementor routes as the compatibility fallback, approval-gated writes, schema/read-first workflows, and post-write/live-page verification guidance.

## Authoritative baseline

- WordPress-side reference: `awesomemotive/wpvibe-ai-mcp` version **1.20.3**, commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`.
- Public product surface: current `wpvibe.ai/features/`, `/docs/`, `/docs/dangerously-bypass-approvals/`, Tools Reference, Cookbook, and Works-with-AI pages as audited on 2026-10-10.
- Elementor reference: current Elementor 4.3+ WordPress Abilities API surface as described by Elementor's own MCP documentation and the WPVibe Elementor compatibility page.
- Direct MCP baseline: `salman0butt/wpvibe-direct-mcp` main `9d0555dc2d8b58d86a5bcdee84b0b0173cc517e8` / released 1.3.0.

When sources disagree on marketing counts (for example WP-CLI command counts), runtime discovery and the installed WPVibe plugin are authoritative. Direct MCP must not hardcode a marketing count as a capability claim.

## Product boundary

### Site-local/native

Direct MCP reuses WPVibe's installed WordPress-side implementation for:

- WordPress/site information and capability checks;
- theme file read/search/outline/edit/write/delete;
- surgical content search/edit;
- draft-theme create/preview/publish/delete and compile-source concurrency checks;
- native PHP WP-CLI emulation;
- WordPress Abilities API;
- media import and WPVibe's SSRF/MIME/SVG protections;
- rendered HTML fallback, navigation, live-reload state;
- Elementor, Beaver Builder, Bricks and Breakdance native integrations;
- WPCode dormant snippet writes;
- audit log and operation receipts.

### Direct worker replacements

The hosted WPVibe Worker normally supplies approval/session/proof orchestration around selected WordPress routes. Direct MCP must reproduce the required *safety semantics* locally without exposing or forging WPVibe private proof credentials:

1. Non-GET generic REST writes require a Direct MCP browser approval unless the site owner enabled WPVibe's `Dangerously bypass approvals` setting.
2. A destructive/high-risk `run_wp_cli` response from `/wpvibe/v1/cli/run` is converted to a Direct MCP approval. After approval, Direct MCP calls the installed `WPVibe_CLI::run_approved()` locally with the exact approved snapshot so WPVibe's drift checks, destructive classifier, DB protections and audit logging remain authoritative.
3. The proof-protected dormant WPCode REST route is not called as if Direct MCP possessed the hosted Worker's proof key. Where the installed WPVibe class supports the dormant path, Direct MCP invokes `WPVibe_Code_Snippet::handle(..., true)` locally after enforcing the same WordPress capability. Older compatible sites may use a safe fallback only when the protected worker boundary is not required.
4. Generic REST is forbidden from reaching WPVibe/Direct authentication, proof, detached-operation, self-update, builder-login, approved-execution and audit-writer control-plane routes. Higher-level safe tools own those flows.

### Hosted/provider-backed

The following remain user-owned provider integrations and must fail closed when absent: hosted account/site registry, profile/billing/usage, fleet jobs/dashboard, Lighthouse/PageSpeed, JavaScript browser rendering, screenshots, stock image search, PDF text extraction and SeedProd browser compilation. Direct MCP must never fabricate WPVibe cloud state or bundle WPVibe private credentials.

## Approval semantics

- `Dangerously bypass approvals` is the installed WPVibe site's source of truth.
- Read-only operations run without Direct approval.
- Generic REST `POST`, `PUT`, `PATCH`, and `DELETE` require a one-time Direct approval when bypass is off.
- Ability execution follows WordPress core method semantics:
  - readonly -> `GET`;
  - destructive **and idempotent** -> `DELETE`;
  - all other abilities -> `POST`.
- All non-read abilities require Direct approval when bypass is off, even if a plugin's own annotation does not describe the operation as destructive.
- Approval records bind to a canonical payload hash and are one-time/replay-resistant.
- Approved WP-CLI execution must bind to the exact command, `confirm_write` state, and WPVibe dry-run/operation snapshot returned before approval.

## Elementor contract

### Modern Elementor 4.3+

Direct MCP must expose WordPress Abilities discovery/info/run so the installed Elementor namespace is runtime-discovered rather than copied into a brittle fixed list. Current Elementor 4.3.x includes page creation/structure, composition, element management, settings, publishing, preview links, widget schemas, assets/components/dynamic tags, resources, global variables/classes/default styles and related guides/resources.

The built-in Elementor skill must require:

1. `discover_abilities` and `get_ability_info` before writes;
2. installed schemas/resources rather than guessed Atomic data;
3. full-object read-modify-write for `elementor/update-page-settings` because it replaces settings rather than merging;
4. WordPress REST/native page-template handling instead of passing a template through Elementor page settings;
5. explicit awareness that many Atomic build/style abilities require Atomic Editor enabled;
6. site-wide warning/approval discipline for global variables, classes and default styles;
7. verification after writes: re-read page structure, publish when requested, then inspect rendered/live output because Elementor autosaves/cache can diverge from a successful write response.

### Compatibility fallback

When modern Elementor Abilities are absent but WPVibe native Elementor routes exist, Direct MCP keeps:

- `elementor_widgets`;
- `elementor_schema`;
- `elementor_style_schema`;
- `elementor_save_page`;
- `elementor_save_template`.

These routes must remain conditional on installed route availability and must never be replaced by generic `_elementor_data` writes when a native route exists.

## Upstream route classification

The parity manifest must classify all relevant upstream route families as one of:

- model-facing native route;
- Direct local equivalent;
- hosted/provider-backed;
- internal/control-plane only.

Internal/control-plane classification includes at minimum `/ping`, `/health`, connection challenge/status/authorization helpers, `/classic-theme-safety`, `/draft-theme/compile-sources`, `/audit-log/record`, `/cli/run-approved`, `/op-proof/check`, approved code-snippet execution, `/builder-login`, detached operations and self-update routes. Classification does not make an internal credential-bearing route callable by the model.

## WAF / transport resilience

WPVibe 1.20.3 supports base64 armoring for code-bearing fields on its own REST routes. Direct MCP's internal REST dispatch avoids that second-hop WAF, but the public Direct MCP REST endpoint itself can still be filtered by a host before WordPress receives JSON-RPC containing PHP/JS/CSS/SQL.

Direct MCP therefore needs a bounded armored-call escape hatch rather than exposing arbitrary internal execution:

- accept an explicit armored tool-call form carrying base64-encoded UTF-8 JSON arguments;
- decode with strict base64 and JSON-object validation;
- prohibit recursive armored calls and app-only/internal helper tools;
- dispatch through the same normal tool handlers so all schemas, approvals, capability checks and route safety remain in force;
- document it as a retry path for hosts/WAFs that reject raw code-bearing MCP JSON.

No decoded bytes may bypass the normal tool implementation.

## Release workflow

The existing 1.3.0 release workflow is tied to an immutable historical tag. It must not run on every future successful `main` parity build and then fail because `v1.3.0` points to the released historical commit. Future release publication must be explicit/version-driven; ordinary `main` CI must stay green without trying to retag old releases.

This parity branch is not automatically a new GitHub release. Version/tag publication is a separate release gate after branch verification and merge.

## Test requirements

Tests must prove, at minimum:

- Ability HTTP method selection for readonly, destructive+idempotent, and destructive+non-idempotent operations.
- Ability write approval, payload binding, bypass behavior and replay rejection.
- Generic REST read vs write approval behavior, payload mismatch/replay rejection, bypass behavior, response limiting, and control-plane denylist.
- WP-CLI destructive approval conversion, exact-snapshot execution through native `run_approved`, bypass path and changed-command rejection.
- WPCode dormant local bridge never activates a snippet and does not require/forge hosted proof credentials.
- Armored call strict decoding, normal-handler dispatch and forbidden recursive/internal targets.
- Upstream 1.20.3 route manifest includes newly classified helper/control-plane routes.
- Elementor skill/contract includes modern Abilities-first and live verification rules.
- All existing legacy, parity and reference suites remain green on PHP 7.4, 8.2 and 8.4.

## Validation boundary

Automated tests can prove Direct MCP protocol, routing, safety, adapters and contracts. They cannot honestly prove a real Elementor/WordPress/host integration without an installed disposable site. After code parity is green, a disposable staging E2E remains required for final acceptance of actual Elementor 4.3+, WPCode, WP-CLI/SQL, uploads, draft themes and host-WAF behavior. No production/client site is used for destructive acceptance testing.
