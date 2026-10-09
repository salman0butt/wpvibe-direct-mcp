# PDF and Media Intelligence

Use `inspect_media` for safe Media Library metadata. It never returns server filesystem paths. For PDFs, text extraction is optional and must come from a user-owned `wpvdmcp_pdf_text` provider/filter; Direct MCP does not shell out to arbitrary binaries or upload documents to an undeclared cloud service. Bound extracted text with `max_text_chars`, treat missing extraction as a supported degraded state, and use existing media upload flows for images. Do not confuse public URL import with local browser upload tickets.
