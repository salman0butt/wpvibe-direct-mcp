# Testing and Verification

## Automated suite

From the plugin root:

```bash
php tests/run.php
php tests/parity-run.php
```

Current 1.2.0 verification runs 26 legacy tests plus 14 parity tests (40 total). It covers:

### MCP transport

- modern `server/discover`;
- supported/unsupported protocol versions;
- legacy initialize negotiation;
- notification-only batch 202 behavior;
- OPTIONS routing headers;
- modern Mcp-Method / Mcp-Name validation;
- modern list cache hints;
- modern-vs-legacy ping behavior;
- invalid JSON-RPC payload;
- unknown method;
- GET 405;
- authenticated health.

### Authentication

- missing token;
- wrong token;
- valid Bearer token;
- valid `X-WPVibe-Direct-Token`;
- valid query token;
- revoked/unconfigured token;
- token owner who lost Administrator access.

### Capability/tools

- current file/publish fields;
- conditional route-backed builders;
- safe dormant WPCode snippets;
- generic REST credential-route blocks;
- response field/size controls;
- Abilities availability;
- readonly ability uses GET;
- write ability needs browser approval then POST;
- destructive ability needs approval then DELETE;
- approval replay rejection.

### Device media flow

- `request_upload` and `check_upload` exposed;
- main MCP token not present in upload URL;
- upload secret stored only as a hash;
- wrong secret rejected;
- successful ticket becomes non-replayable;
- ready results omit filesystem paths;
- raster MIME allowlist rejects HTML/PHP/JS/SVG from the raster path.

### Skills/admin

- required production skill catalog + version metadata;
- file-backed skill loading;
- compatibility summary hosted-only boundary;
- secret-free admin status snapshot.

## PHP syntax verification

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

## Static secret scan

Before packaging, inspect the tree for plaintext passwords/tokens/private keys and ensure test fixtures contain only obviously synthetic values such as `test-token`.

## Manual disposable WordPress integration checklist

**Do not run destructive tests against production.** Use a disposable/staging site with current WPVibe and Direct MCP.

1. Generate Direct MCP token; test Bearer, X header, and query compatibility.
2. Call health and `site_info`.
3. Inspect an active theme file.
4. Create a draft theme.
5. Make a trivial CSS change.
6. Get/inspect preview.
7. Delete draft without publishing.
8. Recreate draft, edit, and publish only after explicit approval.
9. Verify Site Editor `saved_customizations=set_aside` on a test block theme; repeat `keep` if relevant.
10. Create a draft page through REST.
11. Use `search_content` + `edit_content` on a unique match, then test no-match/multiple-match/replace-all/whole-word behavior.
12. Run safe WP-CLI read/status.
13. Exercise a reversible WP-CLI write and an approval-required destructive command; verify upstream approval/receipt behavior.
14. On WordPress 6.9+, discover abilities, inspect a readonly ability, run it, then test a write ability through the browser approval link.
15. With WPCode installed, create/update a dormant snippet and confirm it is not silently activated.
16. Public URL media: JPG/JPEG/PNG/WebP/GIF (if site allows), safe SVG, malicious SVG, wrong MIME, redirect/private-IP cases, 403/404/429, dotted filenames, large/invalid image, title/alt/post parent.
17. Device upload: call `request_upload`, upload a PNG through the browser page, `check_upload`, then confirm replay fails. Repeat with safe SVG; confirm malicious SVG is rejected.
18. If installed, smoke-test Elementor, Beaver, Bricks, and Breakdance native get/schema/save routes on draft pages and verify post status/render/cache behavior.
19. Inspect WPVibe audit log and Direct MCP activity summaries.

## Integration status for this release build

The automated suite and PHP syntax verification are executable in this repository. A real WordPress staging target was **not supplied to this build session**, so the manual staging checklist above has not been falsely marked as passed. Production was not touched.
