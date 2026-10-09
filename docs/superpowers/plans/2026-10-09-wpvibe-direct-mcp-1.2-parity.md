# WPVibe Direct MCP 1.2 Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use executing-plans with test-driven-development.

**Goal:** Ship tested site-local parity for the remaining WPVibe Features-page gaps.

**Architecture:** Add a focused parity subsystem beside existing route wrappers, delegate native operations to WPVibe/WordPress, and use explicit user-owned provider hooks for cloud-adjacent services.

**Tech Stack:** PHP 7.4+, WordPress REST/MCP, WPVibe 1.20.3 APIs, dependency-free PHP tests.

**Spec:** `docs/superpowers/specs/2026-10-09-wpvibe-direct-mcp-1.2-parity-design.md`

## Tasks
- [x] Persistent editable fields/groups/settings with upstream field types and approvals.
- [x] Recursive registered-block validation plus approval-gated validated save.
- [x] Local saved-skill CRUD, immutable built-ins, versions and storage limits.
- [x] Safe media/PDF inspection with bounded provider extraction.
- [x] Site intelligence, integration detection, and live-reload status.
- [x] Read-only SEO HTML audit.
- [x] User-owned provider interfaces for page audit, image search, and JS rendering.
- [x] Kadence/GeneratePress/GenerateBlocks and parity workflow skills.
- [x] Release metadata, parity docs, full verification, ZIP, GitHub publish.
