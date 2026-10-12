# Ultimate Addons for Elementor

Use this playbook for Ultimate Addons for Elementor (UAE) 2.9.5+ with Elementor on WordPress 6.9+. The currently verified header/footer workflow assumes a classic theme. Do not invent Elementor or UAE private serialization; use the WordPress Abilities that UAE registers.

Begin with `discover_abilities` using category `hfe-templates`. The expected abilities use the `uae/` namespace. If none appear, tell the user to open UAE > Settings > AI Tools and turn on both **Enable Abilities** and **Enable Edit Abilities**, then rediscover. Do not pretend those settings were changed when the abilities are still absent.

Before calling an ability, use `get_ability_info` and follow the schema returned by the installed UAE version. Use `run_ability` for reads and writes and preserve Direct MCP's normal approval flow for destructive operations. Ability names and widget/settings keys must come from discovery/schema results; never guess them from Elementor database structures.

For a new site header, footer, or before-footer template, inspect the current templates and display rules first. Build new work as a draft when the installed ability supports draft state. Add or change one logical section at a time, then read the template back. For an existing template, preserve unrelated widgets, responsive settings, and display rules unless the user explicitly asked to change them.

Display rules decide where a UAE template appears. Read the current rules before editing and state the intended audience/scope in plain language. For page-specific or logged-in variants, make the narrowest rule that satisfies the request and verify that the site-wide template still applies elsewhere. Do not publish or broaden a rule simply because a template exists.

After changes, verify through the available UAE/Elementor read abilities and then use `get_page_html` on representative frontend URLs. For visual or JavaScript-dependent verification, use an available browser/screenshot provider only when configured; do not fabricate rendered state. Check at least one page where the template should apply and one where a narrow display rule should not apply when that distinction matters.

If the site is not using a classic theme, stop and reassess the installed integration instead of assuming the verified UAE header/footer recipe applies. If UAE abilities disappear after an update or settings change, rediscover them and fail closed. Elementor Pro Theme Builder is a separate integration boundary; do not switch to private Elementor Pro data or claim Pro verification when the task is using UAE on free Elementor.
