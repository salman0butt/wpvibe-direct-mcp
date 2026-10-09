# Compatibility Architecture

## Audited baseline

Direct MCP 1.1.0 was audited against:

- official WPVibe WordPress plugin 1.20.3;
- upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6` from 2026-10-06;
- WordPress.org requirements: WordPress 6.0+, PHP 7.4+, tested through WordPress 7.1.3 at audit time.

## Feature detection first

Direct MCP does not expose every possible route-backed tool unconditionally. At runtime it asks WordPress for registered REST routes and lists only tools whose required WPVibe/native route exists.

This permits a reasonable version range:

- an older WPVibe installation still receives the tools it actually supports;
- a newer installation can expose compatible routes without a Direct MCP release just to change a version string;
- unavailable features do not appear as fake tools that fail later.

The wp-admin screen shows installed versions, available tool count, feature flags, and a warning when WPVibe is newer than the version last audited by Direct MCP.

## Version-dependent behavior

### WordPress Abilities

Requires WordPress 6.9+ and the `/wp-abilities/v1/abilities` REST namespace. If unavailable, Abilities tools are hidden / return an actionable unavailable error rather than guessing routes.

### WPVibe 1.20-era contracts

1.1.0 understands current fields such as:

- file `scope` and wp-content diagnostic reads;
- `list_files.directory`;
- compile/source hashes where exposed;
- recursive file-pattern semantics managed by WPVibe;
- `publish_draft_theme.saved_customizations = set_aside|keep`;
- current builder routes;
- dormant WPCode snippet route;
- operation receipts;
- current SVG sanitizer/media behavior.

The audit did **not** find an offset/pagination field on WPVibe 1.20.3's theme/wp-content `file/list` route, so Direct MCP does not invent one.

## Builders

Dedicated 1.20.3 integrations detected in upstream source:

- Elementor
- Beaver Builder
- Bricks
- Breakdance

Direct MCP exposes native route-backed tools conditionally.

Gutenberg uses WordPress REST/block behavior and, where relevant, Abilities. SeedProd and other plugins can surface capabilities through native REST/Abilities. No dedicated 1.20.3 WPVibe save class was found for Divi or WPBakery, so Direct MCP does not reverse-engineer their private/serialized storage. It supplies original workflow skills that tell the agent to discover a supported native route first.

## DISALLOW_FILE_EDIT / DISALLOW_FILE_MODS and theme safety

Direct MCP does not override WordPress/WPVibe file-edit policy. If WPVibe refuses an operation because file editing/modification, draft state, PHP validation, path policy, or another safety gate blocks it, Direct MCP returns that upstream error.

## WP-CLI

`run_wp_cli` is WPVibe's native PHP-dispatch command catalog, not a shell bridge. Direct MCP does not add `exec`, shell access, `eval`, or `eval-file` and does not duplicate the catalog. Command availability therefore follows the installed WPVibe version.

## Hosted vs direct

The official open-source WPVibe repository documents itself as the WordPress-side component while the hosted gateway/account/tool registration lives at wpvibe.ai. Direct MCP replaces only the transport/account dependency needed for one site; it does not recreate account/fleet/plan/cloud services.
