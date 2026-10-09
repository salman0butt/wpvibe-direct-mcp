# Rest Api

Use discover_rest_routes before calling unfamiliar routes. Prefer GET for inspection and scoped POST/PUT/PATCH/DELETE only on documented routes. Direct MCP blocks credential-management and its own auth routes; do not attempt to bypass those protections. Use fields and response limits for large payloads.

## Workflow

Inspect current state first, make the smallest supported change, verify the result, and preserve WordPress/WPVibe capability and approval checks.
