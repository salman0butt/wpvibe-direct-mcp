# Changelog

## Unreleased

## 1.5.2 - 2026-10-21
- Production-readiness audit hardening: approval creation/approve/reject now verify exact persisted transient state, fail closed and retain one-time claims when a decision write reports success but cannot be read back. Approval receipts are bound to the live token-hash fingerprint so token rotation (even without an owner change) invalidates outstanding approvals; credential-unbound older pending receipts are rejected and must be requested again.
- Browser media failure recovery now checks durable failed-ticket state, preserves the transition claim on uncertain persistence, and explicitly reports attachment IDs requiring manual cleanup when WordPress cannot roll back a partially completed upload. Multi-file rollback continues attempting every attachment despite individual deletion failure. Authenticated `check_upload` status includes nonsecret failure/reconciliation metadata, and inline MCP Apps uploads retain the hidden POST target until a verified terminal state rather than tearing it down after 1.8 seconds.
- Added disposable MySQL integration checks for eight competing WordPress approval consumers and token rotation, plus nine PHP reference regressions for ambiguous successful/failed acknowledgements, upload state/rollback, credential rotation, legacy receipts and browser-status safety. CI still does not certify Redis or multi-node persistent object caches.

## 1.5.1 - 2026-10-20
- Hardened WPCode retirement against ambiguous IDs and cache readbacks before release: deactivation/trashing now reject negative, fractional, array, prefixed, noncanonical and overflowing IDs instead of silently targeting another snippet. Post-retirement checks recognize object/flat/nested or ID-keyed cache entries and fail closed when WPCode's execution option cannot be inspected; two regression cases cover these boundaries without changing native approval/capability rules.
- Audited official WPVibe 1.20.4 (2026-10-13) and 1.20.5 (2026-10-14) against the last v1.5.0 upstream baseline. The Direct WPCode safety bridge now exposes upstream `deactivate` and approval-gated `delete` only when the native handler supports them, binds deletion to its exact ID/action, and fails closed rather than claim the snippet stopped when WPCode's execution cache still contains it.
- Added Bricks template `template_type` (header/footer and supported template subtypes) and Beaver Builder's optional schema slug; refreshed native WPCode, Bricks, Beaver, and SeedProd skill guidance. Generic route discovery now respects upstream's `show_in_index=false` endpoints while trusted server-side route checks continue to work.
- Extended PHP regression coverage to 204 reference cases and added disposable WordPress 6.9 / Elementor 4.3.4 CI lanes for both pinned WPVibe 1.20.3 and 1.20.5, with an additional disposable WPCode Lite create/deactivate/approval-gated-trash check on the newer lane. Refreshed upstream audit metadata and WordPress admin's newer-version warning after verifying 1.20.5. These checks do not certify licensed Bricks/Beaver Builder, SeedProd Pro, all WordPress plugins, or hosted provider services.

## 1.5.0 - 2026-10-20
- **Security:** Removed bearer-controlled MCP Apps approval decisions: read-only app panels cannot self-approve model mutations; only an authenticated WordPress administrator with nonce/capability checks can approve them.
- **Security:** Bound media-upload tickets to the current owner and live token hash, rejected legacy/unbound records and uncertain persistence, and prevented use after token revocation/rotation.
- **Integrity:** Rejected JSON-RPC tool calls without request IDs and malformed ID types before their handlers can execute side effects. Saved skills, approval states, tokens, and editable definitions gained further validation, one-time claim handling, and durable-state checks.
- **Skills:** Added meaningful descriptions/tags for all 67 bundled WordPress workflows, metadata-only lazy prompt discovery, corrected Abilities HTTP method guidance, and improved token-aware search with exact saved-skill priority and conservative offsite handling.
- **Quality:** Introduced an enforced 174-request routing benchmark (134 positive tasks across all 67 skills; 40 hard no-match cases), with additional held-out reference regressions for builder/plugin distinctions, named integrations, negation, privacy, and malformed metadata.
- **Verification:** PHP 7.4/8.2/8.4 source CI and PHP 8.2 release-archive checks now cover **53 core/legacy + 20 parity + 196 reference = 269 test cases**, the 174-request routing benchmark and focused Abilities raw-JSON test. Live disposable acceptance remains limited to WordPress 6.9 / WPVibe 1.20.3 / Elementor Core 4.3.4; other plugins and hosted providers are not claimed live-certified.
- **Upgrade note:** Query-string MCP token authentication is now **disabled by default**; header authentication is unchanged. Sites relying on `?token=` or `?access_token=` must explicitly enable query-token compatibility in authenticated WP admin settings or migrate their clients to Bearer / `X-WPVibe-Direct-Token` headers.

