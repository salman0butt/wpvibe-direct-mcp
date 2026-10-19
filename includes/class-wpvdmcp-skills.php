<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Original, file-backed workflow guidance for Direct MCP clients. */
final class WPVDMCP_Skills {
	/** Searchable, concise task triggers. Full workflows remain lazy-loaded from files. */
	public static function descriptions() {
		return array(
			'setup' => 'Use when connecting a WordPress site, discovering installed capabilities, and confirming safe access before any work.',
			'theme-redesign' => 'Use when planning or implementing a full-site visual redesign with previews, scope control, and rollback.',
			'classic-themes' => 'Use when editing classic PHP WordPress themes, template hierarchy, child themes, or the Customizer.',
			'block-themes' => 'Use when updating full-site editing templates, theme.json settings, block patterns, and global styles.',
			'gutenberg' => 'Use when creating or editing Gutenberg block content, reusable patterns, and WordPress editor layouts.',
			'elementor' => 'Use when editing Elementor pages, widgets, containers, responsive layout, and builder-native designs.',
			'elementor-atomic' => 'Use when editing Elementor Atomic elements, Abilities API actions, global styles, and design tokens.',
			'beaver-builder' => 'Use when changing Beaver Builder modules, templates, sections, or responsive page layouts.',
			'bricks' => 'Use when safely editing Bricks Builder components, templates, queries, or global styles.',
			'breakdance' => 'Use when modifying Breakdance builder pages, elements, layouts, or design settings.',
			'divi' => 'Use when modifying Divi layouts, modules, Theme Builder templates, or design presets.',
			'seedprod' => 'Use when creating SeedProd landing pages, coming-soon pages, templates, or builder sections.',
			'wpbakery' => 'Use when updating WPBakery shortcodes, rows, columns, and page-builder content safely.',
			'woocommerce' => 'Use when managing WooCommerce store products, stock, inventory, checkout, orders, or product pricing.',
			'content-editing' => 'Use when rewriting WordPress posts or pages, updating titles, metadata, links, and editorial content.',
			'rest-api' => 'Use when discovering and calling WordPress REST API routes with schema checks and write approvals.',
			'abilities-api' => 'Use when discovering WordPress Abilities API operations and running readonly or approved mutations.',
			'custom-fields' => 'Use when editing WordPress post meta, custom fields, data structures, and field validations.',
			'seo' => 'Use when improving on-page SEO titles, metadata, internal links, indexing, and content visibility.',
			'caching' => 'Use when diagnosing WordPress cache invalidation, page cache, object cache, and stale content.',
			'performance' => 'Use when profiling page speed, load times, database queries, assets, and performance regressions.',
			'debugging' => 'Use when debugging PHP errors, broken WordPress routes, plugin conflicts, or failing workflows.',
			'security' => 'Use when auditing WordPress permissions, GDPR privacy, authentication, vulnerabilities, and safe changes.',
			'media' => 'Use when uploading and managing Media Library images, attachments, filenames, alt text, or SVG safety.',
			'accessibility' => 'Use when auditing accessibility, WCAG compliance, keyboard navigation, contrast, and semantic markup.',
			'kadence' => 'Use when editing Kadence Blocks layouts, advanced blocks, templates, and theme design options.',
			'generatepress' => 'Use when editing GeneratePress theme, GP Premium elements, layout, typography, or hooks.',
			'generateblocks' => 'Use when modifying GenerateBlocks containers, query loops, components, and responsive designs.',
			'editable-fields' => 'Use when identifying editable fields and safely applying schema-validated WordPress field updates.',
			'block-validation' => 'Use when validating serialized Gutenberg block output, attributes, and safe round-trip editing.',
			'saved-skills' => 'Use when authoring or updating reusable local site workflow instructions and approved references.',
			'pdf-media' => 'Use when reviewing PDFs, extracted media metadata, attachments, and supported document capabilities.',
			'site-intelligence' => 'Use when inspecting WordPress site health, site analytics, installed plugins, and diagnostic signals.',
			'live-reload' => 'Use when verifying refreshed page changes, preview updates, and development feedback loops.',
			'lighthouse' => 'Use when requesting a Lighthouse performance audit, Core Web Vitals diagnostics, or page-speed reports.',
			'image-search' => 'Use when researching stock photos, royalty-aware images, visual references, and image search providers.',
			'abilities-plugin' => 'Use when developing or operating WordPress plugins through the Abilities API and approved actions.',
			'rank-math' => 'Use when changing Rank Math SEO titles, schemas, metadata, sitemaps, or search settings.',
			'aioseo' => 'Use when configuring All in One SEO metadata, schemas, redirects, and on-page SEO workflows.',
			'seopress' => 'Use when updating SEOPress search metadata, schemas, analytics, and SEO plugin settings.',
			'yoast-seo' => 'Use when editing Yoast SEO titles, descriptions, canonical links, and search optimization.',
			'smash-balloon' => 'Use when managing Smash Balloon social feeds, embed displays, and social media integrations.',
			'memberpress' => 'Use when managing MemberPress memberships, subscriptions, protected pages, and access rules.',
			'charitable' => 'Use when managing Charitable donation campaigns, donations, donors, and fundraising forms.',
			'duplicator' => 'Use when planning site migration, backups, WordPress cloning, or recovery with Duplicator.',
			'pushengage' => 'Use when configuring PushEngage web push notifications, subscriber segments, and campaigns.',
			'easy-digital-downloads' => 'Use when managing digital products, downloadable orders, licensing, and Easy Digital Downloads.',
			'lifterlms' => 'Use when editing LifterLMS online courses, lessons, enrollments, and learning paths.',
			'wpforms' => 'Use when creating WPForms contact forms, field validation, submission flows, and notifications.',
			'kit-convertkit' => 'Use when managing Kit ConvertKit newsletter forms, subscribers, segments, and email marketing.',
			'modern-cart' => 'Use when editing Modern Cart shopping cart layouts, conversion controls, and checkout interactions.',
			'cartflows' => 'Use when building WooCommerce checkout funnels, landing flows, order bumps, upsells, and conversions.',
			'pagelayer' => 'Use when editing Pagelayer page layouts, widgets, sections, and responsive visual content.',
			'elementskit' => 'Use when editing ElementsKit Elementor widgets, headers, menus, and page components.',
			'amelia' => 'Use when managing Amelia appointments, bookings, services, staff calendars, and event reservations.',
			'adtribes-product-feed' => 'Use when preparing product feed exports, shopping integrations, and AdTribes merchant catalogs.',
			'fluentcart' => 'Use when managing FluentCart products, orders, carts, and store checkout workflows.',
			'fluentcommunity' => 'Use when managing FluentCommunity groups, social discussions, members, and community content.',
			'fluentcrm' => 'Use when setting up FluentCRM email automation, customer CRM, contacts, segments, and campaigns.',
			'merchant' => 'Use when configuring Merchant for WooCommerce sales features, offers, conversion tools, or storefront.',
			'wpcode' => 'Use when reviewing WPCode snippets, code injection risk, dormant edits, and activation approval.',
			'sugar-calendar' => 'Use when managing Sugar Calendar events, schedules, calendar listings, and appointments.',
			'optinmonster' => 'Use when optimizing OptinMonster opt-in forms, lead generation, targeting, and conversion campaigns.',
			'botiga' => 'Use when customizing Botiga WooCommerce storefront, product pages, typography, and shop layouts.',
			'advanced-custom-fields' => 'Use when managing Advanced Custom Fields groups, ACF field values, repeaters, and schema-driven content.',
			'ultimate-addons-for-elementor' => 'Use when editing Ultimate Addons for Elementor widgets, advanced components, and page designs.',
			'disable-comments' => 'Use when configuring Disable Comments safely without damaging WooCommerce order notes or discussion records.',
		);
	}

