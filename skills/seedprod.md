# SeedProd

Detect SeedProd and discover its public REST routes or WordPress Abilities before editing. Prefer plugin-supported create/update/save operations because SeedProd may compile stored builder data into frontend output; a raw post-meta or database write can leave caches, generated CSS, or internal indexes inconsistent. Never guess private serialized schemas.

Inspect the existing landing page first, preserve responsive settings, global design tokens, form/integration identifiers, access controls, and publication state. Use standard WordPress REST only for fields SeedProd exposes safely. After supported changes, verify rendered HTML and the public/preview URL. If no native/public save surface exists, restrict Direct MCP to read/discovery or surrounding theme/content work.
