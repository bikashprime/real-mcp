<?php
/**
 * Admin — Dedicated admin menu for Real MCP with tabs: Config, Abilities, Help.
 *
 * @package Real_MCP
 */

namespace Real_MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/**
	 * Option key for storing enabled/disabled tools.
	 */
	const OPTION_ENABLED_TOOLS = 'real_mcp_enabled_tools';

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'wp_ajax_real_mcp_save_abilities', [ self::class, 'ajax_save_abilities' ] );
	}

	/**
	 * Add a dedicated top-level admin menu.
	 */
	public static function add_menu(): void {
		add_menu_page(
			__( 'Real MCP', 'real-mcp' ),
			__( 'Real MCP', 'real-mcp' ),
			'manage_options',
			'real-mcp',
			[ self::class, 'render_page' ],
			'dashicons-rest-api',
			80
		);
	}

	/**
	 * Register settings.
	 */
	public static function register_settings(): void {
		register_setting( 'real_mcp_settings', 'real_mcp_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );

		register_setting( 'real_mcp_settings', 'real_mcp_allowed_origins', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		] );

		register_setting( 'real_mcp_settings', 'real_mcp_admin_user_id', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		] );
	}

	/**
	 * Get the list of enabled tool names.
	 * Returns null if abilities have never been configured (all enabled by default).
	 *
	 * @return array|null
	 */
	public static function get_enabled_tools(): ?array {
		$option = get_option( self::OPTION_ENABLED_TOOLS, null );
		if ( $option === null || $option === false ) {
			return null; // Never configured — all tools enabled.
		}
		return (array) $option;
	}

	/**
	 * Check if a specific tool is enabled.
	 */
	public static function is_tool_enabled( string $tool_name ): bool {
		$enabled = self::get_enabled_tools();
		if ( $enabled === null ) {
			return true; // All enabled by default.
		}
		return in_array( $tool_name, $enabled, true );
	}

	/**
	 * AJAX handler for saving abilities.
	 */
	public static function ajax_save_abilities(): void {
		check_ajax_referer( 'real_mcp_abilities_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$enabled_tools = isset( $_POST['enabled_tools'] ) && is_array( $_POST['enabled_tools'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_tools'] ) )
			: [];

		update_option( self::OPTION_ENABLED_TOOLS, $enabled_tools );
		wp_send_json_success( [ 'enabled' => count( $enabled_tools ) ] );
	}

	/**
	 * Get tool groups for the abilities UI.
	 */
	private static function get_tool_groups(): array {
		$tools = \Real_MCP\Tools\Registry::get_all_tools();
		$groups = [];

		$category_labels = [
			'general'        => __( 'Core — General', 'real-mcp' ),
			'content'        => __( 'Core — Content', 'real-mcp' ),
			'seo'            => __( 'Core — SEO', 'real-mcp' ),
			'media'          => __( 'Core — Media', 'real-mcp' ),
			'security'       => __( 'Core — Security', 'real-mcp' ),
			'performance'    => __( 'Core — Performance', 'real-mcp' ),
			'maintenance'    => __( 'Core — Maintenance', 'real-mcp' ),
			'accessibility'  => __( 'Core — Accessibility', 'real-mcp' ),
			'woocommerce'    => __( 'WooCommerce', 'real-mcp' ),
			'elementor'      => __( 'Elementor', 'real-mcp' ),
			'elementor_pro'  => __( 'Elementor Pro', 'real-mcp' ),
			'rankmath'       => __( 'Rank Math SEO', 'real-mcp' ),
			'acf'            => __( 'Advanced Custom Fields (ACF)', 'real-mcp' ),
			'classic_editor' => __( 'Classic Editor', 'real-mcp' ),
			'astra'          => __( 'Astra / Astra Pro', 'real-mcp' ),
			'table_addons'   => __( 'Table Addons for Elementor', 'real-mcp' ),
		];

		foreach ( $tools as $tool ) {
			$cat = $tool->get_category();
			$label = $category_labels[ $cat ] ?? ucfirst( $cat );
			if ( ! isset( $groups[ $cat ] ) ) {
				$groups[ $cat ] = [
					'label' => $label,
					'tools' => [],
				];
			}
			$groups[ $cat ]['tools'][] = $tool;
		}

		return $groups;
	}

	/**
	 * Render the main admin page with tabs.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only, no data modification.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'config'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = [
			'config'    => __( 'Configuration', 'real-mcp' ),
			'abilities' => __( 'Abilities', 'real-mcp' ),
			'help'      => __( 'Help & Guide', 'real-mcp' ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Real MCP', 'real-mcp' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( "admin.php?page=real-mcp&tab={$slug}" ) ); ?>"
					   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="real-mcp-tab-content" style="margin-top: 20px;">
				<?php
				match ( $tab ) {
					'abilities' => self::render_abilities_tab(),
					'help'      => self::render_help_tab(),
					default     => self::render_config_tab(),
				};
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Configuration tab.
	 */
	private static function render_config_tab(): void {
		$endpoint_url = rest_url( Endpoint::NAMESPACE . Endpoint::ROUTE );
		$api_key      = get_option( 'real_mcp_api_key', '' );
		$tools        = \Real_MCP\Tools\Registry::get_tools();
		$categories   = \Real_MCP\Tools\Registry::get_categories();
		?>
		<div class="notice notice-info" style="padding: 12px;">
			<strong><?php esc_html_e( 'MCP Endpoint:', 'real-mcp' ); ?></strong>
			<code><?php echo esc_url( $endpoint_url ); ?></code>
		</div>

		<?php if ( empty( $api_key ) ) : ?>
			<div class="notice notice-warning" style="padding: 12px;">
				<strong><?php esc_html_e( 'Setup Required:', 'real-mcp' ); ?></strong>
				<?php esc_html_e( 'Generate an API key below to enable MCP connections.', 'real-mcp' ); ?>
			</div>
		<?php else : ?>
			<div class="notice notice-success" style="padding: 12px;">
				<strong><?php esc_html_e( 'Active:', 'real-mcp' ); ?></strong>
				<?php
				printf(
					/* translators: %1$d: number of tools, %2$d: number of categories */
					esc_html__( '%1$d tools available across %2$d categories.', 'real-mcp' ),
					count( $tools ),
					count( $categories )
				);
				?>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'real_mcp_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Key', 'real-mcp' ); ?></th>
					<td>
						<?php self::render_api_key_field(); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Run As User', 'real-mcp' ); ?></th>
					<td>
						<?php self::render_user_field(); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allowed Origins', 'real-mcp' ); ?></th>
					<td>
						<?php self::render_origins_field(); ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the Abilities tab.
	 */
	private static function render_abilities_tab(): void {
		$groups       = self::get_tool_groups();
		$enabled_tools = self::get_enabled_tools();
		$nonce        = wp_create_nonce( 'real_mcp_abilities_nonce' );
		?>
		<p class="description" style="margin-bottom: 16px;">
			<?php esc_html_e( 'Enable or disable individual tools per plugin/category. Only enabled tools are available to AI agents. Toggle groups or individual abilities.', 'real-mcp' ); ?>
		</p>

		<div id="real-mcp-abilities-notice" style="display:none;" class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Abilities saved successfully.', 'real-mcp' ); ?></p>
		</div>

		<form id="real-mcp-abilities-form">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

			<div class="real-mcp-abilities-wrap">
			<?php foreach ( $groups as $cat => $group ) : ?>
				<div class="real-mcp-group" data-group="<?php echo esc_attr( $cat ); ?>">
					<div class="real-mcp-group-header">
						<div class="real-mcp-group-left">
							<span class="dashicons dashicons-arrow-down-alt2 real-mcp-toggle-icon"></span>
							<strong><?php echo esc_html( $group['label'] ); ?></strong>
							<span class="real-mcp-group-badge"><?php echo count( $group['tools'] ); ?></span>
						</div>
						<div class="real-mcp-group-right">
							<label class="real-mcp-switch-label">
								<span class="real-mcp-switch-text"><?php esc_html_e( 'Enable All', 'real-mcp' ); ?></span>
								<input type="checkbox" class="real-mcp-group-toggle" data-group="<?php echo esc_attr( $cat ); ?>">
								<span class="real-mcp-switch-slider"></span>
							</label>
						</div>
					</div>
					<div class="real-mcp-group-body">
						<table class="widefat fixed striped">
							<thead>
								<tr>
									<th style="width:40px;"></th>
									<th style="width:25%;"><?php esc_html_e( 'Tool', 'real-mcp' ); ?></th>
									<th><?php esc_html_e( 'Description', 'real-mcp' ); ?></th>
									<th style="width:15%;"><?php esc_html_e( 'Permission', 'real-mcp' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $group['tools'] as $tool ) :
								$def = $tool->get_definition();
								$name = $def['name'];
								$checked = ( $enabled_tools === null ) || in_array( $name, $enabled_tools, true );
							?>
								<tr>
									<td>
										<input type="checkbox" class="real-mcp-tool-check"
											   name="enabled_tools[]"
											   value="<?php echo esc_attr( $name ); ?>"
											   data-group="<?php echo esc_attr( $cat ); ?>"
											   <?php checked( $checked ); ?>>
									</td>
									<td><code><?php echo esc_html( $name ); ?></code></td>
									<td><?php echo esc_html( $def['description'] ); ?></td>
									<td><code><?php echo esc_html( $tool->get_capability() ); ?></code></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endforeach; ?>
			</div>

			<?php
			// Show inactive plugin groups (locked).
			$inactive_groups = \Real_MCP\Tools\Registry::get_inactive_plugin_tools();
			if ( ! empty( $inactive_groups ) ) :
			?>
			<div class="real-mcp-abilities-wrap" style="margin-top: 24px;">
				<h3 style="margin-bottom: 8px;"><?php esc_html_e( 'Available with additional plugins', 'real-mcp' ); ?></h3>
				<?php foreach ( $inactive_groups as $cat => $group ) : ?>
					<div class="real-mcp-group real-mcp-group-locked collapsed" data-group="<?php echo esc_attr( $cat ); ?>">
						<div class="real-mcp-group-header">
							<div class="real-mcp-group-left">
								<span class="dashicons dashicons-arrow-down-alt2 real-mcp-toggle-icon"></span>
								<strong><?php echo esc_html( $group['label'] ); ?></strong>
								<span class="real-mcp-group-badge real-mcp-badge-locked"><?php echo count( $group['tools'] ); ?></span>
							</div>
							<div class="real-mcp-group-right">
								<a href="<?php echo esc_url( $group['plugin_url'] ); ?>" target="_blank" class="button button-small">
									<?php esc_html_e( 'Install Plugin', 'real-mcp' ); ?>
								</a>
							</div>
						</div>
						<div class="real-mcp-group-body">
							<table class="widefat fixed striped">
								<thead>
									<tr>
										<th style="width:40px;"></th>
										<th style="width:25%;"><?php esc_html_e( 'Tool', 'real-mcp' ); ?></th>
										<th><?php esc_html_e( 'Description', 'real-mcp' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $group['tools'] as $tool_info ) : ?>
									<tr class="real-mcp-row-locked">
										<td><input type="checkbox" disabled></td>
										<td><code><?php echo esc_html( $tool_info['name'] ); ?></code></td>
										<td><?php echo esc_html( $tool_info['description'] ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<p class="submit">
				<button type="submit" class="button button-primary" id="real-mcp-save-abilities">
					<?php esc_html_e( 'Save Abilities', 'real-mcp' ); ?>
				</button>
				<span class="spinner" id="real-mcp-abilities-spinner" style="float:none;"></span>
			</p>
		</form>

		<?php self::render_abilities_styles(); ?>
		<?php self::render_abilities_scripts(); ?>
		<?php
	}

	/**
	 * Render the Help tab.
	 */
	private static function render_help_tab(): void {
		$endpoint_url = rest_url( Endpoint::NAMESPACE . Endpoint::ROUTE );
		$api_key      = get_option( 'real_mcp_api_key', '' );
		?>
		<h2><?php esc_html_e( 'Getting Started', 'real-mcp' ); ?></h2>
		<ol style="max-width:700px; line-height:1.8;">
			<li><?php esc_html_e( 'Go to the Configuration tab and generate an API key.', 'real-mcp' ); ?></li>
			<li><?php esc_html_e( 'Go to the Abilities tab and enable/disable the tools you want AI agents to use.', 'real-mcp' ); ?></li>
			<li><?php esc_html_e( 'Copy the connection config below into your AI client.', 'real-mcp' ); ?></li>
		</ol>

		<h2><?php esc_html_e( 'Connection Config', 'real-mcp' ); ?></h2>
		<h3><?php esc_html_e( 'Claude Desktop / Cursor / Kiro / VS Code', 'real-mcp' ); ?></h3>
		<pre style="background:#f0f0f0;padding:16px;border-radius:4px;max-width:700px;overflow-x:auto;">{
  "mcpServers": {
    "wordpress": {
      "url": "<?php echo esc_url( $endpoint_url ); ?>",
      "headers": {
        "Authorization": "Bearer <?php echo esc_html( $api_key ? '••••••••' : 'YOUR_API_KEY' ); ?>"
      }
    }
  }
}</pre>

		<h3><?php esc_html_e( 'LangChain / CrewAI / AutoGen', 'real-mcp' ); ?></h3>
		<pre style="background:#f0f0f0;padding:16px;border-radius:4px;max-width:700px;overflow-x:auto;"># Endpoint: <?php echo esc_url( $endpoint_url ); ?>

# Header: Authorization: Bearer YOUR_API_KEY
# Protocol: MCP 2025-06-18, Streamable HTTP transport
# Methods: initialize → tools/list → tools/call</pre>

		<h2><?php esc_html_e( 'Protocol Details', 'real-mcp' ); ?></h2>
		<table class="widefat fixed" style="max-width:600px;">
			<tbody>
				<tr><th><?php esc_html_e( 'Transport', 'real-mcp' ); ?></th><td>Streamable HTTP (POST)</td></tr>
				<tr><th><?php esc_html_e( 'Protocol Version', 'real-mcp' ); ?></th><td>2025-06-18 (also supports 2025-03-26, 2024-11-05)</td></tr>
				<tr><th><?php esc_html_e( 'Encoding', 'real-mcp' ); ?></th><td>JSON-RPC 2.0</td></tr>
				<tr><th><?php esc_html_e( 'Authentication', 'real-mcp' ); ?></th><td>Bearer token, X-API-Key header, or ?api_key= parameter</td></tr>
				<tr><th><?php esc_html_e( 'Session', 'real-mcp' ); ?></th><td>Optional (Mcp-Session-Id header)</td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'How Abilities Work', 'real-mcp' ); ?></h2>
		<ul style="max-width:700px; line-height:1.8; list-style:disc; padding-left:20px;">
			<li><?php esc_html_e( 'Each tool is an ability that can be enabled or disabled from the Abilities tab.', 'real-mcp' ); ?></li>
			<li><?php esc_html_e( 'Tools are grouped by plugin (WooCommerce, Elementor, Rank Math) or core category.', 'real-mcp' ); ?></li>
			<li><?php esc_html_e( 'Disabled tools are hidden from AI agents — they won\'t appear in tools/list.', 'real-mcp' ); ?></li>
			<li><?php esc_html_e( 'Plugin-specific tools only appear when that plugin is active.', 'real-mcp' ); ?></li>
			<li><?php esc_html_e( 'Use "Enable All" / "Disable All" toggles on group headers for bulk control.', 'real-mcp' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Supported Plugins', 'real-mcp' ); ?></h2>
		<table class="widefat fixed" style="max-width:600px;">
			<thead><tr><th><?php esc_html_e( 'Plugin', 'real-mcp' ); ?></th><th><?php esc_html_e( 'Status', 'real-mcp' ); ?></th></tr></thead>
			<tbody>
				<tr>
					<td>WooCommerce</td>
					<td><?php echo class_exists( 'WooCommerce' ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Elementor</td>
					<td><?php echo defined( 'ELEMENTOR_VERSION' ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Elementor Pro</td>
					<td><?php echo defined( 'ELEMENTOR_PRO_VERSION' ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Rank Math SEO</td>
					<td><?php echo class_exists( 'RankMath' ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Advanced Custom Fields (ACF)</td>
					<td><?php echo ( class_exists( 'ACF' ) || function_exists( 'acf_get_field_groups' ) ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Classic Editor</td>
					<td><?php echo ( defined( 'CLASSIC_EDITOR_VERSION' ) || class_exists( 'Classic_Editor' ) ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Astra / Astra Pro</td>
					<td><?php echo ( defined( 'ASTRA_THEME_VERSION' ) || get_template() === 'astra' ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Table Addons for Elementor</td>
					<td><?php echo ( defined( 'TABLE_ADDONS_FOR_ELEMENTOR_VERSION' ) || class_exists( 'TableAddonsForElementor' ) ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
				<tr>
					<td>Yoast SEO</td>
					<td><?php echo defined( 'WPSEO_VERSION' ) ? '<span style="color:green;">✓ Active</span>' : '<span style="color:#999;">Not installed</span>'; ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render abilities tab styles.
	 */
	private static function render_abilities_styles(): void {
		?>
		<style>
		.real-mcp-abilities-wrap { max-width: 900px; }
		.real-mcp-group { margin-bottom: 0; border: 1px solid #c3c4c7; border-bottom: none; }
		.real-mcp-group:first-child { border-radius: 4px 4px 0 0; }
		.real-mcp-group:last-child { border-bottom: 1px solid #c3c4c7; border-radius: 0 0 4px 4px; }
		.real-mcp-group:only-child { border-radius: 4px; }
		.real-mcp-group-header {
			display: flex; align-items: center; justify-content: space-between;
			padding: 12px 16px; background: #f6f7f7; cursor: pointer; user-select: none;
		}
		.real-mcp-group-header:hover { background: #eef0f0; }
		.real-mcp-group-left { display: flex; align-items: center; gap: 8px; }
		.real-mcp-group-right { display: flex; align-items: center; }
		.real-mcp-toggle-icon { transition: transform 0.2s; }
		.real-mcp-group.collapsed .real-mcp-toggle-icon { transform: rotate(-90deg); }
		.real-mcp-group.collapsed .real-mcp-group-body { display: none; }
		.real-mcp-group-badge {
			background: #2271b1; color: #fff; font-size: 11px;
			padding: 2px 8px; border-radius: 10px;
		}
		.real-mcp-group-body { border-top: 1px solid #c3c4c7; }
		.real-mcp-group-body table { margin: 0; border: none; }

		/* Locked (inactive plugin) groups */
		.real-mcp-group-locked { opacity: 0.75; }
		.real-mcp-group-locked .real-mcp-group-header { background: #f9f3f0; }
		.real-mcp-badge-locked { background: #9e9e9e; }
		.real-mcp-row-locked { opacity: 0.6; }
		.real-mcp-row-locked input[disabled] { cursor: not-allowed; }

		/* Toggle switch */
		.real-mcp-switch-label {
			display: flex; align-items: center; gap: 8px; cursor: pointer;
			font-size: 12px;
		}
		.real-mcp-switch-label input { display: none; }
		.real-mcp-switch-slider {
			position: relative; width: 36px; height: 20px;
			background: #ccc; border-radius: 20px; transition: background 0.3s;
		}
		.real-mcp-switch-slider::before {
			content: ''; position: absolute; top: 2px; left: 2px;
			width: 16px; height: 16px; background: #fff;
			border-radius: 50%; transition: transform 0.3s;
		}
		.real-mcp-switch-label input:checked + .real-mcp-switch-slider {
			background: #2271b1;
		}
		.real-mcp-switch-label input:checked + .real-mcp-switch-slider::before {
			transform: translateX(16px);
		}
		</style>
		<?php
	}

	/**
	 * Render abilities tab scripts.
	 */
	private static function render_abilities_scripts(): void {
		?>
		<script>
		(function(){
			// Collapse/expand groups (both active and locked).
			document.querySelectorAll('.real-mcp-group-header').forEach(function(header) {
				header.querySelector('.real-mcp-group-left').addEventListener('click', function(e) {
					header.parentElement.classList.toggle('collapsed');
				});
			});

			// Group toggle (Enable All / Disable All).
			document.querySelectorAll('.real-mcp-group-toggle').forEach(function(toggle) {
				var group = toggle.dataset.group;
				var checks = document.querySelectorAll('.real-mcp-tool-check[data-group="'+group+'"]');

				// Set initial state.
				var allChecked = Array.from(checks).every(function(c){ return c.checked; });
				toggle.checked = allChecked;

				// Update label.
				updateToggleLabel(toggle);

				toggle.addEventListener('change', function() {
					checks.forEach(function(c){ c.checked = toggle.checked; });
					updateToggleLabel(toggle);
				});

				// Sync toggle when individual checkboxes change.
				checks.forEach(function(c) {
					c.addEventListener('change', function() {
						var all = Array.from(checks).every(function(ch){ return ch.checked; });
						toggle.checked = all;
						updateToggleLabel(toggle);
					});
				});
			});

			function updateToggleLabel(toggle) {
				var label = toggle.closest('.real-mcp-switch-label').querySelector('.real-mcp-switch-text');
				label.textContent = toggle.checked ? '<?php echo esc_js( __( 'Disable All', 'real-mcp' ) ); ?>' : '<?php echo esc_js( __( 'Enable All', 'real-mcp' ) ); ?>';
			}

			// Save via AJAX.
			document.getElementById('real-mcp-abilities-form').addEventListener('submit', function(e) {
				e.preventDefault();
				var spinner = document.getElementById('real-mcp-abilities-spinner');
				spinner.classList.add('is-active');

				var formData = new FormData();
				formData.append('action', 'real_mcp_save_abilities');
				formData.append('nonce', this.querySelector('[name=nonce]').value);

				var checked = document.querySelectorAll('.real-mcp-tool-check:checked');
				checked.forEach(function(c) {
					formData.append('enabled_tools[]', c.value);
				});

				fetch(ajaxurl, { method: 'POST', body: formData })
					.then(function(r){ return r.json(); })
					.then(function(data) {
						spinner.classList.remove('is-active');
						var notice = document.getElementById('real-mcp-abilities-notice');
						notice.style.display = 'block';
						setTimeout(function(){ notice.style.display = 'none'; }, 3000);
					});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render API key field.
	 */
	private static function render_api_key_field(): void {
		$value = get_option( 'real_mcp_api_key', '' );
		?>
		<input type="text" name="real_mcp_api_key" value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text" id="real-mcp-api-key" autocomplete="off">
		<button type="button" class="button" id="real-mcp-generate-key">
			<?php esc_html_e( 'Generate Key', 'real-mcp' ); ?>
		</button>
		<script>
			document.getElementById('real-mcp-generate-key').addEventListener('click', function() {
				var key = '';
				var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
				for (var i = 0; i < 64; i++) {
					key += chars.charAt(Math.floor(Math.random() * chars.length));
				}
				document.getElementById('real-mcp-api-key').value = key;
			});
		</script>
		<p class="description">
			<?php esc_html_e( 'API key used to authenticate MCP client connections. Keep this secret.', 'real-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Render allowed origins field.
	 */
	private static function render_origins_field(): void {
		$value = get_option( 'real_mcp_allowed_origins', '' );
		?>
		<textarea name="real_mcp_allowed_origins" rows="3" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'One hostname per line. Leave empty to allow all origins.', 'real-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Render "Run As User" dropdown.
	 */
	private static function render_user_field(): void {
		$value  = (int) get_option( 'real_mcp_admin_user_id', 0 );
		$admins = get_users( [ 'role' => 'administrator' ] );
		?>
		<select name="real_mcp_admin_user_id">
			<option value="0"><?php esc_html_e( '— Auto (first administrator) —', 'real-mcp' ); ?></option>
			<?php foreach ( $admins as $admin ) : ?>
				<option value="<?php echo esc_attr( $admin->ID ); ?>" <?php selected( $value, $admin->ID ); ?>>
					<?php echo esc_html( $admin->display_name . ' (' . $admin->user_login . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'MCP operations execute with this user\'s permissions.', 'real-mcp' ); ?>
		</p>
		<?php
	}
}
