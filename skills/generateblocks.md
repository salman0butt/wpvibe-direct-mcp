# GenerateBlocks

Treat GenerateBlocks as Gutenberg blocks registered under the installed site schema. Inspect the current tree and the actual `generateblocks/*` block definitions before constructing attributes. Preserve global styles, responsive controls, unique IDs, nested container relationships, and semantic HTML. Run `validate_blocks` recursively and use `save_validated_blocks` for writes. Unknown blocks or invalid attribute enums/types must block strict writes rather than being silently stored.
