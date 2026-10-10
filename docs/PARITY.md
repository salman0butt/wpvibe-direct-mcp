# WPVibe Reference Parity Matrix

Audit date: **2026-10-10**. Latest published Direct MCP release: **1.3.0**. This branch adds **unreleased runtime parity hardening** on top of 1.3.0. Official WordPress-side baseline: WPVibe **1.20.3**, upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`.

This audit compares the live WPVibe Features page, public docs/safety guidance, Works-with-AI compatibility pages, current Elementor compatibility page, and the official `awesomemotive/wpvibe-ai-mcp` source. “Covered” never means a fabricated cloud response: hosted account/fleet/browser/audit state remains provider-backed and fails closed when no user-owned provider is configured.

## Runtime classes

| Surface | Direct MCP status | Runtime behavior |
|---|---|---|
| WordPress/site info | Native | Installed WPVibe routes and WordPress permissions |
| File/theme operations | Native | WPVibe sandbox, PHP checks, draft-theme concurrency/safety |
| Generic REST reads | Local/native | Direct bridge, destination WordPress permission callbacks preserved |
| Generic REST writes | **Direct Worker replacement** | One-time payload-bound Direct approval unless WPVibe dangerous bypass is enabled, then normal WordPress REST dispatch |
| WordPress Abilities | Local/native | Runtime discovery/info/run; non-read operations approval-gated unless bypass is enabled |
| Destructive/idempotent Ability | Local/native | `DELETE` after approval |
| Destructive/non-idempotent Ability | Local/native | `POST` after approval, matching WordPress core |
| WP-CLI/SQL | **Direct Worker replacement** | `/cli/run` remains WPVibe's classifier; approval-required work binds the exact operation/dry-run snapshot and executes locally through `WPVibe_CLI::run_approved()` after approval |
| WPCode snippets | **Direct Worker replacement** | `WPVibe_Code_Snippet::handle(..., true)` locally; proof credentials are never forged and snippets stay dormant |
| WAF-sensitive code payloads | Direct extension | `call_armored` strict-base64 JSON object retry; decoded call goes through the same normal public tool, approval, and capability gates |
| Hosted account/site registry | Provider-backed | Exact public tool names, no fake WPVibe cloud state |
| Fleet/usage/profile | Provider-backed | User-owned provider required |
| Browser render/screenshots/Lighthouse/stock search/PDF text | Provider-backed | User-owned provider required |
| SeedProd browser compile | Provider-backed + native primitive | Approval-gated trusted browser provider consumes one-time builder login internally; credential is never returned to the model |

## Public MCP tool-name parity

The audited public WPVibe names remain exposed: `connect_site`, `list_sites`, `site_info`, `remove_site`, `rest_api`, `rest_api_write`, `upload_media`, `request_upload`, `check_upload`, `search_images`, `discover_abilities`, `get_ability_info`, `run_ability`, draft/theme/file tools, `run_wp_cli`, `get_page_html`, `navigate`, `load_skill`, `save_skill`, `code_snippet`, approval tools, `audit_page`, profile/fleet tools, and `use_usage_reset`.

`reference_parity_manifest` is the machine-readable source of truth for each tool's implementation class: `native_route`, `local_equivalent`, `worker_replaced`, or `provider_backed`.

## Features/options coverage

- **Full REST API:** reads are direct; writes are payload-bound and approval-gated unless the site owner explicitly enables WPVibe's dangerous approval bypass. Direct blocks authentication/proof/worker control-plane routes.
- **WP-CLI:** the installed WPVibe dispatcher is authoritative. `wp_cli_status` and `run_wp_cli help` are used for runtime command discovery instead of a hardcoded marketing count. Destructive commands preserve WPVibe's dry-run/drift checks through its native `run_approved()` executor.
- **SQL/bulk operations:** read-only database commands stay under WPVibe's rules; writes follow its destructive classifier and the Direct approval handoff. WPVibe's hard blocks remain hard blocks even when bypass is enabled.
- **Theme Builder + Draft/Preview/Publish:** classic scaffold, isolated draft, preview, publish/delete, source-hash checks, and Site Editor customization handling remain native WPVibe behavior.
- **Site Intelligence:** environment/integration diagnostics plus upstream health/performance data when present.
- **Lighthouse/PageSpeed:** provider-backed `audit_page`/`page_audit`.
- **Plugin Abilities:** runtime discover/info/run with WordPress-core GET/POST/DELETE semantics and Direct approvals for all non-read calls unless bypass is on.
- **Images & Media:** native public URL import, one-time device/chat upload, SVG sanitizer path, stock provider, Media Library/PDF inspection.
- **Live Reload:** native `/last-change` status/change lookup and navigation; builder sessions retain upstream reload protections.
- **Saved Skills:** local CRUD, versions, descriptions and bounded text references.
- **Interactive Panels:** MCP Apps approval/upload resources plus secure browser fallbacks.
- **Validated Block Output:** recursive installed-block schema validation before Gutenberg writes.
- **Safe Code Snippets:** native WPVibe dormant handler is called locally; activation remains a human wp-admin action even with approval bypass enabled.
- **Every Site You Manage:** exact account/fleet tool names are provider-backed; Direct does not clone WPVibe's private cloud registry.
- **Editable Fields:** persistent field/group/setting declarations replay through WPVibe native field APIs, covering all 13 upstream post-field types and the supported setting subset.
- **WAF resilience:** `call_armored` provides a bounded retry for hosts that reject raw PHP/JS/CSS/SQL in the incoming MCP body; decoding never bypasses the target tool's normal safety path.

## Elementor end-to-end contract

Current WPVibe verification on 2026-10-10 reports Elementor **4.3.3**, WordPress **6.9+**, and **26 verified abilities**. Direct MCP uses runtime discovery rather than copying that list into a brittle fixed implementation.

### Modern Elementor 4.3+

1. `site_info` / integration inspection.
2. `discover_abilities` for the installed `elementor/*` namespace.
3. `get_ability_info` before every write; use the installed schemas/resources.
4. Read page structure/settings before mutation.
5. Create draft pages, build compositions, manage elements, variables/classes/default styles and other capabilities only when the installed ability exists.
6. Treat Atomic Editor feature-gate errors as prerequisites. Do not fall back to guessed private Elementor data.
7. `elementor/update-page-settings` is whole-object replacement: read, preserve unrelated settings, mutate the requested field, send the complete safe object.
8. Set WordPress page templates through WordPress/native page-template paths, not through Elementor page settings.
9. Treat global variables/classes/default styles as site-wide changes.
10. Re-read after writes. If publishing is requested, publish through the installed ability/native path, then verify preview/live rendered output because autosaves and Elementor caches can leave the live page different from the write response.

### Native compatibility fallback

When current Elementor Abilities are unavailable but WPVibe's native integration exists, Direct exposes `elementor_widgets`, `elementor_schema`, `elementor_style_schema`, `elementor_save_page`, and `elementor_save_template`. These native paths are preferred over generic `_elementor_data` writes.

Elementor Pro features are runtime-discovered when installed; Direct does not claim Pro-only headers/footers/templates/components on a free installation.

## Other builder/workflow parity

- Gutenberg: installed block schemas + validated save path.
- SeedProd: public/native data writes plus approval-gated browser compile.
- Divi / Divi 5: surgical content writes preserve upstream refresh hooks; Theme Builder linkage uses audited multi-value meta guidance.
- Beaver Builder, Bricks, Breakdance: native upstream schema/read/save routes when installed.
- Kadence, GeneratePress/GP Premium, GenerateBlocks, WPBakery and Classic Themes: dedicated playbooks use public/native structures rather than guessed proprietary serialization.

## Current Works-with-AI / cookbook playbooks

Direct MCP ships explicit playbooks for Rank Math, AIOSEO, SEOPress, Yoast SEO, Smash Balloon, MemberPress, Charitable, Duplicator, PushEngage, Easy Digital Downloads, LifterLMS, WPForms, Kit/ConvertKit, Modern Cart, CartFlows, Pagelayer, ElementsKit, Amelia, AdTribes Product Feed, FluentCart, FluentCommunity, FluentCRM, Merchant, WooCommerce, WPCode, Sugar Calendar, OptinMonster, Botiga, Elementor, Beaver Builder, Bricks, Breakdance, Divi/Divi 5, SeedProd, GeneratePress, GenerateBlocks, Kadence, plus a generic Abilities-first workflow for newly compliant plugins.

## MCP protocol/options parity

- Modern `2026-07-28` `server/discover`, routing headers and cache hints.
- Legacy 2025/2024 initialize/ping compatibility.
- Tools, prompts and resources primitives.
- MCP Apps extension with `text/html;profile=mcp-app` resources/read.
- Stateless Streamable-HTTP JSON.
- Bearer, `X-WPVibe-Direct-Token`, and query-token compatibility authentication.
- Bounded `call_armored` WAF retry extension.

## Upstream routes intentionally kept internal

Infrastructure/control-plane primitives stay non-model-facing: `/ping`, `/health`, `/connection-check-challenge`, `/classic-theme-safety`, `/draft-theme/compile-sources`, `/audit-log/record`, `/cli/run-approved`, `/authorize`, `/authorize/preflight`, `/connection-status`, `/op-proof/check`, approved `/code-snippet`, `/builder-login`, `/detached/run`, and self-update routes. Direct uses safe higher-level adapters where necessary; it never exposes Worker proof credentials.

## Validation boundary

Automated protocol/unit/security/contract tests prove Direct MCP routing, approvals, worker replacements, manifest classification and feature contracts. They do **not** prove that a specific WordPress host, Elementor installation, cache stack, page builder or WAF behaves correctly in reality. Final acceptance still requires the disposable-site checklist in `docs/TESTING.md`. Production/client sites are not used for destructive acceptance testing.
