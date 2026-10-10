# Elementor Atomic

Use Elementor's WordPress Abilities API as the primary external automation surface on Elementor 4.3+ / WordPress 6.9+. Call `discover_abilities`, inspect each operation with `get_ability_info`, and validate inputs against the installed schema before execution. Read-only abilities run directly; every non-read ability keeps Direct MCP approval semantics unless the site owner explicitly enabled WPVibe's dangerous approval bypass.

Atomic build/style operations can require Elementor's Atomic Editor. A feature-gate/403 response means the site prerequisite is missing; do not substitute guessed `_elementor_data` or classic-widget settings. Runtime discovery decides which abilities/resources are usable on this site.

## Safe composition workflow

1. Read the page structure and relevant site/page resources.
2. Discover widget schemas before creating elements.
3. Read/create only the global variables and classes actually needed by the requested design.
4. Build compositions or manage elements with schema-valid atomic properties and stable element IDs.
5. For page/site settings, read the complete current object before changing it. `elementor/update-page-settings` is replacement semantics, not merge semantics.
6. Set WordPress page templates through WordPress/native page-template APIs, not through Elementor page settings.
7. Treat global variables/classes/default styles as site-wide writes and preserve unrelated values.
8. Re-read the structure after mutation. When publishing is requested, publish through the installed ability/native flow, then verify preview/live rendered output. Elementor autosaves and element/CSS caches can make the live page differ from a successful write response.

Prefer installed Elementor abilities and WPVibe native Elementor routes over private-meta manipulation at every step.
