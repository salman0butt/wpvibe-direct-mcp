# MCP Protocol Compatibility

Direct MCP 1.1.0 serves two MCP eras from the same stateless WordPress REST endpoint.

## Modern: 2026-07-28

Implemented behavior:

- `server/discover`
- no required initialize/session state
- protocol version in HTTP and per-request `_meta`
- `Mcp-Method` validation on each modern single request
- `Mcp-Name` validation when the request body names a tool/resource/prompt/task identifier
- header/body protocol-version mismatch -> JSON-RPC `-32020`
- unsupported protocol -> `-32022`
- server metadata in response `_meta`
- list results include `resultType`, `ttlMs`, and `cacheScope`
- `tools/list`, `tools/call`, `prompts/list`, `prompts/get`, `resources/list`
- modern `ping` / `initialize` are not exposed as spec methods and return `-32601`
- no `Mcp-Session-Id` is minted or required
- endpoint stays stateless

The current 2026-07-28 specification replaced initialize/session semantics with self-contained requests and `server/discover`, and added routing headers/cache hints. Direct MCP preserves old clients separately instead of pretending the two eras are identical.

## Legacy: 2025/2024

Supported protocol version strings:

- 2025-11-25
- 2025-06-18
- 2025-03-26
- 2024-11-05

Legacy behavior includes:

- `initialize` version negotiation (unknown legacy proposal falls back to the latest supported legacy revision instead of echoing it);
- `notifications/initialized` and other notifications acknowledged without a JSON-RPC body;
- legacy `ping`;
- tools/prompts/resources listing;
- no required Direct-MCP session store.

## HTTP transport

- MCP JSON-RPC: `POST /wp-json/wpvibe-direct/v1/mcp`
- Standalone GET/SSE is not implemented: GET returns 405.
- OPTIONS reports allowed methods and headers.
- Authenticated diagnostics use `/wp-json/wpvibe-direct/v1/health`, not MCP GET.
- Responses include no-cache behavior where appropriate.
- Notification-only POST/batches return HTTP 202 with no JSON-RPC response body.

## Batches

Legacy/mixed-compatible JSON-RPC arrays are processed item-by-item. Notification-only batches return 202. Direct MCP does not create hidden session state for a batch.

Modern 2026 routing headers describe one HTTP request; clients should prefer one modern RPC per HTTP POST, matching current SDK behavior.

## Resources

Direct MCP currently advertises an empty resource list. It does not claim `resources/read` because no first-class resource model is implemented; WordPress data is exposed through explicit tools instead.

## MRTR / MCP Apps / tasks

Direct MCP does not currently implement the 2026 MRTR input-request mechanism, the Tasks extension, or hosted MCP Apps. Its local browser upload and approval URLs are explicit tool/application handles, which keeps the core endpoint stateless while providing a safe fallback for clients that cannot render richer hosted UI.
