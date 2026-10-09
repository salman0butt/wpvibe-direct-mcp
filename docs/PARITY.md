# WPVibe Capability Parity Matrix

Audit baseline: WPVibe **1.20.3**, upstream `main` commit **`8f303926ae11179e38bc0ecf2a87e3cbd18984c6`** (2026-10-06), WordPress.org stable 1.20.3. Direct MCP release: **1.2.0**.

Classification:

- **A — local upstream**: directly backed by the open-source WPVibe WordPress plugin.
- **B — Direct MCP**: implemented safely in this bridge.
- **C — optional external**: possible only with a separate user-owned external API/service; not bundled.
- **D — hosted-only / not applicable**: belongs to WPVibe account/gateway/fleet architecture, not one direct WordPress connection.
- **E — intentionally unsupported**: unsafe/misleading to reproduce locally.

| Capability | Current official MCP tool | WordPress/plugin endpoint | Direct MCP 1.0.4 status | Missing/stale behavior in 1.0.4 | Hosted-only or site-local | 1.2.0 state | Tests |
|---|---|---|---|---|---|---|---|
| Site inspection | `site_info` | `/wpvibe/v1/site-info` | Present | Older description/schema | A site-local | Retained; feature detection/admin summary | Route/tool tests |
| Registered meta | Direct extension | `/wpvibe/v1/registered-meta` | Present | None critical | A site-local | Retained | Tool presence/schema |
| REST API | `rest_api` | WordPress REST router | Present | Weak credential blocking; no response shaping | B + native WP | Site-local | GET/POST/PUT/PATCH/DELETE; JSON body; fields; size cap; block auth/app-password routes | REST hardening tests |
| Content search/edit | Direct extension | `/wpvibe/v1/content/search`, `/content/edit` | Present | Needed current `replace_all`/`whole_word` contract | A site-local | Retained, upstream semantics | Schema/delegation |
| File read | `read_file` | `/wpvibe/v1/file/read` | Present | No current `scope` behavior | A site-local | Add theme vs redacted wp-content scope | Schema tests |
| File list | `list_files` | `/wpvibe/v1/file/list` | Present | Missing scope/directory/current glob semantics | A site-local | Add scope/directory; use upstream recursive glob behavior | Schema tests |
| File search | `search_files` | `/wpvibe/v1/file/search` | Present | Older docs | A site-local | Retained | Route detection |
| File outline | `get_file_outline` | `/wpvibe/v1/file/outline` | Present | Older docs | A site-local | Retained | Route detection |
| File edit/write/delete | `edit_file`, `write_file`, `delete_file` | `/wpvibe/v1/file/*` | Present | Missing current compile/source hash semantics | A site-local | Current fields, retain upstream sandbox/PHP/SVG checks | Schema tests |
| Draft create | `create_draft_theme` | `/wpvibe/v1/draft-theme` | Present | Older conflict/recovery behavior | A site-local | Retained; upstream authoritative | Route detection |
| Draft preview | `get_preview_url` | `/wpvibe/v1/draft-theme/preview` | Present | Older current warning/expiry docs | A site-local | Retained | Route detection |
| Draft publish | `publish_draft_theme` | `/wpvibe/v1/draft-theme/publish` | Present | Missing `saved_customizations`, current Site Editor semantics | A site-local | Add `set_aside|keep`, expected source hash | Schema tests |
| Draft delete | `delete_draft_theme` | `/wpvibe/v1/draft-theme/delete` | Present | Older Site Editor set-aside docs | A site-local | Retained | Route detection |
| Classic theme scaffold | `create_classic_theme` | Current safe/classic route detected | Present | Route naming can vary | A site-local | Prefer safe route when present | Feature detection |
| WP-CLI status/run | `run_wp_cli` | `/wpvibe/v1/cli/status`, `/cli/run` | Present | Static description; no current receipt awareness | A site-local | Retain native PHP allowlist; no shell/eval/eval-file; preserve upstream approvals | Route detection |
| Operation receipt | Hosted status helpers / upstream receipts | `/wpvibe/v1/op-receipt/{id}` | Missing | No reconciliation tool | A site-local | Add `check_operation_receipt` when route exists | Prefix detection |
| WPCode snippet | `code_snippet` | `/wpvibe/v1/code-snippet/dormant` | Missing | No first-class safe snippet flow | A site-local | Add dormant create/update only; no DB bypass or silent activation | Route/schema tests |
| URL media import | `upload_media` | `/wpvibe/v1/upload-media` | Present | Only public URL; older error expectations | A site-local | Retain latest WPVibe SSRF/MIME/SVG path | Delegation + docs |
| Device/chat file upload | `request_upload`, `check_upload` | Hosted staging in cloud product | Missing in 1.0.4 | Structural transport gap | B local equivalent | **Covered**: 30-minute browser media ticket + Media Library insertion | Ticket/hash/replay/MIME tests |
| Stock image search | `search_images` | Hosted Unsplash integration | Missing | No search | C/D | **Conditional**: user-owned `wpvdmcp_search_images` provider; WPVibe/private keys never bundled | Provider tests + docs |
| Abilities discovery/info/run | `discover_abilities`, `get_ability_info`, `run_ability` | WordPress `/wp-abilities/v1/abilities...` | Missing | No first-class API | B + WordPress 6.9+ | Add discovery/info/run; preserve schemas/annotations; browser approval for writes | GET/POST/DELETE + approval tests |
| Elementor metadata/save | Elementor tools | `/wpvibe/v1/elementor/*` | Present | Missing current style schema/route details | A site-local | Current conditional native routes | Builder tests |
| Beaver Builder | Hosted workflows | `/wpvibe/v1/beaver/*` | Missing | No native save route | A site-local | Add modules/schema/save-page conditional tools | Builder tests |
| Bricks | Hosted workflows | `/wpvibe/v1/bricks/*` | Missing | No native save route | A site-local | Add get/elements/save-page conditional tools | Builder tests |
| Breakdance | Hosted workflows | `/wpvibe/v1/breakdance/*` | Missing | No native save route | A site-local | Add get/elements/save-page conditional tools | Builder tests |
| Gutenberg | Skills + REST | Core registered block schemas/REST | Generic only | No enforced validation | A/B | **Covered**: recursive `validate_blocks` + approval-gated `save_validated_blocks` | Validator/write tests |
| SeedProd | Hosted skill/Abilities | Public REST/Abilities where installed | Generic only | No safe universal private-save API | A/B conditional | **Covered conditionally**: richer discovery-first skill; public routes/Abilities only | Skill catalog |
| Divi / Divi 5 | Hosted skill/workflows | No dedicated WPVibe 1.20.3 save class found | Generic only | No safe universal private-save API | A/B conditional | **Covered conditionally**: richer discovery-first skill; never reverse-engineer proprietary storage | Skill catalog |
| WPBakery | Hosted skill/workflows | Usually WordPress post content + plugin APIs | Existing hard-coded skill | Skill system small | A/B conditional | Move to versioned skill; surgical shortcode editing | Skill catalog |
| Audit log | Direct `audit_log` | `/wpvibe/v1/audit-log` | Present | Older docs | A site-local | Retained; device uploads also write Direct activity summary | Route + code review |
| Rendered HTML | `get_page_html` | `/wpvibe/v1/rendered-html` | Present | Could be mistaken for JS DOM | A site-local | Explicitly describe as server/loopback rendered HTML; not browser JS execution | Docs |
| Navigate | `navigate` | `/wpvibe/v1/navigate` | Present | None critical | A site-local | Retained when route exists | Route detection |
| Approval panel/status | `show_approval_panel`, `check_approval_status` | Hosted MCP App UI + local approval/receipt mechanisms | Missing | No Direct approval UI | B partial / D inline UI | Browser-admin Direct approval for Abilities; keep upstream approval URLs/receipts; no fake inline MCP App panel | Approval tests |
| Skills | `load_skill` + hosted saved skills | Hosted account + public workflow concepts | 8 hard-coded | Too small/monolithic | B local catalog / D hosted saved skills | **Covered locally**: expanded file-backed catalog plus approval-gated persistent local skill CRUD | Skills CRUD/catalog tests |
| Account identity | `get_profile` | Hosted account | Missing | N/A | D | Not applicable to single-site Direct MCP | Documented |
| Site connect/list/remove | `connect_site`, `list_sites`, `remove_site` | Hosted account/application-password orchestration | Missing | N/A | D | Not applicable: Direct MCP already represents one installation | Documented |
| Usage reset/plan | `use_usage_reset` / usage UI | Hosted billing/usage | Missing | N/A | D | Not applicable | Documented |
| Fleet jobs/dashboard | `start_fleet_job`, `show_fleet_dashboard` | Hosted cross-site orchestration | Missing | N/A | D | Not applicable; do not confuse with WordPress Multisite | Documented |
| Page audit | `audit_page` / performance diagnostics | Hosted/external browser/PageSpeed style service | Missing | N/A | C/D | **Conditional**: `page_audit` through user-owned `wpvdmcp_page_audit` provider | Provider tests + docs |
| Hosted MCP App upload/approval UI | Interactive panels | Hosted MCP Apps | Missing | N/A | D | Browser-link fallback only | Docs + browser approval/upload implementation |

