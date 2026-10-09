# SeedProd

Detect SeedProd and discover its public REST routes or WordPress Abilities before editing. Use plugin-supported create/update operations for its builder JSON; never guess private serialized schemas. WPVibe's audited WordPress-side implementation notes that REST/Abilities writes can update SeedProd data without compiling the frontend HTML that SeedProd's Vue builder writes on Save.

After a supported SeedProd builder-data change, use `seedprod_compile_page` when compilation is required. That approval-gated Direct MCP workflow consumes WPVibe's internal two-minute, single-use `/wpvibe/v1/builder-login` primitive only inside a trusted user-owned browser provider; the raw login URL is never exposed to the model. Then verify rendered HTML and the public/preview URL. Preserve responsive settings, global design tokens, form/integration identifiers, access controls, and publication state.
