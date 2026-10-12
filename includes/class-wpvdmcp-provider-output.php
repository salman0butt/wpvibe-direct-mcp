<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Normalizes data returned by user-owned provider callbacks/filters before it
 * crosses the model-visible MCP boundary.
 */
final class WPVDMCP_Provider_Output {
	const META_KEY = '_wpvdmcp_provider_output';
	const MAX_DEPTH = 8;
	const MAX_ITEMS = 200;
	const MAX_STRING_BYTES = 65536;
	const MAX_TOTAL_BYTES = 262144;
	const MAX_ERROR_CODE_BYTES = 256;
	const MAX_ERROR_MESSAGE_BYTES = 4096;
	const CONTENT_BUDGET_BYTES = 245760;

	public static function normalize( $value, $provider_name = '' ) {
		$state = array(
			'remaining'                 => self::CONTENT_BUDGET_BYTES,
			'redacted_fields'           => 0,
			'truncated'                 => false,
			'dropped_items'             => 0,
			'depth_limited'             => false,
			'object_normalized'         => false,
			'reserved_metadata_removed' => false,
		);

		if ( is_wp_error( $value ) ) {
			$code = self::bound_string( (string) $value->get_error_code(), self::MAX_ERROR_CODE_BYTES, $state, '[truncated provider error code]' );
			$message = self::bound_string( (string) $value->get_error_message(), self::MAX_ERROR_MESSAGE_BYTES, $state, '[truncated provider error message]' );
			$data = self::walk( $value->get_error_data(), $state, 0 );
			$data = self::attach_metadata( $data, $state, $provider_name );
			$data = self::enforce_serialized_bound( $data, $state, $provider_name );
			$data = self::enforce_error_envelope_bound( $code, $message, $data, $state, $provider_name );
			return new WP_Error( $code, $message, $data );
		}

		$normalized = self::walk( $value, $state, 0 );
		$normalized = self::attach_metadata( $normalized, $state, $provider_name );
		return self::enforce_serialized_bound( $normalized, $state, $provider_name );
	}

	private static function walk( $value, &$state, $depth ) {
		if ( is_object( $value ) ) {
			if ( $depth >= self::MAX_DEPTH ) {
				return self::depth_marker( $state );
			}
			$state['object_normalized'] = true;
			if ( $value instanceof JsonSerializable ) {
				try {
					$serialized = $value->jsonSerialize();
					if ( $serialized === $value ) {
						$value = get_object_vars( $value );
					} elseif ( is_object( $serialized ) ) {
						return self::walk( $serialized, $state, $depth + 1 );
					} else {
						$value = $serialized;
					}
				} catch ( Throwable $e ) {
					$state['truncated'] = true;
					return self::consume_marker( '[unsupported provider object]', $state );
				}
			} else {
				$value = get_object_vars( $value );
			}
		}

		if ( is_array( $value ) ) {
			if ( $depth >= self::MAX_DEPTH ) {
				return self::depth_marker( $state );
			}
			return self::walk_array( $value, $state, $depth );
		}

		if ( is_string( $value ) ) {
			return self::bound_string( $value, self::MAX_STRING_BYTES, $state, '[truncated by WPVibe Direct MCP]' );
		}

		if ( is_null( $value ) || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			self::consume_budget( strlen( wp_json_encode( $value ) ), $state );
			return $value;
		}

		$state['truncated'] = true;
		return self::consume_marker( '[unsupported provider output type]', $state );
	}

