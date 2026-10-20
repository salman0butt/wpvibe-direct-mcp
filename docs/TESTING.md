# Testing and Verification

## Automated suite

From the plugin root:

```bash
php tests/run.php
php tests/parity-run.php
php tests/reference-run.php
php tests/skill-routing-benchmark.php --enforce
php tests/abilities-json-body-run.php
```

Release **1.4.1** contains **35 legacy tests + 14 core parity tests + 70 reference parity tests (119 suite cases)** plus one focused Abilities raw-JSON request-body regression. Source CI runs those checks plus PHP syntax validation on PHP 7.4, 8.2, and 8.4.

Published release 1.5.0 contains the self-contained MCP Apps, query-token-default-off, provider-output-boundary, provider-readiness-discovery, last-active write-throttling, explicit single-site hosted-boundary, Elementor page-design/bulk-mutation/page-settings/global-style fidelity regressions, the 2026-10-13 fail-closed persistence/code-audit regressions, and JSON-RPC notification response suppression, with **53 core/legacy + 20 parity + 196 reference cases (269 suite cases)** plus the focused Abilities regression.

Release **1.5.1** packages the WPVibe 1.20.4/1.20.5 compatibility assertions for WPCode retirement, Bricks template types, optional Beaver schema slugs, index-hidden REST routes, the exact upstream commit manifest and WPCode cache false-success. Source and release ZIP totals are **53 core/legacy + 20 parity + 202 reference cases (275 suite cases)** plus the existing 174-request intent benchmark and focused Abilities regression.

### Skill-intent retrieval benchmark

Run `php tests/skill-routing-benchmark.php --enforce` to verify the frozen, manually labeled requests in `tests/fixtures/skill-routing-goldens.php`. The source benchmark covers **all 67 built-in skills** (two positive task phrasings each, 134 total) and **40 hard negative/no-match requests**, including generic WordPress requests and unrelated security-sensitive or misleading keywords. The guard enforces at least 95% top-1 accuracy, 97% top-3 recall, and 100% no-match specificity for this fixed corpus. Use `--json` for detailed metrics and misses. Many initial positive prompts explicitly name a target plugin; an independent held-out evaluation found gaps in offsite intent, vendor-name conflicts, and vocabulary, so additional held-out assertions for offsite platforms, named WordPress integrations, local saved-skill precedence, negation, variant names, and synonyms are enforced through `tests/reference/cases-59.php`. Context disambiguation is conservative, heuristic and not a general-purpose natural-language understanding system. This is an internal regression dataset, not evidence of accuracy on unknown live requests or of the availability of optional site plugins. Exact/saved-skill precedence and instruction confidentiality have separate reference parity tests; routing never implies permission to execute a mutation.

### MCP transport

- modern `server/discover` and current/legacy protocol negotiation;
- notification-only POST/batch 202 behavior plus mixed legacy batches that execute known-method notifications without emitting a JSON-RPC result for them;
- OPTIONS and Mcp-Method / Mcp-Name routing headers;
- modern list cache hints, legacy ping behavior, invalid JSON-RPC, unknown method, GET 405, and authenticated health.

### Authentication and approvals

