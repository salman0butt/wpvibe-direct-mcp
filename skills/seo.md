# SEO Audit and Editing

Run `seo_audit` for a read-only baseline, then identify the active SEO plugin and its public REST/meta model before making changes. Preserve canonical URLs, robots directives, schema markup, redirects, titles, descriptions, Open Graph/Twitter metadata, breadcrumbs, and indexability. Do not duplicate JSON-LD that the theme or SEO plugin already generates, and never change global robots/index settings without explicit intent.

Use the audit findings as signals rather than automatic rewrite instructions: verify title/description context, heading structure, canonical correctness, missing image alt text, and schema coverage against the page purpose. Make the smallest supported change through public plugin APIs, registered meta, Abilities, or ordinary WordPress REST. Re-run the read-only audit and rendered HTML check afterward.
