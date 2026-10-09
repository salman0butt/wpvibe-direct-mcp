# Kadence Blocks

Detect Kadence from registered `kadence/*` Gutenberg blocks before editing. Inspect the existing block tree and registered attribute schemas, preserve inherited responsive values and global palette references, and validate the complete nested tree with `validate_blocks` before any write. Use `save_validated_blocks` for post-content changes so invalid or unregistered blocks cannot be persisted. Do not invent Kadence attributes from memory; installed schemas are authoritative. For theme-level CSS or template work, use the draft-theme workflow and preview before publish.
