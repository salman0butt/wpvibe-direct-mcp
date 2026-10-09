# WPVibe Direct MCP

A self-hosted MCP endpoint for WordPress that reuses the installed **WPVibe** plugin's protected WordPress-side routes instead of requiring the hosted WPVibe MCP gateway.

> This is an independent community bridge. It is not the official `awesomemotive/wpvibe-ai-mcp` repository and it does not copy WPVibe's hosted gateway, account system, private skills, fleet service, usage metering, or proprietary cloud integrations.

## Release

- Direct MCP: **1.3.0**
- Audited WPVibe release: **1.20.3**
- Audited upstream commit: **`8f303926ae11179e38bc0ecf2a87e3cbd18984c6`** (2026-10-06)
- WordPress: 6.0+; Abilities tools require WordPress 6.9+
- PHP: 7.4+
- MCP: modern `2026-07-28` plus legacy 2025/2024 compatibility

## What 1.3.0 adds

- Exhaustive public-reference parity against the live WPVibe Features/Tools Reference pages and official `awesomemotive/wpvibe-ai-mcp` 1.20.3 source.
- Exact public/hosted tool names that were previously absent: `audit_page`, `rest_api_write`, `save_skill`, `connect_site`, `list_sites`, `remove_site`, `get_profile`, `start_fleet_job`, `show_fleet_dashboard`, and `use_usage_reset`.
- Hosted account/fleet tools fail closed unless a **user-owned provider** is configured; Direct MCP never fabricates WPVibe account, billing, fleet, or usage state.
- Native MCP Apps approval and image-upload panels using `ui://` resources and `text/html;profile=mcp-app`, while retaining secure browser-link fallbacks for clients without MCP Apps.
- A reference-parity manifest that records every audited public tool, MCP protocol option, Direct extension, and upstream REST route intentionally kept internal.
- JavaScript-rendered `get_page_html` through an optional browser provider, provider-backed screenshots/Lighthouse/stock search, and an approval-gated SeedProd compile workflow that consumes WPVibe's one-time builder-login primitive without exposing it to the model.
- Saved Skills now support one-line descriptions plus bounded text reference files, matching the public WPVibe saved-skill model.
- Current builder/workflow playbooks strengthened for Elementor 4.3 Abilities, Divi/Divi 5 Theme Builder multi-value meta, and SeedProd compile behavior.
- Explicit current Works-with-AI/cookbook playbooks for Rank Math, AIOSEO, SEOPress, Yoast, Smash Balloon, MemberPress, Charitable, Duplicator, PushEngage, Easy Digital Downloads, LifterLMS, WPForms, Kit/ConvertKit, Modern Cart, CartFlows, Pagelayer, ElementsKit, Amelia, AdTribes Product Feed, FluentCart, FluentCommunity, FluentCRM, Merchant, WooCommerce, WPCode, Sugar Calendar, OptinMonster, Botiga, Elementor, Beaver Builder, Bricks, Breakdance, Divi/Divi 5, SeedProd, GeneratePress, GenerateBlocks, and Kadence, plus a generic Abilities-first plugin skill for future compliant plugins.
- Everything added in 1.2.0 remains: editable fields, validated Gutenberg writes, local Saved Skills CRUD, PDF/media intelligence, site intelligence/live reload, SEO audit, and provider-backed external services.

## Media uploads

There are now two intentionally different flows:

1. `upload_media` — WordPress downloads a **public HTTP(S) image URL** using WPVibe's current SSRF, MIME, filename, WordPress upload, and SVG protections.
2. `request_upload` / `check_upload` — Direct MCP creates a **30-minute media-only browser URL**. The user uploads the local/chat attachment there. The upload ticket is high entropy, stored only as a SHA-256 hash, bound to the Direct MCP token owner, and invalidated after a successful upload.

The browser upload accepts JPEG, PNG, GIF, WebP, and SVG only. SVG is accepted only when the installed WPVibe exposes its current SVG sanitizer/sideload path. PHP, JavaScript, HTML, arbitrary documents, and server filesystem paths are never exposed through the upload flow.

See `docs/MEDIA-UPLOAD.md`.

## Install

1. Install and activate the official WPVibe WordPress plugin (`vibe-ai`).
2. Install this plugin ZIP and activate **WPVibe Direct MCP**.
3. Open **WPVibe → Direct MCP**.
4. Generate an access token and copy it immediately; only its SHA-256 hash is stored.
5. Configure your MCP client to call:

   `https://example.com/wp-json/wpvibe-direct/v1/mcp`

   Preferred authentication:

   `Authorization: Bearer YOUR_TOKEN`

Some clients cannot attach custom headers. For those clients only, `?token=YOUR_TOKEN` remains supported. Query-string credentials can appear in logs, browser history, analytics, and proxy telemetry, so rotate them if exposed.

## Architecture

Direct MCP stays thin by design:

- **WPVibe owns WordPress mutation safety.** Route permission callbacks, file sandboxing, PHP checks, WP-CLI restrictions, WPCode rules, SVG sanitization, builder APIs, and upstream approval receipts stay in WPVibe.
- **Direct MCP owns transport/auth.** It provides MCP JSON-RPC, a directly managed bearer token, local capability discovery, browser upload tickets, and Direct-MCP browser approvals for non-readonly Abilities.
- **Feature detection wins over version coupling.** Route-backed tools are listed only when the corresponding WordPress/WPVibe REST route exists.

## Hosted-service boundary

Direct MCP exposes the **exact audited hosted tool names** for account/site registry, fleet and usage-reset workflows, but it does not clone WPVibe's private cloud database or credentials. Those tools require a user-owned provider and otherwise return `provider_unavailable`. Stock search, browser rendering, screenshots and Lighthouse-style audits follow the same fail-closed provider model. MCP Apps approval/upload panels are implemented locally and do not require WPVibe's hosted UI.

See `docs/PARITY.md` for the complete matrix.

## Security highlights

- High-entropy access token, stored as SHA-256 only.
- Token owner must still exist and retain `manage_options`.
- Every downstream WordPress route still performs its own permission checks.
- Generic REST blocks all `/wpvibe-direct/v1/` routes and WordPress Application Password management endpoints.
- Device upload links never contain the main MCP token.
- Upload tickets expire, are owner-bound, secret-hashed, and become non-replayable after success.
- Public URL media remains behind WPVibe's SSRF and redirect protections.
- SVG uses WPVibe's sanitizer; Direct MCP does not create a weaker sanitizer.
- Direct activity logs intentionally store summaries, not request bodies, tokens, binary data, or filesystem paths.

See `SECURITY.md`.

## Tests

Run:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

The dependency-free suite covers MCP modern/legacy behavior, routing headers, auth modes, revoked/lost-owner auth, route/schema feature detection, generic REST hardening, Abilities approvals/method selection, device upload ticket security, skills, and admin status data.

A real disposable WordPress staging site is still required for the manual integration checklist in `docs/TESTING.md`; never use production for destructive verification.

## Documentation

- `INSTALL.md`
- `SECURITY.md`
- `docs/PARITY.md`
- `docs/MEDIA-UPLOAD.md`
- `docs/MCP-PROTOCOL.md`
- `docs/COMPATIBILITY.md`
- `docs/TESTING.md`
