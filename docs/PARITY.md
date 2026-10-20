# WPVibe Reference Parity Matrix

Audit date: **2026-10-20** (unreleased upstream compatibility review). Direct MCP release target: **1.5.0** (published and immutable). Previous WordPress-side live baseline: WPVibe **1.20.3**, upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`. New upstream comparison: WPVibe **1.20.5**, commit `62bc77ccd1c15faf766f527a7935fe32f1b227d3`.

The unreleased source's machine-readable `reference_parity_manifest` now pins WPVibe 1.20.5 after independent upstream source review and the two-version disposable WordPress/Elementor E2E matrix passed (including a live WPCode Lite retirement test on the 1.20.5 lane). Historical v1.5.0 release assets remain unchanged. This validates the bounded native paths; other optional plugin skills and external providers remain outside live E2E.

This audit compares the live WPVibe Features page, public Tools Reference, current Works-with-AI/Cookbook guidance, and the official WordPress-side source. “Covered” never means a fabricated cloud response: hosted account/fleet state and external browser/audit/image infrastructure remain user-provider-backed and fail closed when no provider is configured.

Provider-backed parity is also bounded on output in release 1.5.0: local and hosted/reference provider callbacks share one recursive sanitizer for credential-key redaction plus string/collection/depth/total-size limits, including provider `WP_Error` data. Sanitizer metadata is explicit when a result is changed; this is a Direct safety boundary, not evidence that the underlying hosted WPVibe infrastructure has been cloned.

Provider-backed tool definitions also carry non-secret current registration metadata in release 1.5.0. Required external capabilities remain listed for exact-name parity with `configured: false` when absent; `get_page_html` advertises its optional browser provider plus same-site native fallback. Discovery never invokes the provider while computing this metadata.

Release 1.4.1 packages the post-1.4.0 security hardening and the 2026-10-12 Works-with-AI playbook refresh while preserving the immutable history of earlier releases.

## Public MCP tool-name parity

| Reference tool | Direct MCP 1.5.0 + unreleased compatibility work |
|---|---|
| `connect_site` | External-provider-backed exact name; current auth options normalized; no Direct local registry is created and no fake WPVibe cloud registry is synthesized |
| `list_sites` | External-provider-backed exact name; Direct does not fabricate the current site as a one-item hosted registry |
| `site_info` | Native WPVibe route |
| `remove_site` | External-provider-backed exact name; Direct has no local remote-site record to remove |
| `rest_api` | Hardened REST bridge with current WPVibe/Direct option aliases, approval-gated mutations, and explicit Worker/control-plane route blocks |
| `rest_api_write` | Write-only hardened alias with the same normalized query/body/approval/control-plane contract |
| `upload_media` | Native WPVibe route |
| `request_upload`, `check_upload` | Local one-time media ticket + inline MCP App/browser fallback |
| `search_images` | User-owned provider; current count/orientation and Direct `per_page` forms normalized |
| `discover_abilities`, `get_ability_info`, `run_ability` | WordPress Abilities API; current name/input aliases, WordPress-compatible method selection, and core-compatible raw JSON body for POST mutations |
| draft/theme/file tools | Native WPVibe routes, current 1.20.3 contracts, with fresh-proof helpers kept behind their classified trust boundary |
| `run_wp_cli` | WPVibe PHP dispatcher; destructive `approval_required` responses become exact-snapshot-bound one-time Direct approvals, then execute through WPVibe's trusted in-process approved path without exposing Worker proof state |
| `get_page_html` | Current URL/selector/max-length contract; JS browser provider preferred, safe same-site native fallback |
| `navigate` | Native WPVibe route |
| `load_skill` | Built-in + saved catalog; Direct and current WPVibe `name/query/resource` lookup forms |
| `save_skill` | Exact alias to approval-gated local skill create/update |
| `code_snippet` | Direct safety bridge over upstream `WPVibe_Code_Snippet::handle()`: preserves `wpcode_edit_snippets`, runs dormant classification first, requires exact-payload one-time Direct approval only for upstream `snippet_review_required`, keeps Worker proofs hidden, and never raises activation |
| `show_approval_panel`, `check_approval_status` | Native Direct approval state + MCP App/browser UI; `approval_id`/`op_id` compatibility |
| `audit_page` | User-owned Lighthouse/PageSpeed provider with current page URL/category/refresh options |
| `get_profile` | Provider-backed exact hosted name |
| `start_fleet_job`, `show_fleet_dashboard` | Provider-backed exact hosted names with current sites/steps/confirm/idempotency forms |
| `use_usage_reset` | Provider-backed exact hosted name |

`reference_parity_manifest` returns the machine-readable audited list and implementation class for every reference tool. The disposable E2E compares the live installed WPVibe REST registry against that classification so a new operational `/wpvibe/v1` route cannot silently remain unclassified.

## Contract and safety parity

- Generic REST writes are one-time approval-gated unless the site owner explicitly enables WPVibe's bypass setting; approval payloads bind the normalized route/query/body so a token cannot authorize a changed request.
- Generic `rest_api` / `rest_api_write` reject audited WPVibe Worker/control-plane destinations before dispatch and before approval creation. This is an explicit route classification rather than a blanket `/wpvibe/v1` block, so legitimate public model-facing routes remain available.
- Core WordPress content deletes are kept on the recoverable trash path rather than forwarding a generic `force=true` hard-delete request.
- WooCommerce product and batch updates reject partial payloads that would accidentally replace complete protected list fields unless replacement is explicit.
- Public REST accepts current WPVibe option names and Direct compatibility aliases: `route`/`path`, `query`/`params`, string-or-array `fields`, `max_length`/`max_response_bytes`, and embedded query strings with explicit query values taking precedence.
- Abilities accept `ability_name`/`name` and `input_json`/structured `input`; fetched ability metadata is authoritative, with fallback hints used only when metadata cannot be read.
- WordPress 6.9+ POST Ability execution uses an actual `application/json` raw body matching the core Abilities run-controller contract; readonly GET and destructive+idempotent DELETE retain query-param execution.
- Destructive `run_wp_cli` preserves the exact upstream `operation` and `dry_run` snapshot, binds that state plus the exact command to a one-time Direct browser approval, rejects payload drift/replay, and invokes WPVibe's in-process approved executor only after authorization. The Worker-only `/wpvibe/v1/cli/run-approved` route and operation-proof secrets remain model-inaccessible.
- Both upstream WPCode snippet REST routes are Worker-proof-protected, so Direct does not dispatch either one or mint a proof. It re-checks `wpcode_edit_snippets`, invokes the same upstream handler in-process with dormant semantics first, and adds a payload-bound one-time Direct approval only when that classifier returns `snippet_review_required`. Model-supplied activation is discarded and activation remains under WPCode's human wp-admin flow.
- `load_skill` supports current `name`, `query`, and `resource` forms, no-argument bounded catalog metadata, metadata-only search, and exact bounded saved-reference reads.
- Provider-backed public tools normalize the current documented options but still fail closed without user-owned infrastructure; no private WPVibe cloud credentials or account data are copied.
- MCP discovery decorates provider-backed tools with non-secret registration metadata without executing callbacks/filters. Exact names remain visible; absence is signaled rather than hidden, and `get_page_html` identifies its same-site native fallback.
- `call_armored` is a bounded Direct-only transport retry for already-exposed tools. Base64 decoding does not bypass the target tool's schema, approval, route, provider, or replay protections; self/approval-internal/unexposed targets and invalid/oversized payloads fail closed.
- MCP protocol revisions that removed JSON-RPC batching (2025-06-18 and newer, including 2026-07-28) reject batches before tool execution. Retained pre-2025-06-18 legacy batches sanitize tool-result metadata. Current approval panels never mint an approval token in MCP response metadata; they show the wp-admin review URL instead.
- Direct approval consumption uses an atomic claim before a protected operation may execute. Competing consumers and uncertain persistence states fail closed rather than allowing duplicate destructive execution.
- Pending approval creation and approve/reject transitions also verify transient persistence before reporting state, so the full approval lifecycle now fails closed under storage uncertainty rather than only the consume step.
- Browser media tickets are bound to the issuing MCP token owner and credential hash, so revocation, rotation, or owner replacement invalidates their browser upload authorization. They atomically reserve `waiting` → `processing` before attachment creation, verify transient read-back before reporting reservation or finalization success, permit exactly one finalizer, and roll back attachments if a multi-file attempt or final persistence step fails. Tickets issued by older plugin versions without the credential binding fail closed and must be recreated.
- Browser upload ticket creation and destructive WP-CLI snapshot handoff verify their transient state before returning a usable URL/token/approval. MCP Apps approval review is read-only: `approval_decide` cannot authorize a write using MCP bearer credentials, and the browser-only wp-admin action retains nonce and administrator checks.
- wp-admin token generation verifies the token-hash/owner pair and one-time token-display state transactionally, query-token/endpoint settings roll back on partial writes, and revoke verifies a disabled credential state before reporting success.

## Features-page parity

- **Full REST API:** covered through `rest_api` / `rest_api_write` with credential/control-plane route blocking, bounded responses, mutation approvals, and current public option compatibility.
- **WP-CLI:** delegated to the installed WPVibe command dispatcher; `wp_cli_status` is the runtime source of truth because advertised command counts can differ across pages/releases. Direct MCP can safely complete upstream-classified destructive commands through the one-time exact-snapshot approval bridge described above.
- **Theme Builder + Draft/Preview/Publish:** covered with classic-theme scaffold, isolated draft, preview, publish/delete and current Site Editor customization handling. The fresh classic-theme proof helper remains infrastructure-only; the draft compile snapshot is explicitly model-safe.
- **Site Intelligence:** environment/integration diagnostics plus upstream site-health/performance data when present; browser-rendered HTML when a provider exists.
- **Lighthouse:** `audit_page`/`page_audit` via user-owned provider.
- **Plugin Abilities:** discover/info/run with readonly GET, destructive+idempotent DELETE, all other mutations POST; writes remain approval-gated.
- **Images & Media:** native URL import, one-time device/chat upload, SVG sanitizer path, stock search provider, safe Media Library/PDF inspection, and Unicode-safe provider/rendered-text bounding.
- **Live Reload:** native `/wpvibe/v1/last-change` status/change lookup and navigation; builder edit sessions remain protected upstream.
- **Saved Skills:** local CRUD, versions, descriptions/reference files, plus current lookup/search/resource semantics.
- **Interactive Panels:** MCP Apps approval/upload `ui://` resources plus secure browser fallbacks.
- **Validated Block Output:** recursive installed-block schema validation before `save_validated_blocks` mutations; the save path reparses the exact serialized content, rejects a separate parsed-tree/content mismatch, and strict mode rejects non-whitespace freeform markup outside registered block schemas.
- **Bulk Operations & SQL:** installed WPVibe WP-CLI dispatcher, including upstream destructive classification, approval/dry-run semantics and read-only SQL rules.
- **Safe Code Snippets:** WPVibe dormant classifier + Direct reviewed-write approval bridge; Direct never raises activation and human WPCode activation remains authoritative.
- **Every Site You Manage:** exact account/fleet tool names are available only through an external provider/hosted integration; the native Direct deployment model is one independent installation per WordPress site, and Direct MCP does not clone WPVibe’s private cloud registry.
- **Editable Fields:** persistent field/group/setting declarations replay through WPVibe’s native field registry, covering all 13 upstream post-field types and native meta/settings UI/REST exposure.

