# WPVibe Reference Tool Parity Design

## Goal
Make WPVibe Direct MCP expose every current public WPVibe MCP tool name discovered from the live WPVibe Tools Reference, current feature pages/blogs, and the current MCP directory listing, without fabricating WPVibe-hosted account, billing, fleet, or private-cloud data.

## Audited reference surface
Canonical public/site tools: connect_site, list_sites, site_info, remove_site, rest_api, upload_media, search_images, discover_abilities, get_ability_info, run_ability, create_draft_theme, get_preview_url, publish_draft_theme, delete_draft_theme, create_classic_theme, read_file, edit_file, write_file, delete_file, list_files, search_files, get_file_outline, run_wp_cli, get_page_html, navigate, load_skill.

Current hosted/app tools additionally observed: audit_page, check_approval_status, check_upload, code_snippet, get_profile, request_upload, rest_api_write, save_skill, show_approval_panel, show_fleet_dashboard, start_fleet_job. WPVibe's current account update also documents use_usage_reset.

Direct-only useful extensions remain allowed and should not be removed.

## Architecture
1. Add a focused `WPVDMCP_Reference_Parity` facade for exact-name aliases and provider-backed hosted operations.
2. Local aliases must preserve existing security boundaries: `audit_page` delegates to `page_audit`; `rest_api_write` permits POST/PUT/PATCH/DELETE only and reuses Direct MCP REST route hardening; `save_skill` performs approval-gated create-or-update using the existing saved-skill store.
3. Hosted account/site/fleet/reset tools (`connect_site`, `list_sites`, `remove_site`, `get_profile`, `start_fleet_job`, `show_fleet_dashboard`, `use_usage_reset`) are exposed only through a user-owned provider/filter. When no provider exists they return `provider_unavailable` with `hosted_boundary=true`; they never invent WPVibe account/site/plan/job data.
4. Add machine-readable `reference_parity_manifest` so callers can distinguish native, local-equivalent, provider-backed, hosted-boundary, and Direct-only capabilities.
5. Add `get_last_change` for the safe WPVibe `/last-change` site-local route when available, because live reload is an advertised feature even though the hosted MCP does not expose it as a top-level public tool.
6. Keep worker/browser-internal routes such as `/authorize`, `/cli/run-approved`, `/code-snippet` approved path, op-proof, detached/self-update routes out of the model-visible surface; document them as internal security/control-plane endpoints, not missing MCP tools.

## Success criteria
- Every audited public/hosted tool name appears in the Direct MCP tool catalog or is explicitly covered by the reference manifest.
- No hosted-account/fleet result is synthesized.
- Write aliases inherit existing approval, REST-route blocking, and payload validation.
- The parity suite fails if an audited reference tool is absent.
- Legacy and existing parity suites remain green; PHP lint and package/source identity checks pass.
