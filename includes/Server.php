<?php
/**
 * MCP Server — JSON-RPC message router with full MCP lifecycle support.
 *
 * Implements the MCP 2025-06-18 specification for:
 * - Lifecycle: initialize, initialized notification, ping
 * - Tools: tools/list, tools/call
 * - Resources: resources/list, resources/read (empty but spec-compliant)
 * - Prompts: prompts/list (empty but spec-compliant)
 * - Version negotiation with fallback support
 *
 * Designed for universal client compatibility — no client-specific code.
 *
 * @package Real_MCP
 */

namespace Real_MCP;

use Real_MCP\Tools\Registry as ToolRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Server {

	/**
	 * Current session ID.
	 */
	private ?string $session_id = null;

	/**
	 * Negotiated protocol version for this session.
	 */
	private string $negotiated_version = REAL_MCP_PROTOCOL_VERSION;

	/**
	 * Handle a JSON-RPC message and return the response.
	 *
	 * @param array $message The parsed JSON-RPC message.
	 * @return array|null Response array or null for notifications/responses.
	 */
	public function handle_message( array $message ): ?array {
		// Notifications have no id.
		if ( ! isset( $message['id'] ) ) {
			// Handle known notifications silently.
			$this->handle_notification( $message['method'] ?? '', $message['params'] ?? [] );
			return null;
		}

		// Responses from client (has result or error, no method) — accept silently.
		if ( ! isset( $message['method'] ) ) {
			return null;
		}

		$method = $message['method'];
		$params = $message['params'] ?? [];
		$id     = $message['id'];

		// Set up admin user context for capability checks.
		$this->ensure_user_context();

		return match ( $method ) {
			// Lifecycle.
			'initialize'           => $this->handle_initialize( $id, $params ),
			'ping'                 => $this->handle_ping( $id ),

			// Tools.
			'tools/list'           => $this->handle_tools_list( $id, $params ),
			'tools/call'           => $this->handle_tools_call( $id, $params ),

			// Resources (spec-compliant empty response).
			'resources/list'       => $this->handle_resources_list( $id, $params ),
			'resources/read'       => $this->handle_resources_read( $id, $params ),
			'resources/templates/list' => $this->handle_resource_templates_list( $id, $params ),

			// Prompts (spec-compliant empty response).
			'prompts/list'         => $this->handle_prompts_list( $id, $params ),
			'prompts/get'          => $this->handle_prompts_get( $id, $params ),

			// Completions.
			'completion/complete'  => $this->handle_completion( $id, $params ),

			// Logging.
			'logging/setLevel'     => $this->handle_set_log_level( $id, $params ),

			// Unknown method.
			default                => $this->error_response( $id, -32601, "Method not found: {$method}" ),
		};
	}

	/**
	 * Get the session ID assigned during initialization.
	 */
	public function get_session_id(): ?string {
		return $this->session_id;
	}

	/**
	 * Handle notifications (no response needed).
	 */
	private function handle_notification( string $method, array $params ): void {
		// Accept notifications silently per spec.
		// notifications/initialized — client confirms it's ready.
		// notifications/cancelled — client cancels a request.
		// notifications/progress — progress updates.
		// All are accepted without action in this stateless implementation.

		/**
		 * Allow extensions to react to MCP notifications.
		 *
		 * @param string $method Notification method name.
		 * @param array  $params Notification parameters.
		 */
		do_action( 'real_mcp_notification', $method, $params );
	}

	/**
	 * Ensure a WordPress user context exists for capability checks.
	 */
	private function ensure_user_context(): void {
		if ( get_current_user_id() === 0 ) {
			wp_set_current_user( self::get_admin_user_id() );
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// LIFECYCLE
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Handle initialize request.
	 *
	 * Performs version negotiation and capability exchange per MCP spec.
	 */
	private function handle_initialize( int|string $id, array $params ): array {
		// Version negotiation.
		$client_version = $params['protocolVersion'] ?? '';
		if ( in_array( $client_version, Endpoint::SUPPORTED_VERSIONS, true ) ) {
			$this->negotiated_version = $client_version;
		} else {
			// Respond with our latest supported version.
			$this->negotiated_version = REAL_MCP_PROTOCOL_VERSION;
		}

		// Generate a cryptographically secure session ID.
		$this->session_id = wp_generate_uuid4();

		// Store session data.
		set_transient( 'real_mcp_session_' . $this->session_id, [
			'client_info'      => $params['clientInfo'] ?? [],
			'protocol_version' => $this->negotiated_version,
			'capabilities'     => $params['capabilities'] ?? [],
			'created_at'       => time(),
		], HOUR_IN_SECONDS );

		// Build server capabilities.
		$capabilities = [
			'tools' => [
				'listChanged' => false,
			],
		];

		// Declare resources capability (empty set but spec-compliant).
		$capabilities['resources'] = [
			'subscribe'   => false,
			'listChanged' => false,
		];

		// Declare prompts capability (empty set but spec-compliant).
		$capabilities['prompts'] = [
			'listChanged' => false,
		];

		// Declare logging capability.
		$capabilities['logging'] = new \stdClass();

		// Build instructions for the AI agent.
		// These instructions tell the AI how to use the server effectively for complex workflows.
		$instructions = self::build_instructions();

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => [
				'protocolVersion' => $this->negotiated_version,
				'capabilities'    => $capabilities,
				'serverInfo'      => [
					'name'    => 'real-mcp',
					'version' => REAL_MCP_VERSION,
				],
				'instructions'    => $instructions,
			],
		];
	}

	/**
	 * Handle ping request.
	 */
	private function handle_ping( int|string $id ): array {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => new \stdClass(),
		];
	}

	// ─────────────────────────────────────────────────────────────────────
	// TOOLS
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Handle tools/list request with pagination support.
	 */
	private function handle_tools_list( int|string $id, array $params ): array {
		$tools = ToolRegistry::get_tools();
		$cursor = $params['cursor'] ?? null;

		$tool_definitions = [];
		foreach ( $tools as $tool ) {
			$definition = $tool->get_definition();

			// Add annotations per MCP spec for tool behavior hints.
			$definition['annotations'] = [
				'readOnlyHint' => $tool->get_capability() === 'read',
			];

			$tool_definitions[] = $definition;
		}

		// Simple pagination: if cursor is set, skip tools before it.
		if ( $cursor !== null ) {
			$found = false;
			$filtered = [];
			foreach ( $tool_definitions as $def ) {
				if ( $found ) {
					$filtered[] = $def;
				}
				if ( $def['name'] === $cursor ) {
					$found = true;
				}
			}
			$tool_definitions = $filtered;
		}

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => [
				'tools' => $tool_definitions,
			],
		];
	}

	/**
	 * Handle tools/call request.
	 */
	private function handle_tools_call( int|string $id, array $params ): array {
		$name      = $params['name'] ?? '';
		$arguments = $params['arguments'] ?? [];

		$tool = ToolRegistry::get_tool( $name );

		if ( ! $tool ) {
			return $this->error_response( $id, -32602, "Unknown tool: {$name}" );
		}

		// Capability check.
		$capability = $tool->get_capability();
		if ( $capability && ! current_user_can( $capability ) ) {
			return $this->error_response( $id, -32603, "Insufficient permissions for tool: {$name}. Requires: {$capability}" );
		}

		try {
			$result = $tool->execute( $arguments );

			return [
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => [
					'content' => $result,
					'isError' => false,
				],
			];
		} catch ( \Throwable $e ) {
			return [
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => [
					'content' => [
						[
							'type' => 'text',
							'text' => $e->getMessage(),
						],
					],
					'isError' => true,
				],
			];
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// RESOURCES (spec-compliant, extensible)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Handle resources/list — returns empty list.
	 * Extensible via real_mcp_resources filter.
	 */
	private function handle_resources_list( int|string $id, array $params ): array {
		/**
		 * Filter the list of MCP resources.
		 *
		 * @param array $resources Default empty array.
		 */
		$resources = apply_filters( 'real_mcp_resources', [] );

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => [
				'resources' => $resources,
			],
		];
	}

	/**
	 * Handle resources/read — returns error for unknown resource.
	 */
	private function handle_resources_read( int|string $id, array $params ): array {
		$uri = $params['uri'] ?? '';

		/**
		 * Filter resource read result.
		 *
		 * @param array|null $result  Default null (not found).
		 * @param string     $uri     Resource URI.
		 */
		$result = apply_filters( 'real_mcp_resource_read', null, $uri );

		if ( $result !== null ) {
			return [
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $result,
			];
		}

		return $this->error_response( $id, -32602, "Resource not found: {$uri}" );
	}

	/**
	 * Handle resources/templates/list.
	 */
	private function handle_resource_templates_list( int|string $id, array $params ): array {
		/**
		 * Filter resource templates.
		 *
		 * @param array $templates Default empty array.
		 */
		$templates = apply_filters( 'real_mcp_resource_templates', [] );

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => [
				'resourceTemplates' => $templates,
			],
		];
	}

	// ─────────────────────────────────────────────────────────────────────
	// PROMPTS (spec-compliant, extensible)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Handle prompts/list.
	 */
	private function handle_prompts_list( int|string $id, array $params ): array {
		/**
		 * Filter the list of MCP prompts.
		 *
		 * @param array $prompts Default empty array.
		 */
		$prompts = apply_filters( 'real_mcp_prompts', [] );

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => [
				'prompts' => $prompts,
			],
		];
	}

	/**
	 * Handle prompts/get.
	 */
	private function handle_prompts_get( int|string $id, array $params ): array {
		$name = $params['name'] ?? '';

		/**
		 * Filter prompt retrieval.
		 *
		 * @param array|null $result Default null (not found).
		 * @param string     $name   Prompt name.
		 * @param array      $params Full params including arguments.
		 */
		$result = apply_filters( 'real_mcp_prompt_get', null, $name, $params );

		if ( $result !== null ) {
			return [
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $result,
			];
		}

		return $this->error_response( $id, -32602, "Prompt not found: {$name}" );
	}

	// ─────────────────────────────────────────────────────────────────────
	// UTILITIES
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Handle completion/complete — argument autocompletion.
	 */
	private function handle_completion( int|string $id, array $params ): array {
		// Basic implementation — return empty completions.
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => [
				'completion' => [
					'values'  => [],
					'hasMore' => false,
				],
			],
		];
	}

	/**
	 * Handle logging/setLevel — accept log level from client.
	 */
	private function handle_set_log_level( int|string $id, array $params ): array {
		// Accept the request but logging is not implemented in stateless mode.
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => new \stdClass(),
		];
	}

	// ─────────────────────────────────────────────────────────────────────
	// HELPERS
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Get an admin user ID for capability checks.
	 *
	 * The API key grants admin-level access. This method identifies which
	 * WordPress user to act as when processing tool calls.
	 */
	private static function get_admin_user_id(): int {
		// Check if a specific user is configured.
		$admin_user_id = (int) get_option( 'real_mcp_admin_user_id', 0 );
		if ( $admin_user_id && get_userdata( $admin_user_id ) ) {
			return $admin_user_id;
		}

		// Fallback to the first administrator.
		$admins = get_users( [
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		] );

		return ! empty( $admins ) ? (int) $admins[0] : 1;
	}

	/**
	 * Build comprehensive instructions for AI agents.
	 *
	 * These instructions guide the AI on how to orchestrate complex multi-step
	 * workflows using the available tools. This is critical for agents to
	 * understand what they can achieve.
	 */
	private static function build_instructions(): string {
		$site_name   = get_bloginfo( 'name' );
		$site_url    = home_url();
		$site_desc   = get_bloginfo( 'description' );
		$wp_version  = get_bloginfo( 'version' );
		$theme       = wp_get_theme();
		$theme_name  = $theme->get( 'Name' );

		// Detect active integrations.
		$has_woo       = class_exists( 'WooCommerce' );
		$has_elementor = defined( 'ELEMENTOR_VERSION' );
		$has_yoast     = defined( 'WPSEO_VERSION' );
		$has_rankmath  = class_exists( 'RankMath' );
		$has_aioseo    = defined( 'AIOSEO_VERSION' );

		$seo_plugin = 'none';
		if ( $has_rankmath ) $seo_plugin = 'Rank Math';
		elseif ( $has_yoast ) $seo_plugin = 'Yoast SEO';
		elseif ( $has_aioseo ) $seo_plugin = 'All in One SEO';

		$post_count = wp_count_posts( 'post' );
		$page_count = wp_count_posts( 'page' );

		$instructions = "You are connected to the WordPress site \"{$site_name}\" ({$site_url}).\n"
			. "Site description: {$site_desc}\n"
			. "WordPress {$wp_version} | Theme: {$theme_name}\n"
			. "Content: {$post_count->publish} published posts, {$page_count->publish} pages\n"
			. "SEO Plugin: {$seo_plugin}";

		if ( $has_woo ) {
			$product_count = wp_count_posts( 'product' );
			$instructions .= "\nWooCommerce: Active ({$product_count->publish} products)";
		}
		if ( $has_elementor ) {
			$instructions .= "\nElementor: Active (v" . ELEMENTOR_VERSION . ")";
		}

		$instructions .= "\n\n## How to Execute Complex Workflows\n\n"
			. "You have full read/write access to this WordPress site. For complex tasks, follow these patterns:\n\n"
			. "### Content Writing Workflow\n"
			. "1. Use `get_site_info` to understand the site context\n"
			. "2. Use `list_categories` and `list_tags` to understand taxonomy\n"
			. "3. Use `get_posts` to see existing content style and topics\n"
			. "4. Use `create_post` to write new content (use status \"draft\" first for review, \"publish\" to go live)\n"
			. "5. Use `update_seo_meta` to add SEO metadata after creating content\n\n"
			. "### SEO Fix Workflow\n"
			. "1. Use `seo_site_audit` to identify all issues across the site\n"
			. "2. For each post with issues, use `get_post_content` to read the full content\n"
			. "3. Use `update_seo_meta` to fix meta titles and descriptions\n"
			. "4. Use `update_post` to improve content (add internal links, expand thin content)\n"
			. "5. Use `update_alt_text` to fix missing image alt text\n"
			. "6. Use `generate_schema` to add structured data where needed\n"
			. "7. Re-run `get_seo_data` on fixed posts to verify improvements\n\n"
			. "### Rank Math / Yoast Score Improvement\n"
			. "1. Use `get_seo_data` on target posts to see current SEO status and issues\n"
			. "2. Fix focus keyword: use `update_seo_meta` with `focus_keyword`\n"
			. "3. Fix meta description: ensure it's 120-160 chars with keyword\n"
			. "4. Fix meta title: ensure it's under 60 chars with keyword\n"
			. "5. Use `get_post_content` then `update_post` to add the focus keyword in the first paragraph, add internal links, ensure heading hierarchy, ensure content is 300+ words\n"
			. "6. Use `update_alt_text` to add keyword-relevant alt text to images\n\n"
			. "### Elementor Page Creation\n"
			. "1. Use `create_elementor_page` with structured sections (hero, features, CTA, etc.)\n"
			. "2. Provide heading, subheading, content, button text for each section\n"
			. "3. For existing Elementor pages, use `get_elementor_data` to read current structure\n"
			. "4. Use `update_elementor_content` to modify specific widget text by element ID\n"
			. "5. Pages are created as drafts by default — set status \"publish\" to go live immediately\n\n"
			. "### WooCommerce Product Management\n"
			. "1. Use `woo_get_products` to see current inventory\n"
			. "2. Use `woo_create_product` for new products\n"
			. "3. Use `woo_update_product` to improve descriptions, pricing\n"
			. "4. Use `woo_update_inventory` for stock changes\n"
			. "5. Use `woo_bulk_price_update` for seasonal/promotional pricing\n"
			. "6. Use `woo_manage_coupons` to create promotional campaigns\n\n"
			. "### Security & Maintenance\n"
			. "1. Use `security_audit` for a full security review\n"
			. "2. Use `plugin_audit` to find outdated or risky plugins\n"
			. "3. Use `update_plugins` and `update_themes` to apply updates\n"
			. "4. Use `performance_audit` then `database_optimize` and `cache_cleanup`\n"
			. "5. Use `health_check` for WordPress Site Health diagnostics\n\n"
			. "### Accessibility Improvement\n"
			. "1. Use `accessibility_audit` to find WCAG issues\n"
			. "2. Use `list_media` with `missing_alt=true` to find images needing alt text\n"
			. "3. Use `update_alt_text` to add descriptive alt text to all images\n"
			. "4. Use `get_post_content` and `update_post` to fix heading hierarchy and link text\n\n"
			. "## Important Guidelines\n"
			. "- Always use `get_post_content` to read BEFORE updating (never overwrite blindly)\n"
			. "- Use status \"draft\" for new posts if the user wants to review before publishing\n"
			. "- When fixing SEO, process posts one at a time and verify with `get_seo_data` after\n"
			. "- For bulk operations, process in batches and report progress\n"
			. "- Preserve existing content when making SEO improvements (add to it, don't replace)";

		/**
		 * Filter the MCP server instructions sent to AI agents.
		 *
		 * @param string $instructions Complete instructions text.
		 * @param array  $context      Site context data.
		 */
		return apply_filters( 'real_mcp_instructions', $instructions, [
			'site_name'   => $site_name,
			'seo_plugin'  => $seo_plugin,
			'has_woo'     => $has_woo,
			'has_elementor' => $has_elementor,
		] );
	}

	/**
	 * Build a JSON-RPC error response.
	 */
	private function error_response( int|string $id, int $code, string $message ): array {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => [
				'code'    => $code,
				'message' => $message,
			],
		];
	}
}
