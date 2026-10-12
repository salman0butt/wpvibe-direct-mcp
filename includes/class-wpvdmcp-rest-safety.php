<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Safety/parity wrapper for the public generic REST tools.
 *
 * Read requests continue to use the existing WPVDMCP_Tools dispatcher directly.
 * Mutations are bound to a one-time Direct MCP approval unless the site owner has
 * enabled WPVibe's Dangerously bypass approvals option. After approval this class
 * delegates to WPVDMCP_Tools so capability checks, credential-route blocking and
 * response-size limits stay centralized in the existing REST bridge.
 */
final class WPVDMCP_REST_Safety {
	private static $wc_replace_fields = array( 'attributes', 'categories', 'tags', 'images' );

	public static function definitions() {
		$common = array(
			'method' => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), 'description' => 'HTTP method.' ),
			'path' => self::strp( 'Direct MCP REST route beginning with /, for example /wp/v2/pages/123.' ),
			'route' => self::strp( 'WPVibe-compatible REST route. May include a query string; /wp-json is stripped when present.' ),
			'params' => self::objp( 'Direct MCP structured query parameters.' ),
			'query' => self::objp( 'WPVibe-compatible structured query parameters. Explicit query/params override duplicate keys embedded in route.' ),
			'body' => array( 'description' => 'JSON body object, or a JSON string for compatibility.', 'oneOf' => array( array( 'type' => 'object' ), array( 'type' => 'string' ) ) ),
			'fields' => array(
				'description' => 'Response fields: Direct MCP string array, WPVibe comma-separated string, or "all" for no field filtering.',
				'oneOf' => array(
					array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					array( 'type' => 'string' ),
				),
			),
			'max_response_bytes' => self::intp( 'Direct MCP maximum encoded response size. Default 262144, hard cap 1048576 bytes.' ),
			'max_length' => self::intp( 'WPVibe-compatible response-size alias. Mapped to the Direct MCP response byte cap when max_response_bytes is omitted.' ),
			'approval_id' => self::strp( 'One-time Direct MCP approval ID returned by an earlier write call.' ),
		);
		$write = $common;
		$write['method']['enum'] = array( 'POST', 'PUT', 'PATCH', 'DELETE' );
		return array(
			array(
				'name' => 'rest_api',
				'description' => 'Call a registered WordPress REST API route internally as the token owner. Accepts both WPVibe route/query option names and Direct MCP path/params names. GET runs immediately; writes require one-time approval unless the site owner enabled WPVibe Dangerously bypass approvals. Destination capability checks, credential-route blocking, WooCommerce replace-list guards and bounded responses remain enforced.',
				'inputSchema' => self::rest_schema( $common ),
			),
			array(
				'name' => 'rest_api_write',
				'description' => 'Perform an approval-gated WordPress REST mutation through the same hardened Direct MCP REST bridge. Accepts both WPVibe and Direct option names. DELETE never forwards force=true. WooCommerce product/variation replacement lists must preserve existing entries unless wpvibe_replace=true is explicitly supplied.',
				'inputSchema' => self::rest_schema( $write ),
			),
		);
	}

	public static function handles( $name ) {
		return in_array( $name, array( 'rest_api', 'rest_api_write' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		$method = isset( $args['method'] ) ? strtoupper( sanitize_text_field( $args['method'] ) ) : 'GET';
		$allowed = 'rest_api_write' === $name ? array( 'POST', 'PUT', 'PATCH', 'DELETE' ) : array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		if ( ! in_array( $method, $allowed, true ) ) {
			return new WP_Error( 'invalid_method', 'rest_api_write' === $name ? 'rest_api_write only accepts POST, PUT, PATCH, or DELETE.' : 'Unsupported REST method.', array( 'status' => 400 ) );
		}

		$raw_path = '';
		if ( isset( $args['path'] ) && '' !== trim( (string) $args['path'] ) ) {
			$raw_path = (string) $args['path'];
		} elseif ( isset( $args['route'] ) ) {
			$raw_path = (string) $args['route'];
		}
		$path = self::normalize_path( $raw_path );
		if ( '' === $path ) {
			return new WP_Error( 'invalid_path', 'A WordPress REST path/route is required.', array( 'status' => 400 ) );
		}
		if ( self::is_blocked_path( $path ) ) {
			return new WP_Error( 'blocked_route', 'This authentication- or worker-sensitive REST route is blocked by the direct MCP bridge.', array( 'status' => 403 ) );
		}

		$embedded = self::embedded_query( $raw_path );
		$query = isset( $args['query'] ) && is_array( $args['query'] ) ? $args['query'] : array();
		$direct_params = isset( $args['params'] ) && is_array( $args['params'] ) ? $args['params'] : array();
		$params = array_merge( $embedded, $query, $direct_params );

		$body = isset( $args['body'] ) ? $args['body'] : array();
		if ( is_string( $body ) && '' !== trim( $body ) ) {
			$decoded = json_decode( $body, true );
			if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'invalid_json_body', 'body must be an object or a valid JSON object string.', array( 'status' => 400 ) );
			}
			$body = $decoded;
		}
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( 'DELETE' === $method ) {
			// Match WPVibe's public REST safety contract: never let generic REST
			// turn a trash-capable delete into an irreversible force delete.
			unset( $params['force'], $body['force'] );
		}

		if ( 'GET' !== $method && 'DELETE' !== $method ) {
			$guarded = self::guard_woocommerce_replacements( $path, $body );
			if ( is_wp_error( $guarded ) ) { return $guarded; }
			$body = $guarded;
		}

		$fields = self::normalize_fields( isset( $args['fields'] ) ? $args['fields'] : null );
		$delegate = $args;
		$delegate['method'] = $method;
		$delegate['path'] = $path;
		$delegate['params'] = $params;
		$delegate['body'] = $body;
		if ( $fields ) {
			$delegate['fields'] = $fields;
		} else {
			unset( $delegate['fields'] );
		}
		if ( ! isset( $delegate['max_response_bytes'] ) && isset( $args['max_length'] ) ) {
			$delegate['max_response_bytes'] = absint( $args['max_length'] );
		}
		unset( $delegate['approval_id'], $delegate['route'], $delegate['query'], $delegate['max_length'] );

		if ( 'GET' === $method ) {
			return WPVDMCP_Tools::execute( 'rest_api', $delegate );
		}

		$approval_payload = array(
			'method' => $method,
			'path' => $path,
			'params' => $params,
			'body' => $body,
		);
		if ( ! WPVDMCP_Approvals::bypass_enabled() ) {
			$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			if ( '' === $approval_id ) {
				$approval = WPVDMCP_Approvals::request( 'rest_api_write', $approval_payload, $method . ' ' . $path );
				if ( is_wp_error( $approval ) ) { return $approval; }
				return array_merge( $approval, array( 'status' => 'approval_required', 'method' => $method, 'path' => $path ) );
			}
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'rest_api_write', $approval_payload );
			if ( is_wp_error( $consumed ) ) { return $consumed; }
		}

		return WPVDMCP_Tools::execute( 'rest_api', $delegate );
	}

	private static function guard_woocommerce_replacements( $path, $body ) {
		if ( 0 !== strpos( strtolower( $path ), '/wc/v3/products' ) || ! is_array( $body ) ) {
			return $body;
		}

		$replace_all = ! empty( $body['wpvibe_replace'] );
		unset( $body['wpvibe_replace'] );

		if ( preg_match( '#^/wc/v3/products/(\d+)/?$#i', $path, $m ) ) {
			if ( $replace_all ) { return $body; }
			return self::guard_wc_item( $path, $body, 'product #' . (int) $m[1] );
		}

		if ( preg_match( '#^/wc/v3/products/(\d+)/variations/(\d+)/?$#i', $path, $m ) ) {
			if ( $replace_all ) { return $body; }
			return self::guard_wc_item( $path, $body, 'variation #' . (int) $m[2] );
		}

		if ( preg_match( '#^/wc/v3/products/batch/?$#i', $path ) && isset( $body['update'] ) && is_array( $body['update'] ) ) {
			foreach ( $body['update'] as $index => $item ) {
				if ( ! is_array( $item ) ) { continue; }
				$item_replace = $replace_all || ! empty( $item['wpvibe_replace'] );
				unset( $item['wpvibe_replace'] );
				$body['update'][ $index ] = $item;
				$id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
				if ( ! $id || $item_replace || ! self::touches_wc_replace_field( $item ) ) { continue; }
				$checked = self::guard_wc_item( '/wc/v3/products/' . $id, $item, 'product #' . $id . ' in batch update' );
				if ( is_wp_error( $checked ) ) { return $checked; }
				$body['update'][ $index ] = $checked;
			}
			return $body;
		}

		if ( preg_match( '#^/wc/v3/products/(\d+)/variations/batch/?$#i', $path, $m ) && isset( $body['update'] ) && is_array( $body['update'] ) ) {
			$product_id = (int) $m[1];
			foreach ( $body['update'] as $index => $item ) {
				if ( ! is_array( $item ) ) { continue; }
				$item_replace = $replace_all || ! empty( $item['wpvibe_replace'] );
				unset( $item['wpvibe_replace'] );
				$body['update'][ $index ] = $item;
				$id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
				if ( ! $id || $item_replace || ! self::touches_wc_replace_field( $item ) ) { continue; }
				$resource = '/wc/v3/products/' . $product_id . '/variations/' . $id;
				$checked = self::guard_wc_item( $resource, $item, 'variation #' . $id . ' in batch update' );
				if ( is_wp_error( $checked ) ) { return $checked; }
				$body['update'][ $index ] = $checked;
			}
		}
		return $body;
	}

	private static function guard_wc_item( $resource_path, $incoming, $label ) {
		if ( ! self::touches_wc_replace_field( $incoming ) ) { return $incoming; }
		$current = WPVDMCP_Tools::execute( 'rest_api', array( 'method' => 'GET', 'path' => $resource_path ) );
		if ( is_wp_error( $current ) ) { return $current; }
		$existing = isset( $current['data'] ) && is_array( $current['data'] ) ? $current['data'] : null;
		if ( ! is_array( $existing ) ) {
			return new WP_Error( 'woocommerce_replace_preflight_failed', 'Could not read the current WooCommerce resource before a replace-list update.', array( 'status' => 502, 'path' => $resource_path ) );
		}
		foreach ( self::$wc_replace_fields as $field ) {
			if ( ! array_key_exists( $field, $incoming ) || ! is_array( $incoming[ $field ] ) || empty( $existing[ $field ] ) || ! is_array( $existing[ $field ] ) ) { continue; }
			$missing = self::missing_wc_identities( $field, $existing[ $field ], $incoming[ $field ] );
			if ( $missing ) {
				return new WP_Error(
					'woocommerce_replace_guard',
					'WooCommerce replaces the complete ' . $field . ' list on update. The request for ' . $label . ' would drop existing entries. Read the resource, merge the complete list, or explicitly set wpvibe_replace=true if replacement is intentional.',
					array( 'status' => 409, 'path' => $resource_path, 'field' => $field, 'missing' => $missing )
				);
			}
		}
		return $incoming;
	}

	private static function touches_wc_replace_field( $item ) {
		foreach ( self::$wc_replace_fields as $field ) {
			if ( array_key_exists( $field, $item ) ) { return true; }
		}
		return false;
	}

	private static function missing_wc_identities( $field, $existing, $incoming ) {
		$next = array();
		foreach ( $incoming as $item ) { $next[ self::wc_identity( $field, $item ) ] = true; }
		$missing = array();
		foreach ( $existing as $item ) {
			$id = self::wc_identity( $field, $item );
			if ( ! isset( $next[ $id ] ) ) { $missing[] = $id; }
		}
		return array_values( array_unique( $missing ) );
	}

	private static function wc_identity( $field, $item ) {
		if ( ! is_array( $item ) ) { return 'value:' . md5( wp_json_encode( $item ) ); }
		if ( isset( $item['id'] ) && (int) $item['id'] > 0 ) { return 'id:' . (int) $item['id']; }
		if ( 'images' === $field && ! empty( $item['src'] ) ) { return 'src:' . strtolower( trim( (string) $item['src'] ) ); }
		if ( ! empty( $item['slug'] ) ) { return 'slug:' . strtolower( trim( (string) $item['slug'] ) ); }
		if ( ! empty( $item['name'] ) ) { return 'name:' . strtolower( trim( (string) $item['name'] ) ); }
		return 'value:' . md5( wp_json_encode( self::ksort_recursive( $item ) ) );
	}

	private static function ksort_recursive( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) { ksort( $value ); }
		foreach ( $value as $key => $item ) { $value[ $key ] = self::ksort_recursive( $item ); }
		return $value;
	}

	private static function embedded_query( $raw_path ) {
		$query = wp_parse_url( (string) $raw_path, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) { return array(); }
		$out = array();
		parse_str( $query, $out );
		return is_array( $out ) ? $out : array();
	}

	private static function normalize_fields( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value || 'all' === strtolower( $value ) ) { return array(); }
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) { return array(); }
		$out = array();
		foreach ( $value as $field ) {
			$field = sanitize_key( trim( (string) $field ) );
			if ( '' !== $field ) { $out[] = $field; }
		}
		return array_values( array_unique( $out ) );
	}

	private static function normalize_path( $path ) {
		$path = trim( (string) $path );
		if ( '' === $path ) { return ''; }
		$parsed = wp_parse_url( $path, PHP_URL_PATH );
		$path = '/' . ltrim( false !== $parsed && null !== $parsed ? $parsed : $path, '/' );
		if ( 0 === strpos( $path, '/wp-json/' ) ) {
			$path = substr( $path, 8 );
		}
		return '/' . ltrim( $path, '/' );
	}

	private static function is_blocked_path( $path ) {
		$lower = strtolower( $path );
		$control_plane = array(
			'/wpvibe/v1/authorize',
			'/wpvibe/v1/authorize/preflight',
			'/wpvibe/v1/connection-status',
			'/wpvibe/v1/connection-check-challenge',
			'/wpvibe/v1/op-proof/check',
			'/wpvibe/v1/cli/run-approved',
			'/wpvibe/v1/code-snippet',
			'/wpvibe/v1/builder-login',
			'/wpvibe/v1/detached/run',
			'/wpvibe/v1/audit-log/record',
			'/wpvibe/v1/classic-theme-safety',
			'/wpvibe/v1/self-update/health',
			'/wpvibe/v1/self-update/run',
		);
		return 0 === strpos( $lower, '/wpvibe-direct/v1/' )
			|| false !== strpos( $lower, '/application-passwords' )
			|| in_array( rtrim( $lower, '/' ), $control_plane, true );
	}

	private static function rest_schema( $props ) {
		$schema = self::schema( $props, array( 'method' ) );
		$schema['anyOf'] = array(
			array( 'required' => array( 'path' ) ),
			array( 'required' => array( 'route' ) ),
		);
		return $schema;
	}

	private static function schema( $props, $required = array() ) {
		$schema = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) { $schema['required'] = array_values( $required ); }
		return $schema;
	}
	private static function strp( $description ) { return array( 'type' => 'string', 'description' => $description ); }
	private static function intp( $description ) { return array( 'type' => 'integer', 'description' => $description ); }
	private static function objp( $description ) { return array( 'type' => 'object', 'description' => $description ); }
}