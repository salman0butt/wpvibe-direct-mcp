# WPVibe Direct MCP

A self-hosted MCP endpoint for WordPress that reuses the installed **WPVibe** plugin's protected WordPress-side implementation instead of requiring the hosted WPVibe MCP gateway.

> This is an independent community bridge. It is not the official `awesomemotive/wpvibe-ai-mcp` repository and it does not copy WPVibe's hosted account system, private fleet service, billing/usage state, private credentials, or proprietary cloud infrastructure.

## Release / audited baseline

- Latest published Direct MCP release: **1.3.0**
- Current development branch adds **unreleased runtime parity hardening** after 1.3.0
- Audited WPVibe WordPress plugin: **1.20.3**
- Audited upstream commit: **`8f303926ae11179e38bc0ecf2a87e3cbd18984c6`** (2026-10-06)
- WordPress: 6.0+; modern WordPress Abilities require 6.9+
- PHP: 7.4+
- MCP: modern `2026-07-28` plus legacy 2025/2024 compatibility

## What this bridge covers

### Native/site-local

- Site/environment information and upstream health/performance diagnostics.
- Theme/file read, list, search, outline, edit, write and delete through WPVibe's safety layer.
- Draft theme create, preview, publish/delete and Site Editor customization handling.
- Surgical content search/edit.
- WordPress REST reads and approval-gated writes.
- WordPress Abilities discovery/info/run.
- Native WPVibe WP-CLI emulation, including Direct approval handoff for destructive/high-risk commands.
- Public URL media import plus one-time local/device upload flow.
- MCP Apps approval/upload panels with secure browser fallbacks.
- Elementor, Beaver Builder, Bricks and Breakdance native integrations when the corresponding routes are installed.
- Gutenberg schema validation and validated writes.
- Editable fields/groups/settings.
- Saved Skills, site intelligence, live reload, SEO audit and media/PDF inspection.
- Dormant WPCode snippet creation/update without silently activating code.
- WAF-safe `call_armored` retry for code-bearing arguments while preserving the target tool's normal safety gates.

### Provider-backed by design

Hosted account/site registry, profile/usage, fleet operations, JavaScript browser rendering, screenshots, Lighthouse/PageSpeed, stock-image search, PDF text extraction and SeedProd browser compilation require user-owned providers where applicable. Missing providers fail closed with `provider_unavailable`; Direct MCP never fabricates WPVibe cloud state.

See `docs/PARITY.md` for the audited matrix.

## Elementor development

Current WPVibe verification reports Elementor **4.3.3** with **26 verified WordPress Abilities** on WordPress 6.9+. Direct MCP does **not** hardcode that ability list: it discovers the installed `elementor/*` abilities at runtime.

The preferred workflow is:

1. Inspect site/plugin capabilities.
2. `discover_abilities` for Elementor.
3. `get_ability_info` before every write.
4. Read page structure/settings before changing them.
5. Use installed Elementor schemas/resources to create pages, build compositions, manage elements and work with variables/classes/styles.
6. Treat Atomic Editor feature-gate errors as a prerequisite rather than guessing Elementor private data.
7. Preserve full page-settings objects because Elementor's update settings operation is replacement semantics.
8. Set WordPress page templates through WordPress/native page-template APIs.
9. Re-read after writes and verify preview/live rendered output after publish because Elementor autosaves and caches can leave the live page different from the write response.

When modern Elementor Abilities are unavailable, Direct MCP falls back to WPVibe's native `elementor_widgets`, `elementor_schema`, `elementor_style_schema`, `elementor_save_page`, and `elementor_save_template` routes when installed. Generic `_elementor_data` writes are not the preferred path when a supported native route exists.

## Approval model

Direct MCP mirrors the current WPVibe safety model rather than treating site-local access as blanket permission:

