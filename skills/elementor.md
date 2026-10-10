# Elementor / Elementor v4

Use an Abilities-first workflow on current Elementor. Start with `discover_abilities`, locate the installed `elementor/*` abilities, and inspect every intended operation with `get_ability_info` before calling it. Elementor 4.3+ on WordPress 6.9+ can expose native abilities for listing content, reading page structure, creating Elementor pages, building atomic compositions, managing elements, page settings, publishing, preview links, widget schemas, assets, components, dynamic tags, resources, global variables, global classes, class ordering, default styles, style/WordPress best-practices resources, and related guides. Runtime discovery is authoritative; never assume an ability exists because a different Elementor site had it.

Many build/style abilities require Elementor's Atomic Editor. If an installed ability returns the documented Atomic Editor/feature-gate error, report that prerequisite instead of falling back to guessed private data. Elementor Pro may add site-part/component/template capabilities, but free Elementor is sufficient for the core page/Atomic workflow. Classic widgets can appear in page structure without fully readable settings; do not fabricate their controls.

## Read before every write

Inspect the current page/resource and the exact ability schema before mutation. `elementor/update-page-settings` replaces the complete settings object rather than merging one field, so read the current settings first, preserve every unrelated property, change only the requested values, and send the complete safe object back. Never send a WordPress page template through that settings ability; set the page template through the supported WordPress REST/native page-template path instead.

Global variables, classes, class order, and default styles are site-wide. Treat them as broad-impact writes: inspect current values, show/describe the intended change through the normal approval flow, preserve unrelated values, and verify representative pages afterwards.

## Page development workflow

1. Inspect site/plugin versions and `discover_abilities` for the `elementor` namespace.
2. Read the target page structure/settings, or create a draft with the installed Elementor ability/native path.
3. Discover widget/atomic schemas and relevant Elementor resources; never guess element types or property shapes.
4. Create/reuse global variables and classes only when the design needs them.
5. Build the composition or apply bounded element changes with stable IDs and schema-valid properties.
6. Preserve document type, post status, page template, responsive settings, display conditions, classes/variables, interactions, and unrelated page/site settings.
7. Re-read page structure after each meaningful write. If publishing was requested, run the installed publish ability/native flow only after review/approval.
8. Verify the result as a visitor: use a preview link plus `get_page_html`/browser rendering and, when configured, `screenshot_page`. Published Elementor edits can land in autosaves or remain behind Elementor element/CSS caches, so a successful write response is not final proof that the live page changed.
9. If the live page is stale, use the installed supported publish/cache-refresh path (for example WPVibe's allowlisted Elementor cache purge command) and verify again rather than rewriting private `_elementor_data`.

## Compatibility fallback

When modern Elementor Abilities are absent, use WPVibe's native Elementor routes when they are installed: `elementor_widgets`, `elementor_schema`, `elementor_style_schema`, `elementor_save_page`, and `elementor_save_template`. They use Elementor/WPVibe's native save and verification paths and are preferred over generic meta writes. Never write `_elementor_data` generically when either an Elementor Ability or native WPVibe Elementor route covers the task.
