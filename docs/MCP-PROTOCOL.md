# MCP Protocol Compatibility

Direct MCP 1.3.0 serves modern and legacy MCP clients from one stateless WordPress REST endpoint.

## Modern: 2026-07-28

- `server/discover` with supported versions, server info, cache hints and capabilities.
- Stateless HTTP JSON-RPC; no Direct-MCP session store or required `Mcp-Session-Id`.
- Validates `Mcp-Method` and, when applicable, `Mcp-Name` against the JSON-RPC body.
- Supports `tools/list`, `tools/call`, `prompts/list`, `prompts/get`, `resources/list`, and `resources/read`.
- Advertises the MCP Apps extension `io.modelcontextprotocol/ui` with MIME `text/html;profile=mcp-app`.
- Inline approval and upload apps are served as `ui://wpvibe-direct/approval-panel` and `ui://wpvibe-direct/upload-panel`.
- Modern `initialize` and legacy `ping` are not exposed as modern methods.

## Legacy protocols

Supported strings: `2025-11-25`, `2025-06-18`, `2025-03-26`, and `2024-11-05`. Legacy clients retain `initialize`, initialized notifications, `ping`, tools, prompts and resources.

## HTTP transport

- MCP JSON-RPC: `POST /wp-json/wpvibe-direct/v1/mcp`.
- Standalone GET/SSE is intentionally unsupported; GET returns 405.
- OPTIONS advertises accepted auth/routing headers.
- Authenticated diagnostics use `/wp-json/wpvibe-direct/v1/health`.
- Notification-only POSTs/batches return HTTP 202 with no JSON-RPC body.
- Actual `notifications/*` methods remain fire-and-forget. `tools/call` always requires a JSON-RPC request `id`; omitting it fails before executing the tool, rather than silently mutating the site and returning HTTP 202. Legacy mixed batches return a JSON-RPC Invalid Request error with null `id` for an invalid idless tool call while preserving genuine notifications and valid requests.

## MCP Apps

`show_approval_panel` and `request_upload` carry MCP App UI metadata. Supporting clients render the apps inline; non-supporting clients can continue to use the returned secure browser URL. **The approval app is read-only** and displays the pending operation with its WordPress admin approval URL. Actual approve/reject decisions require an authenticated WordPress administrator session and nonce. Neither the MCP bearer token nor an MCP App token can authorize a requested mutation; the earlier bearer-reachable `approval_decide` path now fails closed. The inline image-upload panel remains interactive for its independently scoped upload ticket.

## What is deliberately not claimed

Direct MCP does not claim standalone SSE, a persistent MCP session store, the Tasks extension, or WPVibe's private hosted account/fleet implementation. Hosted-style exact tool names are implemented through explicit user-owned providers and fail closed when no provider exists.