- Removed the MCP Apps runtime dependency on `esm.sh`. The inline approval and upload resources now use a small self-contained postMessage bridge for the MCP Apps initialize/tool-result/tool-call lifecycle, keep browser fallbacks, advertise no external script resource domain inside the approval boundary, preserve the SDK-equivalent 60-second request timeout, and mirror the official fit-content/deduplicated resize notifications.
- Changed URL query-token authentication to fail closed by default. Bearer and `X-WPVibe-Direct-Token` headers remain unchanged; site owners can explicitly enable query-token compatibility for clients that cannot send headers, with the admin UI and diagnostics exposing that state and both `?token=` / legacy `?access_token=` following the same opt-in.
- Added a centralized provider-output trust boundary for local and hosted/reference providers. Credential-shaped keys (including camel/leading-acronym and compact all-uppercase OAuth/API/access-key forms, prefixed auth/authorization, provider secret-key, arbitrary plural credential/secret/token/API-key collections, and recursively wrapped header/value/data/hash/PEM variants) are removed without keyword-redacting ordinary prose or safe count/status/public-key/boolean capability fields; string values and map keys, collections, depth, and total output are bounded with explicit `_wpvdmcp_provider_output` metadata; multibyte truncation preserves valid UTF-8; successful results and provider-created `WP_Error` envelopes are measured with the MCP server's actual serialization flags so pretty-print or Unicode expansion cannot bypass the wire ceiling; stdClass, `JsonSerializable`, native JSON-encodable internal-object, and filter-provider paths share the same sanitizer; fake provider-supplied sanitizer metadata is discarded; and the SeedProd-only ad-hoc redactor now uses the shared path.
- Added side-effect-free provider readiness metadata to provider-backed MCP tool definitions. Exact WPVibe public names stay visible when providers are absent; `configured` reflects callback/filter registration without executing providers or exposing credentials, required providers stay fail-closed, and `get_page_html` explicitly advertises its same-site native fallback.
- Throttled the admin-only `wpvdmcp_last_active` diagnostic timestamp to at most one database write per 60 seconds during successful authentication, while refreshing stale/missing values and healing future-skewed timestamps without changing authentication or request behavior.
- Clarified Direct MCP's single-site architecture: install it independently on each WordPress site; hosted site/account/fleet names remain external-provider-backed compatibility surfaces, and Direct MCP does not add a local remote-site registry, credential vault, or fleet control plane.
- Hardened Elementor page building against false-success designs: after the normal exact-payload approval is consumed, `elementor/build-composition` runs Elementor's own non-persistent `dry_run` compiler before the real write and rejects upstream warnings that would silently drop requested props/styles; explicit dry runs preserve validation warnings without claiming a write. `elementor/manage-elements` partial/error/warning outcomes are surfaced as actionable MCP errors, Elementor guidance now requires schema-first composition and fresh `resolved_xml` IDs, and page-settings guidance matches Elementor 4.3.4's partial-merge contract. The disposable Elementor lane now exercises a styled multi-section page, invalid-prop rejection with approval replay protection, targeted updates, partial-batch recovery, and rendered-content verification.
- Extended Elementor false-success protection to site-wide bulk styling: `elementor/manage-classes`, `elementor/manage-global-variable`, and `elementor/manage-default-styles` now surface partial/error/warning outcomes with explicit `partial_applied` state and the upstream per-operation response instead of reporting incomplete batches as success. The disposable Elementor lane now also exercises update/duplicate/move/delete element operations plus a mixed-success default-style batch and cleanup.
- Fixed Elementor 4.3.4 page-settings preservation in Direct MCP. The upstream `update-page-settings` ability describes partial-merge semantics but passes only the requested keys through `Document::save()`, replacing unrelated stored settings; after exact approval consumption, Direct now reads the latest raw Elementor page settings and recursively merges the approved partial change before dispatch. Disposable Elementor E2E now also covers reversible margin/padding partial updates, global-variable create/read/update/soft-delete, global-class create/read/update/delete through Elementor's own capability migration, and actual generated post-CSS regeneration/cache refresh.
- Extended the disposable Elementor 4.3.4 acceptance lane from global-style CRUD to real shared design-system behavior without changing production PHP. The lane now attaches one global class by label and one `var(--global-variable-label)` Atomic style to two pages through public abilities, verifies public structure persistence and rendered/generated CSS, updates each shared dependency once and proves both pages change without per-element rewrites, proves a published page-local Atomic rule wins by equal-specificity cascade order without leaking to the sibling page, verifies deletion cleanup removes an in-use class from both dependent structures, and retains native variable tombstone semantics. The disposable runner also forces direct filesystem access so Elementor can generate its Atomic CSS assets in the mounted test site.
- Completed a broad fail-closed code audit across Direct MCP state transitions. Approval request/approve/reject persistence, initial upload-ticket creation, MCP Apps approval-panel tokens, and destructive WP-CLI approval snapshots now abort when their transient state cannot be stored; saved-skill and editable field/group/setting mutations verify durable option state; and wp-admin token generation, revocation, endpoint enablement, and query-token compatibility use read-back/rollback or a verified disabled fallback instead of reporting partial writes as success.
- Hardened content/output integrity discovered by the audit. `save_validated_blocks` reparses the exact serialized Gutenberg content that will be written, rejects mismatched caller-provided trees and non-whitespace freeform markup in strict mode, optional PDF provider text is sanitized and Unicode-truncated safely, and `get_page_html` now enforces its `max_length` contract in Unicode characters without splitting multibyte output.
- Closed the remaining immutable-release asset overwrite path: if the verified version tag already has a published release, the release workflow now skips rebuilding/publishing it; `gh release upload --clobber` is removed, and a release that appears mid-run fails publication rather than replacing existing ZIP/checksum assets.

