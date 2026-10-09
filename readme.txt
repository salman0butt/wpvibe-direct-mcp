=== WPVibe Direct MCP ===
Contributors: community
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Adds a self-hosted MCP transport to the WPVibe WordPress plugin.

== Description ==

WPVibe Direct MCP keeps WPVibe's existing WordPress-side safety and tool layer, but lets an MCP client connect directly to your WordPress installation instead of routing through the hosted WPVibe MCP gateway.

It dynamically exposes only the WPVibe routes available on the site, including theme file tools, draft-theme preview and publishing, content search/edit, allowlisted WP-CLI emulation, public-URL and device/browser media uploads, rendered HTML, audit logs, WordPress Abilities, native Elementor/Beaver/Bricks/Breakdance tools, capability-checked WordPress REST routes, route discovery, browser approvals, and original workflow skills supplied by this bridge.

== Installation ==

1. Install and activate WPVibe.
2. Install and activate WPVibe Direct MCP.
3. Open WPVibe > Direct MCP.
4. Generate a token and copy it once.
5. Add the endpoint and Bearer token to an MCP client.

== Security ==

* Tokens are stored only as SHA-256 hashes.
* Each token belongs to one WordPress administrator.
* All tool calls run as that user and still pass WordPress/WPVibe capability checks.
* Application-password management routes and this plugin's own endpoint are blocked from the generic REST tool.
* Valid URL query tokens are supported for ChatGPT No Authentication mode; bearer headers remain preferred where available.
* Activity logs do not store file contents or request bodies.
