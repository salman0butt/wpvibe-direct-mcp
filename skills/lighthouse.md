# Lighthouse / Performance Audit Providers

`page_audit` is intentionally provider-backed. Configure a user-owned Lighthouse, PageSpeed, browser worker, or equivalent service through the `wpvdmcp_page_audit` filter and return normalized results to Direct MCP. No WPVibe cloud credential or private hosted endpoint is embedded. Audit public or explicitly authorized URLs only, record the provider/source in results, and separate lab metrics from WordPress server diagnostics. Use `site_intelligence` for environment facts and this provider for browser performance measurements.
