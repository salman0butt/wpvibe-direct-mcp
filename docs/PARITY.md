# WPVibe Reference Parity Matrix

Audit date: **2026-10-12**. Latest published Direct MCP release: **1.3.3**. Current development state: **post-1.3.3 `main`**. Official WordPress-side baseline: WPVibe **1.20.3**, upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`.

This audit compares the live WPVibe Features page, public Tools Reference, current Works-with-AI/Cookbook guidance, and the official WordPress-side source. “Covered” never means a fabricated cloud response: hosted account/fleet state and external browser/audit/image infrastructure remain user-provider-backed and fail closed when no provider is configured.

The immutable 1.3.3 release remains the published baseline. Sections that describe current `main` include additional merged security/runtime hardening that has not been republished as 1.3.3.

## Public MCP tool-name parity

| Reference tool | Direct MCP current tree |
|---|---|
| `connect_site` | Provider-backed exact name; current auth options normalized; no fake WPVibe cloud registry |
| `list_sites` | Provider-backed exact name |
| `site_info` | Native WPVibe route |
| `remove_site` | Provider-backed exact name |
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
| `code_snippet` | Native dormant WPCode route; no silent activation |
| `show_approval_panel`, `check_approval_status` | Native Direct approval state + MCP App/browser UI; `approval_id`/`op_id` compatibility |
| `audit_page` | User-owned Lighthouse/PageSpeed provider with current page URL/category/refresh options |
| `get_profile` | Provider-backed exact hosted name |
| `start_fleet_job`, `show_fleet_dashboard` | Provider-backed exact hosted names with current sites/steps/confirm/idempotency forms |
| `use_usage_reset` | Provider-backed exact hosted name |

`reference_parity_manifest` returns the machine-readable audited list and implementation class for every reference tool. Current disposable E2E also compares the live installed WPVibe REST registry against that classification so a new operational `/wpvibe/v1` route cannot silently remain unclassified.

## Contract and safety parity

- Generic REST writes are one-time approval-gated unless the site owner explicitly enables WPVibe's bypass setting; approval payloads bind the normalized route/query/body so a token cannot authorize a changed request.
- Generic `rest_api` / `rest_api_write` reject audited WPVibe Worker/control-plane destinations before dispatch and before approval creation. This is an explicit route classification rather than a blanket `/wpvibe/v1` block, so legitimate public model-facing routes remain available.
- Core WordPress content deletes are kept on the recoverable trash path rather than forwarding a generic `force=true` hard-delete request.
- WooCommerce product and batch updates reject partial payloads that would accidentally replace complete protected list fields unless replacement is explicit.
- Public REST accepts current WPVibe option names and Direct compatibility aliases: `route`/`path`, `query`/`params`, string-or-array `fields`, `max_length`/`max_response_bytes`, and embedded query strings with explicit query values taking precedence.
- Abilities accept `ability_name`/`name` and `input_json`/structured `input`; fetched ability metadata is authoritative, with fallback hints used only when metadata cannot be read.
- WordPress 6.9+ POST Ability execution uses an actual `application/json` raw body matching the core Abilities run-controller contract; readonly GET and destructive+idempotent DELETE retain query-param execution.
- Destructive `run_wp_cli` preserves the exact upstream `operation` and `dry_run` snapshot, binds that state plus the exact command to a one-time Direct browser approval, rejects payload drift/replay, and invokes WPVibe's in-process approved executor only after authorization. The Worker-only `/wpvibe/v1/cli/run-approved` route and operation-proof secrets remain model-inaccessible.
- `load_skill` supports current `name`, `query`, and `resource` forms, no-argument bounded catalog metadata, metadata-only search, and exact bounded saved-reference reads.
- Provider-backed public tools normalize the current documented options but still fail closed without user-owned infrastructure; no private WPVibe cloud credentials or account data are copied.
- `call_armored` is a bounded Direct-only transport retry for already-exposed tools. Base64 decoding does not bypass the target tool's schema, approval, route, provider, or replay protections; self/approval-internal/unexposed targets and invalid/oversized payloads fail closed.

## Features-page parity

- **Full REST API:** covered through `rest_api` / `rest_api_write` with credential/control-plane route blocking, bounded responses, mutation approvals, and current public option compatibility.
- **WP-CLI:** delegated to the installed WPVibe command dispatcher; `wp_cli_status` is the runtime source of truth because advertised command counts can differ across pages/releases. Current Direct MCP can safely complete upstream-classified destructive commands through the one-time exact-snapshot approval bridge described above.
- **Theme Builder + Draft/Preview/Publish:** covered with classic-theme scaffold, isolated draft, preview, publish/delete and current Site Editor customization handling. The fresh classic-theme proof helper remains infrastructure-only; the draft compile snapshot is explicitly model-safe.
- **Site Intelligence:** environment/integration diagnostics plus upstream site-health/performance data when present; browser-rendered HTML when a provider exists.
- **Lighthouse:** `audit_page`/`page_audit` via user-owned provider.
- **Plugin Abilities:** discover/info/run with readonly GET, destructive+idempotent DELETE, all other mutations POST; writes remain approval-gated.
- **Images & Media:** native URL import, one-time device/chat upload, SVG sanitizer path, stock search provider, safe Media Library/PDF inspection.
- **Live Reload:** native `/wpvibe/v1/last-change` status/change lookup and navigation; builder edit sessions remain protected upstream.
- **Saved Skills:** local CRUD, versions, descriptions/reference files, plus current lookup/search/resource semantics.
- **Interactive Panels:** MCP Apps approval/upload `ui://` resources plus secure browser fallbacks.
- **Validated Block Output:** recursive installed-block schema validation before `save_validated_blocks` mutations.
- **Bulk Operations & SQL:** installed WPVibe WP-CLI dispatcher, including upstream destructive classification, approval/dry-run semantics and read-only SQL rules.
- **Safe Code Snippets:** dormant WPCode creation/update only; activation stays human/upstream-approved.
- **Every Site You Manage:** exact account/fleet tool names are provider-backed; Direct MCP does not clone WPVibe’s private cloud registry.
- **Editable Fields:** persistent field/group/setting declarations replay through WPVibe’s native field registry, covering all 13 upstream post-field types and native meta/settings UI/REST exposure.

