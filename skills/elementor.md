# Elementor / Elementor v4

Treat Elementor as a builder workflow, not generic post-meta editing. On WordPress 6.9+ with Elementor 4.3+, start with `discover_abilities`, inspect the installed `elementor/*` operations with `get_ability_info`, and follow the schemas actually returned by that site. Prefer those registered Abilities because they are version-aware and schema-described. Use the WPVibe native Elementor routes (`elementor_widgets`, `elementor_schema`, `elementor_style_schema`, `elementor_save_page`, `elementor_save_template`) as the compatible fallback when the newer ability set is unavailable. Never write `_elementor_data` generically when a supported Ability or native Elementor save path exists.

## Current Elementor ability surface

The current Elementor 4.3 generation can expose the following public abilities/resources. Discover them at runtime rather than assuming every site has every item: `elementor/list-posts`, `elementor/get-page-structure`, `elementor/create-page`, `elementor/build-composition`, `elementor/manage-elements`, `elementor/update-page-settings`, `elementor/publish-document`, `elementor/create-preview-link`, `elementor/manage-global-variable`, `elementor/manage-classes`, `elementor/reorder-classes`, `elementor/manage-default-styles`, `elementor/get-default-styles`, `elementor/global-variables-resource`, `elementor/global-classes-resource`, `elementor/list-widget-schemas`, `elementor/get-widget-schema`, `elementor/list-assets`, `elementor/list-components`, `elementor/list-dynamic-tags`, `elementor/interactions-schema-resource`, `elementor/list-resources`, `elementor/read-resource`, `elementor/style-best-practices`, `elementor/wordpress-best-practices`, and `elementor/manage-global-variable-guide`. `elementor/manage-component` may also be registered on supported installations. Elementor Pro can expose additional component/site-part capabilities; always discover instead of inventing them.

Many building/styling abilities require Elementor's Atomic Editor. If an installed ability returns an Atomic-Editor capability/403 response, report that site capability clearly and use a supported classic/native WPVibe Elementor path when it can satisfy the request. Do not misreport the response as an MCP transport failure and do not guess internal serialization.

## End-to-end page development

1. Inspect `site_info` and discover Elementor abilities.
2. Read the existing page/template before editing. For existing pages use `elementor/get-page-structure` when present; inspect widget schemas/resources needed for the requested elements.
3. Prefer editing the existing Elementor document instead of rebuilding it from scratch. Preserve document type, post status, WordPress page template, stable element IDs, responsive values, display conditions, unrelated settings, global classes and variables.
4. For a new Atomic page, create a draft with `elementor/create-page`, then use declared widget schemas/resources and `elementor/build-composition` / `elementor/manage-elements`. For classic/fallback sites, use `elementor_widgets` + `elementor_schema` / `elementor_style_schema`, then `elementor_save_page`.
5. Preview before publishing. Prefer `elementor/create-preview-link` when installed; otherwise use the safe WordPress/native preview path. Use `get_page_html`, `render_browser`, or `screenshot_page` when configured to verify the rendered result rather than trusting only the mutation response.
6. Publish only after the requested review/approval. After `elementor/publish-document` or a native publish save, re-read page structure and inspect the live rendered URL. Published Elementor edits can interact with autosaves and generated CSS/cache, so a successful write response alone is not final verification.

## Settings and site-wide styles

Read before every write. `elementor/update-page-settings` replaces the complete current settings object rather than merging one field. Read the existing settings/resource first, retain every unrelated property, change only the requested values, and send the complete safe object back. Do not send a WordPress page template through this settings ability; use the supported WordPress/native page-template path. If a theme's Hide Title selector does not work, prefer an appropriate Elementor Full Width/Canvas template instead of forcing unrelated theme settings.

Global variables, global classes and default styles are site-wide. `elementor/manage-global-variable`, `elementor/manage-classes`, `elementor/reorder-classes`, and `elementor/manage-default-styles` can restyle many pages immediately. Inspect current resources first, state the intended site-wide impact, retain approval semantics, and verify representative pages afterwards. Before deleting a global class or variable, list the exact target and likely dependents. Remember that per-widget/local styles can override a global rebrand.

## Elementor Pro and Theme Builder

For Elementor Pro headers, footers, singles, archives, search/404 templates, sections and popups, first discover any registered Pro abilities. Otherwise use `elementor_save_template` with the correct type and display conditions. Reuse and update an existing matching Theme Builder template rather than creating duplicate global headers/footers. Verify the active display conditions and a live page where the template should render.

## Safety and compatibility

Classic widgets may expose less-readable settings than Atomic elements; do not guess missing controls. Validate every write against the installed ability/schema. Write/destructive abilities remain approval-gated through `run_ability`. Read-only abilities run directly. Destructive idempotent abilities use DELETE; other writes, including destructive non-idempotent abilities, use POST according to the WordPress Abilities REST contract.
