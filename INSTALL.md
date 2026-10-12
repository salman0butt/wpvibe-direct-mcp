# Installation and Client Setup

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- Official WPVibe plugin (`vibe-ai`) active for route-backed WordPress tools.
- WordPress 6.9+ for the Abilities API tools.
- HTTPS strongly recommended.

## Install

1. Install and activate WPVibe.
2. Upload the `wpvibe-direct-mcp` folder or install the release ZIP from **Plugins → Add New → Upload Plugin**.
3. Activate **WPVibe Direct MCP**.
4. Open **WPVibe → Direct MCP** (or **Direct MCP** if WPVibe's menu is unavailable).
5. Confirm the compatibility table detects the expected WPVibe version, route-backed tools, builders, and media support.
6. Generate a token. Copy it once; the plaintext token is deliberately not stored.

## Preferred MCP configuration

Endpoint:

```text
https://example.com/wp-json/wpvibe-direct/v1/mcp
```

HTTP header:

```text
Authorization: Bearer YOUR_TOKEN
```

Generic client shape:

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://example.com/wp-json/wpvibe-direct/v1/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN"
      }
    }
  }
}
```

## Query-token compatibility

Query-string authentication is disabled by default on current unreleased `main`. For an MCP client that genuinely cannot send an `Authorization` or `X-WPVibe-Direct-Token` header:

1. Open **WPVibe → Direct MCP → Settings**.
2. Enable **Allow query-string token compatibility** and save.
3. Use:

```text
https://example.com/wp-json/wpvibe-direct/v1/mcp?token=YOUR_TOKEN
```

The legacy `?access_token=YOUR_TOKEN` alias follows the same opt-in. This is compatibility behavior, not the preferred authentication method. URLs are commonly recorded by reverse proxies, browser history, analytics, referrers, support screenshots/tooling, and server logs. Keep the option disabled unless required and rotate the token from wp-admin after any suspected exposure. The published `v1.4.1` artifact predates this default-off change.

## Health endpoint

Authenticated diagnostics:

```text
https://example.com/wp-json/wpvibe-direct/v1/health
```

It returns Direct MCP/WPVibe versions and endpoint metadata. It never returns the access token/hash.

## Updating from 1.0.4

1. Keep your current token if you want existing clients to continue connecting. Bearer and `X-WPVibe-Direct-Token` formats remain unchanged; on current unreleased `main`, URL-token clients must explicitly enable query-token compatibility after upgrade.
2. Replace the plugin folder/ZIP and reactivate if WordPress asks.
3. Open **WPVibe → Direct MCP** and verify detected capabilities.
4. Re-open the MCP client so it refreshes its tool list.
5. Prefer Bearer authentication if you previously used a URL token; only enable query-token compatibility when the client cannot send authentication headers.
6. Use `request_upload` for local/chat attachments; the old `upload_media` remains the public-URL import tool.

The 1.3.0 release is additive for existing tool names. New route-backed tools appear only when the installed WordPress/WPVibe combination supports them.