## Builder/workflow parity

- **Elementor:** current Elementor 4.3+/WordPress 6.9+ Abilities-first workflow plus native WPVibe fallback. Current unreleased Direct MCP strictly preflights `elementor/build-composition` with Elementor's own `dry_run` contract so unsupported props/styles that upstream would otherwise skip with warnings cannot be reported as a successful design. Elementor bulk mutations that can partially apply (`manage-elements`, `manage-classes`, `manage-global-variable`, and `manage-default-styles`) surface partial/error/warning outcomes as actionable MCP errors with `partial_applied` and the upstream per-operation response instead of false success. Elementor 4.3.4 advertises `update-page-settings` as a partial merge but its ability implementation calls `Document::save()` with only the supplied settings, replacing unrelated stored values; Direct compensates **after exact approval consumption and before dispatch** by reading the latest raw `_elementor_page_settings` through Elementor's public document API and applying the same recursive merge used by Elementor's own `Document::update_settings()`. The disposable CI lane verifies WordPress 6.9 + exact WPVibe 1.20.3 upstream SHA + Elementor Core 4.3.4 through Direct MCP HTTP for live widget-schema discovery, rejected lossy composition, a styled responsive multi-section Atomic build with no warnings, fresh `resolved_xml` IDs, structure/content/style re-read, update/duplicate/move/delete element operations, deliberate element partial-batch detection and repair, partial page-settings preservation, one disposable default-style partial-batch/cleanup flow, and a real two-page global-style dependency flow. In that flow Direct applies the same global class by **label** plus `var(--<global-variable-label>)` Atomic CSS to both pages, proves public structure persistence, publishes/render-verifies both pages, updates the class and variable once without per-page rewrites, proves both generated CSS bundles change, proves a page-local Atomic color override wins without leaking to the other page, and verifies Elementor removes an in-use class from both dependent structures while variable deletion remains tombstone-based. The lane also verifies generated post-CSS regeneration after a page-setting mutation, publish/live frontend output, no PHP fatals, Atomic-OFF failure/native-fallback discovery, and live WPVibe route classification. The playbook additionally covers preview links, broader Atomic Editor capability boundaries, and Elementor Pro Theme Builder reuse through discovered Pro abilities or `elementor_save_template`; broader production-scale full-site and Pro paths remain unverified unless explicitly listed in the E2E record. Direct MCP does not guess private Elementor serialization when a supported API is unavailable.
- **Gutenberg:** installed block schemas + validated save path.
- **SeedProd:** Abilities/public data writes plus approval-gated browser compile using upstream one-time builder login; raw login URL is never returned to the model.
- **Divi / Divi 5:** surgical content edits trigger upstream Divi refresh hooks; Theme Builder linkage uses audited multi-value `post meta add/delete` rather than destructive row replacement.
- **Beaver Builder, Bricks, Breakdance:** native upstream save/read/schema routes when installed.
- **Kadence, GeneratePress/GP Premium, GenerateBlocks, WPBakery and Classic Themes:** dedicated playbooks using public/native structures rather than guessed private serialization.
- SEO audit, design, site setup and editable-fields playbooks remain available.

