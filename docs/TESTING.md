# Testing and Verification

## Automated suite

From the plugin root:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
```

Current 1.3.1 verification runs 27 legacy tests + 14 core parity tests + 21 reference parity tests (62 total). It covers:

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
- destructive + idempotent ability needs approval then DELETE;
- destructive + non-idempotent ability needs approval then POST;
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

### Exhaustive reference parity

- every audited public WPVibe tool name plus `use_usage_reset`;
- exact aliases (`audit_page`, `rest_api_write`, `save_skill`) and fail-closed hosted-provider tools;
- MCP Apps resources, tool metadata, opaque inline-approval token, decision replay rejection, and browser fallback;
- JavaScript browser preference/fallback for `get_page_html`;
- SeedProd compile approval/provider flow with builder-login secret redaction;
- provider-backed screenshots;
- Saved Skill descriptions/reference files;
- upstream infrastructure routes classified as internal, not model tools;
- Divi Theme Builder multi-value meta guidance;
- current Elementor 4.3 Abilities-first workflow, Atomic capability boundary, whole-settings replacement, native fallback, preview and live/render verification guidance;
- current Works-with-AI and cookbook plugin/page-builder/theme playbook manifest plus generic Abilities-first fallback;
- 1.3.1 release metadata.

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
12. Run safe WP-CLI read/status. Treat the installed `wp_cli_status` result as source of truth rather than a marketing-page command count.
13. Exercise a reversible WP-CLI write and an approval-required destructive command; verify upstream approval/receipt behavior.
14. On WordPress 6.9+, discover abilities and verify all method classes: readonly GET, ordinary write POST, destructive+idempotent DELETE, and destructive+non-idempotent POST.
15. With WPCode installed, create/update a dormant snippet and confirm it is not silently activated.
16. Public URL media: JPG/JPEG/PNG/WebP/GIF (if site allows), safe SVG, malicious SVG, wrong MIME, redirect/private-IP cases, 403/404/429, dotted filenames, large/invalid image, title/alt/post parent.
17. Device upload: call `request_upload`, upload a PNG through the browser page, `check_upload`, then confirm replay fails. Repeat with safe SVG; confirm malicious SVG is rejected.
18. Elementor 4.3+/WordPress 6.9+, Atomic Editor ON: discover the installed Elementor namespace; inspect widget schemas/resources; create a draft; build a composition; update/move an element; exercise page settings with a full read-modify-write object; create a preview; publish; re-read structure; verify the live rendered page and generated styles/cache.
19. Elementor Atomic Editor OFF: verify Atomic-only abilities fail with the expected capability response and that supported native WPVibe Elementor schema/save routes still work for a draft page without guessed `_elementor_data` writes.
20. Elementor site-wide styles: on staging only, create/update a test global variable/class/default style, verify representative Atomic elements, then restore the original state. Verify local per-widget overrides remain understandable.
21. Elementor Pro when available: locate an existing test Theme Builder template, update/reuse it through discovered Pro abilities or `elementor_save_template`, verify display conditions/live rendering, and avoid duplicate global templates. Test component capabilities only when discovered.
22. Smoke-test Beaver, Bricks, and Breakdance native get/schema/save routes when installed and verify post status/render/cache behavior.
23. Inspect WPVibe audit log and Direct MCP activity summaries.

## Integration status for this release build

The automated suite and PHP syntax verification are executable in this repository. A real WordPress staging target was **not supplied to this build session**, so the manual staging checklist above has not been falsely marked as passed. Production was not touched.
