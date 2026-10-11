# Testing and Verification

## Automated suite

From the plugin root:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
php tests/abilities-json-body-run.php
```

Release 1.3.2 contains **27 legacy tests + 14 core parity tests + 53 reference parity tests (94 suite cases)**. Current development CI runs those same suites plus PHP syntax validation and one focused Abilities raw-JSON request-body regression on PHP 7.4, 8.2, and 8.4.

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
- POST Abilities mutations use the raw `application/json` request-body contract expected by the WordPress 6.9 Abilities run controller;
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

Before packaging, inspect the tree for plaintext passwords/tokens/private keys and ensure fixtures contain only obviously synthetic values such as `test-token`. The disposable Elementor job generates an ephemeral Direct MCP token inside the runner, masks it in GitHub Actions, and redacts token-shaped values from failure diagnostics.

## Disposable WordPress + Elementor CI acceptance

`.github/workflows/wordpress-elementor-e2e.yml` provisions an isolated WordPress environment and never accepts a production/customer URL. The verified lane currently pins:

- WordPress **6.9**;
- official WPVibe **1.20.3** at exact upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`;
- Elementor Core **4.3.4**;
- PHP **8.2** / Apache and MySQL 8;
- the exact Direct MCP pull-request/main checkout under test.

The lane enables Elementor's own site-level MCP opt-in and Atomic feature flags, then exercises the real Direct MCP HTTP endpoint. WP-CLI is used only to prepare the disposable site and simulate the administrator approving a pending Direct MCP operation.

Verified on 2026-10-11:

1. authenticated Direct MCP health;
2. MCP `2026-07-28` `server/discover` and routing headers;
3. real `tools/list` including Abilities and native Elementor fallback tools;
4. runtime discovery of `elementor/create-page`, `elementor/build-composition`, `elementor/get-page-structure`, and `elementor/publish-document`;
5. live annotation verification for method selection;
6. one-time approval-required → administrator approval → execution flow, including replay rejection;
7. `elementor/create-page` creates a draft;
8. `elementor/build-composition` creates an Atomic heading through the destructive + non-idempotent **POST** path;
9. `elementor/get-page-structure` re-reads the generated hierarchy through the readonly **GET** path;
10. `elementor/publish-document` publishes through the destructive + idempotent **DELETE** path;
11. the rendered frontend contains the requested Elementor heading;
12. no PHP fatal/uncaught error is present after the acceptance sequence;
13. with Atomic Editor disabled, an Atomic-only mutation fails predictably while the native `elementor_save_page` fallback remains discoverable.

This is real disposable-site E2E evidence for that bounded Elementor workflow. It is **not** evidence that Elementor Pro Theme Builder, every Elementor element/style/global-setting mutation, other page builders, provider-backed browser/screenshot/Lighthouse services, media edge cases, or production/client environments were tested.

## Broader manual disposable WordPress integration checklist

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
12. Elementor 4.3+/WordPress 6.9+, extend the verified Atomic-ON lane to update/move/delete elements, full read-modify-write page settings, generated styles/cache behavior, globals/classes/variables and broader widget coverage.
13. Extend the verified Atomic-OFF lane with native fallback save/schema behavior, not guessed `_elementor_data` writes.
14. Elementor site-wide styles: on staging only, create/update a test global variable/class/default style, verify representative pages, then restore original state.
15. Elementor Pro when available: reuse/update a test Theme Builder template through discovered Pro abilities or `elementor_save_template`; verify display conditions/live rendering; avoid duplicate global templates.
16. Smoke-test Beaver, Bricks, and Breakdance native get/schema/save routes when installed.
17. Inspect WPVibe audit log and Direct MCP activity summaries.

## Current integration status

A real disposable WordPress/Elementor target is now CI-backed for the bounded Elementor workflow listed above. The rest of the broader manual checklist remains intentionally unmarked until executed. No production or customer WordPress site was used for destructive verification.