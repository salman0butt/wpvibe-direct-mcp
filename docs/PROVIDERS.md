# Optional User-Owned Providers

WPVibe Direct MCP 1.3.0 exposes provider hooks for capabilities that require infrastructure outside one WordPress installation. No WPVibe private cloud credential, Unsplash key, fleet database, or billing/account state is bundled. Missing providers return `provider_unavailable`.

## Site-local external providers

- `wpvdmcp_pdf_text` — bounded text extraction for a Media Library PDF.
- `wpvdmcp_page_audit` — Lighthouse/PageSpeed-style analysis.
- `wpvdmcp_search_images` — stock-image search using credentials/licensing you control. Results should carry the provider's source/author/license/attribution metadata whenever the provider supplies it; Direct MCP never invents attribution.
- `wpvdmcp_render_browser` — JavaScript-rendered HTML.
- `wpvdmcp_reference_screenshot_page` — screenshot provider.
- `wpvdmcp_reference_seedprod_compile_page` — trusted browser worker that consumes WPVibe's short-lived SeedProd builder login and returns a redacted result.

## Exact hosted-tool adapters

The following exact public WPVibe tool names can be implemented by callbacks in `$GLOBALS['wpvdmcp_reference_providers']` or filters named `wpvdmcp_reference_<tool>`: `connect_site`, `list_sites`, `remove_site`, `get_profile`, `start_fleet_job`, `show_fleet_dashboard`, and `use_usage_reset`. They return a structured hosted-boundary error when no provider is configured; Direct MCP never invents remote site/account/fleet state.

Provider implementations are responsible for credentials, outbound-network allowlists, SSRF protections, rate limits, data handling, retention, licensing/attribution, and consent for external transmission.
