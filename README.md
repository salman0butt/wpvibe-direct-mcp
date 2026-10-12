# WPVibe Direct MCP

A self-hosted MCP endpoint for WordPress that reuses the installed **WPVibe** plugin's protected WordPress-side routes instead of requiring the hosted WPVibe MCP gateway.

> This is an independent community bridge. It is not the official `awesomemotive/wpvibe-ai-mcp` repository and it does not copy WPVibe's hosted gateway, account system, private skills, fleet service, usage metering, or proprietary cloud integrations.

## Release

- Direct MCP: **1.4.1**
- Audited WPVibe release/source: **1.20.3**
- Audited upstream commit: **`8f303926ae11179e38bc0ecf2a87e3cbd18984c6`** (2026-10-06)
- WordPress: 6.0+; Abilities tools require WordPress 6.9+
- PHP: 7.4+
- MCP: modern `2026-07-28` plus legacy 2025/2024 compatibility

## What 1.4.1 adds and verifies

- MCP 2025-06-18 and newer reject JSON-RPC batches before tool execution. Retained pre-2025-06-18 legacy batching sanitizes MCP Apps metadata per result so inline approval-panel tokens cannot leak into model-visible content.
- One-time approvals now use an atomic claim before protected execution; concurrent reuse, ambiguous claims, and persistence uncertainty fail closed.
- Browser upload tickets atomically reserve `waiting` → `processing` before Media Library side effects, allow exactly one finalizer, and roll back attachments after partial upload or finalization failure.
- Generic REST blocks both Worker-proof-protected WPCode snippet endpoints; the public `code_snippet` capability continues through the capability-preserving in-process safety bridge and is classified as a Direct local equivalent.
- Release publication is provenance-gated to the current remote `main` SHA, requires exact-SHA PHP parity and disposable WordPress/Elementor E2E success, and revalidates both gates immediately before immutable tag creation.
- The 2026-10-12 Works-with-AI/Cookbook refresh adds dedicated ACF, Ultimate Addons for Elementor, and Disable Comments safety playbooks over the existing WordPress Abilities execution surface.
- Release CI contains 35 core/legacy + 14 parity + 70 reference cases = **119 suite cases**, plus the focused Abilities raw-JSON regression, on PHP 7.4, 8.2, and 8.4.

The real disposable acceptance lane remains bounded to WordPress 6.9 + exact WPVibe 1.20.3 upstream SHA + Elementor Core 4.3.4. It verifies authenticated MCP discovery, runtime Elementor Ability discovery, approval/replay protection, draft page creation, Atomic composition, page-structure read, publish, rendered frontend output, PHP-fatal checks, Atomic-OFF fallback behavior, and the live WPVibe route-classification inventory. Elementor Pro Theme Builder, broader global-style/element mutations, other page builders, provider-backed browser/audit/image services, WPCode live runtime behavior, media edge cases, and production/client sites are not claimed as E2E-verified by this release.

See `CHANGELOG.md`, `docs/PARITY.md`, and `docs/TESTING.md` for the evidence boundary.

## Elementor development

On WordPress 6.9+ with Elementor 4.3+, Direct MCP uses the installed Elementor WordPress Abilities first and discovers the schemas actually exposed by that site. The current workflow covers page discovery/creation, page structure, composition building, targeted element edits, widget/resource discovery, global variables/classes/default styles, full-object page settings, preview links, publishing, and live rendered verification. Atomic-only capabilities are detected rather than guessed.

The disposable CI lane verifies the bounded Elementor Core 4.3.4 create → Atomic build → read → publish → rendered-frontend flow through the real Direct MCP HTTP endpoint, including the POST/GET/DELETE method classes and one-time approval replay protection. When Atomic Editor is disabled, the lane also verifies that an Atomic-only write fails instead of silently succeeding and that the native WPVibe `elementor_save_page` fallback remains discoverable.

When the newer Abilities surface is unavailable, Direct MCP exposes WPVibe's native Elementor routes for widget/schema discovery, page save, and Elementor Pro Theme Builder template save. It does not generically replace `_elementor_data` when a supported builder API exists. Elementor Pro runtime E2E remains a separate licensed/disposable-site boundary and is not implied by the Core 4.3.4 lane.

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

