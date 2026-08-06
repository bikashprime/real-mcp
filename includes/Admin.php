<?php
/**
 * Admin settings page for Real MCP.
 *
 * @package Real_MCP
 */

namespace Real_MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public static function add_menu(): void {
		add_options_page(
			__( 'Real MCP', 'real-mcp' ),
			__( 'Real MCP', 'real-mcp' ),
			'manage_options',
			'real-mcp',
			[ self::class, 'render_page' ]
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

		add_settings_section(
			'real_mcp_main',
			__( 'MCP Server Configuration', 'real-mcp' ),
			[ self::class, 'render_section' ],
			'real-mcp'
		);

		add_settings_field(
			'real_mcp_api_key',
			__( 'API Key', 'real-mcp' ),
			[ self::class, 'render_api_key_field' ],
			'real-mcp',
			'real_mcp_main'
		);

		add_settings_field(
			'real_mcp_admin_user_id',
			__( 'Run As User', 'real-mcp' ),
			[ self::class, 'render_user_field' ],
			'real-mcp',
			'real_mcp_main'
		);

		add_settings_field(
			'real_mcp_allowed_origins',
			__( 'Allowed Origins', 'real-mcp' ),
			[ self::class, 'render_origins_field' ],
			'real-mcp',
			'real_mcp_main'
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$endpoint_url = rest_url( Endpoint::NAMESPACE . Endpoint::ROUTE );
		$api_key      = get_option( 'real_mcp_api_key', '' );
		$tools        = \Real_MCP\Tools\Registry::get_tools();
		$categories   = \Real_MCP\Tools\Registry::get_categories();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="notice notice-info" style="padding: 12px;">
				<strong><?php esc_html_e( 'MCP Endpoint URL:', 'real-mcp' ); ?></strong>
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
					(<?php echo esc_html( implode( ', ', $categories ) ); ?>)
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'real_mcp_settings' );
				do_settings_sections( 'real-mcp' );
				submit_button();
				?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Connection Guide', 'real-mcp' ); ?></h2>
			<p><?php esc_html_e( 'Use the following configuration to connect your AI agent. The same endpoint works with all MCP-compatible clients.', 'real-mcp' ); ?></p>

			<h3><?php esc_html_e( 'Claude Desktop / Cursor / Kiro / VS Code', 'real-mcp' ); ?></h3>
			<pre style="background: #f0f0f0; padding: 16px; border-radius: 4px; overflow-x: auto;">{
  "mcpServers": {
    "wordpress": {
      "url": "<?php echo esc_url( $endpoint_url ); ?>",
      "headers": {
        "Authorization": "Bearer <?php echo esc_html( $api_key ? '••••••••' : 'YOUR_API_KEY' ); ?>"
      }
    }
  }
}</pre>

			<h3><?php esc_html_e( 'LangChain / CrewAI / AutoGen / SDK-based', 'real-mcp' ); ?></h3>
			<pre style="background: #f0f0f0; padding: 16px; border-radius: 4px; overflow-x: auto;"># Endpoint: <?php echo esc_url( $endpoint_url ); ?>

# Header: Authorization: Bearer YOUR_API_KEY
# Protocol: MCP 2025-06-18, Streamable HTTP transport
# Methods: initialize → tools/list → tools/call</pre>

			<h3><?php esc_html_e( 'Protocol Details', 'real-mcp' ); ?></h3>
			<table class="widefat fixed" style="max-width: 600px;">
				<tbody>
					<tr><th><?php esc_html_e( 'Transport', 'real-mcp' ); ?></th><td>Streamable HTTP (POST)</td></tr>
					<tr><th><?php esc_html_e( 'Protocol Version', 'real-mcp' ); ?></th><td>2025-06-18 (also supports 2025-03-26, 2024-11-05)</td></tr>
					<tr><th><?php esc_html_e( 'Encoding', 'real-mcp' ); ?></th><td>JSON-RPC 2.0</td></tr>
					<tr><th><?php esc_html_e( 'Authentication', 'real-mcp' ); ?></th><td>Bearer token, X-API-Key header, or ?api_key= parameter</td></tr>
					<tr><th><?php esc_html_e( 'Session', 'real-mcp' ); ?></th><td>Optional (Mcp-Session-Id header)</td></tr>
				</tbody>
			</table>

			<hr>
			<h2><?php esc_html_e( 'Available Tools', 'real-mcp' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %1$d: number of tools, %2$d: number of categories */
					esc_html__( 'Showing %1$d tools across %2$d categories. Click a category to expand/collapse.', 'real-mcp' ),
					count( $tools ),
					count( $categories )
				);
				?>
			</p>

			<?php
			// Group tools by category.
			$tools_by_category = [];
			foreach ( $tools as $tool ) {
				$cat = $tool->get_category();
				if ( ! isset( $tools_by_category[ $cat ] ) ) {
					$tools_by_category[ $cat ] = [];
				}
				$tools_by_category[ $cat ][] = $tool;
			}
			ksort( $tools_by_category );
			?>

			<style>
				.real-mcp-category-header {
					display: flex;
					align-items: center;
					justify-content: space-between;
					cursor: pointer;
					padding: 12px 16px;
					margin: 0;
					background: #f6f7f7;
					border: 1px solid #c3c4c7;
					border-bottom: none;
					user-select: none;
				}
				.real-mcp-category-header:first-of-type {
					border-radius: 4px 4px 0 0;
				}
				.real-mcp-category-header:hover {
					background: #eef0f0;
				}
				.real-mcp-category-header h3 {
					margin: 0;
					font-size: 14px;
					text-transform: capitalize;
				}
				.real-mcp-category-header .dashicons {
					transition: transform 0.2s;
				}
				.real-mcp-category-header.collapsed .dashicons {
					transform: rotate(-90deg);
				}
				.real-mcp-category-badge {
					display: inline-block;
					background: #2271b1;
					color: #fff;
					font-size: 11px;
					padding: 2px 8px;
					border-radius: 10px;
					margin-left: 8px;
					font-weight: normal;
				}
				.real-mcp-category-table {
					margin: 0;
					border-top: none;
					border-radius: 0;
				}
				.real-mcp-category-table:last-of-type {
					border-radius: 0 0 4px 4px;
				}
				.real-mcp-category-wrap {
					margin-bottom: 0;
				}
				.real-mcp-category-wrap:last-child .real-mcp-category-header {
					border-bottom: 1px solid #c3c4c7;
				}
				.real-mcp-category-wrap:last-child .real-mcp-category-header:not(.collapsed) {
					border-bottom: none;
				}
			</style>

			<div class="real-mcp-tools-accordion">
				<?php foreach ( $tools_by_category as $category_name => $category_tools ) : ?>
					<div class="real-mcp-category-wrap">
						<div class="real-mcp-category-header" data-category="<?php echo esc_attr( $category_name ); ?>">
							<h3>
								<span class="dashicons dashicons-arrow-down-alt2"></span>
								<?php echo esc_html( ucfirst( $category_name ) ); ?>
								<span class="real-mcp-category-badge"><?php echo count( $category_tools ); ?></span>
							</h3>
						</div>
						<table class="widefat fixed striped real-mcp-category-table">
							<thead>
								<tr>
									<th style="width: 25%;"><?php esc_html_e( 'Tool', 'real-mcp' ); ?></th>
									<th><?php esc_html_e( 'Description', 'real-mcp' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $category_tools as $tool ) : $def = $tool->get_definition(); ?>
									<tr>
										<td><code><?php echo esc_html( $def['name'] ); ?></code></td>
										<td><?php echo esc_html( $def['description'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>

			<script>
				(function() {
					var headers = document.querySelectorAll('.real-mcp-category-header');
					headers.forEach(function(header) {
						header.addEventListener('click', function() {
							var table = this.nextElementSibling;
							var isCollapsed = this.classList.toggle('collapsed');
							table.style.display = isCollapsed ? 'none' : '';
						});
					});
				})();
			</script>
		</div>
		<?php
	}

	/**
	 * Render section description.
	 */
	public static function render_section(): void {
		echo '<p>' . esc_html__( 'Configure authentication and security for the MCP server endpoint.', 'real-mcp' ) . '</p>';
	}

	/**
	 * Render API key field.
	 */
	public static function render_api_key_field(): void {
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
	public static function render_origins_field(): void {
		$value = get_option( 'real_mcp_allowed_origins', '' );
		?>
		<textarea name="real_mcp_allowed_origins" rows="4" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'One hostname per line (e.g., localhost, myapp.example.com). Leave empty to allow all origins.', 'real-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Render "Run As User" dropdown.
	 */
	public static function render_user_field(): void {
		$value = (int) get_option( 'real_mcp_admin_user_id', 0 );
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
			<?php esc_html_e( 'MCP operations will execute with this user\'s permissions. Choose the admin account the AI agent should act as.', 'real-mcp' ); ?>
		</p>
		<?php
	}
}
