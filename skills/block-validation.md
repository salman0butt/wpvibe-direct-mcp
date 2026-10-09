# Validated Block Output

Never write generated Gutenberg markup first and validate later. Build or parse the complete block tree, call `validate_blocks` in strict mode, fix unknown block names and registered attribute type/enum violations, then use `save_validated_blocks` for the actual REST write. Validation is recursive and covers core blocks plus installed third-party registrations such as Kadence and GenerateBlocks. Preserve unknown attributes only when their installed schema allows them; installed registry data, not model memory, is the contract.