- **WPVibe owns WordPress mutation safety.** Route permission callbacks, file sandboxing, PHP checks, WP-CLI restrictions, WPCode rules, SVG sanitization, builder APIs, and upstream approval receipts stay in WPVibe. Where a Worker-proof REST endpoint cannot be used directly, Direct may call the same trusted upstream PHP handler in-process while re-checking the skipped REST capability boundary.
- **Direct MCP owns transport/auth and compatibility.** It provides MCP JSON-RPC, directly managed tokens, capability discovery, browser upload tickets, approvals, and adapters that normalize current public WPVibe option contracts without weakening downstream WordPress/WPVibe checks.
- **Feature detection wins over version coupling.** Route-backed tools are listed only when corresponding routes exist; Elementor and other Abilities are discovered from the installed site at runtime.

## Hosted-service boundary

Direct MCP exposes audited hosted tool names for account/site registry, fleet and usage-reset workflows, but it does not clone WPVibe's private cloud database or credentials. Those tools require a user-owned provider and otherwise return `provider_unavailable`. Stock search, browser rendering, screenshots and Lighthouse-style audits follow the same fail-closed provider model. MCP Apps approval/upload panels are implemented locally and do not require WPVibe's hosted UI.

See `docs/PARITY.md` for the complete matrix.

## Security highlights

- High-entropy access token, stored as SHA-256 only.
- Token owner must still exist and retain `manage_options`.
- Every ordinary downstream WordPress route still performs its own permission checks; in-process safety bridges explicitly preserve any capability check skipped by not entering a Worker-proof REST permission callback.
- Generic REST blocks all `/wpvibe-direct/v1/` routes, WordPress Application Password management endpoints, and audited WPVibe Worker/control-plane endpoints.
- Generic REST writes are one-time approval-gated unless the site owner explicitly enables WPVibe's approval bypass.
- Approval consumption is atomically claimed before protected execution so the same receipt cannot be consumed by two concurrent requests.
- Destructive WP-CLI approval is command/snapshot-bound, one-time, replay-resistant, and does not reveal Worker operation-proof credentials to the model.
- WPCode reviewed writes are classified first by WPVibe's dormant handler and, when human review is required, use a payload-bound one-time Direct approval. Worker proofs remain inaccessible and Direct never raises snippet activation.
- Device upload links never contain the main MCP token. Upload POSTs reserve the ticket before creating attachments; failed partial/finalization paths roll back attachments.
- Upload tickets expire, are owner-bound, secret-hashed, and become non-replayable after processing begins.
- MCP Apps approval metadata is app-only on normal responses and every retained legacy batch result; modern protocol batches are rejected before tool execution.
- MCP Apps approval/upload HTML uses a self-contained local postMessage bridge and loads no third-party runtime JavaScript inside the approval boundary.
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
php tests/abilities-json-body-run.php
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

Release 1.4.1 verification contains 35 core/legacy + 14 parity + 70 reference tests = **119 suite cases**, plus the focused Abilities raw-JSON request-body regression. GitHub Actions runs the source suites on PHP 7.4, 8.2, and 8.4, and the release workflow repeats the complete PHP suites plus focused regression after extracting the built ZIP.

The WPCode reviewed-write bridge is unit/reference verified against the upstream handler contract, including dormant success, review-required approval, payload drift rejection, replay rejection, approval-bypass ordering, ignored activation input, and the preserved capability gate. The disposable WordPress/Elementor lane does not install WPCode, so no live WPCode E2E claim is made.

A separate disposable WordPress/Elementor CI workflow verifies the bounded Elementor Core 4.3.4 integration described above and the live WPVibe route-classification inventory. The broader manual checklist in `docs/TESTING.md` remains intentionally unmarked until those additional environments/capabilities are actually exercised; production is never used for destructive verification.

## Documentation

- `INSTALL.md`
- `SECURITY.md`
- `docs/PARITY.md`
- `docs/MEDIA-UPLOAD.md`
- `docs/MCP-PROTOCOL.md`
- `docs/COMPATIBILITY.md`
- `docs/TESTING.md`