## Current Works-with-AI playbooks

Direct MCP ships explicit playbooks for Rank Math, AIOSEO, SEOPress, Yoast SEO, Smash Balloon, MemberPress, Charitable, Duplicator, PushEngage, Easy Digital Downloads, LifterLMS, WPForms, Kit/ConvertKit, Modern Cart, CartFlows, Pagelayer, ElementsKit, Amelia, AdTribes Product Feed, FluentCart, FluentCommunity, FluentCRM, Merchant, WooCommerce, WPCode, Sugar Calendar, OptinMonster, Botiga, Elementor, Beaver Builder, Bricks, Breakdance, Divi/Divi 5, SeedProd, GeneratePress, GenerateBlocks, Kadence, Advanced Custom Fields, Ultimate Addons for Elementor, and Disable Comments. `abilities-plugin` remains the generic Abilities-first fallback for newly compliant plugins; capabilities are discovered rather than inferred from private storage.

The 2026-10-12 live Works-with-AI/Cookbook audit added dedicated playbooks for **Advanced Custom Fields 6.8.10**, **Ultimate Addons for Elementor 2.9.5**, and **Disable Comments 2.9.0**. They use the existing WordPress Abilities execution surface and add the setup/safety context generic discovery alone does not provide: ACF's human-enabled `acf/settings/enable_acf_ai` WPCode filter plus per-object AI access and REST exposure, UAE's `Enable Abilities` / `Enable Edit Abilities` settings and classic-theme boundary, and Disable Comments' multisite/WooCommerce/replace-all/permanent-delete safeguards. These playbooks are reference-tested and source-audited; release 1.4.1 does not claim live plugin E2E for ACF, UAE, or Disable Comments.

