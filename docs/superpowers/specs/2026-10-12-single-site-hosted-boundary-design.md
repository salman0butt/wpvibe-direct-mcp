# Single-Site Hosted Boundary Design

## Goal

Keep WPVibe Direct MCP intentionally single-site. Each WordPress installation runs its own manually installed Direct MCP instance and manages only that local WordPress site.

Direct MCP must not add a built-in multi-site registry, remote credential vault, cross-site execution layer, or local fleet-control plane. Hosted/account/fleet public names may remain visible for reference parity through the existing user-owned provider mechanism, but Direct MCP must continue to fail closed when such a provider is absent and must never fabricate WPVibe private-cloud state.

## User intent and success criteria

The deployment model is one Direct MCP installation per managed WordPress site. Multi-site discovery or orchestration inside Direct MCP would add credential custody, outbound-network, SSRF, lifecycle, and fleet-execution complexity that is unnecessary for this deployment model.

Success means:

- a Direct MCP instance manages only its own WordPress installation;
- users install and configure Direct MCP independently on every site they want to manage;
- no built-in remote-site credentials are persisted by Direct MCP;
- no built-in cross-site network transport or fleet scheduler is introduced;
- exact hosted compatibility names remain honest about their implementation source;
- existing external provider integrations continue to work unchanged;
- missing external providers continue to return the established fail-closed `provider_unavailable` behavior;
- authentication, approvals, Worker/control-plane boundaries, provider-output hardening, and release immutability remain unchanged;
- release `v1.4.1` remains immutable.

## Architecture decision

### 1. Direct MCP remains a site-local server

The WordPress site on which the plugin is installed is the only native management target.

Native and local-equivalent tools may read or mutate that site according to their existing route, capability, approval, and validation rules. Nothing in this design adds a concept of a locally registered remote site.

There is no Direct-managed `site_id` registry, remote-site inventory, credential store, or remote transport abstraction.

### 2. Hosted-style site-management names remain provider-backed compatibility tools

The existing exact public names remain available for compatibility where already exposed:

- `connect_site`
- `list_sites`
- `remove_site`
- `get_profile`
- `start_fleet_job`
- `show_fleet_dashboard`
- `use_usage_reset`

These names are not converted into native local multi-site behavior.

Their semantics remain:

1. If an explicit user-owned provider is configured through the established extension points, Direct MCP delegates to that provider.
2. Provider outputs continue through the shared provider-output normalization and redaction boundary.
3. If no provider is configured, execution fails closed with `provider_unavailable` rather than synthesizing account, site, plan, job, quota, or fleet data.
4. Discovery/readiness metadata may describe provider registration status, but computing that metadata must not execute a provider callback or disclose credentials/provider return values.

This preserves exact-name parity without claiming that Direct MCP recreates WPVibe private hosted infrastructure.

### 3. Existing external provider extension points remain authoritative

Direct MCP keeps the established extension points unchanged:

- `$GLOBALS['wpvdmcp_reference_providers']`
- `wpvdmcp_reference_<tool>` filters

No built-in registry provider is inserted ahead of, behind, or alongside these integrations because this design explicitly rejects a built-in multi-site implementation.

Provider owners remain responsible for their own credentials, outbound networking, allowlists, SSRF policy, rate limits, retention, consent, licensing, and external-service security as already documented.

### 4. No remote credential custody

Direct MCP must not introduce persistent storage for credentials belonging to other WordPress sites.

Specifically, this design excludes:

- encrypted remote credential tables/options;
- WordPress-salt-derived remote credential vaults;
- dedicated remote credential master keys;
- credential rotation/re-encryption flows for other sites;
- remote application-password inventories;
- model-visible remote authentication material;
- cross-site session/token caches.

Credentials explicitly supplied to an external provider remain governed by that provider contract and existing provider-output/logging safety boundaries. Direct MCP itself does not become their persistent vault.

### 5. No built-in cross-site network execution layer

Because Direct MCP does not natively manage remote WordPress installations, it does not need a new cross-site HTTP transport, DNS/IP policy engine, redirect policy, private-network allowlist, remote retry scheduler, or connection verifier for multi-site management.

This does not remove or weaken existing network protections for current site-local/provider-backed tools. It only prevents adding a new remote-site management transport that the deployment model does not require.

### 6. No built-in fleet orchestration

`start_fleet_job` and `show_fleet_dashboard` remain hosted/provider-backed compatibility capabilities.

Direct MCP does not add:

- local fleet job persistence;
- cross-site step scheduling;
- remote fan-out execution;
- fleet retries/rollback;
- fleet dashboard state;
- cross-site idempotency coordination.

If a user later needs fleet behavior, it belongs in an explicitly separate architecture decision or an external provider/service, not as an implicit extension of the single-site plugin.

## Public contract behavior

### `connect_site`

The exact name remains provider-backed. Direct MCP does not reinterpret it as "connect this installation to another WordPress site" and does not persist a remote site locally.

Without a provider, the current `provider_unavailable` boundary remains authoritative.

### `list_sites`

The exact name remains provider-backed. Direct MCP does not return the current local site as a fabricated one-item hosted registry and does not construct a local registry from installed plugin state.

Without a provider, the current `provider_unavailable` boundary remains authoritative.

### `remove_site`

The exact name remains provider-backed. Direct MCP has no local remote-site record to delete.

Without a provider, the current `provider_unavailable` boundary remains authoritative.

### Hosted account/fleet tools

`get_profile`, `start_fleet_job`, `show_fleet_dashboard`, and `use_usage_reset` likewise remain provider-backed and must not synthesize WPVibe account, subscription, quota, reset, job, or fleet state.

