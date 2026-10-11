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
			'path' => self::strp( 'WordPress REST route beginning with /, for example /wp/v2/pages/123.' ),
			'params' => self::objp( 'Structured query parameters.' ),
			'body' => array( 'description' => 'JSON body object, or a JSON string for compatibility.', 'oneOf' => array( array( 'type' => 'object' ), array( 'type' => 'string' ) ) ),
			'fields' => self::arrayp( 'Optional selected response fields. Also forwarded as WordPress _fields on GET.', array( 'type' => 'string' ) ),
			'max_response_bytes' => self::intp( 'Maximum encoded response size. Default 262144, hard cap 1048576 bytes.' ),
			'approval_id' => self::strp( 'One-time Direct MCP approval ID returned by an earlier write call.' ),
		);
		$write = $common;
		$write['method']['enum'] = array( 'POST', 'PUT', 'PATCH', 'DELETE' );
		return array(
			array(
				'name' => 'rest_api',
				'description' => 'Call a registered WordPress REST API route internally as the token owner. GET runs immediately; writes require one-time approval unless the site owner enabled WPVibe Dangerously bypass approvals. Destination capability checks, credential-route blocking, WooCommerce replace-list guards and bounded responses remain enforced.',
				'inputSchema' => self::schema( $common, array( 'method', 'path' ) ),
			),
			array(
				'name' => 'rest_api_write',
				'description' => 'Perform an approval-gated WordPress REST mutation through the same hardened Direct MCP REST bridge. DELETE never forwards force=true. WooCommerce product/variation replacement lists must preserve existing entries unless wpvibe_replace=true is explicitly supplied.',
				'inputSchema' => self::schema( $write, array( 'method', 'path' ) ),
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

		$path = self::normalize_path( isset( $args['path'] ) ? (string) $args['path'] : '' );
		if ( '' === $path ) {
			return new WP_Error( 'invalid_path', 'A WordPress REST path is required.', array( 'status' => 400 ) );
		}
		if ( self::is_blocked_path( $path ) ) {
			return new WP_Error( 'blocked_route', 'This authentication-sensitive REST route is blocked by the direct MCP bridge.', array( 'status' => 403 ) );
		}

		$params = isset( $args['params'] ) && is_array( $args['params'] ) ? $args['params'] : array();
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

		$delegate = $args;
		$delegate['method'] = $method;
		$delegate['path'] = $path;
		$delegate['params'] = $params;
		$delegate['body'] = $body;
		unset( $delegate['approval_id'] );

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
		return 0 === strpos( $lower, '/wpvibe-direct/v1/' ) || false !== strpos( $lower, '/application-passwords' );
	}

	private static function schema( $props, $required = array() ) {
		$schema = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) { $schema['required'] = array_values( $required ); }
		return $schema;
	}
	private static function strp( $description ) { return array( 'type' => 'string', 'description' => $description ); }
	private static function intp( $description ) { return array( 'type' => 'integer', 'description' => $description ); }
	private static function objp( $description ) { return array( 'type' => 'object', 'description' => $description ); }
	private static function arrayp( $description, $items ) { return array( 'type' => 'array', 'description' => $description, 'items' => $items ); }
}
