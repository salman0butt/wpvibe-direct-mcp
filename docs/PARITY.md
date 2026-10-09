# WPVibe Reference Parity Matrix

Audit date: **2026-10-10**. Direct MCP release: **1.3.0**. Official WordPress-side baseline: WPVibe **1.20.3**, upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`.

This audit compares four independent surfaces: the live WPVibe Features page, the public Tools Reference, the current Works-with-AI compatibility library, and every upstream source file that registers a `wpvibe/v1` REST route. “Covered” never means a fake cloud response: hosted account/fleet state is provider-backed and fails closed when no provider is configured.

## Public MCP tool-name parity

| Reference tool | Direct MCP 1.3.0 |
|---|---|
| `connect_site` | Provider-backed exact name; no fake WPVibe cloud registry |
| `list_sites` | Provider-backed exact name |
| `site_info` | Native WPVibe route |
| `remove_site` | Provider-backed exact name |
| `rest_api` | Hardened local/native REST bridge |
| `rest_api_write` | Write-only hardened alias |
| `upload_media` | Native WPVibe route |
| `request_upload`, `check_upload` | Local one-time media ticket + inline MCP App/browser fallback |
| `search_images` | User-owned stock-image provider |
| `discover_abilities`, `get_ability_info`, `run_ability` | WordPress Abilities API |
| draft/theme/file tools | Native WPVibe routes, current 1.20.3 contracts |
| `run_wp_cli` | Native WPVibe PHP dispatcher/approval receipts |
| `get_page_html` | JS browser provider when configured; native rendered-HTML fallback |
| `navigate` | Native WPVibe route |
| `load_skill` | Built-in + local saved skill catalog |
| `save_skill` | Exact alias to approval-gated local skill create/update |
| `code_snippet` | Native dormant WPCode route; no silent activation |
| `show_approval_panel`, `check_approval_status` | Native Direct approval state + MCP App/browser UI |
| `audit_page` | User-owned Lighthouse/PageSpeed provider |
| `get_profile` | Provider-backed exact hosted name |
| `start_fleet_job`, `show_fleet_dashboard` | Provider-backed exact hosted names |
| `use_usage_reset` | Provider-backed exact hosted name |

`reference_parity_manifest` returns the machine-readable audited list and implementation class for every reference tool.

## Features-page parity

- **Full REST API:** covered through `rest_api` / `rest_api_write` with credential-route blocking and bounded responses.
- **WP-CLI:** delegated to the installed WPVibe command dispatcher; `wp_cli_status` is the runtime source of truth because WPVibe’s advertised command count changes as releases add commands.
- **Theme Builder + Draft/Preview/Publish:** covered with classic-theme scaffold, isolated draft, preview, publish/delete and current Site Editor customization handling.
- **Site Intelligence:** environment/integration diagnostics plus upstream site-health/performance data when present; browser-rendered HTML when a provider exists.
- **Lighthouse:** `audit_page`/`page_audit` via user-owned provider.
- **Plugin Abilities:** discover/info/run with annotation-aware GET/POST/DELETE and approval for writes/destructive operations.
- **Images & Media:** native URL import, one-time device/chat upload, SVG sanitizer path, stock search provider, safe Media Library/PDF inspection.
- **Live Reload:** native `/wpvibe/v1/last-change` status/change lookup and navigation; builder edit sessions remain protected upstream.
- **Saved Skills:** local CRUD, versions, one-line descriptions and bounded text reference files.
- **Interactive Panels:** MCP Apps approval/upload `ui://` resources plus secure browser fallbacks.
- **Validated Block Output:** recursive installed-block schema validation before `save_validated_blocks` mutations.
- **Bulk Operations & SQL:** installed WPVibe WP-CLI dispatcher, including approval/dry-run semantics and read-only SQL rules.
- **Safe Code Snippets:** dormant WPCode creation/update only; activation stays human/upstream-approved.
- **Every Site You Manage:** exact account/fleet tool names are provider-backed; Direct MCP does not clone WPVibe’s private cloud registry.
- **Editable Fields:** persistent field/group/setting declarations replay through WPVibe’s native field registry, covering all 13 upstream post-field types and native meta/settings UI/REST exposure.

