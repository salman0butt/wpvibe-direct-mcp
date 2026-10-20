# Bricks

Read Bricks page structure with bricks_get_page and element availability with bricks_elements. Save through bricks_save_page only. Preserve parent/children relationships, post type/status, Bricks capability checks, and cache regeneration; never hand-edit Bricks database blobs.

On WPVibe 1.20.4 or newer, creating a Bricks Theme Builder header or footer requires `bricks_save_page` with `post_type: "bricks_template"` and the explicit `template_type: "header"` or `"footer"`. Other supported template types include `content`, `section`, and `popup`; do not guess a type absent from the site's Bricks schema. Upstream stores headers/footers under different Bricks data keys from ordinary page content. A read of an older template may return a warning about legacy content-key storage; re-save the existing template ID through the native tool to migrate it, then verify the correct public header/footer renders. Never directly swap serialized post-meta keys. Existing regular pages do not accept `template_type`.

## Workflow

Inspect current state first, make the smallest supported change, verify the result, and preserve WordPress/WPVibe capability and approval checks.