- missing/wrong/valid bearer and `X-WPVibe-Direct-Token` headers;
- query-token and legacy `access_token` authentication rejected by default, then accepted only after explicit site-owner opt-in;
- revoked tokens and owners who lost Administrator access;
- successful authentication keeps the admin `last_request` diagnostic current without rewriting `wpvdmcp_last_active` more than once per 60 seconds; stale/missing and future-skewed timestamps are refreshed;
- single-use approvals and replay rejection;
- approval creation plus approve/reject state transitions fail closed when their transient state cannot be durably persisted; callers are never told an approval exists or changed state when storage is uncertain;
- malformed array-shaped approval receipts are rejected as `approval_corrupt` before a consume claim or payload-hash comparison can run;
- deterministic concurrent-consumption coverage proving exactly one claimant can consume an approved receipt, old/ambiguous claims are not stolen, and persistence uncertainty fails closed;
- generic REST mutations require approval unless the WPVibe bypass setting is explicitly enabled;
- approval/status aliases (`approval_id`, `op_id`) and fleet-provider delegation;
- destructive WP-CLI converts the upstream `approval_required` result into a one-time Direct approval bound to the exact command plus the exact upstream operation/dry-run snapshot;
- changed commands/snapshots and consumed CLI approvals fail before the native approved executor can run, and failure to durably store that exact snapshot aborts the handoff instead of returning an unusable approval;
- WPCode reviewed writes run the upstream dormant classifier first, preserve the `wpcode_edit_snippets` capability gate, use exact-payload one-time Direct approval only for `snippet_review_required`, reject drift/replay before approved execution, and discard model-supplied activation input;
- the model never receives a Worker operation-proof credential or direct `/wpvibe/v1/cli/run-approved` / approved WPCode route tool.
- MCP 2025-06-18+ batches are rejected before tool execution; retained pre-2025-06-18 legacy batches omit notification responses and strip app-only approval metadata from every returned ID-bearing result's model-visible structured content/text, including duplicate-ID and malformed-first cases.
- wp-admin token generation, revocation and endpoint/query-token settings use read-back verification with rollback or fail-closed fallback; partial option writes are never reported as successful auth-state changes, and persistence failures are surfaced visibly in wp-admin.

### Public REST and mutation safety

- current file/publish fields and conditional route-backed builders;
- generic REST blocks Direct MCP auth routes, Application Password credential routes, and audited WPVibe Worker/control-plane routes before dispatch or approval creation;
- response field/size limits and current WPVibe option aliases (`route`, `query`, string/array `fields`, `max_length`);
- embedded-query normalization with explicit query values winning duplicates;
- core content delete requests cannot force permanent deletion through the generic bridge;
- WooCommerce product/batch writes reject accidental loss of protected replace-all list fields unless replacement is explicit;
- classic-theme fresh-proof route is classified infrastructure-only while draft compile snapshot remains explicitly model-facing.
- `save_validated_blocks` reparses and validates the exact serialized Gutenberg `content` before approval/write; a separate benign `blocks` array cannot mask different serialized markup, and strict mode rejects non-whitespace freeform/classic HTML nodes outside the installed registered-block schemas.

### WordPress Abilities / Elementor foundation

- Abilities availability and schema aliases (`name` / `ability_name`);
- structured `input` and current `input_json` compatibility;
- metadata-first annotations with fallback hints only when metadata lookup fails;
- readonly GET;
- ordinary write POST with approval;
- destructive + idempotent DELETE with approval;
- destructive + non-idempotent POST with approval;
- POST Abilities mutations use the raw `application/json` request-body contract expected by the WordPress 6.9 Abilities run controller;
- current Elementor 4.3 Abilities-first workflow, strict `build-composition` dry-run/warning handling, `manage-elements` partial-failure handling, fresh `resolved_xml` element IDs, partial page-settings merge semantics, native fallback, preview and live/render verification guidance.

### WPCode reviewed writes

- `code_snippet` overrides the Worker-proof REST dispatch while remaining available only when the dormant capability route is registered;
- the bridge explicitly preserves `wpcode_edit_snippets` before calling the upstream handler in-process;
- qualifying dormant writes succeed through upstream dormant semantics without an extra Direct approval;
- `snippet_review_required` preserves the upstream reason and creates a one-time Direct approval bound to the normalized action/id/code/title/type/location/insert-method payload;
- changed code after approval produces `approval_payload_mismatch` before upstream execution;
- a consumed approval cannot be replayed;
- the explicit site-owner WPVibe approval bypass is honored only after upstream dormant classification says review is required;
- model-provided `active` input never reaches the upstream handler and Direct never raises snippet activation.

These are PHP/reference tests using a contract-faithful upstream handler double plus source invariants. The disposable WordPress/Elementor job does **not** install WPCode, so this release does not claim live WPCode E2E from that lane.

### Direct-only armored transport

- `call_armored` can target only an already-exposed model-facing tool;
- decoded arguments must be a bounded JSON object;
- target-tool approvals remain required and one-time replay protection remains active;
- `call_armored` cannot target itself, approval-decision internals, unknown/unexposed tools, or oversized/invalid payloads.

### Device media flow

