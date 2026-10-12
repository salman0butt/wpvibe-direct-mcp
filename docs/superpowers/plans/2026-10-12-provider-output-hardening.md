# Provider Output Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Centralize provider-output secret redaction and bounded traversal so user-owned providers cannot leak credential-shaped fields or multi-megabyte/deep payloads directly to MCP callers.

**Architecture:** Add a focused `WPVDMCP_Provider_Output` helper at provider return boundaries, not at the global MCP result layer. Both local insights providers and reference/hosted providers use the helper; native WPVibe fallbacks, PDF text, and contract-specific HTML bounds keep their existing semantics.

**Tech Stack:** PHP 7.4+, WordPress `WP_Error`, existing lightweight PHP test harness and disposable WordPress/Elementor E2E.

**Spec:** `docs/superpowers/specs/2026-10-12-provider-output-hardening-design.md`

## Global Constraints

- Maximum provider nesting depth is 8.
- Maximum provider collection length processed is 200 items.
- Maximum single provider string is 65,536 bytes.
- Maximum serialized provider result is 262,144 bytes; escaping/encoding expansion fails closed with explicit sanitizer metadata.
- Provider error codes are bounded to 256 bytes when pathological, messages to 4,096 bytes, invalid UTF-8 is made JSON-safe, error data uses the same sanitizer, and the complete model-visible error envelope stays within the serialized ceiling.
- Redaction is key-context based; ordinary prose containing words such as “token” stays intact.
- `provider_unavailable`, approval boundaries, Worker proofs, native REST behavior, and `v1.4.1` remain unchanged.

## Review Focus

- Header-like, contextual value/data, prefixed transport-auth, camelCase, leading-acronym (`AWSAccessKeyId`, `XApiKey`), OAuth consumer, and secret-access credential keys nested several levels deep are removed while safe count/status/public-key fields survive.
- A provider can return a very long string without bypassing the total/string bounds or losing truncation metadata.
- Large/deep arrays cannot amplify output past the collection/depth guards.
- Provider `WP_Error` data cannot leak credential fields; ordinary error codes/messages remain unchanged while pathological code/message/encoding inputs and Unicode JSON-escaping expansion are bounded using the server's actual error serialization flags, so the complete model-visible envelope cannot exceed the provider-output ceiling.
- Successful provider output uses the server's actual pretty-print/Unicode text flags for the final serialized-size check so compact-vs-wire formatting expansion cannot bypass the ceiling.
- Both callback-global and WordPress filter branches traverse the same sanitizer.
- Unmodified provider outputs keep their original shape so existing integrations do not see needless compatibility churn.

---

### Task 1: Pin the vulnerable provider behavior with RED tests

**Files:**
- Create: `tests/reference/cases-18.php`
- Modify: `tests/reference-run.php`

**Interfaces:**
- Consumes: existing `WPVDMCP_Parity::execute()` provider-backed tool paths.
- Produces: failing regressions defining the shared sanitizer contract.

- [ ] **Step 1: Add local-provider secret-context regression**

Use `search_images` with nested `api_key`/`Authorization` fields, normal prose containing “token”, and a `token_count` field. Assert secret fields are absent, prose/count survive, and redaction metadata is present.

- [ ] **Step 2: Add size/collection/depth regression**

Use `page_audit` provider output containing a >65,536-byte string, >200-item collection, >8-level nested value, and escaping-heavy data. Assert current source violates the per-value/serialized bounds before implementation.

- [ ] **Step 3: Add reference-provider and `WP_Error` regressions**

Use `get_profile` for nested credential fields and a provider-created `WP_Error` for credential-bearing/oversized data. Include a huge error code and invalid UTF-8 message and assert the model-visible error JSON stays valid and within the hard ceiling while ordinary code/message values remain stable.

- [ ] **Step 4: Run the focused reference suite and verify RED**

Run: `php tests/reference-run.php`

Expected: new provider-output cases fail for the missing shared redaction/bounds behavior while existing cases remain green.

### Task 2: Implement the shared provider-output normalizer

**Files:**
- Create: `includes/class-wpvdmcp-provider-output.php`
- Modify: `includes/class-wpvdmcp-parity-insights.php`
- Modify: `includes/class-wpvdmcp-reference-parity.php`

**Interfaces:**
- Consumes: arbitrary provider callback/filter return values plus provider name.
- Produces: `WPVDMCP_Provider_Output::normalize( $value, $provider_name )` returning the same safe value shape when unchanged, or a bounded/redacted value with explicit `_wpvdmcp_provider_output` metadata when modified.

- [ ] **Step 1: Implement key normalization and credential-key detection**

Support case, separator, and camelCase variants from the spec. Remove credential-key values and count redactions; do not inspect ordinary prose for keyword presence.

- [ ] **Step 2: Implement recursive budget/depth/item/string limits**

Walk arrays/objects with shared state, enforce 8 levels / 200 items / 65,536 bytes per string / 262,144-byte approximate total budget, and emit visible bounded markers plus metadata when modified.

- [ ] **Step 3: Normalize provider `WP_Error` safely**

Recreate the error with ordinary code/message semantics preserved, a 256-byte pathological-code cap, 4,096-byte message bound, JSON-safe strings, sanitized data/metadata, and a final complete-envelope serialized-size check.

- [ ] **Step 4: Route both provider families through the helper**

Normalize successful/error provider returns in `WPVDMCP_Parity_Insights::provider()`, `WPVDMCP_Reference_Parity::call_reference_provider()`, and hosted `provider()`. Remove the SeedProd-only `redact_provider_secrets()` path after the shared helper covers it.

- [ ] **Step 5: Run focused GREEN tests**

Run: `php tests/reference-run.php`

Expected: all provider-output cases and existing reference cases pass.

### Task 3: Document, fully verify, and prepare exact-head review

**Files:**
- Modify: `SECURITY.md`
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Modify: `docs/PARITY.md`
- Modify: `docs/TESTING.md`

**Interfaces:**
- Consumes: completed shared normalizer and regressions.
- Produces: durable security/parity documentation and a reviewable branch.

- [ ] **Step 1: Document the provider trust boundary and limits**

Describe centralized key-context redaction, size/depth/collection limits, explicit metadata, and the fact that provider-backed tools still require user-owned providers.

- [ ] **Step 2: Run full local verification**

Run:
`php tests/run.php`
`php tests/parity-run.php`
`php tests/reference-run.php`
`php tests/abilities-json-body-run.php`
full PHP syntax scan
`git diff --check`

Expected: zero failures/errors.

- [ ] **Step 3: Commit, push, and open a narrow PR**

Use a security-focused commit/PR title and record exact base/head SHAs.

- [ ] **Step 4: Require exact-head CI and independent review**

Both PHP parity and disposable WordPress/Elementor E2E must be green for the exact PR head, and at least one independent reviewer must report no Critical/Important finding. Any head change invalidates prior evidence.

- [ ] **Step 5: Merge and reverify exact main**

Merge with expected-head locking, verify exact-main PHP/E2E, and verify the Release workflow skips republishing existing `v1.4.1`.
