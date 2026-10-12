# Security Model

WPVibe Direct MCP gives an MCP client administrator-scoped access to a WordPress site. Treat the endpoint token like an administrator credential even though it is narrower than a WordPress password.

## Authentication

- Access tokens are generated with high entropy.
- WordPress stores only `SHA-256(token)` and uses `hash_equals()` for comparison.
- The token is bound to one WordPress user ID.
- The user must still exist and retain `manage_options` at request time.
- Regeneration immediately invalidates the old token; revocation removes the token/owner mapping.
- Plaintext tokens are shown only immediately after generation and are not returned by health/status tools.
- Bearer authentication is preferred. `X-WPVibe-Direct-Token` and query-token authentication are retained for compatibility.

## Query-token risk

A token in `?token=` or `?access_token=` can leak through access logs, reverse proxies, browser history, analytics, referrers, screenshots, and support tooling. Use the Authorization header whenever the MCP client supports it and rotate URL credentials after exposure.

## Downstream authorization

Direct MCP sets the current WordPress user to the configured token owner and internally dispatches the requested route. It does **not** bypass the permission callback of the destination REST route. WPVibe/native-plugin capability checks remain authoritative.

The generic `rest_api` / `rest_api_write` bridge explicitly blocks before dispatch and before approval creation:

- every `/wpvibe-direct/v1/*` route, so the MCP cannot rewrite its own authentication state;
- WordPress Application Password management routes;
- audited WPVibe Worker/control-plane routes such as authorization/preflight, connection challenge/status, operation-proof checks, approved CLI execution, executable code approval, builder-login minting, detached Worker jobs, audit-log recording, and self-update control plane;
- methods outside GET/POST/PUT/PATCH/DELETE.

The block is an explicit route classification, not a blanket `/wpvibe/v1/*` denylist. Legitimate model-facing WPVibe routes remain available. The disposable current-main E2E lane also compares the live installed `/wpvibe/v1` route registry with the parity manifest and fails if an operational route is unclassified.

## Device/browser media upload

`request_upload` creates a narrow upload ticket, not a second login mechanism.

- 256-bit random browser secret; only SHA-256 is stored.
- 128-bit random upload ID.
- ~30-minute expiry.
- Bound to the current Direct MCP token owner.
- Limited to at most five images and the lower of the WordPress upload limit or 10 MiB per file.
- Ticket becomes unusable after the first successful transfer.
- JPEG, PNG, GIF, WebP only as raster formats.
- SVG accepted only through the current WPVibe sanitizer/sideload implementation.
- PHP, JavaScript, HTML, documents, arbitrary binary uploads, path traversal, and arbitrary destination paths are not accepted.
- Browser page sends no-store/no-referrer/nosniff/CSP response headers.
- `check_upload` exposes Media Library metadata, not PHP temp paths or WordPress filesystem paths.

## Public URL media / SSRF

`upload_media` remains a WPVibe route. Direct MCP intentionally does not reimplement its downloader. Current WPVibe validates public HTTP(S) destinations and redirect hops, protects against private/loopback/link-local/reserved addresses and DNS rebinding, validates content/MIME, and sanitizes SVG before it reaches the Media Library.

Do not loosen those upstream checks to make a blocked internal URL work.

## Approvals

- WordPress Abilities marked readonly execute directly.
- Write/destructive abilities require a Direct MCP browser approval unless the administrator has explicitly enabled WPVibe's **Dangerously bypass approvals** setting in wp-admin.
- Approval IDs are random, expire, are bound to an operation + canonical payload hash + token owner, and can be consumed only once.
- Direct MCP does not expose an MCP tool capable of turning WPVibe approval bypass on.
- For destructive `run_wp_cli` calls, the public `/wpvibe/v1/cli/run` response remains the classifier. When WPVibe returns `approval_required`, Direct MCP preserves the exact upstream operation/dry-run snapshot and binds a one-time browser approval to that snapshot plus the exact command.
- A changed command or changed approved payload fails closed; a consumed CLI approval cannot be replayed.
- After Direct approval, execution uses WPVibe's trusted in-process approved executor with the preserved snapshot. The Worker-only `/wpvibe/v1/cli/run-approved` route and operation-proof secrets are never exposed to the model or generic REST bridge.
- WPVibe route-backed commands keep their upstream capability checks, allowlist, destructive classification, and approved execution semantics; Direct MCP does not forge Worker proof state.