- `request_upload` / `check_upload` exposure;
- main MCP token never appears in the upload URL;
- initial browser upload ticket creation fails closed if the waiting ticket cannot be durably persisted, so Direct never returns a dead upload URL/secret pair;
- malformed array-shaped upload ticket state is invalidated as `upload_corrupt` before status serialization or transition/media handling;
- upload secret stored only as a hash, wrong secret rejected, successful tickets non-replayable;
- browser POST atomically reserves a waiting ticket before media creation, concurrent claims cannot both create attachments, and only one finalizer can complete;
- partial multi-file failures and finalization persistence failures roll back attachments already created by that attempt;
- ready results omit filesystem paths;
- raster allowlist rejects HTML/PHP/JS/SVG from the raster path.
- optional PDF provider text uses the shared provider-output sanitizer and Unicode-safe `max_text_chars` truncation, so multibyte text cannot be split into invalid MCP JSON.

### Hosted/provider-backed public contracts

- exact public hosted names fail closed without a user-owned provider;
- local and hosted/reference provider responses share the same credential-key redaction and output-bounding layer;
- provider-output tests cover nested/camelCase/leading-acronym and compact all-uppercase secret keys (including OAuth consumer and AWS access-key forms), prefixed auth/authorization and provider-secret-key fields, arbitrary plural credential/secret/token/API-key collections with boolean capability-predicate controls, recursive header/value/data/hash/PEM wrapper chains, safe count/status/public-key false-positive controls, normal prose preservation, fake sanitizer-metadata removal, 65,536-byte value **and map-key** / 200-item collection / 8-level depth limits, a 1.5 MB source payload, escaping-heavy and pretty-print serialized-size fallbacks at 262,144 bytes, hosted callback and WordPress-filter provider paths, stdClass/JsonSerializable **and native JSON-encodable internal-object** normalization, bounded/redacted provider `WP_Error` data, huge error-code containment, invalid-UTF8 error safety, code-point-safe multibyte truncation, Unicode escaping expansion, and the complete model-visible error-envelope ceiling;
- `search_images` accepts current count/orientation plus Direct `per_page`;
- `audit_page` accepts `page_url`, categories, refresh, and local-site default;
- `get_page_html` accepts current URL/selector/max-length forms; native fallback is same-site only while external rendering requires a browser provider;
- `get_page_html` applies `max_length` as Unicode characters to both provider and native response shapes, preserving valid UTF-8 and reporting full rendered length in characters when truncated;
- `connect_site` and fleet job options match the current public contract without fabricating WPVibe cloud state;
- `connect_site`, `list_sites`, and `remove_site` remain provider-only compatibility names in the single-site architecture and never fall back to a Direct-managed local site registry;
- screenshot, browser, Lighthouse/PageSpeed, stock image, SeedProd compile, account/fleet and usage capabilities remain explicitly provider-backed where external infrastructure is required.
- provider-backed tools remain listed in actual MCP `tools/list` with non-secret registration metadata; missing providers show `configured: false`, callback/filter registrations show `configured: true`, discovery never invokes them, existing MCP Apps `_meta` composes unchanged, and `get_page_html` advertises the same-site native fallback rather than claiming hard unavailability.

### Skills/admin/reference parity

- required workflow skill catalog and version metadata;
- current `load_skill` Direct and WPVibe forms (`skill`, `name`, `query`, `resource`), bounded catalog metadata/search, and exact saved reference reads;
- saved-skill create/update/delete and editable field/group/setting registration re-read durable WordPress option state and fail closed instead of reporting persistence that did not occur;
- malformed non-array saved-skill records are ignored during discovery while valid sibling records remain available;
- malformed nested editable-definition records are ignored during startup replay while valid sibling definitions still register through the same WPVibe native calls;
- compatibility summary and secret-free admin status;
- malformed legacy/scalar `wpvdmcp_activity` state is normalized at both tool-call logging and wp-admin rendering boundaries instead of causing PHP 8 type errors;
- every audited public WPVibe tool name, MCP Apps approval/upload resources, saved skills, upstream infrastructure/model-route classification, current Works-with-AI/Cookbook playbooks, and 1.4.1 release metadata;
- current manifest records `call_armored` as a Direct-only extension;
- the release workflow invariant requires the focused Abilities raw-JSON regression in both source and extracted-package verification.

