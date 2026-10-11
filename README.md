# WPVibe Direct MCP

A self-hosted MCP endpoint for WordPress that reuses the installed **WPVibe** plugin's protected WordPress-side routes instead of requiring the hosted WPVibe MCP gateway.

> This is an independent community bridge. It is not the official `awesomemotive/wpvibe-ai-mcp` repository and it does not copy WPVibe's hosted gateway, account system, private skills, fleet service, usage metering, or proprietary cloud integrations.

## Release

- Direct MCP: **1.3.2**
- Audited WPVibe release: **1.20.3**
- Audited upstream commit: **`8f303926ae11179e38bc0ecf2a87e3cbd18984c6`** (2026-10-06)
- WordPress: 6.0+; Abilities tools require WordPress 6.9+
- PHP: 7.4+
- MCP: modern `2026-07-28` plus legacy 2025/2024 compatibility

## What 1.3.2 adds

- Approval-gates generic REST mutations, keeps core-content deletes recoverable, and protects WooCommerce replace-all list fields from partial destructive payloads.
- Matches current WPVibe REST option names while retaining Direct aliases: `route`/`path`, `query`/`params`, string-or-array `fields`, `max_length`/`max_response_bytes`, and embedded query normalization.
- Matches current WordPress Abilities public options: `ability_name`/`name`, `input_json`/structured input, metadata-first annotations, approval aliases, and correct GET/POST/DELETE execution semantics.
- Matches current provider-backed public contracts for image search, page audits, rendered HTML, site connection, and fleet jobs without fabricating WPVibe cloud state.
- Matches current Saved Skill lookup/search/resource contracts, including bounded catalog metadata and saved reference-file reads.
- Expands automated verification to **94 tests** (27 legacy + 14 parity + 53 reference) on PHP 7.4, 8.2, and 8.4.

## Elementor development

On WordPress 6.9+ with Elementor 4.3+, Direct MCP uses the installed Elementor WordPress Abilities first and discovers the schemas actually exposed by that site. The current workflow covers page discovery/creation, page structure, composition building, targeted element edits, widget/resource discovery, global variables/classes/default styles, full-object page settings, preview links, publishing, and live rendered verification. Atomic-only capabilities are detected rather than guessed.

When the newer Abilities surface is unavailable, Direct MCP exposes WPVibe's native Elementor routes for widget/schema discovery, page save, and Elementor Pro Theme Builder template save. It does not generically replace `_elementor_data` when a supported builder API exists.

A real disposable WordPress + Elementor staging target is still required to certify rendered Atomic/Pro behavior, autosaves, generated CSS/cache, and live theme conditions. Production is never treated as the destructive test environment.

## Media uploads

There are two intentionally different flows:

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
- **Direct MCP owns transport/auth and compatibility.** It provides MCP JSON-RPC, directly managed tokens, capability discovery, browser upload tickets, approvals, and adapters that normalize current public WPVibe option contracts without weakening downstream WordPress/WPVibe checks.
- **Feature detection wins over version coupling.** Route-backed tools are listed only when corresponding routes exist; Elementor and other Abilities are discovered from the installed site at runtime.

## Hosted-service boundary

Direct MCP exposes audited hosted tool names for account/site registry, fleet and usage-reset workflows, but it does not clone WPVibe's private cloud database or credentials. Those tools require a user-owned provider and otherwise return `provider_unavailable`. Stock search, browser rendering, screenshots and Lighthouse-style audits follow the same fail-closed provider model. MCP Apps approval/upload panels are implemented locally and do not require WPVibe's hosted UI.

See `docs/PARITY.md` for the complete matrix.

## Security highlights

- High-entropy access token, stored as SHA-256 only.
- Token owner must still exist and retain `manage_options`.
- Every downstream WordPress route still performs its own permission checks.
- Generic REST blocks all `/wpvibe-direct/v1/` routes and WordPress Application Password management endpoints.
- Generic REST writes are one-time approval-gated unless the site owner explicitly enables WPVibe's approval bypass.
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

Current 1.3.2 verification is 27 legacy + 14 parity + 53 reference tests = **94 tests**, with GitHub Actions running the suites on PHP 7.4, 8.2, and 8.4.

A real disposable WordPress staging site is still required for the manual integration checklist in `docs/TESTING.md`; never use production for destructive verification.

## Documentation

- `INSTALL.md`
- `SECURITY.md`
- `docs/PARITY.md`
- `docs/MEDIA-UPLOAD.md`
- `docs/MCP-PROTOCOL.md`
- `docs/COMPATIBILITY.md`
- `docs/TESTING.md`
