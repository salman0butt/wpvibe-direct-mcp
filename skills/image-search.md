# Stock Image Search Providers

`search_images` requires a user-owned provider configured through the `wpvdmcp_search_images` filter. This preserves feature coverage without copying WPVibe's private Unsplash or hosted credentials. Return source attribution, image URL, author/license information when available, and enough metadata for a user to make a licensing decision. Never scrape a provider that forbids it or claim a license that the provider did not return. After selection, import only a public HTTPS image through the protected `upload_media` flow.
