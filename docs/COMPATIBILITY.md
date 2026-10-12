# Compatibility Architecture

## Audited baseline

WPVibe Direct MCP 1.4.0 remains audited against:

- official WPVibe WordPress plugin **1.20.3**;
- upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6` from 2026-10-06;
- WordPress 6.0+ and PHP 7.4+ as the plugin floor;
- WordPress 6.9 + Elementor Core 4.3.4 for the disposable Abilities/Elementor acceptance lane.

Release **1.4.0** incorporates the verified runtime/security hardening accumulated after immutable release 1.3.3. Older tags remain unchanged.

## Feature detection first

Direct MCP does not expose every possible route-backed tool unconditionally. At runtime it asks WordPress for registered REST routes and lists only tools whose required WPVibe/native route exists.

This permits a reasonable version range:

- an older WPVibe installation still receives the tools it actually supports;
- a newer installation can expose compatible routes without a Direct MCP release just to change a version string;
- unavailable features do not appear as fake tools that fail later.

The wp-admin screen shows installed versions, available tool count, feature flags, and a warning when WPVibe is newer than the version last audited by Direct MCP.

The disposable 1.4.0 lane also checks the site's live `/wpvibe/v1` REST registry against the machine-readable parity manifest. Parameterized runtime routes are normalized to their manifest form; WordPress core's generated namespace index is ignored; any unclassified operational WPVibe route fails the E2E gate.

## Version-dependent behavior

### WordPress Abilities

Requires WordPress 6.9+ and the `/wp-abilities/v1/abilities` REST namespace. If unavailable, Abilities tools are hidden / return an actionable unavailable error rather than guessing routes.

POST Ability mutations use a real raw `application/json` request body. The focused regression protecting that contract runs in the PHP matrix and is required on both the verified source checkout and extracted 1.4.0 release ZIP before publication.

### WPVibe 1.20-era contracts

Direct MCP understands current fields such as:

- file `scope` and wp-content diagnostic reads;
- `list_files.directory`;
- compile/source hashes where exposed;
- recursive file-pattern semantics managed by WPVibe;
- `publish_draft_theme.saved_customizations = set_aside|keep`;
- current builder routes;
- dormant WPCode snippet capability;
- operation receipts;
- current SVG sanitizer/media behavior.

The audit did **not** find an offset/pagination field on WPVibe 1.20.3's theme/wp-content `file/list` route, so Direct MCP does not invent one.

## Builders

Dedicated 1.20.3 integrations detected in upstream source:

- Elementor
- Beaver Builder
- Bricks
- Breakdance

Direct MCP exposes native route-backed tools conditionally.

Gutenberg uses WordPress REST/block behavior and, where relevant, Abilities. SeedProd and other plugins can surface capabilities through native REST/Abilities. No dedicated 1.20.3 WPVibe save class was found for Divi or WPBakery, so Direct MCP does not reverse-engineer their private/serialized storage. It supplies original workflow skills that tell the agent to discover a supported native route first.

## DISALLOW_FILE_EDIT / DISALLOW_FILE_MODS and theme safety

Direct MCP does not override WordPress/WPVibe file-edit policy. If WPVibe refuses an operation because file editing/modification, draft state, PHP validation, path policy, or another safety gate blocks it, Direct MCP returns that upstream error.

Current route classification keeps `/wpvibe/v1/classic-theme-safety` infrastructure-only because it participates in the fresh-proof boundary, while `/wpvibe/v1/draft-theme/compile-sources` remains explicitly model-facing as a safe compile snapshot. Generic REST cannot be used to cross that boundary.

## WP-CLI

`run_wp_cli` is WPVibe's native PHP-dispatch command catalog, not a shell bridge. Direct MCP does not add `exec`, shell access, `eval`, or `eval-file` and does not duplicate the catalog. Command availability therefore follows the installed WPVibe version.

Safe/non-destructive calls continue through WPVibe's public `/wpvibe/v1/cli/run` path. When WPVibe classifies a command as destructive and returns `approval_required`, Direct MCP 1.4.0 converts that result into a one-time browser-admin approval bound to the exact command plus the exact upstream operation/dry-run snapshot. Command drift and replay fail closed. Once approved, Direct MCP calls WPVibe's trusted in-process approved executor with that snapshot; it never exposes the Worker-only `/wpvibe/v1/cli/run-approved` route or operation-proof secret to the model.

## WPCode snippets

Both current WPVibe snippet REST routes require Worker operation proofs. Direct MCP 1.4.0 does not mint or expose those proofs. It re-checks `wpcode_edit_snippets` and calls the same trusted `WPVibe_Code_Snippet::handle()` implementation in-process, using dormant semantics first. Only upstream `snippet_review_required` results enter the payload-bound one-time Direct approval flow. Model-supplied activation is discarded and human activation in WPCode remains authoritative.

## Generic REST and Worker/control-plane compatibility

The generic REST bridge preserves normal public WPVibe route compatibility, but explicitly rejects audited infrastructure/control-plane endpoints before dispatch and before approval creation. This includes authorization/preflight, connection challenge/status, operation-proof verification, approved Worker CLI execution, executable code approval, builder login minting, detached Worker execution, audit-log recording, and self-update control plane.

This is explicit route classification rather than a namespace-wide block. The runtime inventory gate is intended to catch newly registered upstream routes that need a deliberate model-facing vs infrastructure-only decision.

## Armored transport retry

Release 1.4.0 includes `call_armored` as a Direct-only local extension. It base64-decodes a bounded JSON argument object and then invokes only an already-exposed model tool. It does not widen compatibility into a generic execution primitive: unknown/unexposed targets, self-targeting, approval internals, invalid payloads, and oversized payloads fail closed, and the called tool keeps its normal approval/provider/routing behavior.

## Hosted vs direct

The official open-source WPVibe repository documents itself as the WordPress-side component while the hosted gateway/account/tool registration lives at wpvibe.ai. Direct MCP replaces only the transport/account dependency needed for one site; it does not recreate account/fleet/plan/cloud services.

## Reference audit status

The reference audit compares Direct MCP against the live WPVibe Features page, public Tools Reference, current Works-with-AI compatibility pages, and every REST-registering class in upstream WPVibe 1.20.3. Exact hosted tool names are present as provider-backed adapters where no single-site local equivalent exists. Internal control-plane routes are deliberately not exposed directly to the model.

The latest upstream source check on 2026-10-12 still resolved `awesomemotive/wpvibe-ai-mcp` `main` to `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`, so no newer WordPress-side WPVibe source change required a parity implementation during this audit.
