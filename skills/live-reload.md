# Live Reload

WPVibe live reload uses the site-local `/wpvibe/v1/last-change` polling mechanism. Use `live_reload_status` to confirm availability and `navigate` only for deliberate same-site navigation. Do not build a second competing websocket transport. WPVibe intentionally suppresses automatic reload in known builder edit sessions because refreshing Elementor, Divi, Beaver, Bricks, Breakdance, WPBakery and similar editors can destroy in-progress state. Treat unavailable polling as a capability gap, not a reason to force-refresh the browser.