## 1.4.1 - 2026-10-12
- Closed the MCP Apps approval-boundary leak: MCP 2025-06-18 and newer now reject JSON-RPC batches before tool execution, while retained pre-2025-06-18 legacy batches sanitize app-only metadata per result so approval-panel tokens never remain in model-visible structured content or text.
- Made one-time Direct approvals concurrency-safe with an atomic claim before destructive execution. Competing consumers, stale/ambiguous claims, and consumed-state persistence failures fail closed instead of allowing duplicate execution.
- Hardened browser upload tickets with an atomic `waiting` → `processing` reservation before Media Library side effects, one-winner finalization, fail-closed persistence handling, and rollback of attachments created by partial or unfinalized multi-file attempts.
- Hardened release provenance: publication now targets only the current remote `main` SHA after both exact-SHA PHP parity and disposable WordPress/Elementor E2E gates complete successfully, and those gates are revalidated immediately before immutable tag creation.
- Blocked the Worker-proof-protected `/wpvibe/v1/code-snippet/dormant` route from generic REST alongside the approved snippet route. The public `code_snippet` tool remains available only through Direct's capability-preserving in-process safety bridge and is now classified correctly as a local equivalent in the parity manifest.
- Refreshed the live WPVibe Works-with-AI/Cookbook audit through 2026-10-12 and added dedicated Direct playbooks for Advanced Custom Fields 6.8+, Ultimate Addons for Elementor 2.9.5+, and Disable Comments 2.9+, including their current setup gates and destructive-operation safety notes.
- Release verification now contains 35 core/legacy + 14 parity + 70 reference cases = 119 suite cases on PHP 7.4, 8.2, and 8.4, plus the focused Abilities raw-JSON regression; the disposable WordPress 6.9 / WPVibe 1.20.3 / Elementor Core 4.3.4 lane remains the bounded live E2E gate.

