# Changelog

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