## PHP syntax verification

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

## Static secret scan

Before packaging, inspect the tree for plaintext passwords/tokens/private keys and ensure fixtures contain only obviously synthetic values such as `test-token`. The disposable Elementor job generates an ephemeral Direct MCP token inside the runner, masks it in GitHub Actions, and redacts token-shaped values from failure diagnostics.

## Disposable WordPress + Elementor CI acceptance

`.github/workflows/wordpress-elementor-e2e.yml` provisions an isolated WordPress environment and never accepts a production/customer URL. The verified lane pins:

- WordPress **6.9**;
- official WPVibe **1.20.3** at exact upstream commit `8f303926ae11179e38bc0ecf2a87e3cbd18984c6`; and **1.20.5** at `62bc77ccd1c15faf766f527a7935fe32f1b227d3`;
- Elementor Core **4.3.4**;
- PHP **8.2** / Apache and MySQL 8;
- the exact Direct MCP pull-request/main checkout under test.

The lane enables Elementor's own site-level MCP opt-in and Atomic feature flags, then exercises the real Direct MCP HTTP endpoint. WP-CLI is used only to prepare the disposable site and simulate the administrator approving a pending Direct MCP operation.

The 1.5.0 disposable acceptance lane verifies:

1. authenticated Direct MCP health;
2. MCP `2026-07-28` `server/discover` and routing headers;
3. real `tools/list` including Abilities and native Elementor fallback tools;
4. runtime discovery of `elementor/create-page`, `elementor/build-composition`, `elementor/get-page-structure`, `elementor/list-widget-schemas`, `elementor/get-widget-schema`, `elementor/manage-elements`, `elementor/update-page-settings`, `elementor/read-resource`, `elementor/manage-global-variable`, `elementor/manage-classes`, `elementor/get-default-styles`, `elementor/manage-default-styles`, and `elementor/publish-document`;
5. live annotation verification for method selection;
6. one-time approval-required → administrator approval → execution flow, including replay rejection;
7. `elementor/create-page` creates a draft;
8. live widget-schema discovery confirms the canonical Atomic props used for flexbox, heading, paragraph, and button composition;
9. an intentionally unsupported Elementor property first requires the normal exact-payload approval, then is rejected by Direct's strict dry-run preflight before persistence; the consumed approval cannot be replayed and the invalid content never reaches the document;
10. `elementor/build-composition` creates a styled, responsive, multi-section Atomic page through the destructive + non-idempotent **POST** path with no upstream warnings and with server-generated IDs returned in `resolved_xml`;
11. `elementor/get-page-structure` re-reads the generated hierarchy and content/styles through the readonly **GET** path;
12. `elementor/manage-elements` updates an element by the fresh ID returned from `resolved_xml`, then duplicates that element, moves the duplicate between real Atomic parents, and deletes it while each resulting hierarchy is re-read from Elementor;
13. a deliberate mixed-success `manage-elements` batch is surfaced as `elementor_manage_elements_partial_failure` with `partial_applied=true`, then the lane re-reads current structure and repairs the element before continuing;
14. `elementor/update-page-settings` is exercised with both margin and padding, followed by a margin-only update; Direct's post-approval merge preserves the untouched padding even though Elementor 4.3.4's upstream ability otherwise replaces the stored page-settings object;
15. a deliberate mixed-success `elementor/manage-default-styles` batch applies one valid site-wide sibling, fails one invalid tag, surfaces `elementor_manage_default_styles_partial_failure` with `partial_applied=true`, proves the applied sibling is inspectable through `elementor/get-default-styles`, then cleans the disposable style back up;
16. a disposable global color variable and global class are created through their public manage abilities and re-read through `elementor://global-variables` / `elementor://global-classes`; the runner enables Elementor's own Global Classes capability migration after noninteractive Atomic opt-in rather than bypassing the capability gate;
17. two additional disposable Atomic pages are built, the same global class is attached to a real heading on both pages by passing the **class label** to `elementor/manage-elements`, and the shared variable is referenced from supported Atomic CSS as `var(--<variable-label>)`; `elementor/get-page-structure` with `include_content:true` then proves the public serializer exposes the resolved global class ID plus the variable-backed local CSS without any private `_elementor_data` writes;
18. both shared-style pages are published and their rendered HTML/CSS assets prove the public global class label, initial global-class CSS, and initial global-variable value are all live; the disposable runner forces direct filesystem access so Elementor can write its generated Atomic CSS into the mounted uploads directory;
19. the global class CSS and global variable value are each updated **once**, with no per-page `manage-elements` rewrite afterwards; freshly fetched Elementor CSS bundles for both pages contain the new shared values, proving multi-page dependency propagation;
20. page A then receives one local Atomic color override while retaining the shared global class. After publishing that autosave, page A alone contains the local value, page B remains unchanged, both pages still expose the shared class attachment, and generated CSS shows the equal-specificity local style rule after the shared global-class rule so the local override wins by cascade order;
21. deleting the in-use global class removes it from the global resource and Elementor cleans the deleted class ID from both dependent page structures; deleting the global variable follows native tombstone semantics (`deleted=true`, inactive), and the disposable shared-style pages are removed afterwards;
22. `elementor/publish-document` publishes the primary acceptance page through the destructive + idempotent **DELETE** path;
23. the rendered frontend contains the repaired hero heading, hero paragraph/button, and second section content while rejected invalid content is absent;
24. the lane switches the disposable site to external Elementor CSS, fetches the actual `elementor-post-<id>` stylesheet through the rendered page, then performs an approved margin-only page-setting change and proves regenerated CSS contains the new margin while preserving the untouched padding; the initial margin/padding snapshot is restored afterwards;
25. no PHP fatal/uncaught error is present after the acceptance sequence;
26. with Atomic Editor disabled, an Atomic-only mutation fails predictably while the native `elementor_save_page` fallback remains discoverable;
27. the live installed `/wpvibe/v1` REST registry is normalized and every operational route is present in either the model-facing or infrastructure-only parity-manifest classification, ignoring only WordPress core's generated namespace index.

