# CartFlows

Use `discover_abilities` for the `cartflows` namespace. Read funnels, steps and analytics first, and call `get_ability_info` before mutations so page-builder links/settings and required inputs are preserved. Publishing/unpublishing affects visitor-visible funnels, so make the target flow and step order explicit and use `run_ability` with its declared approval semantics. For page content inside a step, use the actual builder integration rather than editing CartFlows private meta. Verify flow status, ordered steps, URLs and rendered checkout/thank-you behavior after changes.
