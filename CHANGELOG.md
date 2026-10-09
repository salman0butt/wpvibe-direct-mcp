# Changelog

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
