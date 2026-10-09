<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Original, file-backed workflow guidance for Direct MCP clients. */
final class WPVDMCP_Skills {
	public static function catalog() {
		$base = array(
			'setup'            => array( 'title' => 'Setup and discovery', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'theme-redesign'   => array( 'title' => 'Theme redesign', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'classic-themes'    => array( 'title' => 'Classic themes', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'block-themes'      => array( 'title' => 'Block themes', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'gutenberg'         => array( 'title' => 'Gutenberg / Block Editor', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'elementor'         => array( 'title' => 'Elementor', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'elementor-atomic'  => array( 'title' => 'Elementor Atomic / Abilities', 'min_wordpress' => '6.9', 'min_wpvibe' => '1.20.0' ),
			'beaver-builder'    => array( 'title' => 'Beaver Builder', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'bricks'            => array( 'title' => 'Bricks', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'breakdance'        => array( 'title' => 'Breakdance', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'divi'              => array( 'title' => 'Divi', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'seedprod'          => array( 'title' => 'SeedProd', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'wpbakery'          => array( 'title' => 'WPBakery', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'woocommerce'       => array( 'title' => 'WooCommerce', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'content-editing'   => array( 'title' => 'Content editing', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'rest-api'          => array( 'title' => 'REST API', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'abilities-api'     => array( 'title' => 'WordPress Abilities API', 'min_wordpress' => '6.9', 'min_wpvibe' => '1.20.0' ),
			'custom-fields'     => array( 'title' => 'Custom fields and meta', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'seo'               => array( 'title' => 'SEO', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'caching'           => array( 'title' => 'Caching', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'performance'       => array( 'title' => 'Performance', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'debugging'         => array( 'title' => 'Debugging', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'security'          => array( 'title' => 'Security', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
			'media'             => array( 'title' => 'Media', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.20.0' ),
			'accessibility'     => array( 'title' => 'Accessibility', 'min_wordpress' => '6.0', 'min_wpvibe' => '1.0.0' ),
		);
		foreach ( $base as $slug => &$meta ) {
			$meta['slug'] = $slug;
			$meta['path'] = 'skills/' . $slug . '.md';
		}
		unset( $meta );
		return $base;
	}

	public static function list_metadata() {
		return array_values( self::catalog() );
	}

	public static function load( $slug ) {
		$slug    = sanitize_key( $slug );
		$catalog = self::catalog();
		if ( ! isset( $catalog[ $slug ] ) ) {
			return new WP_Error( 'unknown_skill', 'Unknown skill.', array( 'status' => 404 ) );
		}
		$path = dirname( __DIR__ ) . '/' . $catalog[ $slug ]['path'];
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'skill_file_missing', 'The requested skill file is missing from this Direct MCP installation.', array( 'status' => 500 ) );
		}
		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- trusted plugin file.
		if ( false === $content ) {
			return new WP_Error( 'skill_file_unreadable', 'The requested skill file could not be read.', array( 'status' => 500 ) );
		}
		return array( 'skill' => $slug, 'metadata' => $catalog[ $slug ], 'instructions' => trim( $content ) );
	}
}
