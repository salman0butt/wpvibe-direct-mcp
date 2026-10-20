# Beaver Builder

Use beaver_modules and beaver_schema before composing node data, then beaver_save_page. Do not rewrite Beaver serialized/meta storage through generic REST. Preserve enabled post types, layout metadata, builder permissions, post status, and generated CSS/cache behavior.

WPVibe 1.20.4+ also accepts `beaver_schema` without a slug to list available modules. The layout nodes `row`, `column-group`, and `column` do not require a module schema: their settings may be empty, and columns use `settings.size` as a percentage. Older Beaver Builder versions without `FLBuilderModel::user_has_unfiltered_html()` are supported by upstream's WordPress capability fallback; let the native save path enforce HTML permissions and report PHP errors. After a failed save, read back the post rather than immediately repeating a potentially partially applied write.

## Workflow

Inspect current state first, make the smallest supported change, verify the result, and preserve WordPress/WPVibe capability and approval checks.
