# SEOPress

Start with `discover_abilities` and the `seopress` namespace. Inspect schemas using `get_ability_info`; current public abilities include post title/description, robots/canonical, social metadata, content analysis and global title settings. Read current values before changing them, then call `run_ability` with only schema-valid inputs. Keep canonical/indexing changes explicit because they can affect search visibility. After writes, re-read the same ability and inspect the rendered page/SEO audit so stored and frontend state agree. Never write undocumented SEOPress options directly.
