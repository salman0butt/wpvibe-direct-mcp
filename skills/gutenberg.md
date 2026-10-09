# Gutenberg / Block Editor

Treat the installed block registry as the schema authority. Read the current post before editing, preserve block comments and nested relationships, and never invent third-party attributes from memory. Build or parse the complete block tree and run `validate_blocks` in strict mode. Fix unknown block names plus registered attribute type/enum errors before writing. Use `save_validated_blocks` for post-content changes so validation happens before the REST mutation and the write is covered by a one-time browser approval.

For reusable patterns, templates, navigation, or global styles, first discover the relevant WordPress REST routes or Abilities and preserve existing theme.json/global-style semantics. Create drafts unless publication is explicitly requested, inspect rendered HTML after meaningful changes, and avoid converting blocks to generic HTML when the installed block is still available.