## Builder/workflow parity

- Elementor: native WPVibe routes plus Elementor 4.3+ Abilities-first workflow, including whole-settings replacement warnings.
- Gutenberg: installed block schemas + validated save path.
- SeedProd: Abilities/public data writes plus approval-gated browser compile using upstream one-time builder login; raw login URL is never returned to the model.
- Divi / Divi 5: surgical content edits trigger upstream Divi refresh hooks; Theme Builder linkage uses audited multi-value `post meta add/delete` rather than destructive row replacement.
- Beaver Builder, Bricks, Breakdance: native upstream save/read/schema routes when installed.
- Kadence, GeneratePress/GP Premium, GenerateBlocks, WPBakery and Classic Themes: dedicated playbooks using public/native structures rather than guessed private serialization.
- SEO audit, design, site setup and editable-fields playbooks remain available.

## Current Works-with-AI playbooks

Direct MCP ships explicit playbooks for: Rank Math, AIOSEO, SEOPress, Yoast SEO, Smash Balloon, MemberPress, Charitable, Duplicator, PushEngage, Easy Digital Downloads, LifterLMS, WPForms, Kit/ConvertKit, Modern Cart, CartFlows, Pagelayer, ElementsKit, Amelia, AdTribes Product Feed, FluentCart, FluentCommunity, FluentCRM, Merchant, WooCommerce, WPCode, Sugar Calendar, OptinMonster, Botiga, Elementor, Beaver Builder, Bricks, Breakdance, Divi/Divi 5, SeedProd, GeneratePress, GenerateBlocks, and Kadence. The machine-readable `works_with_ai_integrations` manifest pins this complete audited set. `abilities-plugin` is the generic fallback for newly compliant plugins: discover installed abilities, inspect schemas/annotations, execute through `run_ability`, and never guess private storage. OptinMonster is intentionally documented as a companion-connector workflow because the current WPVibe reference does not expose it through WordPress Abilities; Botiga is a theme workflow rather than an Abilities provider.

## MCP protocol/options parity

- Modern `2026-07-28` `server/discover`, routing headers and cache hints.
- Legacy 2025/2024 initialize/ping compatibility.
- Tools, prompts and resources primitives.
- MCP Apps extension with `text/html;profile=mcp-app` resources/read.
- Stateless Streamable-HTTP JSON; no standalone SSE/session store.
- Bearer, `X-WPVibe-Direct-Token`, and query-token compatibility authentication.

## Upstream routes intentionally kept internal

These are infrastructure/control-plane primitives, not model tools: `/wpvibe/v1/ping`, `/health`, `/connection-check-challenge`, `/audit-log/record`, `/cli/run-approved`, `/authorize`, `/authorize/preflight`, `/connection-status`, `/op-proof/check`, `/code-snippet` approved path, `/builder-login`, `/detached/run`, `/self-update/health`, and `/self-update/run`. The model uses safe higher-level tools instead; for example SeedProd compile consumes `/builder-login` internally and redacts its one-time credential.

## Direct extensions beyond the public named surface

`site_intelligence`, `live_reload_status`, `get_last_change`, `integration_capabilities`, `inspect_media`, `seo_audit`, `validate_blocks`, `save_validated_blocks`, editable-field registration tools, saved-skill CRUD, `seedprod_compile_page`, `screenshot_page`, `render_browser`, `page_audit`, `wp_cli_status`, `check_operation_receipt`, and `reference_parity_manifest` provide safe local equivalents or diagnostics without pretending they are separately named WPVibe hosted tools.

## Validation boundary

Automated protocol/unit/security/contract coverage proves the Direct code paths and reference manifest. A disposable real WordPress site is still required for destructive/manual E2E acceptance across actual installed builder/plugin versions; production is not used for that verification.
