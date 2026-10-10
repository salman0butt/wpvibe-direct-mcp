# Testing and Verification

## Automated suite

From the plugin root:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
```

The current runtime-parity branch runs **26 legacy tests + 14 core parity tests + 34 reference/runtime tests = 74 automated tests**, plus PHP syntax validation. The published 1.3.0 release predates the added runtime tests; do not confuse branch verification with a new published release.

### MCP transport and authentication

Coverage includes modern `server/discover`, supported/unsupported protocol negotiation, legacy initialize, notification batches, modern routing headers/cache hints, invalid JSON-RPC, GET rejection, authenticated health, Bearer/X-header/query-token authentication, revoked tokens and owners who lost Administrator access.

### Runtime safety / worker parity

Coverage now includes:

- WordPress Abilities runtime discovery and method semantics;
- readonly Ability -> GET;
- destructive + idempotent Ability -> DELETE;
- destructive + non-idempotent Ability -> POST;
- all non-read public Ability execution approval-gated unless WPVibe dangerous bypass is enabled;
- generic REST GET immediate vs POST/PUT/PATCH/DELETE payload-bound approval;
- REST approval payload mismatch and replay rejection;
- Direct/WPVibe authentication, proof, approved-execution, builder-login, detached/self-update, audit-writer and helper control-plane route blocking;
- destructive/high-risk WP-CLI approval conversion, exact command + operation/dry-run snapshot binding, native `WPVibe_CLI::run_approved()` execution and replay rejection;
- dangerous-bypass WP-CLI path;
- local dormant `WPVibe_Code_Snippet::handle(..., true)` bridge without Worker op-proof credentials;
- WAF-safe `call_armored` strict decoding, normal public-handler dispatch, recursion/app-only/unknown-target rejection;
- current upstream route/worker manifest classification;
- historical release workflow no longer auto-running after future main CI.

### Existing capability coverage

The suites continue to cover current file/publish fields, route-backed builders, REST response limits, device upload ticket hashing/replay protection, raster MIME policy, editable fields/groups/settings, recursive Gutenberg block validation and approval-gated writes, saved-skill CRUD/reference files, media/PDF intelligence, site intelligence/live reload, SEO audit, provider fail-closed behavior, MCP Apps, SeedProd compile secret redaction, screenshots, Works-with-AI playbooks and release metadata.

## PHP syntax verification

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

GitHub Actions validates PHP **7.4, 8.2 and 8.4**.

## Static release review

Before merge/release:

1. Compare the exact branch head to `main`.
2. Re-check official `awesomemotive/wpvibe-ai-mcp` `main` so the pinned reference did not move during development.
3. Run all three suites + PHP lint on the exact candidate SHA.
4. Search for plaintext secrets/private keys and synthetic-test tokens accidentally copied into production files.
5. Confirm no duplicate public tool names after parity overrides.
6. Confirm internal proof/auth/control-plane routes are not exposed as normal tools or generic REST targets.
7. Confirm hosted/provider-backed tools still fail closed without user-owned providers.
8. Release only through the explicit version + exact-SHA workflow after the exact target has successful parity CI.

## Manual disposable WordPress integration checklist

**Do not run destructive acceptance tests against production.** Use a disposable/staging WordPress site with current WPVibe + the Direct MCP candidate.

### Connection / transport

1. Generate a Direct MCP token; test Bearer, X header and query compatibility.
2. Call health, `site_info`, `integration_capabilities` and `reference_parity_manifest`.
3. From a host/WAF that accepts raw source, exercise a normal code-bearing call. If the host rejects raw PHP/JS/CSS/SQL before WordPress receives it, retry the same operation through `call_armored` and verify the final normal tool still enforces its approval/capability rules.

### Themes / files / content

4. Inspect an active theme file; search/outline it.
5. Create a draft theme, make a trivial CSS change, get preview, then delete without publishing.
6. Recreate/edit/publish after explicit approval. On a block theme, test `saved_customizations=set_aside`; separately test `keep` if relevant.
7. Exercise `search_content` + `edit_content` on a unique match and verify no-match/multiple-match/replace-all/whole-word behavior.

### REST approvals

8. Run a REST GET and confirm no Direct approval is required.
9. Run a reversible REST POST/PATCH and confirm a Direct approval is required when bypass is off.
10. Approve it, mutate the body before replay, and confirm the changed request is refused. Run the exact approved request and confirm a second replay fails.
11. Enable WPVibe **Dangerously bypass approvals** from wp-admin on this disposable site only; verify the same REST write runs immediately. Turn bypass off again from wp-admin.
12. Confirm generic REST refuses Direct/WPVibe authentication/proof/approved-worker/builder-login/self-update routes.

### WP-CLI / SQL

13. Run `wp_cli_status` and `run_wp_cli help`; treat the installed command inventory as authoritative.
14. Run safe read commands.
15. Exercise a reversible WP-CLI write/confirm flow.
16. Run a destructive operation (for example a disposable post delete) and verify `/cli/run` produces the dry-run/operation snapshot, Direct browser approval pauses execution, and native approved execution runs only after approval.
17. Attempt to change the command after approval and confirm the approval cannot be reused.
18. Exercise read-only `db query` and an approval-required disposable SQL row write. Confirm upstream hard blocks (users/user-meta/options/security/WPVibe tables where applicable) remain hard blocks.
19. On the disposable site, briefly enable dangerous bypass and confirm an approval-classified WP-CLI operation follows WPVibe's bypass semantics; turn it off immediately afterwards.

### WordPress Abilities / plugin abilities

20. On WordPress 6.9+, discover abilities and inspect a readonly ability.
21. Verify a readonly ability runs immediately.
22. Verify a normal write Ability pauses for Direct approval.
23. Where test providers expose representative annotations, verify destructive+idempotent uses DELETE while destructive+non-idempotent uses POST.

### Elementor 4.3+ end-to-end

24. Install current Elementor **4.3+** (reference audit: 4.3.3) on WordPress 6.9+.
25. With **Atomic Editor OFF**, discover Elementor abilities and confirm Atomic build/style operations fail with the expected prerequisite/feature-gate behavior rather than Direct inventing private data.
26. Turn Atomic Editor ON through wp-admin. Discover the current `elementor/*` ability set and inspect the schemas/resources used by the test.
27. Create a draft Elementor page through the installed ability/native path.
28. Read page structure/settings before mutation.
29. Build a small composition using schema-discovered atomic elements (container/layout, heading/text/button/image as available).
30. Manage elements: update, move/reorder/duplicate/delete on disposable elements only; re-read structure after each meaningful batch.
31. Read/create/update global variables/classes only when needed; verify the site-wide effect and restore them afterward.
32. Read page settings, change one setting while sending back the full preserved object, then confirm unrelated settings remain intact.
33. Set/change the WordPress page template through WordPress/native page-template APIs, not `elementor/update-page-settings`.
34. Create a preview link and inspect visitor-rendered HTML; when a browser/screenshot provider is configured, compare a screenshot as well.
35. Publish the disposable page when requested, then re-read page structure and fetch the live page as a visitor. Verify that autosaves/Elementor element or CSS caches did not leave the live page stale; use supported cache-refresh paths if required and verify again.
36. If Elementor Pro is installed, separately test the Pro abilities/templates/site-parts actually discovered at runtime. Do not assume Pro-only abilities on free Elementor.
37. On an older/non-Abilities Elementor-compatible site, smoke-test `elementor_widgets`, `elementor_schema`, `elementor_style_schema`, `elementor_save_page`, and (with Pro) `elementor_save_template` as the native fallback.

### WPCode / builders / blocks / media

38. With WPCode installed, create/update a dormant snippet through `code_snippet`; verify it stays disabled and no model input can activate it.
39. Smoke-test Beaver Builder, Bricks and Breakdance native get/schema/save routes when installed; verify post status/render/cache behavior.
40. Validate and save Gutenberg/Kadence/GenerateBlocks content through `validate_blocks` / `save_validated_blocks`.
41. Test public URL media: JPG/JPEG/PNG/WebP/GIF, safe SVG, malicious SVG, wrong MIME, redirects/private IP cases, 403/404/429, dotted filenames, large/invalid image, title/alt/parent.
42. Test device upload: browser/inline MCP App upload, `check_upload`, replay failure, safe SVG and malicious SVG behavior.
43. Inspect WPVibe Approval Log and Direct MCP activity summaries after the test run.

## Integration status

The repository's automated suite and PHP lint verify code/contracts. A real disposable WordPress/Elementor target has **not** been supplied to this development session, so the manual checklist above remains unverified. Production/client WordPress sites were not used for destructive acceptance testing.
