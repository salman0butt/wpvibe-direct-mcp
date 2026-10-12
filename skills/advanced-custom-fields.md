# Advanced Custom Fields (ACF)

Use this playbook for ACF 6.8+ on WordPress 6.9+. Prefer ACF's own WordPress Abilities over guessing ACF post/meta storage.

Start by running `discover_abilities` with category `acf-field-management`. If ACF abilities such as `acf/field-groups` are absent, check that ACF is active and current. ACF's AI abilities are off by default. When the user wants them enabled and WPCode is installed, create a disabled PHP snippet with Direct MCP `code_snippet` using exactly:

`add_filter( 'acf/settings/enable_acf_ai', '__return_true' );`

Do not activate that snippet yourself. Give the user the WPCode review/enable link and wait for the human to enable it. This preserves the WPCode human activation boundary.

After the human enables the snippet, run `discover_abilities` for `acf-field-management` and `wordpress-content-discovery`. Tell the user which `acf/` abilities are actually registered. ACF also has per-object access controls: for every field group, post type, or taxonomy the user wants managed through ACF abilities, have them enable the relevant ACF AI access option in wp-admin. Field groups used for field data should also have **Show in REST API** enabled. Do not assume a missing dynamic ability means an error until those controls are checked.

Before every mutation, call `get_ability_info` for the exact discovered ability and follow its current input schema. Use `run_ability` with structured input. Let Direct MCP apply its normal approval rules for destructive abilities; never add an `approved=true` style bypass. For dynamic post-type/taxonomy abilities, discover the exact names at runtime instead of constructing names from a slug.

For field-value work, read the target entry and current field values first, show the proposed changes, then update only the fields the user asked for. Validate values against the ability schema, especially numbers, choices, dates, relationships, and repeaters. For new content, create drafts first unless the user explicitly asked to publish. Re-read the result after writing and report fields that were rejected or normalized.

For new ACF structures, first confirm `acf/register-custom-post-type`, `acf/register-custom-taxonomy`, and `acf/register-field-group` are present. Propose keys, labels, taxonomy links, field types, and choices before creating them. Re-discover abilities afterward because new ACF post types and taxonomies can add new dynamic abilities.

Treat ACF's AI access switches as capability-scoping for the ACF abilities, not as a guarantee that ordinary WordPress REST cannot access the same content. Do not use generic REST as a workaround when the user's requested workflow specifically relies on ACF's AI access controls. If the user disables the WPCode filter snippet, expect the ACF abilities to disappear and fail closed rather than guessing private ACF storage.
