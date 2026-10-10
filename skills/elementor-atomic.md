# Elementor Atomic

Use this workflow for Elementor 4.3+ Atomic Editor sites on WordPress 6.9+. Discover the installed Elementor abilities first with `discover_abilities`, then inspect each operation with `get_ability_info`; do not assume an ability, widget type, property shape, resource, or Elementor Pro feature exists merely because another site exposes it.

## Build workflow

1. Read the existing document with `elementor/get-page-structure` when available. For a new page, create a draft with `elementor/create-page`.
2. Inspect `elementor/list-widget-schemas`, `elementor/get-widget-schema`, and relevant `elementor/list-resources` / `elementor/read-resource` data before composing elements.
3. Inspect or create reusable design tokens first: global variables (`elementor/manage-global-variable`) and global classes (`elementor/manage-classes`). Read existing global resources before mutation and reuse existing tokens where possible.
4. Build larger Atomic sections with `elementor/build-composition`; make targeted updates, moves, duplicates, or deletes with `elementor/manage-elements`. Keep stable element IDs and change only the requested subtree.
5. For interactions/dynamic content, inspect `elementor/interactions-schema-resource`, `elementor/list-dynamic-tags`, assets and components before writing. Do not invent unsupported schemas.
6. Read current page settings before using `elementor/update-page-settings`. That ability replaces the whole settings object, so preserve every unrelated property. A WordPress page template belongs on the supported WordPress/native page-template path, not inside the Elementor settings replacement.
7. Create a short-lived preview with `elementor/create-preview-link` when supported and verify the rendered output. Browser/screenshot providers are preferred for visual verification when configured.
8. Publish through `elementor/publish-document` only after review/approval. Re-read structure and verify the live rendered URL afterwards; published Elementor data can be affected by autosaves, generated CSS and caches.

## Site-wide styles

`elementor/manage-global-variable`, `elementor/manage-classes`, `elementor/reorder-classes`, and `elementor/manage-default-styles` are site-wide operations. Read the corresponding resources/defaults first, preserve unrelated values, describe broad impact before mutation, and verify representative pages after the change. Per-widget/local values can override global styles, so a global rebrand is not complete until rendered pages are inspected.

## Capability boundaries

Many current Elementor building/styling abilities require Atomic Editor. A capability/403 response when Atomic Editor is disabled is an Elementor site capability result, not an MCP transport failure. Use supported native/classic Elementor routes when they can satisfy the request. Components and some site-wide capabilities can require Elementor Pro; discover them rather than fabricating them.

Readonly abilities may execute directly. Write operations retain Direct MCP approval semantics. The WordPress Abilities REST method contract is: readonly -> GET; destructive + idempotent -> DELETE; all other mutations, including destructive non-idempotent operations -> POST.

Never bypass supported APIs by directly replacing `_elementor_data` for ordinary Atomic development. If a supported Ability/native route cannot represent the requested change, stop with the missing capability/schema instead of guessing private serialization.
