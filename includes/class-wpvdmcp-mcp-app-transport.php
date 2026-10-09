<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Adds MCP Apps resources/metadata at the REST response boundary without changing the core MCP server. */
final class WPVDMCP_MCP_App_Transport {
	public static function bootstrap() {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'filter_response' ), 20, 3 );
	}

	public static function filter_response( $response, $server, $request ) {
		if ( ! is_object( $request ) ) { return $response; }
		$route = method_exists( $request, 'get_route' ) ? $request->get_route() : ( method_exists( $request, 'get_param' ) ? $request->get_param( '_route' ) : '' );
		if ( '/wpvibe-direct/v1/mcp' !== $route ) { return $response; }
		if ( ! $response instanceof WP_REST_Response ) { return $response; }
		$rpc = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		if ( ! is_array( $rpc ) || isset( $rpc[0] ) ) { return $response; }
		$method = isset( $rpc['method'] ) ? (string) $rpc['method'] : '';
		$id = array_key_exists( 'id', $rpc ) ? $rpc['id'] : null;
		$data = $response->get_data();
		if ( ! is_array( $data ) ) { return $response; }

		if ( 'resources/list' === $method ) {
			if ( isset( $data['result'] ) && is_array( $data['result'] ) ) {
				$data['result']['resources'] = WPVDMCP_MCP_Apps::resources();
			} else {
				$data = array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'resources' => WPVDMCP_MCP_Apps::resources() ) );
			}
		}
		if ( 'resources/read' === $method ) {
			$params = isset( $rpc['params'] ) && is_array( $rpc['params'] ) ? $rpc['params'] : array();
			$result = WPVDMCP_MCP_Apps::read_resource( isset( $params['uri'] ) ? (string) $params['uri'] : '' );
			if ( is_wp_error( $result ) ) {
				$data = array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => -32602, 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ) );
			} else {
				$data = array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
			}
		}
		if ( 'tools/call' === $method && isset( $data['result']['structuredContent']['__mcp_meta'] ) && is_array( $data['result']['structuredContent']['__mcp_meta'] ) ) {
			$data['result']['_meta'] = $data['result']['structuredContent']['__mcp_meta'];
			unset( $data['result']['structuredContent']['__mcp_meta'] );
			if ( isset( $data['result']['content'][0]['text'] ) ) {
				$data['result']['content'][0]['text'] = wp_json_encode( $data['result']['structuredContent'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
		}
		if ( in_array( $method, array( 'server/discover', 'initialize' ), true ) && isset( $data['result']['capabilities'] ) && is_array( $data['result']['capabilities'] ) ) {
			$data['result']['capabilities']['extensions'] = array( WPVDMCP_MCP_Apps::EXTENSION_ID => array( 'mimeTypes' => array( WPVDMCP_MCP_Apps::MIME_TYPE ) ) );
		}
		$response->set_data( $data );
		return $response;
	}
}
