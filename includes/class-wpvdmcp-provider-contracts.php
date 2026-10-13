<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Public-contract compatibility for capabilities backed by user-owned/hosted
 * providers. This class normalizes WPVibe's current public argument shapes but
 * never supplies private WPVibe credentials or fabricates hosted state.
 */
final class WPVDMCP_Provider_Contracts {
	public static function definitions() {
		return array(
			self::tool( 'search_images', 'Search stock images through a configured user-owned provider. Accepts WPVibe count/orientation and the existing Direct MCP per_page alias.', array(
				'query' => self::strp( 'Search query.', true ),
				'count' => self::intp( 'Number of images, 1-10.' ),
				'per_page' => self::intp( 'Direct MCP alias for image count, 1-10.' ),
				'orientation' => self::enump( array( 'landscape', 'portrait', 'squarish' ), 'Optional image orientation.' ),
			) ),
			self::tool( 'audit_page', 'Run Lighthouse/PageSpeed-style analysis through a configured user-owned provider. Supports the current WPVibe page_url/categories/refresh contract and the Direct MCP url alias.', array(
				'url' => self::strp( 'Direct MCP public URL alias.' ),
				'page_url' => self::strp( 'Specific public page URL. Defaults to this WordPress site root.' ),
				'strategy' => self::enump( array( 'mobile', 'desktop' ), 'Device profile.' ),
				'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'performance', 'accessibility', 'best-practices', 'seo' ) ), 'description' => 'Lighthouse categories.' ),
				'refresh' => array( 'type' => 'boolean', 'description' => 'Bypass provider cache when supported.' ),
			) ),
			self::tool( 'get_page_html', 'Retrieve rendered page HTML. A configured JavaScript browser provider supports URL, selector and max_length; same-site URLs safely fall back to WPVibe server-rendered HTML.', array(
				'path' => self::strp( 'Direct MCP site-relative path.' ),
				'url' => self::strp( 'Specific URL to render.' ),
				'selector' => self::strp( 'Optional CSS selector for browser providers.' ),
				'max_length' => self::intp( 'Maximum returned HTML characters.' ),
			) ),
			self::tool( 'connect_site', 'Connect a site through a configured user-owned hosted-account provider. Direct MCP never fabricates WPVibe cloud account state.', array(
				'site_url' => self::strp( 'Site URL.', true ),
				'name' => self::strp( 'Optional site label.' ),
				'auth' => self::strp( 'Optional provider-defined authentication material; only pass credentials the user explicitly supplied.' ),
			) ),
			self::fleet_tool(),
		);
	}

