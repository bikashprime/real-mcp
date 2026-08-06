<?php
/**
 * REST API endpoint for MCP Streamable HTTP transport.
 *
 * Implements the MCP 2025-06-18 Streamable HTTP transport specification.
 * Designed for universal compatibility with all MCP clients including
 * ChatGPT, Claude Desktop, Cursor, Gemini, Kiro, VS Code, and any
 * standards-compliant MCP client.
 *
 * @package Real_MCP
 */

namespace Real_MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Endpoint {

	const NAMESPACE = 'real-mcp/v1';
	const ROUTE     = '/mcp';

	/**
	 * Supported protocol versions for version negotiation.
	 */
	const SUPPORTED_VERSIONS = [
		'2025-06-18',
		'2025-03-26',
		'2024-11-05',
	];

	/**
	 * Register REST routes.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ self::class, 'add_cors_headers' ], 10, 4 );
	}

	/**
	 * Register the MCP endpoint routes.
	 */
	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'handle_post' ],
				'permission_callback' => [ self::class, 'check_permission' ],
			],
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'handle_get' ],
				'permission_callback' => [ self::class, 'check_permission' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ self::class, 'handle_delete' ],
				'permission_callback' => [ self::class, 'check_permission' ],
			],
		] );
	}

	/**
	 * Add CORS headers for MCP client compatibility.
	 *
	 * Many MCP clients (browser-based, Electron apps) require CORS.
	 */
	public static function add_cors_headers( $served, $result, $request, $server ): bool {
		// Only add CORS to our endpoint.
		$route = $request->get_route();
		if ( strpos( $route, '/' . self::NAMESPACE . self::ROUTE ) === false ) {
			return $served;
		}

		$origin = $request->get_header( 'origin' );
		if ( $origin ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Access-Control-Allow-Credentials: true' );
		}

		header( 'Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept, Mcp-Session-Id, MCP-Protocol-Version, Last-Event-ID' );
		header( 'Access-Control-Expose-Headers: Mcp-Session-Id, Content-Type' );

		return $served;
	}

	/**
	 * Permission check for MCP endpoint.
	 */
	public static function check_permission( \WP_REST_Request $request ): bool {
		// Allow OPTIONS preflight requests through without auth.
		if ( $request->get_method() === 'OPTIONS' ) {
			return true;
		}

		// Validate Origin header to prevent DNS rebinding attacks.
		$origin = $request->get_header( 'origin' );
		if ( $origin ) {
			$allowed = self::get_allowed_origins();
			if ( ! empty( $allowed ) ) {
				$parsed = wp_parse_url( $origin );
				$host   = $parsed['host'] ?? '';
				if ( ! in_array( $host, $allowed, true ) ) {
					return false;
				}
			}
		}

		// Check API key authentication.
		return self::authenticate( $request );
	}

	/**
	 * Authenticate the request.
	 *
	 * Supports multiple auth methods for maximum client compatibility:
	 * - Authorization: Bearer <token> (OAuth2 style, most clients)
	 * - X-API-Key: <key> (simple API key header)
	 * - ?api_key=<key> (query parameter, for clients that can't set headers)
	 */
	private static function authenticate( \WP_REST_Request $request ): bool {
		$stored_key = get_option( 'real_mcp_api_key', '' );

		// If no API key is configured, deny all access.
		if ( empty( $stored_key ) ) {
			return false;
		}

		// Method 1: Authorization: Bearer <token>.
		$auth_header = $request->get_header( 'authorization' );
		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			return hash_equals( $stored_key, trim( $matches[1] ) );
		}

		// Method 2: X-API-Key header.
		$api_key_header = $request->get_header( 'x-api-key' );
		if ( $api_key_header ) {
			return hash_equals( $stored_key, trim( $api_key_header ) );
		}

		// Method 3: Query parameter (for clients that cannot set custom headers).
		$query_key = $request->get_param( 'api_key' );
		if ( $query_key ) {
			return hash_equals( $stored_key, trim( $query_key ) );
		}

		return false;
	}

	/**
	 * Handle POST requests — receives JSON-RPC messages from clients.
	 *
	 * Per MCP spec: every JSON-RPC message from client is a new HTTP POST.
	 * The body is a single JSON-RPC request, notification, or response.
	 */
	public static function handle_post( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		// Validate JSON-RPC envelope.
		if ( empty( $body ) || ! isset( $body['jsonrpc'] ) || $body['jsonrpc'] !== '2.0' ) {
			return self::json_rpc_error( $body['id'] ?? null, -32600, 'Invalid JSON-RPC request.' );
		}

		// If it's a notification or response from client (no method, or method but no id).
		$is_notification = isset( $body['method'] ) && ! isset( $body['id'] );
		$is_response     = ! isset( $body['method'] ) && ( isset( $body['result'] ) || isset( $body['error'] ) );

		if ( $is_notification || $is_response ) {
			// Accept notifications/responses with 202 Accepted, no body.
			return new \WP_REST_Response( null, 202 );
		}

		// It's a request (has method and id).
		if ( ! isset( $body['method'] ) || ! isset( $body['id'] ) ) {
			return self::json_rpc_error( $body['id'] ?? null, -32600, 'Invalid JSON-RPC request: missing method or id.' );
		}

		// Validate MCP-Protocol-Version header on non-initialize requests.
		$method = $body['method'];
		if ( $method !== 'initialize' ) {
			$protocol_version = $request->get_header( 'mcp-protocol-version' );
			if ( $protocol_version && ! in_array( $protocol_version, self::SUPPORTED_VERSIONS, true ) ) {
				return self::json_rpc_error( $body['id'], -32600, 'Unsupported MCP protocol version.', 400 );
			}
		}

		// Route to server.
		$server   = new Server();
		$response = $server->handle_message( $body );

		if ( $response === null ) {
			return new \WP_REST_Response( null, 202 );
		}

		// Build HTTP response.
		$rest_response = new \WP_REST_Response( $response, 200 );
		$rest_response->header( 'Content-Type', 'application/json' );

		// Add Mcp-Session-Id header per spec.
		$session_id = $server->get_session_id();
		if ( $session_id ) {
			$rest_response->header( 'Mcp-Session-Id', $session_id );
		}

		return $rest_response;
	}

	/**
	 * Handle GET requests.
	 *
	 * Per MCP spec: GET opens an SSE stream for server-initiated messages.
	 * Since this is a stateless WordPress implementation, we return 405 to
	 * indicate SSE is not available. Clients will use POST for all interactions.
	 * This is spec-compliant — the server MAY return 405 Method Not Allowed.
	 */
	public static function handle_get( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( null, 405 );
	}

	/**
	 * Handle DELETE requests — session termination.
	 *
	 * Per MCP spec: clients that no longer need a session SHOULD send DELETE.
	 */
	public static function handle_delete( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id = $request->get_header( 'mcp-session-id' );

		if ( $session_id ) {
			// Sanitize session ID to prevent transient key injection.
			$safe_id = preg_replace( '/[^a-f0-9\-]/i', '', $session_id );
			delete_transient( 'real_mcp_session_' . $safe_id );
		}

		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Build a JSON-RPC error response.
	 */
	private static function json_rpc_error( mixed $id, int $code, string $message, int $http_status = 400 ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => [
				'code'    => $code,
				'message' => $message,
			],
		], $http_status );
	}

	/**
	 * Get allowed origins from settings.
	 *
	 * @return array<string>
	 */
	private static function get_allowed_origins(): array {
		$origins = get_option( 'real_mcp_allowed_origins', '' );
		if ( empty( $origins ) ) {
			return [];
		}
		return array_filter( array_map( 'trim', explode( "\n", $origins ) ) );
	}
}