## Builder/workflow parity

- **Elementor:** current Elementor 4.3+/WordPress 6.9+ Abilities-first workflow plus native WPVibe fallback. The disposable CI lane verifies WordPress 6.9 + exact WPVibe 1.20.3 upstream SHA + Elementor Core 4.3.4 through Direct MCP HTTP for discovery, method annotations, one-time approvals/replay rejection, draft page creation, Atomic composition, structure read, publish, live frontend output, no PHP fatals, and Atomic-OFF failure/native-fallback discovery. Current `main` additionally verifies that every live WPVibe operational REST route is classified in the parity manifest. The playbook additionally covers page/element operations, widget schemas/resources, global variables/classes/default styles, full-object page settings, preview links, publish/live verification, Atomic Editor capability boundaries, and Elementor Pro Theme Builder reuse through discovered Pro abilities or `elementor_save_template`; those broader/Pro paths remain unverified unless explicitly listed in the E2E record. Direct MCP does not guess private Elementor serialization when a supported API is unavailable.
- **Gutenberg:** installed block schemas + validated save path.
- **SeedProd:** Abilities/public data writes plus approval-gated browser compile using upstream one-time builder login; raw login URL is never returned to the model.
- **Divi / Divi 5:** surgical content edits trigger upstream Divi refresh hooks; Theme Builder linkage uses audited multi-value `post meta add/delete` rather than destructive row replacement.
- **Beaver Builder, Bricks, Breakdance:** native upstream save/read/schema routes when installed.
- **Kadence, GeneratePress/GP Premium, GenerateBlocks, WPBakery and Classic Themes:** dedicated playbooks using public/native structures rather than guessed private serialization.
- SEO audit, design, site setup and editable-fields playbooks remain available.

