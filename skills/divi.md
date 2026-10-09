# Divi / Divi 5

Start with `integration_capabilities`, REST discovery, and Abilities discovery. Prefer public/native Divi APIs if the installed version exposes them. Divi content can involve builder-specific structures and compiled output, so do not reverse-engineer undocumented serialized storage or overwrite builder meta with guessed JSON. When no safe native save API exists, limit changes to ordinary WordPress fields, public plugin APIs, or theme files in WPVibe's draft sandbox.

Preserve module hierarchy, responsive settings, dynamic content, Theme Builder assignments, global presets, and shortcodes that you did not intentionally change. Verify frontend rendered HTML after a supported mutation. If a task requires proprietary builder storage that the installed Divi version does not expose safely, report that capability boundary instead of pretending the write succeeded.
