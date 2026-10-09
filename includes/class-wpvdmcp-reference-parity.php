<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exact-name compatibility layer for the audited public WPVibe MCP surface.
 *
 * Definitions live here so hosted/public tool names cannot silently disappear
 * from Direct MCP even when their execution is local-equivalent or provider-backed.
 */
final class WPVDMCP_Reference_Parity {
	public static function definitions() {
		return array(
			self::tool( 'get_page_html', 'Retrieve page HTML. Prefer a configured JavaScript browser provider; otherwise fall back to WPVibe server-rendered HTML.', array(
				'path' => self::strp( 'Site-relative path or absolute URL.', true ),
			) ),
			self::tool( 'seedprod_compile_page', 'Compile a SeedProd page through WPVibe’s single-use builder login and a trusted user-owned browser provider. Requires approval.', array(
				'page_id' => self::intp( 'SeedProd page ID.', true ),
				'approval_id' => self::strp( 'One-time Direct MCP approval ID.' ),
			) ),
			self::tool( 'screenshot_page', 'Capture a page screenshot through a configured user-owned browser provider. Direct MCP never fabricates image bytes.', array(
				'url' => self::strp( 'Public or preview URL.', true ),
				'width' => self::intp( 'Viewport width in pixels.' ),
				'height' => self::intp( 'Viewport height in pixels.' ),
				'full_page' => array( 'type' => 'boolean', 'description' => 'Capture the full scrollable page.' ),
			) ),
			self::tool( 'audit_page', 'Run a page performance audit through the configured user-owned audit provider.', array(
				'url' => self::strp( 'Public URL.', true ),
				'strategy' => self::strp( 'Optional mobile or desktop strategy.' ),
			) ),
			self::tool( 'rest_api_write', 'Perform a write-only WordPress REST request with Direct MCP REST hardening.', array(
				'method' => self::enump( array( 'POST', 'PUT', 'PATCH', 'DELETE' ), 'Write HTTP method.', true ),
				'path' => self::strp( 'WordPress REST path.', true ),
				'params' => self::objp( 'Optional query parameters.' ),
				'body' => array( 'description' => 'JSON body object, or a JSON string for compatibility.', 'oneOf' => array( array( 'type' => 'object' ), array( 'type' => 'string' ) ) ),
				'fields' => self::arrayp( 'Optional selected response fields.', array( 'type' => 'string' ) ),
				'max_response_bytes' => self::intp( 'Optional response-size cap.' ),
			) ),
			self::tool( 'save_skill', 'Create or update a persistent local skill using the existing approval-gated saved-skill store.', array(
				'slug' => self::strp( 'Skill slug.', true ),
				'title' => self::strp( 'Skill title.' ),
				'description' => self::strp( 'Optional one-line skill description.' ),
				'instructions' => self::strp( 'Skill instructions.' ),
				'reference_files' => array( 'type' => 'array', 'description' => 'Optional bounded text reference files saved with the skill.', 'items' => array( 'type' => 'object', 'properties' => array( 'name' => array( 'type' => 'string' ), 'mime_type' => array( 'type' => 'string' ), 'content' => array( 'type' => 'string' ) ), 'required' => array( 'name', 'mime_type', 'content' ), 'additionalProperties' => false ) ),
				'approval_id' => self::strp( 'One-time approval ID.' ),
			) ),
			self::tool( 'connect_site', 'Connect a site through a configured user-owned hosted-account provider. Direct MCP does not fabricate WPVibe cloud account state.', array(
				'site_url' => self::strp( 'Site URL.', true ),
				'name' => self::strp( 'Optional site label.' ),
			) ),
			self::tool( 'list_sites', 'List connected sites through a configured user-owned hosted-account provider.', array() ),
			self::tool( 'remove_site', 'Remove a connected site through a configured user-owned hosted-account provider.', array(
				'site_id' => self::strp( 'Provider site identifier.', true ),
			) ),
			self::tool( 'get_profile', 'Read hosted account/profile information through a configured user-owned provider.', array() ),
			self::tool( 'start_fleet_job', 'Start a multi-site fleet job through a configured user-owned fleet provider.', array(
				'plan' => self::objp( 'Provider-defined fleet job plan.', true ),
			) ),
			self::tool( 'show_fleet_dashboard', 'Read fleet-job/dashboard state through a configured user-owned fleet provider.', array(
				'job_id' => self::strp( 'Optional provider job identifier.' ),
			) ),
			self::tool( 'use_usage_reset', 'Request a hosted usage reset through a configured user-owned account provider.', array() ),
			self::tool( 'reference_parity_manifest', 'Report the audited WPVibe public MCP tool surface, Direct MCP implementation class, and intentionally internal upstream routes.', array() ),
			self::tool( 'get_last_change', 'Read WPVibe live-reload change state from the installed site when the upstream route is available.', array(
				'since' => self::strp( 'Optional WPVibe change cursor/timestamp.' ),
			) ),
		);
	}