- Reads run normally.
- Generic REST writes require a one-time Direct browser approval.
- All non-read plugin Ability calls require Direct approval.
- WPVibe-classified destructive/high-risk WP-CLI/SQL work pauses for Direct approval, then executes through WPVibe's native `run_approved()` path using the exact approved dry-run snapshot.
- Changing the REST body or WP-CLI command after approval invalidates the approval.
- WPCode snippets remain dormant/disabled.
- When the site owner explicitly enables WPVibe **Dangerously bypass approvals** in wp-admin, Direct honors that setting for supported approval-gated operations; upstream hard blocks still remain blocked.

## Media uploads

There are two intentionally different flows:

1. `upload_media` — WordPress downloads a **public HTTP(S) image URL** through WPVibe's SSRF, MIME, filename, WordPress upload and SVG protections.
2. `request_upload` / `check_upload` — Direct MCP creates a **30-minute media-only one-time browser ticket**. The ticket secret is stored only as a hash, bound to the Direct token owner, and becomes non-replayable after success.

The browser upload accepts JPEG, PNG, GIF, WebP and SVG only; SVG still depends on WPVibe's safe sanitizer/sideload path. Arbitrary PHP/JS/HTML/documents and server filesystem paths are not exposed through the upload flow.

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

Clients that cannot attach custom headers may use `?token=YOUR_TOKEN`. Query-string credentials can appear in logs/browser history/proxy telemetry, so rotate them if exposed.

## Architecture

- **WPVibe stays the native WordPress executor.** Route permissions, file sandboxing, PHP checks, builder APIs, WP-CLI restrictions/classification, SQL hard blocks, SVG sanitization and other WordPress-side protections remain authoritative.
- **Direct MCP owns transport/auth plus missing Worker orchestration.** It provides MCP JSON-RPC, bearer-token auth, local browser approvals, payload binding, approved WP-CLI handoff, dormant WPCode bridging and WAF-safe retries without copying hosted proof credentials.
- **Feature detection beats version assumptions.** Route-backed tools are listed only when supported, and plugin Abilities are discovered at runtime.
- **Hosted-only state remains provider-backed.** Direct does not pretend to be WPVibe's private account/fleet/billing systems.

## Security highlights

- High-entropy access token, stored as SHA-256 only.
- Token owner must still exist and retain Administrator/manage-options access.
- Downstream WordPress routes continue to run their own permission callbacks.
- Generic REST blocks Direct/WPVibe authentication, proof, approved-worker, builder-login, detached/self-update and other control-plane routes.
- REST/Ability/WP-CLI approvals are one-time and payload-bound.
- Direct never forges WPVibe Worker op-proof credentials.
- Device upload links never contain the main MCP token; ticket secrets are hashed and single-use.
- Public URL media remains behind WPVibe's SSRF/redirect protections.
- SVG uses WPVibe's sanitizer; Direct does not substitute a weaker parser.
- Activity logs store summaries, not full request bodies, tokens, binary data or filesystem paths.

See `SECURITY.md`.

## Tests

Run:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

The current runtime-parity branch has **74 automated tests** plus PHP syntax validation and CI on PHP 7.4, 8.2 and 8.4. Coverage includes modern/legacy MCP, authentication, route/schema detection, REST approvals, Ability method/approval semantics, destructive WP-CLI snapshot handoff, dormant WPCode bridging, WAF-armored calls, media tickets, MCP Apps, saved skills, provider boundaries and the reference manifest.

A real disposable WordPress staging site is still required for the detailed Elementor/builders/host acceptance checklist in `docs/TESTING.md`; automated tests do not falsely claim that external E2E has occurred.

## Releases

GitHub release publication is explicit: the workflow requires a version and an **exact commit SHA that already has successful parity CI**. Normal future `main` CI does not automatically recreate or move historical tags.

## Documentation

- `INSTALL.md`
- `SECURITY.md`
- `docs/PARITY.md`
- `docs/MEDIA-UPLOAD.md`
- `docs/MCP-PROTOCOL.md`
- `docs/COMPATIBILITY.md`
- `docs/TESTING.md`
