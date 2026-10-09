<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPVDMCP_Server {
	private static $instance;
	private $authenticated_user = 0;
	private $modern_request = false;

	const MODERN_PROTOCOL = '2026-07-28';
	const LEGACY_PROTOCOL = '2025-11-25';

	public static function supported_protocol_versions() {
		return array( self::MODERN_PROTOCOL, self::LEGACY_PROTOCOL, '2025-06-18', '2025-03-26', '2024-11-05' );
	}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 100 );
		add_filter( 'plugin_action_links_' . plugin_basename( WPVDMCP_FILE ), array( $this, 'action_links' ) );
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wpvibe-direct-mcp' ) ) . '">Direct MCP</a>' );
		return $links;
	}

	public function register_routes() {
		register_rest_route( 'wpvibe-direct/v1', '/mcp', array(
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'stream_get' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods' => 'OPTIONS',
				'callback' => array( $this, 'options' ),
				'permission_callback' => '__return_true',
			),
		) );

		// Keep browser diagnostics separate from the MCP transport endpoint.
		// The Streamable HTTP specification requires a non-streaming MCP GET
		// request to return 405 rather than ordinary JSON.
		register_rest_route( 'wpvibe-direct/v1', '/health', array(
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'status' ),
				'permission_callback' => '__return_true',
			),
		) );
	}


	public function stream_get() {
		$response = new WP_REST_Response( array(
			'error' => 'standalone_sse_not_supported',
			'message' => 'This MCP server is stateless and does not provide a standalone SSE stream. Send MCP JSON-RPC messages with POST.',
		), 405 );
		$response->header( 'Allow', 'POST, OPTIONS' );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	public function options() {
		$response = new WP_REST_Response( null, 204 );
		$response->header( 'Allow', 'POST, GET, OPTIONS' );
		$response->header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version, Mcp-Method, Mcp-Name, MCP-Session-Id, X-WPVibe-Direct-Token' );
		$response->header( 'Access-Control-Allow-Methods', 'POST, GET, OPTIONS' );
		$response->header( 'Access-Control-Max-Age', '600' );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	public function status( $request ) {
		$auth = $this->authenticate( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		return rest_ensure_response( array(
			'name' => 'WPVibe Direct MCP',
			'version' => WPVDMCP_VERSION,
			'endpoint' => rest_url( 'wpvibe-direct/v1/mcp' ),
			'health' => rest_url( 'wpvibe-direct/v1/health' ),
			'wpvibe_active' => defined( 'WPVIBE_VERSION' ) || class_exists( 'WPVibe_REST' ),
			'wpvibe_version' => defined( 'WPVIBE_VERSION' ) ? WPVIBE_VERSION : null,
			'transport' => 'streamable-http-json',
			'note' => 'Send JSON-RPC 2.0 MCP requests with POST.',
		) );
	}

	public function handle( $request ) {
		if ( '1' !== get_option( 'wpvdmcp_enabled', '1' ) ) {
			return new WP_Error( 'mcp_disabled', 'WPVibe Direct MCP is disabled.', array( 'status' => 503 ) );
		}
		$auth = $this->authenticate( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		if ( ! defined( 'WPVIBE_VERSION' ) && ! class_exists( 'WPVibe_REST' ) ) {
			return new WP_Error( 'wpvibe_required', 'Activate the WPVibe plugin first. This bridge reuses WPVibe’s protected WordPress tools.', array( 'status' => 503 ) );
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return $this->rpc_error( null, -32700, 'Invalid JSON-RPC payload.', 400 );
		}

		$http_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );

		if ( isset( $payload[0] ) ) {
			$responses = array();
			foreach ( $payload as $item ) {
				$protocol_error = $this->validate_protocol( $item, $http_version, null );
				if ( $protocol_error ) {
					$responses[] = $protocol_error;
					continue;
				}
				$this->modern_request = $this->is_modern_rpc( $item, $http_version );
				$result = $this->process_rpc( $item );
				if ( null !== $result ) {
					$responses[] = $result;
				}
			}
			return $responses ? rest_ensure_response( $responses ) : new WP_REST_Response( null, 202 );
		}

		$protocol_error = $this->validate_protocol( $payload, $http_version, $request );
		if ( $protocol_error ) {
			return new WP_REST_Response( $protocol_error, 400 );
		}
		$this->modern_request = $this->is_modern_rpc( $payload, $http_version );
		$result = $this->process_rpc( $payload );
		return null === $result ? new WP_REST_Response( null, 202 ) : rest_ensure_response( $result );
	}

	private function protocol_version_from_rpc( $rpc, $http_version = '' ) {
		$params = isset( $rpc['params'] ) && is_array( $rpc['params'] ) ? $rpc['params'] : array();
		$meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
		$body_version = isset( $meta['io.modelcontextprotocol/protocolVersion'] ) ? (string) $meta['io.modelcontextprotocol/protocolVersion'] : '';
		return $body_version ? $body_version : (string) $http_version;
	}

	private function is_modern_rpc( $rpc, $http_version = '' ) {
		return self::MODERN_PROTOCOL === $this->protocol_version_from_rpc( $rpc, $http_version );
	}

	private function validate_protocol( $rpc, $http_version = '', $request = null ) {
		if ( ! is_array( $rpc ) ) {
			return $this->rpc_error_array( null, -32600, 'Invalid Request.' );
		}
		$params = isset( $rpc['params'] ) && is_array( $rpc['params'] ) ? $rpc['params'] : array();
		$meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
		$body_version = isset( $meta['io.modelcontextprotocol/protocolVersion'] ) ? (string) $meta['io.modelcontextprotocol/protocolVersion'] : '';
		$id = array_key_exists( 'id', $rpc ) ? $rpc['id'] : null;

		if ( $body_version && $http_version && $body_version !== $http_version ) {
			return $this->rpc_error_array( $id, -32020, 'MCP protocol header does not match request metadata.', array( 'header' => $http_version, 'metadata' => $body_version ) );
		}

		$requested = $body_version ? $body_version : (string) $http_version;
		if ( $requested && ! in_array( $requested, self::supported_protocol_versions(), true ) ) {
			return $this->rpc_error_array( $id, -32022, 'Unsupported protocol version', array( 'supported' => self::supported_protocol_versions(), 'requested' => $requested ) );
		}

		// MCP 2026-07-28 Streamable HTTP routes every request by headers. Validate
		// the standard headers against the JSON-RPC body instead of trusting them.
		if ( self::MODERN_PROTOCOL === $requested && $request ) {
			$method = isset( $rpc['method'] ) ? (string) $rpc['method'] : '';
			$header_method = trim( (string) $request->get_header( 'mcp-method' ) );
			if ( '' === $header_method || $header_method !== $method ) {
				return $this->rpc_error_array( $id, -32020, 'MCP routing header does not match the request body.', array( 'header' => 'Mcp-Method', 'expected' => $method, 'received' => $header_method ) );
			}
			$expected_name = '';
			if ( isset( $params['name'] ) && is_scalar( $params['name'] ) ) {
				$expected_name = (string) $params['name'];
			} elseif ( isset( $params['uri'] ) && is_scalar( $params['uri'] ) ) {
				$expected_name = (string) $params['uri'];
			} elseif ( isset( $params['taskId'] ) && is_scalar( $params['taskId'] ) ) {
				$expected_name = (string) $params['taskId'];
			}
			if ( '' !== $expected_name ) {
				$header_name = trim( (string) $request->get_header( 'mcp-name' ) );
				if ( '' === $header_name || $header_name !== $expected_name ) {
					return $this->rpc_error_array( $id, -32020, 'MCP name header does not match the request body.', array( 'header' => 'Mcp-Name', 'expected' => $expected_name, 'received' => $header_name ) );
				}
			}
		}
		return null;
	}

	private function process_rpc( $rpc ) {
		$id = isset( $rpc['id'] ) ? $rpc['id'] : null;
		$method = isset( $rpc['method'] ) ? (string) $rpc['method'] : '';
		$params = isset( $rpc['params'] ) && is_array( $rpc['params'] ) ? $rpc['params'] : array();
		$is_notification = ! array_key_exists( 'id', $rpc );

		if ( 'server/discover' === $method ) {
			return $this->rpc_result( $id, array(
				'resultType' => 'complete',
				'supportedVersions' => self::supported_protocol_versions(),
				'capabilities' => $this->server_capabilities( true ),
				'_meta' => array( 'io.modelcontextprotocol/serverInfo' => $this->server_info() ),
				'instructions' => 'Use read-first workflows. Preview draft-theme changes and request explicit approval before publishing.',
				'ttlMs' => 300000,
				'cacheScope' => 'private',
			) );
		}
		if ( 'initialize' === $method ) {
			if ( $this->modern_request ) {
				return $this->rpc_error_array( $id, -32601, 'Method not found: initialize' );
			}
			$requested = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
			$legacy = array_slice( self::supported_protocol_versions(), 1 );
			$negotiated = in_array( $requested, $legacy, true ) ? $requested : self::LEGACY_PROTOCOL;
			return $this->rpc_result( $id, array(
				'protocolVersion' => $negotiated,
				'capabilities' => $this->server_capabilities( false ),
				'serverInfo' => $this->server_info(),
				'instructions' => 'Use read-first workflows. Preview draft-theme changes and request explicit approval before publishing.',
			) );
		}
		if ( 'notifications/initialized' === $method || 0 === strpos( $method, 'notifications/' ) ) {
			return null;
		}
		if ( 'ping' === $method ) {
			if ( $this->modern_request ) {
				return $this->rpc_error_array( $id, -32601, 'Method not found: ping' );
			}
			return $this->rpc_result( $id, (object) array() );
		}
		if ( 'tools/list' === $method ) {
			$defs = class_exists( 'WPVDMCP_Parity' ) ? WPVDMCP_Parity::merge_definitions( WPVDMCP_Tools::definitions() ) : WPVDMCP_Tools::definitions();
			return $this->rpc_result( $id, $this->list_result( 'tools', $defs ) );
		}
		if ( 'tools/call' === $method ) {
			$name = isset( $params['name'] ) ? sanitize_key( $params['name'] ) : '';
			$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
			$result = class_exists( 'WPVDMCP_Parity' ) && WPVDMCP_Parity::handles( $name ) ? WPVDMCP_Parity::execute( $name, $args ) : WPVDMCP_Tools::execute( $name, $args );
			$this->log_call( $name, $args, ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
			if ( is_wp_error( $result ) ) {
				$data = $result->get_error_data();
				return $this->rpc_result( $id, array(
					'isError' => true,
					'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $data ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) ),
				) );
			}
			return $this->rpc_result( $id, array(
				'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ),
				'structuredContent' => $result,
			) );
		}
		if ( 'prompts/list' === $method ) {
			$prompts = array();
			foreach ( class_exists( 'WPVDMCP_Parity' ) ? WPVDMCP_Parity::skill_instructions() : WPVDMCP_Tools::skills() as $name => $instructions ) {
				$prompts[] = array( 'name' => $name, 'description' => 'WordPress workflow skill: ' . str_replace( '-', ' ', $name ), 'arguments' => array() );
			}
			return $this->rpc_result( $id, $this->list_result( 'prompts', $prompts ) );
		}
		if ( 'prompts/get' === $method ) {
			$name = isset( $params['name'] ) ? sanitize_key( $params['name'] ) : '';
			$skills = class_exists( 'WPVDMCP_Parity' ) ? WPVDMCP_Parity::skill_instructions() : WPVDMCP_Tools::skills();
			if ( ! isset( $skills[ $name ] ) ) {
				return $this->rpc_error_array( $id, -32602, 'Unknown prompt.' );
			}
			return $this->rpc_result( $id, array( 'description' => $name, 'messages' => array( array( 'role' => 'user', 'content' => array( 'type' => 'text', 'text' => $skills[ $name ] ) ) ) ) );
		}
		if ( 'resources/list' === $method ) {
			return $this->rpc_result( $id, $this->list_result( 'resources', array() ) );
		}
		if ( $is_notification ) {
			return null;
		}
		return $this->rpc_error_array( $id, -32601, 'Method not found: ' . $method );
	}

	private function server_info() {
		return array(
			'name' => 'wpvibe-direct-mcp',
			'version' => WPVDMCP_VERSION,
			'description' => 'Self-hosted MCP bridge for the WPVibe WordPress plugin.',
			'websiteUrl' => 'https://github.com/salman0butt/wpvibe-direct-mcp',
		);
	}

	private function server_capabilities( $modern ) {
		if ( $modern ) {
			return array( 'tools' => (object) array(), 'prompts' => (object) array(), 'resources' => (object) array() );
		}
		return array(
			'tools' => array( 'listChanged' => false ),
			'prompts' => array( 'listChanged' => false ),
			'resources' => array( 'subscribe' => false, 'listChanged' => false ),
		);
	}

	private function list_result( $key, $items ) {
		$result = array( $key => $items );
		if ( $this->modern_request ) {
			$result = array_merge( array( 'resultType' => 'complete', 'ttlMs' => 300000, 'cacheScope' => 'private' ), $result );
		}
		return $result;
	}

	private function authenticate( $request ) {
		$hash = (string) get_option( 'wpvdmcp_token_hash', '' );
		$user_id = (int) get_option( 'wpvdmcp_user_id', 0 );
		if ( ! $hash || ! $user_id ) {
			return new WP_Error( 'mcp_not_configured', 'Generate a Direct MCP access token in WPVibe → Direct MCP.', array( 'status' => 401 ) );
		}
		$token = '';
		$header = (string) $request->get_header( 'authorization' );
		if ( preg_match( '/^Bearer\s+(.+)$/i', trim( $header ), $match ) ) {
			$token = trim( $match[1] );
		}
		if ( ! $token ) {
			$token = trim( (string) $request->get_header( 'x-wpvibe-direct-token' ) );
		}
		// Read URL authentication strictly from the query string. Using get_param() can
		// be shadowed by a same-named JSON body parameter in some MCP clients.
		$query_params = $request->get_query_params();
		$query_token  = isset( $query_params['token'] ) ? trim( (string) $query_params['token'] ) : '';
		if ( ! $query_token && isset( $query_params['access_token'] ) ) {
			$query_token = trim( (string) $query_params['access_token'] );
		}

		// Query-string tokens are always accepted when present. This is required for
		// ChatGPT's "No authentication" custom MCP mode, which cannot attach a
		// custom Authorization header. Bearer and X-WPVibe-Direct-Token headers
		// remain preferred for clients that support them.
		if ( ! $token && $query_token ) {
			$token = $query_token;
		}

		if ( ! $token ) {
			return new WP_Error( 'missing_mcp_token', 'No MCP token was received. Send an Authorization: Bearer header or append ?token=YOUR_TOKEN to the MCP endpoint.', array( 'status' => 401 ) );
		}
		if ( ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			return new WP_Error( 'invalid_mcp_token', 'The supplied MCP token does not match the currently configured token. Regenerate it and update the MCP URL.', array( 'status' => 401 ) );
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'invalid_mcp_user', 'The MCP token owner no longer has administrator access.', array( 'status' => 403 ) );
		}
		wp_set_current_user( $user_id );
		$this->authenticated_user = $user_id;
		update_option( 'wpvdmcp_last_active', time(), false );
		return true;
	}

	private function log_call( $name, $args, $success, $message ) {
		$log = get_option( 'wpvdmcp_activity', array() );
		$summary = array();
		foreach ( array( 'path', 'command', 'post_id', 'id', 'target_type', 'theme_name', 'title', 'skill' ) as $key ) {
			if ( isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) ) {
				$summary[ $key ] = self::truncate( (string) $args[ $key ], 180 );
			}
		}
		array_unshift( $log, array(
			'time' => time(),
			'user_id' => $this->authenticated_user,
			'tool' => $name,
			'success' => (bool) $success,
			'summary' => $summary,
			'message' => self::truncate( (string) $message, 300 ),
		) );
		update_option( 'wpvdmcp_activity', array_slice( $log, 0, 50 ), false );
	}

	private function rpc_result( $id, $result ) {
		if ( $this->modern_request && is_array( $result ) ) {
			if ( ! isset( $result['_meta'] ) || ! is_array( $result['_meta'] ) ) {
				$result['_meta'] = array();
			}
			if ( ! isset( $result['_meta']['io.modelcontextprotocol/serverInfo'] ) ) {
				$result['_meta']['io.modelcontextprotocol/serverInfo'] = $this->server_info();
			}
		}
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
	}
	private function rpc_error_array( $id, $code, $message, $data = null ) {
		$error = array( 'code' => $code, 'message' => $message );
		if ( null !== $data ) { $error['data'] = $data; }
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => $error );
	}
	private function rpc_error( $id, $code, $message, $status ) {
		return new WP_REST_Response( $this->rpc_error_array( $id, $code, $message ), $status );
	}
	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, 0, $length ) : substr( (string) $value, 0, $length );
	}

}
