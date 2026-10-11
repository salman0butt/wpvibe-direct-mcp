# Testing and Verification

## Automated suite

From the plugin root:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
```

Current 1.3.2 verification runs **27 legacy tests + 14 core parity tests + 53 reference parity tests (94 total)**. CI runs the same suites plus PHP syntax validation on PHP 7.4, 8.2, and 8.4.

### MCP transport

- modern `server/discover` and current/legacy protocol negotiation;
- notification-only batch 202 behavior;
- OPTIONS and Mcp-Method / Mcp-Name routing headers;
- modern list cache hints, legacy ping behavior, invalid JSON-RPC, unknown method, GET 405, and authenticated health.

### Authentication and approvals

- missing/wrong/valid bearer, `X-WPVibe-Direct-Token`, and query tokens;
- revoked tokens and owners who lost Administrator access;
- single-use approvals and replay rejection;
- generic REST mutations require approval unless the WPVibe bypass setting is explicitly enabled;
- approval/status aliases (`approval_id`, `op_id`) and fleet-provider delegation.

### Public REST and mutation safety

- current file/publish fields and conditional route-backed builders;
- generic REST credential-route blocks, response field/size limits, and current WPVibe option aliases (`route`, `query`, string/array `fields`, `max_length`);
- embedded-query normalization with explicit query values winning duplicates;
- core content delete requests cannot force permanent deletion through the generic bridge;
- WooCommerce product/batch writes reject accidental loss of protected replace-all list fields unless replacement is explicit.

### WordPress Abilities / Elementor foundation

- Abilities availability and schema aliases (`name` / `ability_name`);
- structured `input` and current `input_json` compatibility;
- metadata-first annotations with fallback hints only when metadata lookup fails;
- readonly GET;
- ordinary write POST with approval;
- destructive + idempotent DELETE with approval;
- destructive + non-idempotent POST with approval;
- current Elementor 4.3 Abilities-first workflow, Atomic capability boundary, whole-settings replacement warning, native fallback, preview and live/render verification guidance.

### Device media flow

- `request_upload` / `check_upload` exposure;
- main MCP token never appears in the upload URL;
- upload secret stored only as a hash, wrong secret rejected, successful tickets non-replayable;
- ready results omit filesystem paths;
- raster allowlist rejects HTML/PHP/JS/SVG from the raster path.

### Hosted/provider-backed public contracts

- exact public hosted names fail closed without a user-owned provider;
- `search_images` accepts current count/orientation plus Direct `per_page`;
- `audit_page` accepts `page_url`, categories, refresh, and local-site default;
- `get_page_html` accepts current URL/selector/max-length forms; native fallback is same-site only while external rendering requires a browser provider;
- `connect_site` and fleet job options match the current public contract without fabricating WPVibe cloud state;
- screenshot, browser, Lighthouse/PageSpeed, stock image, SeedProd compile, account/fleet and usage capabilities remain explicitly provider-backed where external infrastructure is required.

### Skills/admin/reference parity

- required workflow skill catalog and version metadata;
- current `load_skill` Direct and WPVibe forms (`skill`, `name`, `query`, `resource`), bounded catalog metadata/search, and exact saved reference reads;
- compatibility summary and secret-free admin status;
- every audited public WPVibe tool name, MCP Apps approval/upload resources, saved skills, upstream infrastructure/model-route classification, current Works-with-AI/Cookbook playbooks, and 1.3.2 release metadata.

## PHP syntax verification

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

## Static secret scan

Before packaging, inspect the tree for plaintext passwords/tokens/private keys and ensure fixtures contain only obviously synthetic values such as `test-token`.

## Manual disposable WordPress integration checklist

**Do not run destructive tests against production.** Use a disposable/staging site with current WPVibe and Direct MCP.

1. Generate Direct MCP token; test Bearer, X header, and query compatibility.
2. Call health and `site_info`.
3. Inspect an active theme file.
4. Create a draft theme, make a trivial CSS change, get/inspect preview, then delete without publishing.
5. Recreate the draft, edit and publish only after explicit approval; verify `saved_customizations=set_aside` and `keep` on a test block theme where relevant.
6. Create a draft page through REST; exercise `search_content` + `edit_content` including unique/no/multiple-match cases.
7. Run safe WP-CLI read/status and an approval-required reversible/destructive workflow; inspect receipts.
8. On WordPress 6.9+, verify all Ability method classes and public aliases/input forms.
9. With WPCode installed, create/update a dormant snippet and confirm it is not silently activated.
10. Public URL media: raster types, safe/malicious SVG, wrong MIME, redirects/private IPs, HTTP failures, dotted filenames, large/invalid images, metadata/parenting.
11. Device upload: PNG browser upload + replay rejection; safe SVG if upstream sanitizer exists; malicious SVG rejection.
12. Elementor 4.3+/WordPress 6.9+, Atomic Editor ON: discover installed Elementor abilities/resources; create a draft; build composition; update/move elements; full read-modify-write page settings; preview; publish; re-read structure; verify live rendered page, generated styles and caches.
13. Elementor Atomic Editor OFF: verify Atomic-only capabilities fail predictably and native WPVibe Elementor schema/save fallback still works without guessed `_elementor_data` writes.
14. Elementor site-wide styles: on staging only, create/update a test global variable/class/default style, verify representative pages, then restore original state.
15. Elementor Pro when available: reuse/update a test Theme Builder template through discovered Pro abilities or `elementor_save_template`; verify display conditions/live rendering; avoid duplicate global templates.
16. Smoke-test Beaver, Bricks, and Breakdance native get/schema/save routes when installed.
17. Inspect WPVibe audit log and Direct MCP activity summaries.

## Integration status for this release build

The automated suite and PHP syntax verification are executable and CI-backed. A real disposable WordPress/Elementor staging target was **not supplied to this build session**, so the manual integration checklist above is intentionally not marked as passed. Production was not touched.