## Important findings

- WPVibe 1.20.3 changed file glob behavior to match recursively and added current Site Editor publish handling (`saved_customizations` semantics) plus WordPress.org theme-folder warnings.
- The open-source repository is the **WordPress-side component**; the hosted WPVibe gateway/account/tool registration is explicitly outside that repository.
- Official public docs currently describe site connection/account tools, REST/media, Abilities, theme/file tools, WP-CLI, rendered HTML/navigation, and skills. Product pages also advertise hosted image search, interactive panels, fleet/bulk workflows, saved skills, and performance tooling. Direct MCP only implements the parts that make sense safely on one WordPress installation.

## Not implemented by design

No fake implementations are returned for account profile, cloud site registry, plan usage reset, cloud fleet orchestration, hosted saved skills, hosted MCP App panels, cloud Unsplash search, or cloud/real-browser performance audit. A caller can distinguish “not applicable” from a missing local feature by reading this file and the wp-admin capability summary.

## 1.2.0 Features-page parity additions

- **Editable Fields:** persistent Direct MCP field/group/setting declarations replay through WPVibe native registration APIs. All 13 upstream post-field types are accepted; global settings use the upstream-safe subset.
- **Validated Block Output:** registered Gutenberg schemas are checked recursively before `save_validated_blocks` can mutate content. Kadence and GenerateBlocks are covered through their installed block registrations.
- **Kadence / GeneratePress / GenerateBlocks:** dedicated skills plus capability detection; no undocumented proprietary storage writes.
- **Saved Skills:** local create/update/delete with one-time approvals, immutable built-ins, versions, and storage limits.
- **PDF/media intelligence:** safe metadata plus optional bounded PDF text from a site-owner provider; no filesystem paths leak.
- **Site Intelligence / Live Reload:** bounded environment diagnostics and detection of WPVibe `/last-change` polling.
- **SEO audit:** read-only rendered-HTML checks for key technical/on-page signals.
- **Lighthouse, stock images, JS browser render:** conditional user-owned providers; no WPVibe cloud credentials are cloned.

Hosted account identity, billing/usage, WPVibe cloud site registry, fleet orchestration, and hosted MCP UI panels remain intentionally outside a single-site self-hosted bridge.
