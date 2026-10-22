# WPVibe Direct MCP

A self-hosted MCP endpoint for WordPress that reuses the installed **WPVibe** plugin's protected WordPress-side routes instead of requiring the hosted WPVibe MCP gateway.

> This is an independent community bridge. It is not the official `awesomemotive/wpvibe-ai-mcp` repository and it does not copy WPVibe's hosted gateway, account system, private skills, fleet service, usage metering, or proprietary cloud integrations.

## Release

- Direct MCP: **1.5.3**
- Audited WPVibe release/source: **1.20.5** (plus regression coverage for **1.20.3**)
- Audited upstream commit: **`62bc77ccd1c15faf766f527a7935fe32f1b227d3`** (2026-10-14)
- WordPress: 6.0+; Abilities tools require WordPress 6.9+
- PHP: 7.4+
- MCP: modern `2026-07-28` plus legacy 2025/2024 compatibility

## What’s new in 1.5.3

- Fixed ChatGPT custom MCP registration failure (`HTTP 424 invalid_response`) caused by missing modern `resources/read` result fields (`ttlMs`, `cacheScope`). Both MCP Apps now return private zero-TTL cache hints with `resultType: complete`.
- Modern successful RPC results consistently include `resultType: complete` without changing legacy JSON-RPC responses or security boundaries. Added three reference regressions; **53 core + 20 parity + 216 reference = 289 suite cases**.

## What 1.5.2 added (previous release)

- Approval persistence and token lifecycle: exact readback of pending/approved/rejected states, retained atomic claims on ambiguous storage writes (including false acknowledgements after a commit), and credential fingerprint binding so an approved receipt cannot outlive bearer rotation even when the admin is unchanged.
- Device uploads: fail-closed persistence checks for failed tickets, explicit manual-reconciliation IDs if WordPress attachment rollback fails, and secret-free `check_upload` failure status. Inline MCP App uploads no longer tear down slow multipart transfers after an arbitrary 1.8-second delay and display failed/orphan status.
- Production-style regression coverage: **53 core + 20 parity + 213 reference = 286 suite cases**, a 174-request skill benchmark, focused Abilities raw-JSON regression and Node-based simulated inline upload host tests. Disposable WordPress 6.9 / MySQL 8 E2E adds an eight-process one-winner approval race and same-admin credential rotation in both WPVibe lanes. Redis/multi-node and actual slow-browser-host/deletion-failure integration remain unverified.

## What 1.5.1 added (previous release)

- WPVibe 1.20.4/1.20.5 compatibility: expose native WPCode `deactivate` and approval-gated `delete` only on supported versions, preserve exact snippet/action approvals and replay rejection, and fail closed if the WPCode execution cache still references a retired snippet.
- Builder and preview parity: Bricks `template_type` for header/footer templates, optional Beaver schema `slug`, SeedProd preview/publishing guidance, and hidden REST-index routes excluded from generic discovery.
- CI now tests WordPress 6.9 + Elementor Core 4.3.4 with both pinned WPVibe 1.20.3 and 1.20.5; its 1.20.5 lane also performs a live disposable WPCode Lite retirement/approval/replay workflow. PHP suites have **53 core/legacy + 20 parity + 204 reference = 277 cases**, plus the 174-request skills benchmark and Abilities regression. Licensed builders, SeedProd Pro, and external providers remain outside live verification.

## What 1.5.0 added (previous release)

- Security: bearer-authenticated MCP Apps can no longer self-approve mutations; only a capability- and nonce-checked WordPress admin can approve them.
- Security: upload tickets are bound to the live token/owner; malformed or missing JSON-RPC request IDs are rejected before side effects.
- Workflow discovery: 67 skills have intent metadata; matching recognizes named WordPress integrations while avoiding offsite false positives. CI enforces a 174-request curated benchmark.
- Query-string authentication is disabled by default; migrate clients to an authorization header or explicitly enable the compatibility option in WP admin.
- PHP release verification now includes **53 core/legacy + 20 parity + 196 reference = 269 suite cases**, the routing benchmark and focused Abilities request-body regression.

