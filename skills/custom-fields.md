# Custom Fields

Call registered_meta for the post type before assuming a meta key can be written through REST. Respect show_in_rest schemas and protected meta rules. Prefer plugin-provided Abilities or REST endpoints when they exist; do not use raw SQL as a shortcut.

## Workflow

Inspect current state first, make the smallest supported change, verify the result, and preserve WordPress/WPVibe capability and approval checks.