## Armored retry boundary

`call_armored` is a Direct-only transport helper for WAF-sensitive payloads. It accepts a base64-encoded JSON object and can retry only an already-exposed model tool. Decoding does not grant a new capability: the target tool's normal schema, approval, route classification, provider boundary, and replay protection still apply. The helper cannot target itself, approval-decision internals, or unexposed tools, and invalid/oversized payloads fail closed.

## Threat model

| Threat | Mitigation |
|---|---|
| SSRF / private-network media fetch | Public-URL media delegated to WPVibe's current URL/redirect/DNS checks |
| CSRF against admin approvals | WordPress cookie authentication + nonce; only token owner may approve Direct-MCP receipts |
| Confused deputy / privilege escalation | Token owner must stay Administrator; downstream route permission callbacks preserved |
| Path traversal / arbitrary file write | Theme writes delegated to WPVibe file sandbox; device upload never chooses a server directory |
| Arbitrary executable upload | Browser transfer accepts image MIME only; SVG requires sanitizer |
| PHP upload / polyglot risk | Raster MIME detected from bytes; SVG handled by WPVibe path; no arbitrary extension allowlist |
| REST credential/control-plane abuse | Direct MCP auth namespace, Application Password routes, and audited WPVibe Worker/control-plane routes blocked in generic REST |
| Destructive CLI proof forgery | Model receives only Direct approval IDs; Worker operation-proof secrets and approved route stay inaccessible |
| Destructive CLI drift/replay | Approval binds exact command + upstream snapshot and is single-use |
| Armored payload used as a bypass | Only already-exposed tools can be targeted; normal target-tool safety semantics still run |
| Token/log leakage | Hash-only token storage; tool log summaries exclude token/body/binary data |
| SVG stored XSS | Current WPVibe SVG sanitizer required; no Direct-MCP bypass |
| Malicious redirects / DNS rebind | WPVibe public URL downloader validates redirect destinations and pins validated public IPs where supported |
| Replay | Browser upload and Direct approvals use expiring one-time state |
| Approval bypass | Direct MCP honors, but cannot enable, WPVibe's browser-admin bypass setting |
| MCP prompt/tool injection | Skills require read-first behavior; authorization is enforced in code, not trusted prompt text |
| Secret disclosure via wp-content diagnostics | WPVibe's scoped diagnostic reader/redaction remains authoritative |

## Reporting security issues

For vulnerabilities in this Direct MCP bridge, report them privately to the repository owner rather than opening a public exploit issue. For vulnerabilities in official WPVibe itself, follow WPVibe's published security-contact process.

## Optional external providers (1.3.0)

`page_audit`, `search_images`, `render_browser`, and PDF text extraction are disabled unless the site owner supplies a provider via the documented WordPress filters. Direct MCP stores no WPVibe cloud credential and does not claim these providers are WPVibe-hosted services. Provider operators are responsible for outbound-network policy, credentials, rate limits, data handling, and licensing.

## MCP Apps security

Inline approval uses a second high-entropy panel token that is hash-stored, token-owner-bound, expires after 15 minutes, is delivered only in tool-result `_meta`, and is consumed after one decision. The model-visible payload is bounded and redacts secret-looking keys. The upload app posts only to the random, one-time media ticket endpoint; it never receives the main MCP bearer token. Browser approval/upload URLs remain available as fallback.

The MCP App HTML currently imports the official `@modelcontextprotocol/ext-apps` browser client from a version-pinned `esm.sh` URL. The app CSP allows that resource domain; production operators with stricter supply-chain policies should proxy/vendor an audited copy before enabling inline apps in a locked-down environment.

## Hosted/provider boundary

Account/site registry, fleet, usage reset, stock search, Lighthouse, screenshots, browser rendering and SeedProd browser automation do not silently call WPVibe private infrastructure. They fail closed unless a site owner configures a provider. Provider code is responsible for its own network egress, credential storage, SSRF policy, retention and licensing.