## 1.4.0 - 2026-10-12
- Hardened generic `rest_api` / `rest_api_write` so audited WPVibe Worker/control-plane routes are rejected before dispatch or approval creation, while model-facing WPVibe routes remain available.
- Added a destructive WP-CLI handoff that preserves WPVibe's upstream `approval_required` dry-run/operation snapshot, binds a one-time Direct MCP browser approval to the exact command and snapshot, rejects drift/replay, and invokes the trusted in-process approved executor without exposing `/wpvibe/v1/cli/run-approved` or operation-proof secrets.
- Classified the classic-theme fresh-proof helper as infrastructure-only while retaining the draft compile snapshot route as model-facing, preserving the theme trust boundary.
- Added bounded `call_armored` retries for already-exposed tools so WAF-sensitive UTF-8/code payloads can be base64-armored without bypassing normal approvals, route restrictions, provider boundaries, or replay protection.
- Added a disposable runtime gate that compares the live installed WPVibe `/wpvibe/v1` REST registry with the parity manifest, normalizes parameterized routes, ignores only WordPress core's generated namespace index, and fails on unclassified operational routes.
- Added a Direct WPCode reviewed-write bridge because upstream protects both snippet REST routes with Worker operation proofs. Direct now reuses `WPVibe_Code_Snippet::handle()` in-process, preserves `wpcode_edit_snippets`, runs upstream dormant classification first, adds an exact-payload one-time browser approval only for `snippet_review_required`, and never exposes Worker proofs or raises snippet activation; human activation remains in wp-admin.
- Hardened release verification so the focused Abilities raw-JSON request-body regression runs against both the verified source checkout and the extracted release ZIP. Release 1.4.0 runs 27 legacy + 14 parity + 62 reference cases = 103 suite cases on PHP 7.4, 8.2, and 8.4, plus the focused Abilities regression.

## 1.3.3 - 2026-10-12
- Fixed WordPress Abilities POST execution to send the structured `input` as a real raw `application/json` request body, matching the WordPress 6.9 Abilities run-controller contract used by current Elementor abilities.
- Added a focused request-body regression to the PHP 7.4, 8.2, and 8.4 parity matrix while preserving readonly GET, destructive+idempotent DELETE, one-time approval binding, and replay rejection.
- Added a real disposable WordPress 6.9 / WPVibe 1.20.3 (`8f303926ae11179e38bc0ecf2a87e3cbd18984c6`) / Elementor Core 4.3.4 CI lane that exercises Direct MCP health/discovery, runtime Ability discovery, approval flow, draft page creation, Atomic composition, structure read, publish, rendered frontend verification, PHP-fatal checks, and Atomic-OFF fallback behavior.
- The Elementor Core E2E boundary is now verified in CI. Elementor Pro Theme Builder, broader global-style/element mutation coverage, other builders, provider-backed hosted/browser services, media edge cases, and production/client sites remain explicitly outside this release's verified E2E scope.

