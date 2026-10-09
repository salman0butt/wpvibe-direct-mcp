# WPVibe Direct MCP setup

1. Keep the official **WPVibe** plugin installed and active.
2. Upload and activate `wpvibe-direct-mcp`.
3. Open **WPVibe → Direct MCP** in wp-admin.
4. Generate a token and copy it immediately.
5. Configure the MCP client with the endpoint shown in wp-admin and this header:

```text
Authorization: Bearer YOUR_TOKEN
```

Example configuration:

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

The hosted WPVibe connection is not required for this direct endpoint. The official WPVibe plugin remains responsible for its protected REST operations, capability checks, theme sandbox, PHP linting, WP-CLI allowlist, Elementor integration, and audit log.

For clients that cannot send HTTP headers, wp-admin has an optional query-token mode. It is disabled by default because URLs are more likely to be logged.


## Health check
Use `/wp-json/wpvibe-direct/v1/health?token=YOUR_TOKEN` in a browser. The MCP endpoint itself intentionally returns HTTP 405 to GET requests because this server is stateless and does not expose standalone SSE.