This is real disposable-site E2E evidence for schema-first Elementor Core page composition, strict warning handling, targeted update/duplicate/move/delete element operations, partial element-batch recovery, partial page-settings preservation, shared global-class/global-variable application across two Atomic pages, one-write dependency propagation, a verified local-over-global cascade, in-use global-class dependency cleanup, native variable tombstones, one disposable site-wide default-style partial-batch/cleanup flow, actual generated Elementor CSS regeneration, publishing/rendering, and the route-classification gate. The 1.20.5 lane additionally verifies one WPCode Lite create/deactivate/approval-gated-trash/replay-rejection workflow. It is **not** evidence that Elementor Pro Theme Builder, every global-class/global-variable/default-style/page-setting path, a production-scale full-site rebrand, pixel-perfect visual equivalence, other WPCode editions or caching configurations, other page builders, provider-backed browser/screenshot/Lighthouse services, media edge cases, or production/client environments were tested.

## Release-package verification

The release workflow first resolves plugin-header/version/stable-tag consistency and refuses to reuse an immutable version tag for a different commit. It also treats an already published release at the verified tag as a terminal no-op: existing ZIP/checksum assets are never uploaded with `--clobber`, and a release that appears during a build makes publication fail closed rather than overwrite it. For 1.5.1, publication requires all of the following:

1. source PHP syntax validation;
2. source legacy, parity, reference, 174-request skill routing benchmark, and focused Abilities raw-JSON request-body regression suites;
3. release ZIP build, ZIP integrity test, and SHA-256 generation;
4. extraction of the built ZIP;
5. extracted legacy, parity, reference, routing benchmark, and focused Abilities raw-JSON regression suites;
6. extracted PHP syntax validation;
7. a fresh current-remote-`main` check plus the latest exact-SHA `PHP parity checks` and `WordPress Elementor E2E` runs both reporting `completed:success` immediately before tag creation;
8. new immutable `v1.5.1` tag verification/creation and release publication only after those checks pass.

Existing tags remain immutable. In particular, published `v1.5.0` and `v1.4.1` tags and assets remain unchanged; the 1.5.1 workflow must create a new tag rather than move or overwrite any prior release.