	public static function handles( $name ) {
		foreach ( self::definitions() as $definition ) {
			if ( isset( $definition['name'] ) && $definition['name'] === $name ) {
				return true;
			}
		}
		return false;
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		if ( 'get_page_html' === $name ) {
			$browser_args = array( 'path' => isset( $args['path'] ) ? (string) $args['path'] : '' );
			if ( function_exists( 'home_url' ) && '' !== $browser_args['path'] && 0 !== strpos( $browser_args['path'], 'http://' ) && 0 !== strpos( $browser_args['path'], 'https://' ) ) {
				$browser_args['url'] = home_url( '/' . ltrim( $browser_args['path'], '/' ) );
			} elseif ( 0 === strpos( $browser_args['path'], 'http://' ) || 0 === strpos( $browser_args['path'], 'https://' ) ) {
				$browser_args['url'] = $browser_args['path'];
			}
			$rendered = WPVDMCP_Parity_Insights::execute( 'render_browser', $browser_args );
			if ( ! is_wp_error( $rendered ) || 'provider_unavailable' !== $rendered->get_error_code() ) {
				return $rendered;
			}
			return WPVDMCP_Tools::execute( 'get_page_html', $args );
		}
		if ( 'seedprod_compile_page' === $name ) {
			return self::seedprod_compile( $args );
		}
		if ( 'audit_page' === $name ) {
			return WPVDMCP_Parity_Insights::execute( 'page_audit', $args );
		}
		if ( 'rest_api_write' === $name ) {
			$method = isset( $args['method'] ) ? strtoupper( sanitize_text_field( $args['method'] ) ) : '';
			if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
				return new WP_Error( 'invalid_method', 'rest_api_write only accepts POST, PUT, PATCH, or DELETE.', array( 'status' => 400 ) );
			}
			$args['method'] = $method;
			return WPVDMCP_Tools::execute( 'rest_api', $args );
		}
		if ( 'screenshot_page' === $name ) {
			return self::call_reference_provider( 'screenshot_page', $args );
		}
		if ( 'save_skill' === $name ) {
			$slug = sanitize_key( isset( $args['slug'] ) ? $args['slug'] : '' );
			if ( '' === $slug ) {
				return new WP_Error( 'invalid_skill_slug', 'Skill slug is required.', array( 'status' => 400 ) );
			}
			$args['slug'] = $slug;
			$saved = WPVDMCP_Parity_Skills::saved();
			$action = isset( $saved[ $slug ] ) ? 'update_skill' : 'create_skill';
			return WPVDMCP_Parity_Skills::execute( $action, $args );
		}
		if ( in_array( $name, self::hosted_provider_tools(), true ) ) {
			return self::provider( $name, $args );
		}
		if ( 'reference_parity_manifest' === $name ) {
			return WPVDMCP_Reference_Manifest::build( self::hosted_provider_tools() );
		}
		if ( 'get_last_change' === $name ) {
			if ( ! WPVDMCP_Compatibility::route_exists( '/wpvibe/v1/last-change' ) ) {
				return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose live-reload change lookup.', array( 'status' => 404, 'route' => '/wpvibe/v1/last-change' ) );
			}
			$query = array();
			if ( isset( $args['since'] ) && '' !== (string) $args['since'] ) {
				$query['since'] = sanitize_text_field( $args['since'] );
			}
			return WPVDMCP_Tools::dispatch( 'GET', '/wpvibe/v1/last-change', $query );
		}
		return new WP_Error( 'unknown_reference_tool', 'Unknown reference parity tool.', array( 'status' => 404, 'tool' => $name ) );
	}

	private static function seedprod_compile( $args ) {
		$page_id = absint( isset( $args['page_id'] ) ? $args['page_id'] : 0 );
		if ( $page_id <= 0 ) {
			return new WP_Error( 'invalid_page_id', 'page_id is required.', array( 'status' => 400 ) );
		}
		if ( ! self::reference_provider_available( 'seedprod_compile_page' ) ) {
			return new WP_Error( 'provider_unavailable', 'SeedProd compile automation requires a trusted user-owned browser provider.', array( 'status' => 501, 'provider' => 'wpvdmcp_reference_seedprod_compile_page', 'site_local_provider' => true ) );
		}
		if ( ! WPVDMCP_Compatibility::route_exists( '/wpvibe/v1/builder-login' ) ) {
			return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose the SeedProd builder-login primitive.', array( 'status' => 404, 'route' => '/wpvibe/v1/builder-login' ) );
		}
		$payload = array( 'page_id' => $page_id );
		if ( ! WPVDMCP_Approvals::bypass_enabled() ) {
			$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			if ( '' === $approval_id ) {
				$approval = WPVDMCP_Approvals::request( 'seedprod_compile_page', $payload, 'Compile SeedProd page #' . $page_id . ' through a trusted browser provider.' );
				if ( is_wp_error( $approval ) ) { return $approval; }
				return array_merge( $approval, array( 'status' => 'approval_required', 'page_id' => $page_id ) );
			}
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'seedprod_compile_page', $payload );
			if ( is_wp_error( $consumed ) ) { return $consumed; }
		}
		$login = WPVDMCP_Tools::dispatch( 'POST', '/wpvibe/v1/builder-login', array(), array( 'page_id' => $page_id ) );
		if ( is_wp_error( $login ) ) { return $login; }
		$data = isset( $login['data'] ) && is_array( $login['data'] ) ? $login['data'] : array();
		if ( empty( $data['login_url'] ) || empty( $data['builder_url'] ) ) {
			return new WP_Error( 'builder_login_invalid_response', 'WPVibe did not return the expected one-time SeedProd builder login.', array( 'status' => 502 ) );
		}
		$provider_args = array(
			'page_id' => $page_id,
			'login_url' => $data['login_url'],
			'builder_url' => $data['builder_url'],
			'expires_in' => isset( $data['expires_in'] ) ? absint( $data['expires_in'] ) : 120,
		);
		$result = self::call_reference_provider( 'seedprod_compile_page', $provider_args );
		if ( is_wp_error( $result ) ) { return $result; }
		return self::redact_provider_secrets( $result );
	}

	private static function reference_provider_available( $name ) {
		if ( isset( $GLOBALS['wpvdmcp_reference_providers'][ $name ] ) && is_callable( $GLOBALS['wpvdmcp_reference_providers'][ $name ] ) ) { return true; }
		return function_exists( 'has_filter' ) && (bool) has_filter( 'wpvdmcp_reference_' . $name );
	}

	private static function call_reference_provider( $name, $args ) {
		if ( isset( $GLOBALS['wpvdmcp_reference_providers'][ $name ] ) && is_callable( $GLOBALS['wpvdmcp_reference_providers'][ $name ] ) ) {
			return call_user_func( $GLOBALS['wpvdmcp_reference_providers'][ $name ], $args );
		}
		if ( function_exists( 'apply_filters' ) ) {
			$value = apply_filters( 'wpvdmcp_reference_' . $name, null, $args );
			if ( null !== $value && false !== $value ) { return $value; }
		}
		return new WP_Error( 'provider_unavailable', 'No provider is configured for ' . $name . '.', array( 'status' => 501, 'provider' => 'wpvdmcp_reference_' . $name ) );
	}

	private static function redact_provider_secrets( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		foreach ( array( 'login_url', 'token', 'secret', 'cookie', 'authorization', 'password' ) as $key ) { unset( $value[ $key ] ); }
		foreach ( $value as $key => $item ) { if ( is_array( $item ) ) { $value[ $key ] = self::redact_provider_secrets( $item ); } }
		return $value;
	}

	private static function hosted_provider_tools() {
		return array( 'connect_site', 'list_sites', 'remove_site', 'get_profile', 'start_fleet_job', 'show_fleet_dashboard', 'use_usage_reset' );
	}

	private static function provider( $name, $args ) {
		if ( isset( $GLOBALS['wpvdmcp_reference_providers'][ $name ] ) && is_callable( $GLOBALS['wpvdmcp_reference_providers'][ $name ] ) ) {
			return call_user_func( $GLOBALS['wpvdmcp_reference_providers'][ $name ], $args );
		}
		$value = null;
		if ( function_exists( 'apply_filters' ) ) {
			$value = apply_filters( 'wpvdmcp_reference_' . $name, null, $args );
		}
		if ( null !== $value && false !== $value ) {
			return $value;
		}
		return new WP_Error(
			'provider_unavailable',
			'This tool belongs to WPVibe hosted account/fleet infrastructure. Configure a user-owned Direct MCP provider to use the exact tool name without fabricating cloud state.',
			array(
				'status' => 501,
				'tool' => $name,
				'provider' => 'wpvdmcp_reference_' . $name,
				'hosted_boundary' => true,
				'official_service' => 'https://mcp.wpvibe.ai/mcp',
			)
		);
	}





	private static function tool( $name, $description, $props ) {
		$required = array();
		foreach ( $props as $key => &$prop ) {
			if ( ! empty( $prop['_required'] ) ) {
				$required[] = $key;
				unset( $prop['_required'] );
			}
		}
		unset( $prop );
		$schema = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) { $schema['required'] = $required; }
		return array( 'name' => $name, 'description' => $description, 'inputSchema' => $schema );
	}
	private static function strp( $description, $required = false ) { return array( 'type' => 'string', 'description' => $description, '_required' => $required ); }
	private static function intp( $description, $required = false ) { return array( 'type' => 'integer', 'description' => $description, '_required' => $required ); }
	private static function objp( $description, $required = false ) { return array( 'type' => 'object', 'description' => $description, '_required' => $required ); }
	private static function arrayp( $description, $items, $required = false ) { return array( 'type' => 'array', 'items' => $items, 'description' => $description, '_required' => $required ); }
	private static function enump( $values, $description, $required = false ) { return array( 'type' => 'string', 'enum' => $values, 'description' => $description, '_required' => $required ); }
}
