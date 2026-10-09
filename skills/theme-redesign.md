# Theme Redesign

Begin with `site_intelligence`, `site_info`, and `integration_capabilities`, then create a WPVibe draft theme before filesystem edits. Inspect only relevant files with `list_files`, `read_file`, `search_files`, and `get_file_outline`. Preserve WordPress hooks, template hierarchy, dynamic data, accessibility landmarks, responsive behavior, and existing design tokens. Prefer surgical edits over whole-file rewrites and use registered editable fields for content surfaces that clients should be able to change later.

For block content, validate registered schemas before writes. For builder pages, use native detected routes rather than editing proprietary storage. Check rendered HTML, preview the draft at desktop/mobile widths, and review PHP/CSS/JS changes for regressions. Publish only after the reviewed preview and explicit approval; when Site Editor customizations exist, choose the current WPVibe `saved_customizations` behavior deliberately.