## 1.3.2 - 2026-10-11
- Hardened generic `rest_api` / `rest_api_write` mutations with one-time Direct MCP approvals, safe trash behavior for core content deletes, and WooCommerce protection against accidentally replacing complete product list fields with partial payloads.
- Matched the current WPVibe public REST option contract while preserving Direct aliases: `route`/`path`, `query`/`params`, string-or-array `fields`, `max_length`/`max_response_bytes`, and embedded-query normalization.
- Expanded WordPress Abilities compatibility with `ability_name`/`name`, `input_json`/structured input, metadata-first annotations with bounded fallback hints, and `op_id`/`approval_id` compatibility without weakening approval replay protection.
- Matched current provider-backed public contracts for image search, page audits, rendered HTML, site connection, and fleet jobs while continuing to fail closed when no user-owned provider is configured.
- Matched current Saved Skill lookup contracts with `name`, `query`, and `resource` forms, no-argument catalog metadata, bounded metadata search, and exact saved-reference reads.
- Re-verified the complete current tree against official WPVibe 1.20.3 (`8f303926ae11179e38bc0ecf2a87e3cbd18984c6`) and the current WPVibe docs/Elementor workflow. Automated CI now runs 27 legacy + 14 parity + 53 reference cases = 94 tests on PHP 7.4, 8.2, and 8.4.
- Real WordPress/Elementor destructive E2E remains an explicit disposable-staging acceptance boundary and is not falsely marked as passed.

## 1.3.1 - 2026-10-10
- Fixed WordPress Abilities REST method selection: readonly abilities use `GET`, destructive+idempotent abilities use `DELETE`, and every other mutation (including destructive non-idempotent abilities) uses `POST`.
- Added regression coverage for both idempotent and non-idempotent destructive abilities so Elementor/plugin mutations cannot silently regress to the wrong HTTP method.
- Re-audited the current WPVibe website/docs and official WPVibe 1.20.3 source; public tool-name coverage and the model-facing/internal REST route classification remain complete for that upstream commit.
- Expanded the Elementor 4.3+/WordPress 6.9+ playbooks to cover the current Abilities-first page-development workflow, Atomic Editor capability boundaries, whole-object settings writes, global classes/variables/default styles, preview/publish/live verification, native Elementor fallback, and Elementor Pro Theme Builder reuse.
- Hardened release automation so patch releases derive their version/tag/archive from plugin metadata instead of attempting to reuse a previous immutable tag.

## 1.3.0 - 2026-10-10
- Re-audited the live WPVibe Features page, Tools Reference, current Works-with-AI library, and official WPVibe 1.20.3 GitHub source from zero assumptions.
- Added every missing audited public/hosted tool name: `audit_page`, `rest_api_write`, `save_skill`, `connect_site`, `list_sites`, `remove_site`, `get_profile`, `start_fleet_job`, `show_fleet_dashboard`, and `use_usage_reset`.
- Added fail-closed user-owned provider adapters for hosted account/fleet/usage tools instead of fabricating WPVibe cloud state.
- Added native MCP Apps inline approval and upload panels (`ui://` resources, `text/html;profile=mcp-app`) with opaque one-use approval tokens and browser fallbacks.
- Added JavaScript browser preference for `get_page_html`, provider-backed screenshots, an approval-gated SeedProd compile flow, and exact `rest_api_write` schema hardening.
- Extended Saved Skills with descriptions and bounded text reference files.
- Added a machine-readable reference parity manifest covering audited tools, MCP options, the current Works-with-AI/Cookbook integration set, Direct extensions, and every upstream REST route classified as model-facing or deliberately internal.
- Expanded current skill coverage for Elementor 4.3, Divi/Divi 5, SeedProd, the live Works-with-AI library, and every current cookbook plugin/page-builder/theme filter, including FluentCart, FluentCommunity, FluentCRM, Merchant, WooCommerce, WPCode, Sugar Calendar, OptinMonster, Botiga, and the current builder/theme surfaces; also retained a generic Abilities-first plugin workflow for future compliant plugins.
- Expanded parity tests to cover exact public tool names, provider boundaries, MCP Apps, inline approval replay protection, screenshots, Saved Skill references, internal routes, current plugin skills, and release metadata.