	private static function walk_array( $value, &$state, $depth ) {
		$out = array();
		$total = count( $value );
		$position = 0;

		foreach ( $value as $key => $item ) {
			if ( $position >= self::MAX_ITEMS || $state['remaining'] <= 0 ) {
				$state['truncated'] = true;
				$state['dropped_items'] += max( 0, $total - $position );
				break;
			}
			$position++;

			if ( is_string( $key ) && self::META_KEY === $key ) {
				$state['reserved_metadata_removed'] = true;
				continue;
			}
			if ( is_string( $key ) && self::is_sensitive_key( $key ) ) {
				$state['redacted_fields']++;
				continue;
			}

			$key_cost = is_string( $key ) ? strlen( $key ) : 8;
			if ( $key_cost > $state['remaining'] ) {
				$state['truncated'] = true;
				$state['dropped_items'] += max( 1, $total - $position + 1 );
				break;
			}
			self::consume_budget( $key_cost, $state );
			$out[ $key ] = self::walk( $item, $state, $depth + 1 );
		}

		return $out;
	}

	private static function is_sensitive_key( $key ) {
		$normalized = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1_$2', (string) $key );
		$normalized = preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', (string) $normalized );
		$normalized = strtolower( (string) $normalized );
		$normalized = trim( preg_replace( '/[^a-z0-9]+/', '_', $normalized ), '_' );
		$exact = array(
			'auth', 'authentication', 'authorization', 'proxy_authorization', 'bearer', 'cookie', 'set_cookie',
			'password', 'passwd', 'secret', 'api_key', 'apikey', 'access_token',
			'refresh_token', 'id_token', 'token', 'private_key', 'secret_key', 'consumer_key',
			'access_key', 'access_key_id', 'aws_access_key_id', 'aws_secret_access_key',
			'password_hash', 'credential', 'credentials', 'login_url', 'nonce',
		);
		if ( in_array( $normalized, $exact, true ) ) { return true; }
		foreach ( array( '_token', '_secret', '_password', '_api_key', '_private_key', '_consumer_key', '_access_key', '_access_key_id', '_nonce', '_cookie', '_credential' ) as $suffix ) {
			if ( strlen( $normalized ) > strlen( $suffix ) && substr( $normalized, -strlen( $suffix ) ) === $suffix ) { return true; }
		}
		$context_bases = array(
			'auth', 'authorization', 'bearer', 'cookie', 'password', 'passwd', 'secret',
			'api_key', 'access_token', 'refresh_token', 'id_token', 'private_key', 'secret_key',
			'consumer_key', 'access_key', 'access_key_id', 'credential', 'credentials', 'nonce',
		);
		foreach ( $context_bases as $base ) {
			foreach ( array( 'header', 'value', 'raw', 'plain', 'plaintext', 'encoded' ) as $context ) {
				$credential_context = $base . '_' . $context;
				if ( $normalized === $credential_context ) { return true; }
				$suffix = '_' . $credential_context;
				if ( strlen( $normalized ) > strlen( $suffix ) && substr( $normalized, -strlen( $suffix ) ) === $suffix ) { return true; }
			}
		}
		return false;
	}

	private static function bound_string( $value, $max_bytes, &$state, $marker ) {
		$value = self::json_safe_string( (string) $value, $state );
		$length = strlen( $value );
		$limit = min( (int) $max_bytes, max( 0, (int) $state['remaining'] ) );
		if ( $length <= $limit ) {
			self::consume_budget( $length, $state );
			return $value;
		}

		$state['truncated'] = true;
		if ( $limit <= 0 ) { return self::consume_marker( $marker, $state ); }
		$marker = (string) $marker;
		$marker_length = strlen( $marker );
		if ( $marker_length >= $limit ) {
			$out = substr( $marker, 0, $limit );
		} else {
			$out = substr( $value, 0, $limit - $marker_length ) . $marker;
		}
		self::consume_budget( strlen( $out ), $state );
		return $out;
	}

