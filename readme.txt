=== Real MCP ===
Contributors: bikashcmahata
Donate link: https://github.com/sponsors/bikashcmahata
Tags: mcp, ai, api, llm, automation
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turns your WordPress site into an MCP server. AI agents can manage content, SEO, media, and more — no external server needed.

== Description ==

Real MCP transforms your WordPress site into a fully compliant [Model Context Protocol](https://modelcontextprotocol.io/) server. Any MCP-compatible AI agent can connect to your site and perform real operations — writing posts, optimizing SEO, managing products, auditing security, and much more.

**No external server. No Node.js. No Python. Just WordPress.**

The plugin itself acts as the MCP server using the Streamable HTTP transport. When you connect an AI agent, it gains intelligent access to your entire WordPress site.

= What AI Agents Can Do =

**Content** — Write blog posts, rewrite articles, generate FAQs, merge content, create landing pages and documentation.

**SEO** — Run site audits, fix missing meta descriptions, improve Rank Math/Yoast scores, generate structured data, optimize internal linking.

**WooCommerce** — Create products, update inventory, bulk adjust prices, manage coupons, plan seasonal campaigns.

**Media** — Upload images, bulk-update alt text for accessibility, regenerate thumbnails.

**Elementor** — Read and update page builder content without opening the editor.

**Security** — Audit configuration, review plugin risks, check user permissions.

**Performance** — Clean database bloat, flush caches, identify optimization opportunities.

**Maintenance** — Update plugins and themes, run health checks.

**Accessibility** — WCAG audit of content, heading hierarchy, link text quality.

= 30+ Built-in Tools =

The plugin ships with tools organized by category. WooCommerce and Elementor tools auto-enable only when those plugins are active.

= Key Features =

* **Zero dependencies** — Pure PHP, runs entirely within WordPress
* **Secure by default** — API key authentication, origin validation, capability checks per tool
* **Smart plugin detection** — WooCommerce and Elementor tools activate automatically
* **SEO plugin aware** — Works with Yoast, Rank Math, and All in One SEO
* **Cache plugin aware** — Integrates with WP Super Cache, W3TC, LiteSpeed, WP Rocket
* **Read and write** — Full CRUD on posts, pages, products, media, and settings
* **Lightweight** — No database tables, no cron jobs, no external requests
* **Extensible** — Register custom tools via `real_mcp_register_tools` hook

= How It Works =

1. Install and activate the plugin
2. Go to Settings → Real MCP
3. Generate an API key
4. Connect your AI agent using the endpoint URL and key

= Privacy =

This plugin does not collect, transmit, or share any user data. It does not connect to any external service. All data remains on your server. The plugin only responds to authenticated incoming requests from AI agents that you have explicitly authorized with your API key.

== Installation ==

1. Upload the `real-mcp` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Use the Settings → Real MCP screen to generate an API key and configure the plugin.

== Frequently Asked Questions ==

= Which AI agents are supported? =

Any MCP-compatible client works. The Model Context Protocol is an open standard supported by a growing ecosystem of AI tools.

= Does this require a separate server? =

No. The plugin itself is the MCP server. It uses the WordPress REST API as the HTTP transport layer — no Node.js, Python, or external processes needed.

= Is it secure? =

Yes. All requests require a valid API key. Every tool declares a WordPress capability requirement (like `edit_posts` or `manage_options`). Write operations are restricted to admin-level access. You can also restrict connections to specific origins.

= Does it work with WooCommerce? =

Yes. WooCommerce tools (product management, inventory, pricing, coupons) are automatically enabled when WooCommerce is active. No additional configuration needed.

= Does it work with Elementor? =

Yes. Elementor tools are auto-enabled when Elementor is active. AI agents can read and modify page builder content directly.

= Which SEO plugins are supported? =

Yoast SEO, Rank Math, and All in One SEO. The plugin auto-detects which is active and uses the appropriate meta keys.

= Can I add custom tools? =

Yes. Use the `real_mcp_register_tools` action hook to register your own tools extending `AbstractTool`. See the developer documentation.

= What happens to WooCommerce tools if WooCommerce is deactivated? =

They simply disappear from the tools list. The AI agent will only see tools that are currently available.

== Screenshots ==

1. Settings page showing the MCP endpoint URL and API key configuration.
2. Connection guide with ready-to-use JSON configuration for AI agents.

== Changelog ==

= 1.0.0 =
* Initial release.
* 30+ built-in tools across 8 categories.
* Content management: create, update, delete, merge posts and pages.
* SEO tools with Yoast, Rank Math, and AIOSEO integration.
* WooCommerce tools: products, inventory, pricing, coupons.
* Elementor tools: read and update page builder content.
* Media tools: upload, list, alt text management, thumbnail regeneration.
* Security audit, plugin risk assessment, user permission review.
* Performance audit, database optimization, cache management.
* Maintenance: plugin/theme updates, health checks.
* Accessibility (WCAG) content audit.
* API key authentication with Bearer token and X-API-Key support.
* Conditional tool loading based on active plugins.
* Extensible tool registry via `real_mcp_register_tools` hook.
* MCP 2025-06-18 Streamable HTTP transport via WordPress REST API.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