## 1.2.0 - 2026-10-09
- Added persistent WPVibe editable fields, groups, and settings with 13 upstream field types, safe setting-type restrictions, native registry replay, and one-time browser approvals.
- Added recursive Gutenberg schema validation plus approval-gated `save_validated_blocks`, covering installed core, Kadence, GenerateBlocks, and other registered blocks.
- Added persistent local saved-skill CRUD with immutable built-ins, size/count limits, versions, and approval-gated mutations.
- Added safe Media Library inspection and bounded optional PDF text extraction through a user-owned provider/filter without exposing filesystem paths.
- Added site intelligence, integration detection, WPVibe live-reload capability reporting, and read-only SEO auditing.
- Added provider-backed `page_audit`, `search_images`, and `render_browser` tools so self-hosted operators can supply Lighthouse, stock-image, and JS-browser services without embedding WPVibe private cloud credentials.
- Added Kadence, GeneratePress, GenerateBlocks, editable-fields, block-validation, saved-skills, PDF/media, site-intelligence, live-reload, Lighthouse, and image-search skills.
- Expanded parity tests from 26 to 40 cases and kept hosted account/billing/fleet/private-cloud features explicitly outside the single-site bridge.

## 1.1.0 - 2026-10-09
- Audited against WPVibe 1.20.3 at upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`.
- Added modern MCP `2026-07-28` discovery/version handling while retaining legacy initialize compatibility.
- Added first-class WordPress Abilities discovery/info/run with annotation-aware methods and browser approvals for writes.
- Added current WPVibe file/theme schemas, operation receipts, WPCode dormant snippets, and native Elementor, Beaver Builder, Bricks, and Breakdance tools.
- Added secure `request_upload` / `check_upload` browser transfer for device/chat attachments with expiring one-time hashed tickets and WPVibe SVG sanitization.
- Hardened generic REST access, added response limits/field selection, and kept credential/authentication routes blocked.
- Added a file-backed 25-skill catalog, expanded wp-admin compatibility/status UI, and corrected plugin repository metadata.
- Added dependency-free protocol/security/capability tests and compatibility/security documentation.

## 1.0.4
- Fixed Streamable HTTP notification handling: accepted JSON-RPC notifications now return HTTP 202 Accepted with no body, as required by MCP.
- Fixed notification-only JSON-RPC batches to return HTTP 202 instead of 204.
- This specifically fixes the ChatGPT scan sequence after `initialize` and `notifications/initialized`.

## 1.0.3
- Fixed Streamable HTTP compliance: MCP `GET` now returns HTTP 405 when standalone SSE is unsupported.
- Added authenticated `/wp-json/wpvibe-direct/v1/health` diagnostics endpoint.
- Added no-cache transport headers and improved OPTIONS handling.

## 1.0.2
- Always accept a valid `?token=` query parameter for ChatGPT No Auth mode.
- Removed the unreliable URL-token enable/disable option.
- Kept bearer-header and `X-WPVibe-Direct-Token` authentication support.
- Improved missing-token guidance.

## 1.0.1

- Fixed query-string token parsing for MCP clients that send JSON parameters with overlapping names.
- Added precise errors for disabled URL authentication, missing tokens, and outdated tokens.
- Added the ChatGPT No Auth URL format to the admin screen.

## 1.0.0

- Added direct self-hosted MCP JSON-RPC endpoint.
- Added bearer-token management tied to a WordPress administrator.
- Exposed available WPVibe file, theme, content, WP-CLI, media, HTML, navigation, audit, and Elementor routes as MCP tools.
- Added generic capability-checked WordPress REST access and route discovery.
- Added eight original WordPress workflow skills and MCP prompts.
- Added local direct-MCP activity logging without storing request bodies or file contents.
