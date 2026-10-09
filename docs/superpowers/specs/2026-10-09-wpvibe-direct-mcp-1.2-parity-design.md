# WPVibe Direct MCP 1.2 Site-Local Parity Design

## Goal
Cover every missing WPVibe Features-page capability that can safely exist on one self-hosted WordPress connection, while clearly separating cloud account/fleet/private-provider features.

## Architecture
Direct MCP remains a thin MCP transport/auth bridge. Native WPVibe routes continue to own file/theme/CLI/builder safety. A focused `WPVDMCP_Parity` subsystem owns capabilities that span multiple WordPress APIs: persistent editable-field declarations, block validation, saved local skills, safe media/PDF inspection, diagnostics, SEO audit, integration detection, and optional user-owned external providers.

## Safety boundaries
All local mutations that Direct MCP itself owns require existing one-time browser approvals. Built-in skills are immutable. Block content is validated before write. Media inspection strips filesystem paths. PDF extraction, browser rendering, Lighthouse-style auditing, and stock-image search are provider-backed and contain no bundled WPVibe/private third-party credentials. Account identity, billing, WPVibe cloud site registry, cloud fleet orchestration, and hosted MCP panels remain out of scope.

## Compatibility
PHP 7.4+, WordPress 6.0+, WPVibe 1.20.3 audit baseline. Feature detection wins over version assumptions. WordPress 6.9+ remains required for first-class Abilities.
