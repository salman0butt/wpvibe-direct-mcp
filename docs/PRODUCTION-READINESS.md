# Production readiness — bounded evidence

This audit started from immutable published Direct MCP `v1.5.1` (`ec035e95ac62af1057c9b744d2f870465141a51f`). The changes below are **unreleased source** until a separate reviewed release; the existing v1.5.1 ZIP and tags remain unchanged.

## Verified in PHP fault-injection tests

- An acknowledged but unpersisted `pending`, `approved`, or `rejected` approval transient cannot return success. For uncertain decisions, the one-time claim remains locked until the transient expires, rather than allowing an opposing decision or destructive replay.
- Approved receipts bind to the credential fingerprint, not just the Administrator user ID. Rotating/revoking that bearer prevents use of older receipts, and a new credential can request and consume its own independently approved receipt. Legacy unbound receipts deliberately fail closed and must be re-requested.
- Failed upload transitions are not considered terminal unless the exact `failed` record can be read back. Uncertain state preserves the transition claim rather than enabling a second browser upload attempt.
- A failed `wp_delete_attachment` rollback returns `upload_rollback_failed` with `partial_applied`, the affected attachment IDs and the original upload error code. The attempted cleanup covers every attachment, even after a failed deletion. The browser and activity log display a manual-reconciliation warning.

These tests use WordPress-function mocks to inject failures. They do **not** prove that any particular MySQL, cache, or media-storage deployment experiences these faults.

## Disposable WordPress + MySQL integration

The `WordPress Elementor E2E` workflow already uses a real disposable WordPress 6.9 installation with MySQL 8, alongside pinned WPVibe 1.20.3 or 1.20.5 and Elementor Core 4.3.4. It now also runs `tests/e2e/approval-reliability-smoke.sh` after the ordinary Elementor/WPCode flows. The smoke asks eight separate WordPress CLI processes to consume one approved receipt and asserts exactly one winner; it rotates a token hash with the owner unchanged and proves the old approved receipt can no longer be used. This new gate is only verified once the **exact PR-head hosted CI succeeds**.

## Important limitations and future acceptance work

- No local Docker daemon was available during this audit, so a local MySQL or Redis instance was not run. Hosted MySQL checks are intentionally disposable; never test destructive workflows against customer sites.
- An external Redis/Memcached object-cache drop-in, cache eviction/replication, multi-host WordPress, persistent proxy caching, and interrupted PHP processes have **not** been live-certified. Run those in a purpose-built disposable environment before claiming distributed-systems reliability.
- Real Media Library deletion failures, short-lived upload ticket expiration under Redis, WordPress admin nonce/cookie interaction, the full active-to-inactive WPCode execution-cache purge and licensed builder workflows remain manual/extended integration scenarios.
- Current plugin architecture is **single-site**, not a hosted multi-site fleet controller. Install independently per WordPress site.

## Release/operational policy

Review the exact candidate diff and green PHP 7.4/8.2/8.4 matrix, both WordPress/Elementor lanes, new MySQL contention smoke and the existing 174-request skill-intent benchmark. Merge only verified fixes. Increment the plugin version before publishing a new immutable release; a merge that retains v1.5.1 metadata must not overwrite existing v1.5.1 ZIP/checksum assets.