	public static function tags( $slug ) {
		$tags = array(
			'woocommerce' => array( 'stock', 'inventory', 'checkout', 'orders', 'product inventory' ),
			'cartflows' => array( 'checkout', 'funnels', 'order bumps' ),
			'amelia' => array( 'appointments', 'bookings', 'calendar' ),
			'charitable' => array( 'donations', 'fundraising' ),
			'adtribes-product-feed' => array( 'product feed', 'shopping feed' ),
			'duplicator' => array( 'site migration', 'backup', 'restore' ),
			'fluentcrm' => array( 'email automation', 'customer crm' ),
			'elementor-atomic' => array( 'global styles', 'atomic elements', 'design tokens' ),
			'security' => array( 'GDPR privacy', 'permissions', 'vulnerability' ),
			'wpforms' => array( 'form', 'contact form', 'forms' ),
			'advanced-custom-fields' => array( 'acf', 'repeater fields', 'custom fields' ),
		);
		return isset( $tags[ $slug ] ) ? $tags[ $slug ] : array();
	}

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
		$descriptions = self::descriptions();
		foreach ( $base as $slug => &$meta ) {
			$meta['slug'] = $slug;
			$meta['path'] = 'skills/' . $slug . '.md';
			$meta['description'] = isset( $descriptions[ $slug ] ) ? $descriptions[ $slug ] : '';
			$meta['tags'] = self::tags( $slug );
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