## What 1.4.1 added (previous release)

- MCP 2025-06-18 and newer reject JSON-RPC batches before tool execution. Retained pre-2025-06-18 legacy batching sanitizes MCP Apps metadata per result so inline approval-panel tokens cannot leak into model-visible content.
- One-time approvals now use an atomic claim before protected execution; concurrent reuse, ambiguous claims, and persistence uncertainty fail closed.
- Browser upload tickets atomically reserve `waiting` → `processing` before Media Library side effects, allow exactly one finalizer, and roll back attachments after partial upload or finalization failure.
- Generic REST blocks both Worker-proof-protected WPCode snippet endpoints; the public `code_snippet` capability continues through the capability-preserving in-process safety bridge and is classified as a Direct local equivalent.
- Release publication is provenance-gated to the current remote `main` SHA, requires exact-SHA PHP parity and disposable WordPress/Elementor E2E success, and revalidates both gates immediately before immutable tag creation.
- The 2026-10-12 Works-with-AI/Cookbook refresh adds dedicated ACF, Ultimate Addons for Elementor, and Disable Comments safety playbooks over the existing WordPress Abilities execution surface.
- Release CI contains 35 core/legacy + 14 parity + 70 reference cases = **119 suite cases**, plus the focused Abilities raw-JSON regression, on PHP 7.4, 8.2, and 8.4.

The real disposable acceptance matrix for 1.5.3 is bounded to WordPress 6.9 + Elementor Core 4.3.4 with pinned WPVibe 1.20.3 and 1.20.5. Both lanes verify authenticated MCP discovery, Elementor Abilities, approval/replay protection, draft pages, Atomic composition, shared global-style/element updates, read/publish/render behavior, PHP-fatal checks, Atomic-OFF fallback, live WPVibe route classification, and MySQL one-winner approval concurrency/credential rotation. The 1.20.5 lane additionally verifies WPCode Lite create/deactivate/approval-gated trash/replay rejection. Elementor Pro Theme Builder, licensed Bricks/Beaver, SeedProd Pro, provider-backed browser/audit/image services, other WPCode versions, Redis/multi-node object caches, real slow-browser transfers/media deletion failures and production/client sites are not claimed E2E-certified.

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

Install Direct MCP separately on each WordPress site you want to manage. A Direct MCP instance natively manages only the WordPress installation on which it is installed.

Release 1.5.0 disables query-string authentication by default. If a client genuinely cannot attach authentication headers, a site owner can explicitly enable **Allow query-string token compatibility** in **WPVibe → Direct MCP → Settings**, then use `?token=YOUR_TOKEN` (or the legacy `?access_token=` alias). Query-string credentials can appear in logs, browser history, analytics, referrers, screenshots, support tooling, and proxy telemetry, so keep the option off unless required and rotate the token after suspected exposure. The published `v1.4.1` artifact predates this hardening and still accepts valid query tokens by default.

## Architecture

Direct MCP stays thin by design:

- **WPVibe owns WordPress mutation safety.** Route permission callbacks, file sandboxing, PHP checks, WP-CLI restrictions, WPCode rules, SVG sanitization, builder APIs, and upstream approval receipts stay in WPVibe. Where a Worker-proof REST endpoint cannot be used directly, Direct may call the same trusted upstream PHP handler in-process while re-checking the skipped REST capability boundary.
- **Direct MCP owns transport/auth and compatibility.** It provides MCP JSON-RPC, directly managed tokens, capability discovery, browser upload tickets, approvals, and adapters that normalize current public WPVibe option contracts without weakening downstream WordPress/WPVibe checks.
- **Feature detection wins over version coupling.** Route-backed tools are listed only when corresponding routes exist; Elementor and other Abilities are discovered from the installed site at runtime.

