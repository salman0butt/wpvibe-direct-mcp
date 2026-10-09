# Media Upload Architecture

## Root cause in 1.0.4

Direct MCP 1.0.4 exposed only:

```text
upload_media(url, title, alt_text, post_id)
```

That tool asks WordPress/WPVibe to **download a public URL**. An attachment placed in ChatGPT, Claude, or another MCP client is local to the client/provider and is not automatically represented as a public URL or multipart body in ordinary MCP tool arguments. The generic REST tool also does not transfer arbitrary binary multipart bodies.

Therefore “upload this logo I attached” failed even though URL imports could work. The missing component was a transport from the user's device/client into the site's Media Library.

## Public URL flow

Use `upload_media` when a stable public HTTP(S) image URL already exists.

Direct MCP delegates directly to current WPVibe `/wpvibe/v1/upload-media`. WPVibe 1.20.3 is responsible for:

- public HTTP(S) validation;
- private/loopback/link-local/reserved IP rejection;
- redirect-hop validation;
- DNS-rebind protections where supported by the HTTP transport;
- download errors/statuses;
- file-byte/MIME checks rather than trusting a `.jpg` suffix;
- safe filename normalization for dotted/timestamp-like names;
- WordPress upload type policy;
- `media_handle_sideload()` errors;
- SVG detection and sanitization;
- attachment metadata, title, alt text, and parent post handling.

Direct MCP does not weaken or replace those checks.

## Device/chat attachment flow

### 1. Request

Call:

```text
request_upload(title?, alt_text?, post_id?)
```

Response includes:

- `upload_id`
- `status = waiting`
- `expires_at`
- one-time `upload_url`
- `max_files`
- `max_file_size`
- allowed media types

### 2. Browser transfer

Open `upload_url`. It is an `admin-post.php` endpoint available without a WordPress login because the **ticket itself** is the narrow authorization.

The link contains only:

- random `upload_id`;
- independent random upload secret.

It does **not** contain the Direct MCP bearer token or a WordPress password/application password.

Server storage contains only `SHA-256(upload secret)`, the token-owner user ID, media metadata defaults, lifecycle state, and expiry.

The browser form uses `multipart/form-data`. Response headers include no-store, no-referrer, nosniff, and a restrictive CSP.

### 3. Validation and Media Library insert

- Token owner must still exist, remain Administrator, and retain `upload_files`.
- Maximum five files per ticket.
- Per-file limit = min(WordPress upload limit, 10 MiB).
- Raster byte MIME must be JPEG, PNG, GIF, or WebP.
- SVG is accepted only if current WPVibe exposes its sanitizer and safe SVG sideload path.
- PHP, JavaScript, HTML, arbitrary documents and unknown binary types are rejected.
- User-supplied names are sanitized; no directory/path argument exists.
- WordPress Media Library APIs create the attachment.
- The ticket changes to `ready` and its secret hash is erased, making the browser URL unusable for replay.

### 4. Check

Call:

```text
check_upload(upload_id)
```

While no upload has completed:

```text
status = waiting
```

After success:

```text
status = ready
files = [{ attachment_id, filename, mime_type, url, width, height }]
```

No PHP temp filename or server filesystem path is returned.

## Expiry and retry behavior

Tickets expire in roughly 30 minutes. A successful ticket is single-use. If validation fails before any Media Library insertion, the ticket remains waiting so the user can choose a correct image while the link is still valid.

## SVG

Direct MCP intentionally does not maintain a second SVG sanitizer. Current WPVibe uses a strict sanitizer designed to reject or strip executable/remote content before the file is stored. If those upstream classes are unavailable, device SVG upload fails closed and instructs the user to update WPVibe or use PNG/WebP.

## Diagnostics

Errors distinguish major classes such as:

- missing/expired/used upload ticket;
- token owner/capability change;
- PHP upload limit/temp-directory/disk write errors;
- empty or oversized file;
- unsupported/unknown MIME;
- unavailable SVG sanitizer;
- WPVibe sanitizer rejection;
- WordPress media sideload failure.

For public URLs, detailed download/HTTP/content-type/SSRF/filesystem errors continue to come from WPVibe itself.
