# Editable Fields

Use `list_editable_fields` before adding definitions. Direct MCP persists field, group, and setting definitions and replays them through WPVibe native field APIs, so values remain ordinary WordPress post meta or options. Supported post field types are text, textarea, number, email, url, date, checkbox, color, image, gallery, wysiwyg, post_select, and repeater; settings intentionally use the smaller upstream-safe subset. Registration is approval-gated. Prefer stable keys, explicit labels, conservative defaults, and REST-visible fields rather than ad-hoc database writes.
