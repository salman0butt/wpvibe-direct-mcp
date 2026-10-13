# Elementor Atomic

Use this workflow for Elementor 4.3+ Atomic Editor sites on WordPress 6.9+. Discover the installed Elementor abilities first with `discover_abilities`, then inspect each operation with `get_ability_info`; do not assume an ability, widget type, property shape, resource, or Elementor Pro feature exists merely because another site exposes it.

## Build workflow

1. Read the existing document with `elementor/get-page-structure` when available. For a new page, create a draft with `elementor/create-page`.
2. Read `elementor/style-best-practices` and `elementor/wordpress-best-practices` when exposed. Inspect `elementor/list-widget-schemas`, `elementor/get-widget-schema`, and relevant `elementor/list-resources` / `elementor/read-resource` data before composing elements. Read the schema for every element type, including `e-flexbox` / `e-div-block`; unsupported prop names can otherwise be skipped with warnings while Elementor still reports build success.
3. Inspect or create reusable design tokens first: global variables (`elementor/manage-global-variable`) and global classes (`elementor/manage-classes`). Read existing global resources before mutation and reuse existing tokens where possible.
4. Build larger Atomic sections with `elementor/build-composition`. Direct MCP automatically runs Elementor's `dry_run` compiler after the exact one-time approval is consumed and before persistence, and refuses the write when warnings are present. An explicit `dry_run: true` call remains available when you deliberately want validation output and follows the normal mutation-approval policy.
5. Use only element IDs from the successful build response's `resolved_xml` for subsequent `elementor/manage-elements` calls. Never use IDs from a page-structure read taken before that build because Elementor's read-modify-write manage path can restore the old tree and overwrite the new composition.
6. Make targeted updates, moves, duplicates, or deletes with `elementor/manage-elements`. Treat `partial_error`, `error`, or per-operation warnings as incomplete work: re-read the current structure, repair failed operations using current IDs, and verify again before reporting success.
7. For interactions/dynamic content, inspect `elementor/interactions-schema-resource`, `elementor/list-dynamic-tags`, assets and components before writing. Do not invent unsupported schemas.
8. `elementor/update-page-settings` accepts a partial settings object, but Elementor 4.3.4's upstream MCP implementation currently replaces the stored settings object. Direct MCP compensates after exact approval consumption by merging the requested keys into the latest raw Elementor page settings before dispatch. Send only keys you intend to change; Direct preserves unrelated settings. A WordPress page template belongs on the supported WordPress/native page-template path, not inside Elementor page settings.
9. Create a short-lived preview with `elementor/create-preview-link` when supported and verify the rendered output. Browser/screenshot providers are preferred for visual verification when configured.
10. Publish through `elementor/publish-document` only after review/approval. Re-read structure and verify the live rendered URL afterwards; published Elementor data can be affected by autosaves, generated CSS and caches.

## Site-wide styles

`elementor/manage-global-variable`, `elementor/manage-classes`, `elementor/reorder-classes`, and `elementor/manage-default-styles` are site-wide operations. Read the corresponding resources/defaults first, preserve unrelated values, describe broad impact before mutation, and verify representative pages after the change. `manage-global-variable`, `manage-classes`, and `manage-default-styles` can return partial batch success; Direct converts those responses to actionable MCP errors with `partial_applied` plus the upstream per-operation result. Re-read the affected global state and repair only failed operations instead of replaying successful siblings.

Apply a Global Class to an Atomic element by passing its **label** in `elementor/manage-elements` `classes:[...]`; Elementor resolves the label to its native ID. With `include_content:true`, `elementor/get-page-structure` exposes the resolved IDs in the normalized public `settings.classes` array while local Atomic style metadata is serialized separately under `styles`. Use compatible global variables through supported CSS such as `background-color: var(--brand-token)` rather than constructing private variable PropValue arrays. To prove a reusable design system really works, attach the same class/variable to at least two representative pages, publish/render them, update the shared class/variable once, and verify both pages' generated CSS changes without another `manage-elements` write.

A local Atomic style can coexist with the shared global class; verify intended overrides against generated/live CSS, including cascade order when specificity is equal. Variable deletion is tombstone-based: the resource may retain the ID with `deleted=true`, which means it is inactive. Global Classes also has its own Elementor capability gate; never bypass it. When deleting an in-use global class, re-read dependent documents and confirm Elementor's cleanup removed the class ID from each public structure. Per-widget/local values can override global styles, so a global rebrand is not complete until rendered pages are inspected.

## Capability boundaries

Many current Elementor building/styling abilities require Atomic Editor. A capability/403 response when Atomic Editor is disabled is an Elementor site capability result, not an MCP transport failure. Use supported native/classic Elementor routes when they can satisfy the request. Components and some site-wide capabilities can require Elementor Pro; discover them rather than fabricating them.

Readonly abilities may execute directly. Write operations retain Direct MCP approval semantics. The WordPress Abilities REST method contract is: readonly -> GET; destructive + idempotent -> DELETE; all other mutations, including destructive non-idempotent operations -> POST.

Never bypass supported APIs by directly replacing `_elementor_data` for ordinary Atomic development. If a supported Ability/native route cannot represent the requested change, stop with the missing capability/schema instead of guessing private serialization.
