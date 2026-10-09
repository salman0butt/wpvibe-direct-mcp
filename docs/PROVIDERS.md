# Optional User-Owned Providers

WPVibe Direct MCP 1.2 exposes optional hooks for capabilities that otherwise require external infrastructure. The plugin ships no WPVibe cloud secrets and no third-party API keys.

- `wpvdmcp_pdf_text`: return extracted PDF text for the supplied attachment context.
- `wpvdmcp_page_audit`: return Lighthouse/PageSpeed-style results for an explicitly requested URL.
- `wpvdmcp_search_images`: return stock-image results from a provider/account you control.
- `wpvdmcp_render_browser`: return JavaScript-rendered HTML from a browser worker you control.

A missing provider returns `provider_unavailable` rather than fabricated data. Provider implementations are responsible for credentials, network allowlists, rate limits, licensing/attribution, and any consent needed for external transmission.