## MCP protocol/options parity

- Modern `2026-07-28` `server/discover`, routing headers and cache hints; JSON-RPC batches are rejected for 2025-06-18+ revisions where batching is no longer supported.
- Legacy 2025/2024 initialize/ping compatibility.
- Retained pre-2025-06-18 batch compatibility sanitizes MCP Apps metadata independently for every returned batch result.
- Tools, prompts and resources primitives.
- MCP Apps extension with `text/html;profile=mcp-app` resources/read.
- Stateless Streamable-HTTP JSON; no standalone SSE/session store.
- Bearer and `X-WPVibe-Direct-Token` authentication, plus query-token compatibility only after explicit site-owner opt-in on current unreleased `main` (`v1.4.1` predates this default-off hardening).

## Upstream routes intentionally kept internal

These are infrastructure/control-plane primitives, not model tools: `/wpvibe/v1/ping`, `/health`, `/connection-check-challenge`, `/audit-log/record`, `/cli/run-approved`, `/authorize`, `/authorize/preflight`, `/connection-status`, `/op-proof/check`, `/code-snippet` approved path, `/builder-login`, `/detached/run`, `/self-update/health`, `/self-update/run`, and `/classic-theme-safety`. Higher-level safe tools may consume trusted in-process equivalents where that preserves upstream semantics, but generic REST cannot reach these endpoints. The related `/wpvibe/v1/code-snippet/dormant` route remains the upstream dormant-classification capability marker, but Direct executes the trusted snippet handler in-process because that REST route itself also requires Worker proof. The related `/wpvibe/v1/draft-theme/compile-sources` snapshot route is explicitly classified model-facing.

