# Provider Output Hardening Design

## Goal

Prevent user-owned provider callbacks/filters from returning credential-shaped fields or unbounded payloads directly to MCP clients, while preserving useful structured output and the existing provider-unavailable/fallback semantics.

## Trust boundary

The affected runtime boundary is **provider output only**:

- `WPVDMCP_Parity_Insights` providers (`page_audit`, `search_images`, `render_browser`).
- `WPVDMCP_Reference_Parity` providers, including screenshots, SeedProd browser automation, hosted account tools, and fleet tools.
- Provider-created `WP_Error` data returned through those same paths.

Existing native WordPress/WPVibe responses are not globally rewritten. PDF text keeps its existing explicit 50,000-character maximum, and `get_page_html` keeps its existing contract-specific HTML maximum (default 50,000 characters).

## Shared normalizer

Add one `WPVDMCP_Provider_Output` helper and call it immediately after provider callbacks/filters return. It must be PHP 7.4 compatible and deterministic.

Safety limits:

- Maximum nesting depth: **8**.
- Maximum items processed in one collection: **200**.
- Maximum bytes in one provider string: **65,536**.
- Maximum serialized provider output: **262,144 bytes**. Traversal keeps an internal content reserve below that ceiling, then verifies the JSON-encoded result; pathological escaping/encoding expansion fails closed to a compact explicit truncation result instead of crossing the limit.
- Provider `WP_Error` messages keep their code/message semantics but messages are bounded to **4,096 bytes**; error data is normalized with the same rules as successful output.

The normalizer removes values under credential-shaped keys. Key matching is case-insensitive, understands camelCase/separator variants, and is based on key context rather than prose content. It must cover exact credential keys such as `auth`, authorization/bearer forms, cookie forms, password/secret forms, API keys, access/refresh/ID tokens, private/secret/consumer/access-key forms (including AWS access-key names), credential fields, one-time `login_url`, and nonce fields, plus keys ending in credential suffixes such as `_token`, `_secret`, `_password`, `_api_key`, `_private_key`, `_nonce`, `_cookie`, or `_credential`.

Do **not** redact an ordinary value merely because prose contains words such as “token” or “password”. A field such as `token_count` also remains intact because the key describes a count, not a credential.

## Truncation and metadata

When nothing is changed, preserve the provider result shape exactly.

When redaction, truncation, collection clipping, depth limiting, or object normalization occurs, expose explicit metadata under `_wpvdmcp_provider_output` with the provider name and applicable counters/flags. For associative-array/object roots, attach the metadata directly. If an unsafe top-level scalar/list must be modified, wrap the sanitized value as `data` plus `_wpvdmcp_provider_output`; ordinary unmodified scalar/list outputs remain unchanged.

Oversized strings must include a visible truncation marker. Depth overflow must be replaced with a visible bounded marker. Dropped collection items are counted in metadata rather than silently disappearing. If JSON escaping would still make the serialized result exceed 262,144 bytes, return a compact result with `serialized_limit: true` metadata.

Objects are normalized through their JSON/public-property representation before recursion so nested fields receive the same redaction/depth/size rules. Resources/unsupported runtime values are replaced with a bounded marker and recorded as truncated.

## Compatibility constraints

- Missing providers still return `provider_unavailable` exactly as today.
- Existing argument normalization is unchanged.
- Bearer/query auth, approvals, Worker proofs, WordPress REST dispatch, and release semantics are out of scope.
- SeedProd no longer uses a one-off redactor; its provider result flows through the shared normalizer instead.
- No provider input is modified by this unit.
- Do not change or republish `v1.4.1`; document this under Unreleased.

## Verification

RED tests must prove current source leaks nested credential-key fields and permits oversized/deep provider data across both provider families. GREEN tests must cover:

- local provider nested credential redaction;
- normal prose and `token_count` preservation;
- hosted/reference provider using the same normalizer;
- oversized strings and collections with explicit metadata;
- depth limiting;
- `WP_Error` data redaction/bounds while retaining code/message;
- unchanged `provider_unavailable` behavior;
- existing HTML/PDF bounds remaining green.
