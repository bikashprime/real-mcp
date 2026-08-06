# Real MCP

A lightweight WordPress plugin that turns your site into a [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server. Connect any MCP-compatible AI agent directly to your WordPress content and operations.

**No external server. No Node.js. No Python. The plugin itself is the MCP server.**

## What Can AI Agents Do With This?

When connected via MCP, AI agents gain full read/write access to your WordPress site. The agent provides the intelligence — writing, analyzing, optimizing — while this plugin provides the operational bridge.

### Update Capabilities
This plugin provides comprehensive update tools across the entire WordPress stack:

- **Content** — Update post titles, content, excerpts, status, categories, and tags
- **Site Settings** — Update site title, description, timezone, permalink structure, and more
- **Custom Fields** — Read and write post meta (custom fields) on any post
- **SEO Metadata** — Update meta titles, descriptions, focus keywords, and Open Graph data (Yoast, Rank Math, AIOSEO)
- **Media** — Bulk update image alt text for accessibility
- **WooCommerce Products** — Update product names, descriptions, prices, SKUs, categories, and status
- **WooCommerce Inventory** — Update stock quantities and stock status
- **WooCommerce Pricing** — Bulk price adjustments (fixed, percentage increase/decrease)
- **Plugins** — Update one or all WordPress plugins to their latest versions
- **Themes** — Update one or all themes to their latest versions
- **Elementor** — Update text content in Elementor widgets without opening the builder

### Content Workflows
- Write new blog posts, articles, and pages
- Rewrite, expand, or summarize existing articles
- Merge duplicate articles into one
- Generate FAQs, documentation, release notes
- Create landing pages

### SEO Workflows
- Run full site SEO audits
- Improve meta titles and descriptions (Yoast, Rank Math, AIOSEO)
- Fix missing metadata across all posts
- Generate structured data (JSON-LD schema)
- Analyze internal linking opportunities
- Keyword optimization

### Accessibility (WCAG) Workflows
- Audit content for WCAG compliance
- Fix missing image alt text across the media library
- Identify heading hierarchy issues
- Review link text quality

### WooCommerce Workflows
- Create and update products
- Write product descriptions and comparisons
- Manage inventory and bulk price updates
- Create and manage promotional coupons
- Plan seasonal campaigns

### Elementor Workflows
- Read and update Elementor page content
- Update hero section copy, headings, CTAs
- Modify landing page text without opening the builder

### Security Workflows
- Run comprehensive security audits
- Plugin risk assessment (outdated, inactive plugins)
- User permission review
- Configuration vulnerability checks

### Performance Workflows
- Audit caching, database bloat, autoloaded data
- Clean up revisions, transients, spam comments
- Optimize database tables
- Flush page/object caches
- Core Web Vitals preparation

### Maintenance Workflows
- Update plugins and themes
- Run WordPress Site Health checks
- Database optimization

## Quick Start

1. Install and activate the plugin
2. Go to **Settings → Real MCP**
3. Click **Generate Key** and save
4. Connect your AI agent:

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://yoursite.com/wp-json/real-mcp/v1/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```

## All Built-in Tools

### General
| Tool | Description |
|------|-------------|
| `get_site_info` | Site name, URL, content counts, WordPress version |

### Content
| Tool | Description |
|------|-------------|
| `get_posts` | List published posts with filtering and pagination |
| `get_post` | Get a single published post by ID or slug |
| `get_post_content` | Get raw HTML content of any post (including drafts) |
| `search_content` | Search posts and pages by keyword |
| `list_categories` | All post categories with counts |
| `list_tags` | All post tags with counts |
| `create_post` | Create a new blog post |
| `update_post` | Update title, content, excerpt, status, categories, or tags |
| `delete_post` | Trash or delete a post |
| `merge_posts` | Merge two posts into one |
| `create_page` | Create a new page |
| `manage_post_meta` | Read/write custom fields on any post |
| `manage_options` | Read/update core site settings (title, description, permalinks, etc.) |

### SEO
| Tool | Description |
|------|-------------|
| `get_seo_data` | Get SEO metadata and issues for a post |
| `bulk_get_seo_data` | Get SEO data for multiple posts at once (bulk audit) |
| `update_seo_meta` | Update meta title, description, focus keyword, Open Graph |
| `seo_site_audit` | Site-wide SEO audit |
| `generate_schema` | Generate JSON-LD structured data |

### Media
| Tool | Description |
|------|-------------|
| `upload_media` | Upload image from URL to media library |
| `list_media` | List media library items |
| `update_alt_text` | Bulk update image alt text (accessibility/WCAG) |
| `regenerate_thumbnails` | Regenerate image sizes |

### WooCommerce (auto-enabled when WooCommerce is active)
| Tool | Description |
|------|-------------|
| `woo_get_products` | List products with details |
| `woo_create_product` | Create a new product |
| `woo_update_product` | Update product name, description, price, SKU, categories, status |
| `woo_update_inventory` | Update stock quantity and status for one or more products |
| `woo_bulk_price_update` | Bulk adjust prices (fixed, percentage increase/decrease) |
| `woo_manage_coupons` | Create/manage promotional coupons |

### Elementor (auto-enabled when Elementor is active)
| Tool | Description |
|------|-------------|
| `get_elementor_data` | Read Elementor page structure |
| `create_elementor_page` | Create a page with Elementor section/widget structure |
| `update_elementor_content` | Update text in Elementor widgets |

### Security
| Tool | Description |
|------|-------------|
| `security_audit` | Comprehensive security configuration audit |
| `plugin_audit` | Plugin risk assessment and update check |
| `user_permission_review` | Review user roles and flag issues |

### Performance
| Tool | Description |
|------|-------------|
| `performance_audit` | Full performance configuration audit |
| `database_optimize` | Clean revisions, transients, spam, optimize tables |
| `cache_cleanup` | Flush object cache, page cache, transients |

### Maintenance
| Tool | Description |
|------|-------------|
| `update_plugins` | Update one or all plugins to latest versions |
| `update_themes` | Update one or all themes to latest versions |
| `health_check` | WordPress Site Health diagnostics |

### Accessibility
| Tool | Description |
|------|-------------|
| `accessibility_audit` | WCAG content audit |

## Architecture

```
real-mcp/
├── real-mcp.php              ← Plugin entry + autoloader
├── includes/
│   ├── bootstrap.php            ← Hooks and initialization
│   ├── Server.php               ← JSON-RPC router + capability checks
│   ├── Endpoint.php             ← REST API endpoint (Streamable HTTP)
│   ├── Admin.php                ← Settings page
│   └── Tools/
│       ├── ToolInterface.php    ← Contract (get_definition, execute, get_capability, get_category)
│       ├── AbstractTool.php     ← Base class with helper methods
│       ├── Registry.php         ← Auto-registers all built-in tools
│       ├── Content/             ← Content management tools
│       ├── SEO/                 ← SEO tools (Yoast, Rank Math, AIOSEO)
│       ├── Media/               ← Media library tools
│       ├── WooCommerce/         ← WooCommerce integration
│       ├── Elementor/           ← Elementor page builder integration
│       ├── Security/            ← Security audit tools
│       ├── Performance/         ← Performance & optimization tools
│       ├── Maintenance/         ← Update & health check tools
│       └── Accessibility/       ← WCAG audit tools
├── readme.txt                   ← WordPress.org format
├── uninstall.php                ← Clean removal
└── languages/                   ← i18n
```

## Extending with Custom Tools

Register your own tools using the `real_mcp_register_tools` action:

```php
add_action( 'real_mcp_register_tools', function ( $registry_class ) {
    $registry_class::register( new My_Custom_Tool() );
} );
```

Your tool must extend `Real_MCP\Tools\AbstractTool`:

```php
use Real_MCP\Tools\AbstractTool;

class My_Custom_Tool extends AbstractTool {

    public function get_capability(): string {
        return 'edit_posts'; // WordPress capability required
    }

    public function get_category(): string {
        return 'custom';
    }

    public function get_definition(): array {
        return [
            'name'        => 'my_tool',
            'description' => 'Does something useful.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'param1' => [
                        'type'        => 'string',
                        'description' => 'A parameter.',
                    ],
                ],
                'required' => [ 'param1' ],
            ],
        ];
    }

    public function execute( array $arguments ): array {
        // Your logic here.
        return $this->json_response( [ 'result' => 'done' ] );
    }
}
```

## Conditional Tool Loading

- **WooCommerce tools** — only registered when WooCommerce is active
- **Elementor tools** — only registered when Elementor is active
- **SEO tools** — auto-detect Yoast, Rank Math, or All in One SEO for metadata operations

## Plugin Abilities by Plugin Name

Real MCP exposes different capabilities depending on which plugins are active on your site. Below is a breakdown of abilities grouped by plugin.

### Rank Math SEO

When [Rank Math](https://rankmath.com/) is active, the following MCP abilities become available:

#### SEO Audit & Fixes
| Ability | Tool | Description |
|---------|------|-------------|
| Run SEO Audit | `rank-math/audit-site-seo` | Run a detailed SEO audit on your site (or a competitor's site with PRO) |
| Fix SEO Issues | `rank-math/fix-site-seo` | Automatically fix failed SEO tests (blog visibility, permalinks, sitemaps, schema, robots.txt, missing focus keywords) |

#### Post-Level SEO
| Ability | Tool | Description |
|---------|------|-------------|
| Get Post Schema | `rank-math/get-post-schema` | Retrieve Schema markup applied to a post and available Schema types |
| Get Post SEO Meta | `rank-math/get-post-seo-meta` | Retrieve SEO metadata — title, description, focus keyword, robots, canonical, OpenGraph, Twitter Card, SEO score |
| Get Post Links | `rank-math/get-post-links` | Retrieve all internal and external links with anchor text and follow status |

#### Site-Wide Link Analysis
| Ability | Tool | Description |
|---------|------|-------------|
| Get Link Report | `rank-math/get-link-report` | Check status of links across your site — broken links, redirect chains, follow status (PRO: full HTTP status audit) |

#### AI Visibility (Content AI — PRO)
| Ability | Tool | Description |
|---------|------|-------------|
| AI Visibility Overview | `rank-math/get-ai-visibility-overview` | Retrieve brand visibility scores, mentions, citations, and sentiment across AI platforms |
| Brand Insights | `rank-math/get-ai-visibility-brand-insights` | Get AI Visibility metrics for a specific brand — score, rank, mentions, competitors |
| Brand Queries | `rank-math/get-ai-visibility-brand-queries` | Retrieve specific queries being monitored for a tracked brand |
| Create Brand | `rank-math/create-ai-visibility-brand` | Add a new brand/product to track on AI platforms |

### WooCommerce

When [WooCommerce](https://woocommerce.com/) is active:

| Ability | Tool | Description |
|---------|------|-------------|
| List Products | `woo_get_products` | List products with details, filtering, and pagination |
| Create Product | `woo_create_product` | Create a new product with full details |
| Update Product | `woo_update_product` | Update name, description, price, SKU, categories, status |
| Update Inventory | `woo_update_inventory` | Update stock quantity and status for one or more products |
| Bulk Price Update | `woo_bulk_price_update` | Bulk adjust prices — fixed, percentage increase/decrease |
| Manage Coupons | `woo_manage_coupons` | Create and manage promotional coupons |

### Elementor

When [Elementor](https://elementor.com/) is active:

| Ability | Tool | Description |
|---------|------|-------------|
| Read Page Structure | `get_elementor_data` | Read Elementor page structure and widget data |
| Create Page | `create_elementor_page` | Create a page with Elementor section/widget structure |
| Update Content | `update_elementor_content` | Update text in Elementor widgets (headings, text editors, buttons) |

### Yoast SEO / All in One SEO

When Yoast SEO or AIOSEO is active, Real MCP's built-in SEO tools automatically use the correct meta keys:

| Ability | Tool | Description |
|---------|------|-------------|
| Get SEO Data | `get_seo_data` | Retrieve SEO metadata and issues for a post |
| Bulk SEO Audit | `bulk_get_seo_data` | Get SEO data for multiple posts at once |
| Update SEO Meta | `update_seo_meta` | Update meta title, description, focus keyword, Open Graph |
| Site Audit | `seo_site_audit` | Run a site-wide SEO audit |
| Generate Schema | `generate_schema` | Generate JSON-LD structured data |

## Security Model

- All requests require a valid API key (Bearer token or X-API-Key header)
- Origin validation prevents DNS rebinding attacks
- Each tool declares a WordPress capability requirement
- Write operations require admin-level capabilities
- Session management via WordPress transients (1-hour TTL)
- Safe options allowlist prevents modification of sensitive settings

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Optional: WooCommerce 7.0+ (for WooCommerce tools)
- Optional: Elementor 3.0+ (for Elementor tools)

## License

GPL-2.0-or-later
