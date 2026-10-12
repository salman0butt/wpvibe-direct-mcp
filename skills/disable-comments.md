# Disable Comments

Use this playbook for Disable Comments 2.9+ on WordPress 6.9+. Its Abilities register automatically, so start with `discover_abilities` using category `disable-comments`. Inspect the exact installed schemas with `get_ability_info` and execute them through `run_ability`.

Always begin with `disable-comments/get-status`. If `is_network_active` is true, stop before changing settings or deleting comments; this playbook does not assume multisite/network-admin semantics. Also report role exemptions, conditional rules, current disabled post types, product-review state, and any allowlisted comment types that affect the result.

For ordinary enable/disable changes, prefer `disable-comments/set-status`. Read the existing state first and preserve the returned previous mode/post-type list so the change can be undone. The post-type list is replace-all state, so build the complete intended list rather than sending only the newly requested type. The ability requires its confirmation field when the discovered schema says so. On WooCommerce sites, remember that product reviews are comments: avoid an everywhere mode when the user wants reviews to stay available, and normally leave `product` out of the disabled set unless the user explicitly asks otherwise.

Turning comments off hides or blocks commenting; it does not necessarily delete existing rows. For destructive cleanup, first discover `disable-comments/delete-comments` and inspect its schema. Never treat deletion as reversible. Count existing rows before deletion and show the user what will be affected. A read-only `run_wp_cli` database query may be used to group counts by post type and comment type when the installed WPVibe command catalog permits it.

Never use delete mode `everywhere` for routine cleanup. It can remove every row in the comments table, including WooCommerce `order_note` rows and data stored there by other plugins. Prefer the ability's post-type mode, use `dry_run` first, inspect the matched count, and require the normal browser approval for the destructive call. If the count includes unexpected comment types, block the delete until the user understands the impact. Advise a backup before permanent deletion.

For a confirmed delete, use the same post types that were dry-run, set the ability's confirmation input exactly as its current schema requires, and do not broaden the scope between approval and execution. Recount afterward and compare before/after results. If products were included, note that WooCommerce rating aggregates may need their normal recount/update path.

For diagnostics such as “why is commenting closed?”, read the target post's `comment_status`, WordPress discussion options, and `disable-comments/get-status` before changing anything. Distinguish the post-level setting from Disable Comments rules; an open post can still have comments disabled globally. Keep this diagnostic path read-only unless the user separately asks for a change.
