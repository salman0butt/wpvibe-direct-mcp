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
		if ( ! is_array( $rpc ) ) { return $response; }
		$data = $response->get_data();
		if ( ! is_array( $data ) ) { return $response; }
		if ( self::is_batch_payload( $rpc ) ) {
			foreach ( $data as $index => $item ) {
				if ( ! is_array( $item ) ) { continue; }
				$item = self::move_app_meta( $item );
				$matched = self::batch_request_for_response( $rpc, $item );
				$data[ $index ] = null === $matched ? $item : self::filter_data( $item, $matched, $request );
			}
			$response->set_data( $data );
			return $response;
		}
		$response->set_data( self::filter_data( $data, $rpc, $request ) );
		return $response;
	}

	private static function is_batch_payload( $rpc ) {
		if ( ! is_array( $rpc ) || empty( $rpc ) ) {
			return false;
		}
		return array_keys( $rpc ) === range( 0, count( $rpc ) - 1 );
	}

	private static function batch_request_for_response( $batch, $response_item ) {
		if ( ! array_key_exists( 'id', $response_item ) ) { return null; }
		foreach ( (array) $batch as $rpc ) {
			if ( is_array( $rpc ) && array_key_exists( 'id', $rpc ) && $rpc['id'] === $response_item['id'] ) {
				return $rpc;
			}
		}
		return null;
	}

	private static function filter_data( $data, $rpc, $request ) {
		$data = self::move_app_meta( $data );
		$method = isset( $rpc['method'] ) ? (string) $rpc['method'] : '';
		$id = array_key_exists( 'id', $rpc ) ? $rpc['id'] : null;

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
				// MCP 2026-07-28 resource reads require cache hints and a
				// result discriminator. Keep older clients' wire format unchanged.
				$protocol = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
				$meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
				if ( isset( $meta['io.modelcontextprotocol/protocolVersion'] ) ) {
					$protocol = (string) $meta['io.modelcontextprotocol/protocolVersion'];
				}
				if ( WPVDMCP_Server::MODERN_PROTOCOL === $protocol ) {
					$result = array_merge( array(
						'resultType' => 'complete',
						'ttlMs' => 0,
						'cacheScope' => 'private',
					), $result );
				}
				$data = array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
			}
		}
		if ( in_array( $method, array( 'server/discover', 'initialize' ), true ) && isset( $data['result']['capabilities'] ) && is_array( $data['result']['capabilities'] ) ) {
			$data['result']['capabilities']['extensions'] = array( WPVDMCP_MCP_Apps::EXTENSION_ID => array( 'mimeTypes' => array( WPVDMCP_MCP_Apps::MIME_TYPE ) ) );
		}
		return $data;
	}

	private static function move_app_meta( $data ) {
		if ( ! isset( $data['result'] ) || ! is_array( $data['result'] ) || ! isset( $data['result']['structuredContent']['__mcp_meta'] ) || ! is_array( $data['result']['structuredContent']['__mcp_meta'] ) ) {
			return $data;
		}
		$current = isset( $data['result']['_meta'] ) && is_array( $data['result']['_meta'] ) ? $data['result']['_meta'] : array();
		$data['result']['_meta'] = array_merge( $current, $data['result']['structuredContent']['__mcp_meta'] );
		unset( $data['result']['structuredContent']['__mcp_meta'] );
		if ( isset( $data['result']['content'][0]['text'] ) ) {
			$data['result']['content'][0]['text'] = wp_json_encode( $data['result']['structuredContent'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		return $data;
	}
}
