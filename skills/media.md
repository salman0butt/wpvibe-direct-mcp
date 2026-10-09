# Media

For a public image URL, use upload_media so WPVibe applies SSRF, MIME, filename, and SVG protections. For a user/device attachment that the MCP client cannot transfer, call request_upload, give the user the expiring one-time browser URL, then poll check_upload. Accept only supported images; SVG must pass WPVibe sanitization. Never expose temp/server paths.

## Workflow

Inspect current state first, make the smallest supported change, verify the result, and preserve WordPress/WPVibe capability and approval checks.
