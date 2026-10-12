# Provider Availability Metadata Design

## Goal

Expose non-secret provider readiness on provider-backed MCP tool definitions without hiding audited WPVibe public tool names, invoking providers, or changing execution/fail-closed behavior.

## Boundary

Provider-backed tools stay in `tools/list` even when no provider is configured. Direct MCP continues to return `provider_unavailable` when a required provider is absent. Availability metadata is diagnostic capability metadata only; it is not authorization and does not assert that every non-provider prerequisite is satisfied.

Metadata lives at `_meta['io.wpvibe.direct/provider']` and preserves any existing `_meta` values. It contains only fixed tool/provider descriptors and booleans; it never contains provider return values, credentials, callback identities, filter contents, URLs supplied as credentials, or secrets.

Required-provider metadata:

- `backed: true`
- `configured: <bool>`
- `mode: 'required'`
- `failClosed: true`
- `provider: <stable non-secret provider name>`

`get_page_html` is hybrid:

- provider `render_browser`
- `mode: 'optional_same_site_fallback'`
- `fallback: 'wpvibe_rendered_html_same_site'`
- `externalUrlRequiresProvider: true`

## Provider detection

Local provider `<name>` is configured when either:

- `$GLOBALS['wpvdmcp_providers'][<name>]` is callable, or
- WordPress reports a filter on `wpvdmcp_<name>`.

Reference/hosted provider `<name>` is configured when either:

- `$GLOBALS['wpvdmcp_reference_providers'][<name>]` is callable, or
- WordPress reports a filter on `wpvdmcp_reference_<name>`.

Detection must use `is_callable`/`has_filter` only. `tools/list` must never call provider callbacks or execute provider filters.

## Tool mapping

Local required providers:

- `page_audit` / `audit_page` → `page_audit`
- `search_images` → `search_images`
- `render_browser` → `render_browser`

Local hybrid provider:

- `get_page_html` → `render_browser`

Reference required providers:

- `screenshot_page`
- `seedprod_compile_page`
- `connect_site`
- `list_sites`
- `remove_site`
- `get_profile`
- `start_fleet_job`
- `show_fleet_dashboard`
- `use_usage_reset`

Each reference tool maps to the same-named `wpvdmcp_reference_*` provider.

`inspect_media` is intentionally not marked provider-backed because the tool remains useful without the optional PDF-text provider.

## Compatibility constraints

- Exact public tool-name parity remains unchanged.
- MCP Apps `_meta` stays intact.
- Provider callbacks/filters are not invoked during discovery.
- No provider input/output or execution path changes.
- `provider_unavailable`, approvals, Worker proofs, auth, native WPVibe fallbacks, and release semantics are unchanged.
- `v1.4.1` is not moved or republished.

## Verification

Tests must prove missing-provider metadata, callback-provider detection, filter-provider detection, no discovery invocation, hybrid fallback metadata, preservation of existing `_meta`, exact tool-name visibility, non-provider omission, and unchanged fail-closed tool execution.