## Broader manual disposable WordPress integration checklist

**Do not run destructive tests against production.** Use a disposable/staging site with current WPVibe and Direct MCP.

1. Generate a Direct MCP token; verify Bearer and X-header authentication first, verify query authentication fails while compatibility is disabled, then explicitly enable the setting and verify both `?token=` and `?access_token=` work.
2. Call health and `site_info`.
3. Inspect an active theme file.
4. Create a draft theme, make a trivial CSS change, get/inspect preview, then delete without publishing.
5. Recreate the draft, edit and publish only after explicit approval; verify `saved_customizations=set_aside` and `keep` on a test block theme where relevant.
6. Create a draft page through REST; exercise `search_content` + `edit_content` including unique/no/multiple-match cases.
7. Run safe WP-CLI read/status and an approval-required reversible/destructive workflow; inspect receipts and Direct approval replay/drift rejection.
8. On WordPress 6.9+, verify all Ability method classes and public aliases/input forms.
9. With WPCode installed on a disposable site, verify dormant create, owned-disabled update, a `snippet_review_required` update, exact-payload approval/replay rejection, no model-driven activation, and the resulting wp-admin human enable/re-enable path.
10. Public URL media: raster types, safe/malicious SVG, wrong MIME, redirects/private IPs, HTTP failures, dotted filenames, large/invalid images, metadata/parenting.
11. Device upload: PNG browser upload + replay rejection; safe SVG if upstream sanitizer exists; malicious SVG rejection.
12. Elementor 4.3+/WordPress 6.9+, extend the verified Atomic-ON lane beyond its current schema-first multi-section build + update/duplicate/move/delete/partial-recovery + reversible page-settings + two-page shared class/variable propagation/cascade/cleanup coverage to additional page-setting fields, widgets and interaction/dynamic-content schemas.
13. Extend the verified Atomic-OFF lane with native fallback save/schema behavior, not guessed `_elementor_data` writes.
14. Elementor site-wide styles: repeat the now-verified disposable two-page shared-class/global-variable flow against representative staging pages, add broader default-style propagation/full-site rebrand checks, visually inspect affected pages, then restore original state.
15. Elementor Pro when available: reuse/update a test Theme Builder template through discovered Pro abilities or `elementor_save_template`; verify display conditions/live rendering; avoid duplicate global templates.
16. Smoke-test Beaver, Bricks, and Breakdance native get/schema/save routes when installed.
17. Inspect WPVibe audit log and Direct MCP activity summaries.

## Current integration status

Published release 1.4.1 retains the original bounded disposable WordPress/Elementor Core CI target and route-classification gate. Release 1.5.0 strengthens that same lane with schema-first multi-section composition, strict lossy-warning rejection after approval, fresh-ID element updates, partial-batch recovery, Direct-side preservation of Elementor page settings, two-page shared global-class/global-variable application, one-write propagation, rendered local-over-global cascade verification, in-use class dependency cleanup, native variable tombstones, reversible default-style coverage, and actual generated Elementor CSS refresh verification. No production PHP change was required for the shared-style phase: the verified public Elementor contracts already behaved correctly once the E2E used the full public structure serializer and generated Atomic CSS assets. The 2026-10-13 full-code audit additionally hardens approval/upload/app/CLI transient persistence, malformed approval-receipt and upload-ticket record rejection, validated Gutenberg writes, saved-skill/editable-definition persistence, malformed saved-skill record discovery, malformed editable-definition replay, wp-admin credential/settings state, malformed activity-log recovery in tool logging and wp-admin rendering, UTF-8 PDF/rendered-HTML bounds, and release-asset immutability. The subsequent transport audit fixes known-method JSON-RPC notifications so they still execute but never emit result objects, including retained legacy mixed batches. Release **1.5.1** adds verified 1.20.3/1.20.5 matrix coverage and a real WPCode Lite retirement/approval/replay smoke on 1.20.5; the previous lack of WPCode live testing no longer applies to that bounded scenario. The rest of the broader manual checklist remains intentionally unmarked until executed. No production or customer WordPress site was used for destructive verification.
