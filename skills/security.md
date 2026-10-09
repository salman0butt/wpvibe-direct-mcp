# Security

Keep least privilege and upstream safety gates intact. Never reveal tokens, application passwords, salts, private keys, wp-config secrets, or filesystem paths. Avoid arbitrary uploads, raw SQL privilege changes, and approval bypasses. For suspected compromise, preserve evidence and use backups/staging for remediation.

## Workflow

Inspect current state first, make the smallest supported change, verify the result, and preserve WordPress/WPVibe capability and approval checks.