## Direct extensions beyond the public named surface

`site_intelligence`, `live_reload_status`, `get_last_change`, `integration_capabilities`, `inspect_media`, `seo_audit`, `validate_blocks`, `save_validated_blocks`, editable-field registration tools, saved-skill CRUD, `seedprod_compile_page`, `screenshot_page`, `render_browser`, `page_audit`, `wp_cli_status`, `check_operation_receipt`, `call_armored`, and `reference_parity_manifest` provide safe local equivalents, transport helpers, or diagnostics without pretending they are separately named WPVibe hosted tools.

## Validation boundary

Release **1.4.1** runs **119 suite cases (35 core/legacy + 14 parity + 70 reference)** on PHP 7.4, 8.2 and 8.4 plus the focused Abilities raw-JSON request-body regression. Current unreleased source runs **175 suite cases (48 core/legacy + 20 parity + 107 reference)** plus the focused regression. The release workflow repeats the full PHP suites and focused regression against the extracted release ZIP before publication, publication is gated to the current `main` SHA after both exact-SHA PHP parity and disposable WordPress/Elementor E2E succeed, and an already published verified tag/release is a no-op rather than an asset replacement path.

The WPCode bridge is reference/unit verified for dormant-first classification, approval binding, drift/replay rejection, bypass ordering, capability preservation, and ignored activation input. WPCode is not installed in the disposable Elementor lane, so no live WPCode E2E claim is made. The disposable lane verifies the live WPVibe REST route-classification inventory together with the bounded Elementor Core acceptance.

The live lane remains intentionally bounded: it proves the listed schema-first Elementor Core multi-section composition/update/duplicate/move/delete/recovery flow, reversible partial page-settings preservation, shared global-class/global-variable application and one-write propagation across two disposable Atomic pages, local-over-global cascade behavior, in-use global-class cleanup, native global-variable tombstones, one disposable default-style partial-batch/cleanup path, generated Elementor CSS regeneration, and runtime route inventory on WordPress 6.9 / WPVibe 1.20.3 exact SHA / Elementor 4.3.4. This does not imply Elementor Pro, every global-class/global-variable/default-style/page-settings path, a production-scale full-site rebrand, pixel-perfect visual equivalence, WPCode runtime behavior, ACF/UAE/Disable Comments runtime behavior, media/network edge cases, provider-backed browser/audit/image services, other builders, or production/client sites were exercised.
