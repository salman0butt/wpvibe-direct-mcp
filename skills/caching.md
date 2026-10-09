# Caching

Identify cache layers before purging anything: page cache, object cache, CDN, builder CSS, and browser cache are different. Make the underlying fix first, then purge the narrowest relevant cache through supported plugin/WP-CLI routes. Do not disable caching globally to mask a defect.

## Workflow

Inspect current state first, make the smallest supported change, verify the result, and preserve WordPress/WPVibe capability and approval checks.