## Hosted-service boundary

Direct MCP exposes audited hosted tool names for account/site registry, fleet and usage-reset workflows, but it does not clone WPVibe's private cloud database or credentials. Those tools require a user-owned provider and otherwise return `provider_unavailable`. In particular, `connect_site`, `list_sites`, and `remove_site` are exact-name compatibility adapters only; they do not create or manage a Direct-owned remote-site registry. Stock search, browser rendering, screenshots and Lighthouse-style audits follow the same fail-closed provider model. MCP Apps approval/upload panels are implemented locally and do not require WPVibe's hosted UI.

Release 1.5.0 keeps those exact public tool names visible in MCP discovery and adds non-secret provider readiness under each provider-backed tool's `_meta['io.wpvibe.direct/provider']`. Discovery checks only whether a callback/filter is registered; it never calls the provider. `get_page_html` is marked as a hybrid capability because same-site HTML can still fall back to WPVibe's native rendered-HTML path while external JavaScript rendering requires the configured browser provider.

Release 1.5.0 also treats provider **responses** as an external trust boundary. Local and hosted/reference provider results pass through one bounded normalizer before becoming model-visible: credential-shaped keys are removed by key context, nested structures are depth/item bounded, individual strings and the overall output have explicit size limits, provider `WP_Error` data is sanitized, and any changed result carries `_wpvdmcp_provider_output` metadata. Ordinary prose containing words such as “token” is not redacted merely for using that word. The published `v1.4.1` artifact predates this hardening.

See `docs/PARITY.md` for the complete matrix.

## Security highlights

Release 1.5.0 hardens the MCP Apps approval boundary: a bearer-authenticated client can inspect a pending approval but cannot mint an approval credential or approve its own mutation through `approval_decide`. The read-only inline app links to the separate authenticated WordPress admin form, where administrator capability and nonce checks remain mandatory. The published `v1.4.1` artifact predates this change.

- High-entropy access token, stored as SHA-256 only.
- Token owner must still exist and retain `manage_options`.
- Bearer and `X-WPVibe-Direct-Token` headers work by default; query-string credentials are an explicit site-owner opt-in in release 1.5.0.
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
php tests/skill-routing-benchmark.php --enforce
php tests/abilities-json-body-run.php
node tests/inline-upload-app-run.js
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

Release 1.5.3 verification contains 53 core/legacy + 20 parity + 216 reference tests = **289 suite cases**, plus the 174-request routing benchmark, focused Abilities raw-JSON request-body regression, and three simulated inline MCP upload host scenarios. GitHub Actions runs source PHP suites on PHP 7.4, 8.2, and 8.4; the release workflow repeats source and extracted-ZIP suites plus Node simulations. Historical v1.5.2 contains 286 cases, v1.5.1 contains 277 cases, v1.5.0 contains 269 and v1.4.1 contains 119; all remain unchanged.

The WPCode reviewed-write bridge is unit/reference verified against the upstream handler contract, including dormant success, review-required approval, payload drift rejection, replay rejection, approval-bypass ordering, ignored activation input, and the preserved capability gate. The disposable WPVibe 1.20.5 lane additionally installs WPCode Lite and verifies create/deactivate, approved trash and replay rejection. This does not certify other WPCode versions or storage/cache implementations.

A separate disposable WordPress/Elementor CI workflow verifies the bounded Elementor Core 4.3.4 integration described above and the live WPVibe route-classification inventory. The broader manual checklist in `docs/TESTING.md` remains intentionally unmarked until those additional environments/capabilities are actually exercised; production is never used for destructive verification.

## Documentation

- `INSTALL.md`
- `SECURITY.md`
- `docs/PARITY.md`
- `docs/MEDIA-UPLOAD.md`
- `docs/MCP-PROTOCOL.md`
- `docs/COMPATIBILITY.md`
- `docs/TESTING.md`
