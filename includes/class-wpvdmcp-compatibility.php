<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Runtime feature detection for the installed WordPress + WPVibe combination. */
final class WPVDMCP_Compatibility {
	public static function routes() {
		$server = rest_get_server();
		return $server ? $server->get_routes() : array();
	}

	public static function route_exists( $route ) {
		$routes = self::routes();
		return isset( $routes[ $route ] );
	}

	public static function route_prefix_exists( $prefix ) {
		foreach ( array_keys( self::routes() ) as $route ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	public static function wordpress_version() {
		if ( function_exists( 'get_bloginfo' ) ) {
			return (string) get_bloginfo( 'version' );
		}
		global $wp_version;
		return isset( $wp_version ) ? (string) $wp_version : '';
	}

	public static function wpvibe_version() {
		return defined( 'WPVIBE_VERSION' ) ? (string) WPVIBE_VERSION : '';
	}

	public static function capability_summary() {
		return array(
			'wpvibe' => '' !== self::wpvibe_version(),
			'abilities_api' => self::route_exists( '/wp-abilities/v1/abilities' ),
			'wp_cli' => self::route_exists( '/wpvibe/v1/cli/run' ),
			'code_snippets' => self::route_exists( '/wpvibe/v1/code-snippet/dormant' ) || self::route_exists( '/wpvibe/v1/code-snippet' ),
			'elementor' => self::route_exists( '/wpvibe/v1/elementor/save-page' ),
			'beaver_builder' => self::route_exists( '/wpvibe/v1/beaver/save-page' ),
			'bricks' => self::route_exists( '/wpvibe/v1/bricks/save-page' ),
			'breakdance' => self::route_exists( '/wpvibe/v1/breakdance/save-page' ),
			'op_receipts' => self::route_prefix_exists( '/wpvibe/v1/op-receipt/' ),
			'device_upload' => class_exists( 'WPVDMCP_Upload' ),
			'hosted_only' => array( 'get_profile', 'connect_site', 'list_sites', 'remove_site', 'use_usage_reset', 'start_fleet_job', 'show_fleet_dashboard', 'cloud_search_images', 'cloud_audit_page', 'hosted_mcp_app_panels' ),
		);
	}
}