## Data flow

### Native single-site tools

1. MCP request is authenticated by the existing Direct MCP authentication boundary.
2. Tool discovery/routing resolves the local/native/local-equivalent implementation.
3. Existing capability, route, schema, approval, Worker/control-plane, replay, and payload checks run as applicable.
4. The operation executes against the same WordPress installation.
5. Existing response bounding/sanitization rules apply.

No remote-site registry lookup or remote credential retrieval is added to this path.

### Provider-backed hosted compatibility tools

1. MCP request is authenticated normally.
2. The exact hosted compatibility tool resolves through the existing provider contract.
3. Direct MCP checks provider registration without invoking it during discovery/readiness calculation.
4. At execution time, an explicitly configured provider may run.
5. Provider output passes through the shared provider-output normalization boundary.
6. If no provider exists, Direct MCP returns `provider_unavailable`.

No built-in fallback may fabricate hosted state.

## Error semantics

This design preserves existing errors rather than adding a new multi-site error family.

Important invariants:

- absent hosted provider: `provider_unavailable`;
- unavailable native/site-local capability: existing `tool_unavailable` or tool-specific error semantics;
- invalid arguments: existing tool/provider-contract validation errors;
- approval-required operations: existing Direct MCP approval flow;
- Worker/control-plane-only operations: existing fail-closed boundary;
- provider errors/results: existing provider-output normalization and secret-redaction boundary.

A caller must be able to distinguish "not implemented locally / no provider configured" from successful local site state. Direct MCP must never convert provider absence into an empty hosted registry, fake profile, fake quota, or completed fleet job.

## Security and trust boundaries

The following boundaries are unchanged and remain authoritative:

- bearer/header authentication and current query-token opt-in behavior;
- administrator ownership/capability checks;
- one-time approval consumption and replay protection;
- WPVibe Worker operation-proof isolation;
- generic REST control-plane route blocking;
- provider-output credential-key redaction and recursive size/depth limits;
- provider readiness detection without callback/filter execution;
- MCP Apps metadata separation from model-visible content;
- release current-main verification and immutable release/tag rules.

This design intentionally reduces attack surface by not introducing remote credential custody or a new cross-site SSRF/network boundary.

## Compatibility and documentation

Existing documentation already distinguishes Direct MCP's one-site transport replacement from WPVibe hosted account/fleet/plan/cloud services. Future documentation changes should make the deployment model explicit where useful:

> Install Direct MCP separately on each WordPress site you want to manage. Direct MCP natively manages only the site on which it is installed. Hosted account, connected-site registry, and fleet capabilities remain external-provider-backed compatibility surfaces.

`docs/PARITY.md`, `docs/COMPATIBILITY.md`, and `docs/PROVIDERS.md` should preserve the distinction between:

- native/site-local functionality;
- local-equivalent functionality;
- user-owned external provider functionality;
- WPVibe private hosted infrastructure that Direct MCP does not recreate.

No documentation should imply that `connect_site`, `list_sites`, `remove_site`, profile, quota, or fleet state becomes locally implemented by this decision.

## Testing strategy

No production implementation is required to establish this architecture decision. The implementation plan that follows this spec should therefore focus only on verifying/documenting the boundary and closing any misleading parity/documentation gaps that actually exist.

Tests must preserve these behaviors:

1. `connect_site`, `list_sites`, and `remove_site` remain present as exact compatibility names where the parity catalog requires them.
2. With no configured provider, hosted compatibility execution still returns `provider_unavailable` and does not fabricate state.
3. External `$GLOBALS['wpvdmcp_reference_providers']` callbacks still execute when explicitly configured.
4. Existing `wpvdmcp_reference_<tool>` filter providers still execute when explicitly configured.
5. Provider discovery/readiness checks do not invoke callbacks/filters.
6. Provider outputs remain subject to the shared provider-output sanitizer/redactor.
7. No new WordPress option/table/secret is created for remote-site credentials.
8. Existing authentication, approval, Worker, REST-blocking, MCP Apps, and release regressions remain green.

If current tests already prove an invariant, the implementation plan should reuse them rather than duplicate coverage solely to satisfy this design document.

## Out of scope

The following are intentionally not part of Direct MCP under this design:

- built-in multi-site registry;
- WordPress-as-control-hub credential vault;
- companion credential-custody service bundled with Direct MCP;
- automatic discovery of other WordPress installations;
- native `connect_site` persistence;
- native `list_sites` inventory;
- native `remove_site` deletion/revocation lifecycle;
- cross-site REST/WP-CLI execution;
- fleet scheduler/dashboard;
- cross-site retries, rollback, or idempotency coordination;
- hosted profile, billing, plan, quota, or usage-reset emulation.

A future request for any of these capabilities requires a new architectural brainstorming/spec/plan cycle rather than being treated as implied by parity work.

## Release boundary

This design is an unreleased architecture decision on top of current `main`. It does not alter or republish release `v1.4.1`.

Any future code or documentation PR derived from this spec must use the normal exact-head verification flow. Release automation must continue to skip release/tag/publication work unless an explicitly authorized new release is being prepared.

## Acceptance criteria

The design is satisfied when:

- the repository has a durable written statement that Direct MCP is intentionally single-site;
- no built-in multi-site registry or remote credential subsystem is introduced;
- current hosted compatibility names remain provider-backed and fail closed without providers;
- external provider hooks remain backward compatible;
- parity/compatibility documentation does not imply private hosted state is recreated;
- any implementation work derived from this spec is limited to documentation/test/contract corrections needed to enforce those boundaries;
- all applicable existing verification remains green;
- `v1.4.1` remains untouched.