	public static function handles( $name ) {
		return in_array( $name, array( 'search_images', 'audit_page', 'get_page_html', 'connect_site', 'start_fleet_job' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		if ( 'search_images' === $name ) { return self::search_images( $args ); }
		if ( 'audit_page' === $name ) { return self::audit_page( $args ); }
		if ( 'get_page_html' === $name ) { return self::get_page_html( $args ); }
		if ( 'connect_site' === $name ) {
			if ( empty( $args['site_url'] ) ) { return new WP_Error( 'invalid_site_url', 'site_url is required.', array( 'status' => 400 ) ); }
			return WPVDMCP_Reference_Parity::execute( 'connect_site', $args );
		}
		if ( 'start_fleet_job' === $name ) {
			if ( empty( $args['plan'] ) && ( empty( $args['sites'] ) || empty( $args['steps'] ) ) ) {
				return new WP_Error( 'invalid_fleet_plan', 'Provide legacy plan or current sites + steps.', array( 'status' => 400 ) );
			}
			return WPVDMCP_Reference_Parity::execute( 'start_fleet_job', $args );
		}
		return new WP_Error( 'unknown_provider_contract_tool', 'Unknown provider contract tool.', array( 'status' => 404 ) );
	}

	private static function search_images( $args ) {
		$query = trim( (string) ( $args['query'] ?? '' ) );
		if ( '' === $query ) { return new WP_Error( 'invalid_image_query', 'query is required.', array( 'status' => 400 ) ); }
		$count = isset( $args['count'] ) ? absint( $args['count'] ) : ( isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 5 );
		$count = max( 1, min( 10, $count ) );
		$normalized = array( 'query' => $query, 'count' => $count, 'per_page' => $count );
		if ( ! empty( $args['orientation'] ) ) {
			$orientation = sanitize_key( $args['orientation'] );
			if ( ! in_array( $orientation, array( 'landscape', 'portrait', 'squarish' ), true ) ) {
				return new WP_Error( 'invalid_orientation', 'orientation must be landscape, portrait, or squarish.', array( 'status' => 400 ) );
			}
			$normalized['orientation'] = $orientation;
		}
		return WPVDMCP_Parity_Insights::execute( 'search_images', $normalized );
	}

	private static function audit_page( $args ) {
		$url = trim( (string) ( $args['page_url'] ?? ( $args['url'] ?? '' ) ) );
		if ( '' === $url ) { $url = self::site_root(); }
		if ( '' === $url ) { return new WP_Error( 'invalid_audit_url', 'page_url/url is required when the site root cannot be resolved.', array( 'status' => 400 ) ); }
		$normalized = array( 'url' => $url, 'page_url' => $url );
		if ( isset( $args['strategy'] ) && '' !== (string) $args['strategy'] ) {
			$strategy = sanitize_key( $args['strategy'] );
			if ( ! in_array( $strategy, array( 'mobile', 'desktop' ), true ) ) { return new WP_Error( 'invalid_audit_strategy', 'strategy must be mobile or desktop.', array( 'status' => 400 ) ); }
			$normalized['strategy'] = $strategy;
		}
		if ( isset( $args['categories'] ) && is_array( $args['categories'] ) ) {
			$allowed = array( 'performance', 'accessibility', 'best-practices', 'seo' );
			$categories = array_values( array_unique( array_map( 'sanitize_key', $args['categories'] ) ) );
			foreach ( $categories as $category ) { if ( ! in_array( $category, $allowed, true ) ) { return new WP_Error( 'invalid_audit_category', 'Unsupported Lighthouse category.', array( 'status' => 400, 'category' => $category ) ); } }
			$normalized['categories'] = $categories;
		}
		if ( array_key_exists( 'refresh', $args ) ) { $normalized['refresh'] = (bool) $args['refresh']; }
		return WPVDMCP_Parity_Insights::execute( 'page_audit', $normalized );
	}

	private static function get_page_html( $args ) {
		$path = isset( $args['path'] ) ? trim( (string) $args['path'] ) : '';
		$url = isset( $args['url'] ) ? trim( (string) $args['url'] ) : '';
		if ( '' === $url && '' !== $path ) {
			$url = self::absolute_url( $path );
		}
		if ( '' === $url && '' === $path ) {
			$url = self::preview_or_site_root();
		}

		$browser_args = array();
		if ( '' !== $url ) { $browser_args['url'] = $url; }
		if ( '' !== $path ) { $browser_args['path'] = $path; }
		if ( ! empty( $args['selector'] ) ) { $browser_args['selector'] = (string) $args['selector']; }
		if ( isset( $args['max_length'] ) ) { $browser_args['max_length'] = max( 1, absint( $args['max_length'] ) ); }
		$rendered = WPVDMCP_Parity_Insights::execute( 'render_browser', $browser_args );
		if ( ! is_wp_error( $rendered ) ) { return self::bound_html( $rendered, $args ); }
		if ( 'provider_unavailable' !== $rendered->get_error_code() ) { return $rendered; }

		$fallback_path = $path;
		if ( '' === $fallback_path && '' !== $url ) {
			if ( ! self::is_same_site_url( $url ) ) { return $rendered; }
			$parsed = wp_parse_url( $url, PHP_URL_PATH );
			$fallback_path = is_string( $parsed ) && '' !== $parsed ? $parsed : '/';
		}
		if ( '' === $fallback_path ) { $fallback_path = '/'; }
		$server = WPVDMCP_Tools::execute( 'get_page_html', array( 'path' => $fallback_path ) );
		if ( is_wp_error( $server ) ) { return $server; }
		return self::bound_html( $server, $args );
	}

	private static function bound_html( $result, $args ) {
		$max = isset( $args['max_length'] ) ? max( 1, absint( $args['max_length'] ) ) : 50000;
		if ( ! is_array( $result ) ) { return $result; }
		// Provider results normally expose html directly; native REST responses
		// wrap it under data. Preserve either shape while applying the same bound.
		if ( isset( $result['html'] ) && is_string( $result['html'] ) ) {
			$full = self::utf8_length( $result['html'] );
			if ( $full > $max ) { $result['html'] = self::utf8_prefix( $result['html'], $max ); $result['html_truncated'] = true; $result['html_full_length'] = $full; }
			return $result;
		}
		if ( isset( $result['data'] ) && is_array( $result['data'] ) && isset( $result['data']['html'] ) && is_string( $result['data']['html'] ) ) {
			$full = self::utf8_length( $result['data']['html'] );
			if ( $full > $max ) { $result['data']['html'] = self::utf8_prefix( $result['data']['html'], $max ); $result['data']['html_truncated'] = true; $result['data']['html_full_length'] = $full; }
		}
		return $result;
	}

	private static function utf8_length( $value ) {
		if ( function_exists( 'mb_strlen' ) ) { return (int) mb_strlen( $value, 'UTF-8' ); }
		$matched = preg_match_all( '/./us', $value, $chars );
		return false === $matched ? strlen( $value ) : (int) $matched;
	}

	private static function utf8_prefix( $value, $max ) {
		$max = max( 0, (int) $max );
		if ( function_exists( 'mb_substr' ) ) { return (string) mb_substr( $value, 0, $max, 'UTF-8' ); }
		$matched = preg_match_all( '/./us', $value, $chars );
		return false === $matched ? substr( $value, 0, $max ) : implode( '', array_slice( $chars[0], 0, $max ) );
	}

	private static function preview_or_site_root() {
		if ( WPVDMCP_Compatibility::route_exists( '/wpvibe/v1/draft-theme/preview' ) ) {
			$preview = WPVDMCP_Tools::execute( 'get_preview_url', array() );
			if ( ! is_wp_error( $preview ) && isset( $preview['data'] ) && is_array( $preview['data'] ) ) {
				foreach ( array( 'preview_url', 'url' ) as $key ) { if ( ! empty( $preview['data'][ $key ] ) ) { return (string) $preview['data'][ $key ]; } }
			}
		}
		return self::site_root();
	}

	private static function absolute_url( $path ) {
		if ( 0 === strpos( $path, 'http://' ) || 0 === strpos( $path, 'https://' ) ) { return $path; }
		$root = self::site_root();
		return '' === $root ? '' : rtrim( $root, '/' ) . '/' . ltrim( $path, '/' );
	}

	private static function site_root() {
		if ( function_exists( 'home_url' ) ) { return rtrim( (string) home_url( '/' ), '/' ) . '/'; }
		if ( function_exists( 'rest_url' ) ) {
			$rest = (string) rest_url( '/' );
			$marker = strpos( $rest, '/wp-json/' );
			if ( false !== $marker ) { return rtrim( substr( $rest, 0, $marker ), '/' ) . '/'; }
		}
		return '';
	}

	private static function is_same_site_url( $url ) {
		$root = self::site_root();
		if ( '' === $root ) { return false; }
		return strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === strtolower( (string) wp_parse_url( $root, PHP_URL_HOST ) );
	}

	private static function fleet_tool() {
		$step = array(
			'type' => 'object',
			'properties' => array(
				'kind' => array( 'type' => 'string', 'enum' => array( 'plugin_update', 'theme_update', 'core_update', 'bulk_cli', 'command_list' ) ),
				'sites' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'targets' => array( 'description' => '"available" or a list of explicit slugs.' ),
				'exclude' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'family' => array( 'type' => 'string', 'enum' => array( 'user_delete', 'post_delete_force', 'comment_delete' ) ),
				'ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'status' => array( 'type' => 'string', 'enum' => array( 'spam', 'trash', 'hold' ) ),
				'reassign' => array( 'type' => 'integer' ),
				'commands' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required' => array( 'kind' ),
			'additionalProperties' => false,
		);
		$props = array(
			'plan' => self::objp( 'Legacy provider-defined fleet job plan.' ),
			'sites' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Connected site URLs.' ),
			'steps' => array( 'type' => 'array', 'items' => $step, 'description' => 'Current WPVibe fleet steps.' ),
			'confirm' => array( 'type' => 'boolean', 'description' => 'False/omitted returns a provider plan; true starts the job.' ),
			'idempotency_key' => self::strp( 'Optional provider idempotency key.' ),
		);
		$schema = self::schema( $props );
		$schema['anyOf'] = array( array( 'required' => array( 'plan' ) ), array( 'required' => array( 'sites', 'steps' ) ) );
		return array( 'name' => 'start_fleet_job', 'description' => 'Start a multi-site or background fleet job through a configured user-owned provider. Accepts the current WPVibe sites/steps/confirm/idempotency_key contract and the legacy Direct plan shape.', 'inputSchema' => $schema );
	}

	private static function tool( $name, $description, $props ) {
		$required = array();
		foreach ( $props as $key => &$prop ) { if ( ! empty( $prop['_required'] ) ) { $required[] = $key; unset( $prop['_required'] ); } }
		unset( $prop );
		$schema = self::schema( $props );
		if ( $required ) { $schema['required'] = $required; }
		return array( 'name' => $name, 'description' => $description, 'inputSchema' => $schema );
	}
	private static function schema( $props ) { return array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false ); }
	private static function strp( $description, $required = false ) { return array( 'type' => 'string', 'description' => $description, '_required' => $required ); }
	private static function intp( $description ) { return array( 'type' => 'integer', 'description' => $description ); }
	private static function objp( $description ) { return array( 'type' => 'object', 'description' => $description ); }
	private static function enump( $values, $description ) { return array( 'type' => 'string', 'enum' => $values, 'description' => $description ); }
}
