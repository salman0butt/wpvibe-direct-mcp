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

For MCP clients that cannot send an Authorization header:

```text
https://example.com/wp-json/wpvibe-direct/v1/mcp?token=YOUR_TOKEN
```

This is compatibility behavior, not the preferred authentication method. URLs are commonly recorded by reverse proxies, browser history, analytics, support screenshots, and server logs. Rotate the token from wp-admin after any suspected exposure.

## Health endpoint

Authenticated diagnostics:

```text
https://example.com/wp-json/wpvibe-direct/v1/health
```

It returns Direct MCP/WPVibe versions and endpoint metadata. It never returns the access token/hash.

## Updating from 1.0.4

1. Keep your current token if you want existing clients to continue connecting; 1.3.0 preserves the authentication formats.
2. Replace the plugin folder/ZIP and reactivate if WordPress asks.
3. Open **WPVibe → Direct MCP** and verify detected capabilities.
4. Re-open the MCP client so it refreshes its tool list.
5. Prefer Bearer authentication if you previously used a URL token.
6. Use `request_upload` for local/chat attachments; the old `upload_media` remains the public-URL import tool.

The 1.3.0 release is additive for existing tool names. New route-backed tools appear only when the installed WordPress/WPVibe combination supports them.