	private static function json_safe_string( $value, &$state ) {
		if ( '' === $value || 1 === @preg_match( '//u', $value ) ) { return $value; }
		$state['truncated'] = true;
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$clean = wp_check_invalid_utf8( $value, true );
			if ( is_string( $clean ) && ( '' === $clean || 1 === @preg_match( '//u', $clean ) ) ) { return $clean; }
		}
		$clean = preg_replace( '/[\x80-\xFF]/', '?', $value );
		return is_string( $clean ) ? $clean : '[invalid provider string encoding]';
	}

	private static function depth_marker( &$state ) {
		$state['truncated'] = true;
		$state['depth_limited'] = true;
		return self::consume_marker( '[truncated: provider output depth limit]', $state );
	}

	private static function consume_marker( $marker, &$state ) {
		$marker = (string) $marker;
		self::consume_budget( strlen( $marker ), $state );
		return $marker;
	}

	private static function consume_budget( $bytes, &$state ) {
		$state['remaining'] = max( 0, (int) $state['remaining'] - max( 0, (int) $bytes ) );
	}

	private static function attach_metadata( $value, $state, $provider_name ) {
		$changed = $state['redacted_fields'] > 0 || $state['truncated'] || $state['object_normalized'] || $state['reserved_metadata_removed'];
		if ( ! $changed ) { return $value; }

		$meta = self::metadata( $state, $provider_name );

		if ( is_array( $value ) && ! self::is_list( $value ) ) {
			$value[ self::META_KEY ] = $meta;
			return $value;
		}

		return array( 'data' => $value, self::META_KEY => $meta );
	}

	private static function metadata( $state, $provider_name ) {
		$meta = array( 'provider' => (string) $provider_name );
		if ( $state['redacted_fields'] > 0 ) { $meta['redacted_fields'] = (int) $state['redacted_fields']; }
		if ( $state['truncated'] ) { $meta['truncated'] = true; }
		if ( $state['dropped_items'] > 0 ) { $meta['dropped_items'] = (int) $state['dropped_items']; }
		if ( $state['depth_limited'] ) { $meta['depth_limited'] = true; }
		if ( $state['object_normalized'] ) { $meta['object_normalized'] = true; }
		if ( $state['reserved_metadata_removed'] ) { $meta['reserved_metadata_removed'] = true; }
		if ( $state['truncated'] ) {
			$meta['limits'] = array(
				'max_depth' => self::MAX_DEPTH,
				'max_items' => self::MAX_ITEMS,
				'max_string_bytes' => self::MAX_STRING_BYTES,
				'max_total_bytes' => self::MAX_TOTAL_BYTES,
			);
		}
		return $meta;
	}

	private static function enforce_serialized_bound( $value, $state, $provider_name ) {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false !== $encoded && strlen( $encoded ) <= self::MAX_TOTAL_BYTES ) {
			return $value;
		}

		$state['truncated'] = true;
		$meta = self::metadata( $state, $provider_name );
		$meta['serialized_limit'] = true;
		if ( false === $encoded ) { $meta['encoding_failure'] = true; }
		return array(
			'data' => '[provider output exceeded serialized size limit]',
			self::META_KEY => $meta,
		);
	}

	private static function enforce_error_envelope_bound( $code, $message, $data, $state, $provider_name ) {
		$envelope = array( 'code' => $code, 'message' => $message, 'data' => $data );
		// Match WPVDMCP_Server's model-visible WP_Error encoding exactly. Omitting
		// JSON_UNESCAPED_UNICODE is intentional: escaped Unicode can expand by ~3x.
		$encoded = wp_json_encode( $envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false !== $encoded && strlen( $encoded ) <= self::MAX_TOTAL_BYTES ) { return $data; }

		$state['truncated'] = true;
		$meta = self::metadata( $state, $provider_name );
		$meta['serialized_limit'] = true;
		$meta['error_envelope_limit'] = true;
		if ( false === $encoded ) { $meta['encoding_failure'] = true; }
		return array(
			'data' => '[provider error output exceeded serialized size limit]',
			self::META_KEY => $meta,
		);
	}

	private static function is_list( $value ) {
		if ( ! is_array( $value ) ) { return false; }
		$expected = 0;
		foreach ( $value as $key => $_item ) {
			if ( $key !== $expected ) { return false; }
			$expected++;
		}
		return true;
	}
}