## Current Works-with-AI playbooks

Direct MCP ships explicit playbooks for Rank Math, AIOSEO, SEOPress, Yoast SEO, Smash Balloon, MemberPress, Charitable, Duplicator, PushEngage, Easy Digital Downloads, LifterLMS, WPForms, Kit/ConvertKit, Modern Cart, CartFlows, Pagelayer, ElementsKit, Amelia, AdTribes Product Feed, FluentCart, FluentCommunity, FluentCRM, Merchant, WooCommerce, WPCode, Sugar Calendar, OptinMonster, Botiga, Elementor, Beaver Builder, Bricks, Breakdance, Divi/Divi 5, SeedProd, GeneratePress, GenerateBlocks, and Kadence. `abilities-plugin` remains the generic Abilities-first fallback for newly compliant plugins; capabilities are discovered rather than inferred from private storage.

A fresh 2026-10-11 website audit also found newly documented WPVibe Works-with-AI coverage for **Ultimate Addons for Elementor 2.9.5** and **Advanced Custom Fields 6.8.10**. The generic Abilities bridge can discover and run compliant abilities when those plugins expose them, but dedicated Direct MCP playbooks/live plugin E2E for those newly documented integrations are a separate parity milestone and are not claimed complete by the Elementor Core E2E lane.

## MCP protocol/options parity

- Modern `2026-07-28` `server/discover`, routing headers and cache hints.
- Legacy 2025/2024 initialize/ping compatibility.
- Tools, prompts and resources primitives.
- MCP Apps extension with `text/html;profile=mcp-app` resources/read.
- Stateless Streamable-HTTP JSON; no standalone SSE/session store.
- Bearer, `X-WPVibe-Direct-Token`, and query-token compatibility authentication.

## Upstream routes intentionally kept internal

These are infrastructure/control-plane primitives, not model tools: `/wpvibe/v1/ping`, `/health`, `/connection-check-challenge`, `/audit-log/record`, `/cli/run-approved`, `/authorize`, `/authorize/preflight`, `/connection-status`, `/op-proof/check`, `/code-snippet` approved path, `/builder-login`, `/detached/run`, `/self-update/health`, `/self-update/run`, and `/classic-theme-safety`. Higher-level safe tools may consume trusted in-process equivalents where that preserves upstream semantics, but generic REST cannot reach these endpoints. The related `/wpvibe/v1/draft-theme/compile-sources` snapshot route is explicitly classified model-facing.

## Direct extensions beyond the public named surface

`site_intelligence`, `live_reload_status`, `get_last_change`, `integration_capabilities`, `inspect_media`, `seo_audit`, `validate_blocks`, `save_validated_blocks`, editable-field registration tools, saved-skill CRUD, `seedprod_compile_page`, `screenshot_page`, `render_browser`, `page_audit`, `wp_cli_status`, `check_operation_receipt`, `call_armored`, and `reference_parity_manifest` provide safe local equivalents, transport helpers, or diagnostics without pretending they are separately named WPVibe hosted tools.

## Validation boundary

Published release **1.3.3** runs 94 suite cases (27 legacy + 14 parity + 53 reference) on PHP 7.4, 8.2 and 8.4 plus a focused Abilities raw-JSON request-body regression. Its disposable Elementor acceptance workflow verifies the bounded Core 4.3.4 flow described above.

Current post-1.3.3 **`main`** runs **101 suite cases (27 legacy + 14 parity + 60 reference)** on PHP 7.4, 8.2 and 8.4 plus the focused Abilities regression. The current disposable lane also verifies the live WPVibe REST route-classification inventory. Future release packaging requires the focused Abilities request-body regression both before packaging and again after extracting the release ZIP.

The live lane remains intentionally bounded: it proves the listed Elementor Core flow and runtime route inventory on WordPress 6.9 / WPVibe 1.20.3 exact SHA / Elementor 4.3.4, but does not imply Elementor Pro, every Atomic mutation/global-style path, media/network edge cases, provider-backed browser/audit/image services, other builders, or production/client sites were exercised.
