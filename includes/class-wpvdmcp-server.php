<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPVDMCP_Server {
	private static $instance;
	private $authenticated_user = 0;

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
		$response->header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version, MCP-Session-Id, X-WPVibe-Direct-Token' );
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
		if ( isset( $payload[0] ) ) {
			$responses = array();
			foreach ( $payload as $item ) {
				$result = $this->process_rpc( $item );
				if ( null !== $result ) {
					$responses[] = $result;
				}
			}
			return $responses ? rest_ensure_response( $responses ) : new WP_REST_Response( null, 202 );
		}
		$result = $this->process_rpc( $payload );
		return null === $result ? new WP_REST_Response( null, 202 ) : rest_ensure_response( $result );
	}

	private function process_rpc( $rpc ) {
		$id = isset( $rpc['id'] ) ? $rpc['id'] : null;
		$method = isset( $rpc['method'] ) ? (string) $rpc['method'] : '';
		$params = isset( $rpc['params'] ) && is_array( $rpc['params'] ) ? $rpc['params'] : array();
		$is_notification = ! array_key_exists( 'id', $rpc );

		if ( 'initialize' === $method ) {
			return $this->rpc_result( $id, array(
				'protocolVersion' => isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '2025-03-26',
				'capabilities' => array(
					'tools' => array( 'listChanged' => false ),
					'prompts' => array( 'listChanged' => false ),
					'resources' => array( 'subscribe' => false, 'listChanged' => false ),
				),
				'serverInfo' => array( 'name' => 'wpvibe-direct-mcp', 'version' => WPVDMCP_VERSION ),
				'instructions' => 'Use read-first workflows. Preview draft-theme changes and request explicit approval before publishing.',
			) );
		}
		if ( 'notifications/initialized' === $method || 0 === strpos( $method, 'notifications/' ) ) {
			return null;
		}
		if ( 'ping' === $method ) {
			return $this->rpc_result( $id, (object) array() );
		}
		if ( 'tools/list' === $method ) {
			return $this->rpc_result( $id, array( 'tools' => WPVDMCP_Tools::definitions() ) );
		}
		if ( 'tools/call' === $method ) {
			$name = isset( $params['name'] ) ? sanitize_key( $params['name'] ) : '';
			$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
			$result = WPVDMCP_Tools::execute( $name, $args );
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
			foreach ( WPVDMCP_Tools::skills() as $name => $instructions ) {
				$prompts[] = array( 'name' => $name, 'description' => 'WordPress workflow skill: ' . str_replace( '-', ' ', $name ), 'arguments' => array() );
			}
			return $this->rpc_result( $id, array( 'prompts' => $prompts ) );
		}
		if ( 'prompts/get' === $method ) {
			$name = isset( $params['name'] ) ? sanitize_key( $params['name'] ) : '';
			$skills = WPVDMCP_Tools::skills();
			if ( ! isset( $skills[ $name ] ) ) {
				return $this->rpc_error_array( $id, -32602, 'Unknown prompt.' );
			}
			return $this->rpc_result( $id, array( 'description' => $name, 'messages' => array( array( 'role' => 'user', 'content' => array( 'type' => 'text', 'text' => $skills[ $name ] ) ) ) ) );
		}
		if ( 'resources/list' === $method ) {
			return $this->rpc_result( $id, array( 'resources' => array() ) );
		}
		if ( $is_notification ) {
			return null;
		}
		return $this->rpc_error_array( $id, -32601, 'Method not found: ' . $method );
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
		$query_params = $request->get_query_params();
		$query_token  = isset( $query_params['token'] ) ? trim( (string) $query_params['token'] ) : '';
		if ( ! $query_token && isset( $query_params['access_token'] ) ) {
			$query_token = trim( (string) $query_params['access_token'] );
		}
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
				$summary[ $key ] = mb_substr( (string) $args[ $key ], 0, 180 );
			}
		}
		array_unshift( $log, array(
			'time' => time(),
			'user_id' => $this->authenticated_user,
			'tool' => $name,
			'success' => (bool) $success,
			'summary' => $summary,
			'message' => mb_substr( (string) $message, 0, 300 ),
		) );
		update_option( 'wpvdmcp_activity', array_slice( $log, 0, 50 ), false );
	}

	private function rpc_result( $id, $result ) {
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
}
