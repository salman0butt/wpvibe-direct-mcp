<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPVDMCP_Tools {
	/** Return MCP tool definitions available on this site. */
	public static function definitions() {
		$tools = array(
			self::route_tool( 'site_info', 'Inspect the connected WordPress site, theme, capabilities, plugin versions, and WPVibe feature flags.', 'GET', '/wpvibe/v1/site-info', array() ),
			self::route_tool( 'registered_meta', 'List REST-visible post meta for a post type and diagnose why custom-field writes may be dropped.', 'GET', '/wpvibe/v1/registered-meta', array(
				'post_type' => self::string_prop( 'WordPress post-type slug.', true ),
			) ),
			self::route_tool( 'read_file', 'Read a file from WPVibe’s active-theme or draft-theme sandbox, optionally by line range.', 'POST', '/wpvibe/v1/file/read', array(
				'path' => self::string_prop( 'Theme-relative file path.', true ),
				'start_line' => self::integer_prop( 'First line, 1-based.' ),
				'end_line' => self::integer_prop( 'Last line, inclusive.' ),
			) ),
			self::route_tool( 'list_files', 'List files available in WPVibe’s active/draft theme sandbox.', 'GET', '/wpvibe/v1/file/list', array(
				'pattern' => self::string_prop( 'Optional glob/search pattern.' ),
			) ),
			self::route_tool( 'search_files', 'Search text across theme files before editing.', 'POST', '/wpvibe/v1/file/search', array(
				'pattern' => self::string_prop( 'Text or pattern to locate.', true ),
				'case_sensitive' => self::boolean_prop( 'Use case-sensitive matching.' ),
				'extensions' => self::array_prop( 'Optional file extensions, such as php, css, js.', array( 'type' => 'string' ) ),
				'max_results' => self::integer_prop( 'Maximum matches.' ),
			) ),
			self::route_tool( 'get_file_outline', 'Return a compact structural outline of a PHP, CSS, JavaScript, or template file.', 'POST', '/wpvibe/v1/file/outline', array(
				'path' => self::string_prop( 'Theme-relative file path.', true ),
			) ),
			self::route_tool( 'edit_file', 'Surgically replace an exact block in a sandboxed theme file. Prefer this over rewriting a whole file.', 'POST', '/wpvibe/v1/file/edit', array(
				'path' => self::string_prop( 'Theme-relative file path.', true ),
				'old_content' => self::string_prop( 'Exact existing text to replace.', true ),
				'new_content' => self::string_prop( 'Replacement text.', true ),
			) ),
			self::route_tool( 'write_file', 'Create or completely replace a file in WPVibe’s protected theme sandbox. PHP is linted by WPVibe.', 'POST', '/wpvibe/v1/file/write', array(
				'path' => self::string_prop( 'Theme-relative file path.', true ),
				'content' => self::string_prop( 'Complete new file contents.', true ),
			) ),
			self::route_tool( 'delete_file', 'Delete a file from WPVibe’s draft-theme sandbox.', 'POST', '/wpvibe/v1/file/delete', array(
				'path' => self::string_prop( 'Theme-relative file path.', true ),
			) ),
			self::route_tool( 'search_content', 'Search within a post field, post meta value, or option before making a surgical edit.', 'POST', '/wpvibe/v1/content/search', self::content_props( false ) ),
			self::route_tool( 'edit_content', 'Surgically replace text inside a post field, post meta value, or allowed option.', 'POST', '/wpvibe/v1/content/edit', self::content_props( true ) ),
			self::route_tool( 'create_draft_theme', 'Clone the current theme into WPVibe’s isolated draft-theme sandbox.', 'POST', '/wpvibe/v1/draft-theme', array() ),
			self::route_tool( 'get_preview_url', 'Get a secure preview URL for the WPVibe draft theme.', 'GET', '/wpvibe/v1/draft-theme/preview', array() ),
			self::route_tool( 'publish_draft_theme', 'Publish the reviewed WPVibe draft theme to the live site. Use only after preview and explicit approval.', 'POST', '/wpvibe/v1/draft-theme/publish', array() ),
			self::route_tool( 'delete_draft_theme', 'Delete the current WPVibe draft theme without publishing.', 'POST', '/wpvibe/v1/draft-theme/delete', array() ),
			self::route_tool( 'run_wp_cli', 'Run a WPVibe allowlisted WP-CLI-style command through native PHP dispatch.', 'POST', '/wpvibe/v1/cli/run', array(
				'command' => self::string_prop( 'Allowlisted WP-CLI-style command.', true ),
				'confirm_write' => self::boolean_prop( 'Confirm a non-destructive write when WPVibe requires it.' ),
			) ),
			self::route_tool( 'wp_cli_status', 'Check which native WP-CLI emulation features are available.', 'GET', '/wpvibe/v1/cli/status', array() ),
			self::route_tool( 'upload_media', 'Download a public image/file URL into the WordPress media library using WPVibe SSRF protections.', 'POST', '/wpvibe/v1/upload-media', array(
				'url' => self::string_prop( 'Public HTTP(S) media URL.', true ),
				'title' => self::string_prop( 'Attachment title.' ),
				'alt_text' => self::string_prop( 'Image alternative text.' ),
				'post_id' => self::integer_prop( 'Optional parent post ID.' ),
			) ),
			self::route_tool( 'get_page_html', 'Fetch rendered frontend HTML for a path to inspect the actual output.', 'POST', '/wpvibe/v1/rendered-html', array(
				'path' => self::string_prop( 'Site-relative path such as /about/.' ),
			) ),
			self::route_tool( 'navigate', 'Ask WPVibe’s browser/live-reload helper to navigate the current admin user to a URL.', 'POST', '/wpvibe/v1/navigate', array(
				'url' => self::string_prop( 'URL on this WordPress site.', true ),
			) ),
			self::route_tool( 'create_classic_theme', 'Create a new classic WordPress theme through WPVibe’s scaffold and safety layer.', 'POST', '/wpvibe/v1/create-classic-theme', array(
				'theme_name' => self::string_prop( 'Human-readable theme name.', true ),
				'description' => self::string_prop( 'Theme description.' ),
			) ),
			self::route_tool( 'audit_log', 'Read WPVibe’s append-only activity/approval log.', 'GET', '/wpvibe/v1/audit-log', array(
				'limit' => self::integer_prop( 'Number of entries.' ),
				'offset' => self::integer_prop( 'Pagination offset.' ),
			) ),
			self::route_tool( 'elementor_widgets', 'List Elementor widgets and structural elements installed on the site.', 'GET', '/wpvibe/v1/elementor/widgets', array() ),
			self::route_tool( 'elementor_schema', 'Inspect the controls accepted by one Elementor widget or element.', 'GET', '/wpvibe/v1/elementor/schema', array(
				'slug' => self::string_prop( 'Elementor widget/element slug.', true ),
				'names' => self::string_prop( 'Optional comma-separated control names.' ),
				'prefix' => self::string_prop( 'Optional control-name prefix.' ),
			) ),
			self::route_tool( 'elementor_save_page', 'Create or update an Elementor page using native Elementor document APIs.', 'POST', '/wpvibe/v1/elementor/save-page', array(
				'id' => self::integer_prop( 'Existing page ID; omit to create.' ),
				'title' => self::string_prop( 'Page title; required when creating.' ),
				'post_type' => self::string_prop( 'Post type, normally page.' ),
				'status' => self::string_prop( 'draft, pending, private, or publish.' ),
				'template_type' => self::string_prop( 'Elementor document template type.' ),
				'data' => self::array_prop( 'Array of root Elementor elements.', array() , true ),
				'page_template' => self::string_prop( 'Optional WordPress page template such as elementor_canvas.' ),
			) ),
			self::route_tool( 'elementor_save_template', 'Create or update an Elementor Pro Theme Builder template and conditions.', 'POST', '/wpvibe/v1/elementor/save-template', array(
				'id' => self::integer_prop( 'Existing template ID; omit to create.' ),
				'title' => self::string_prop( 'Template title.' ),
				'type' => self::string_prop( 'header, footer, single, archive, search-results, error-404, section, or popup.', true ),
				'status' => self::string_prop( 'draft or publish.' ),
				'data' => self::array_prop( 'Array of root Elementor elements.', array(), true ),
				'conditions' => self::array_prop( 'Elementor display conditions such as include/general.', array( 'type' => 'string' ) ),
			) ),
			array(
				'name' => 'rest_api',
				'description' => 'Call any registered WordPress REST API route internally as the token owner. This unlocks posts, pages, custom post types, WooCommerce, plugin routes, and WordPress Abilities while preserving each route’s capability checks.',
				'inputSchema' => self::schema( array(
					'method' => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), 'description' => 'HTTP method.' ),
					'path' => self::string_prop( 'REST route beginning with /, for example /wp/v2/pages/123.', true ),
					'params' => array( 'type' => 'object', 'description' => 'Query parameters.' ),
					'body' => array( 'type' => 'object', 'description' => 'JSON/body parameters.' ),
				), array( 'method', 'path' ) ),
			),
			array(
				'name' => 'discover_rest_routes',
				'description' => 'Discover registered WordPress REST routes, optionally filtered by namespace or text. Useful for plugin APIs, WooCommerce, page builders, and Abilities API routes.',
				'inputSchema' => self::schema( array(
					'filter' => self::string_prop( 'Optional substring such as wc/v3, elementor, abilities, wp/v2, or wpvibe.' ),
					'max_results' => self::integer_prop( 'Maximum routes, default 100.' ),
				) ),
			),
			array(
				'name' => 'list_skills',
				'description' => 'List the built-in workflow skills supplied by WPVibe Direct MCP.',
				'inputSchema' => self::schema( array() ),
			),
			array(
				'name' => 'load_skill',
				'description' => 'Load concise workflow instructions for a WordPress task before using tools.',
				'inputSchema' => self::schema( array(
					'skill' => array( 'type' => 'string', 'enum' => array_keys( self::skills() ), 'description' => 'Skill identifier.' ),
				), array( 'skill' ) ),
			),
		);

		$available = array();
		foreach ( $tools as $tool ) {
			if ( empty( $tool['_route'] ) || self::route_exists( $tool['_route'] ) ) {
				unset( $tool['_route'], $tool['_method'] );
				$available[] = $tool;
			}
		}
		return $available;
	}

	public static function execute( $name, $arguments ) {
		$arguments = is_array( $arguments ) ? $arguments : array();
		if ( 'rest_api' === $name ) {
			return self::rest_api( $arguments );
		}
		if ( 'discover_rest_routes' === $name ) {
			return self::discover_routes( $arguments );
		}
		if ( 'list_skills' === $name ) {
			return array( 'skills' => array_keys( self::skills() ) );
		}
		if ( 'load_skill' === $name ) {
			$skills = self::skills();
			$skill = isset( $arguments['skill'] ) ? sanitize_key( $arguments['skill'] ) : '';
			if ( ! isset( $skills[ $skill ] ) ) {
				return new WP_Error( 'unknown_skill', 'Unknown skill.', array( 'status' => 404 ) );
			}
			return array( 'skill' => $skill, 'instructions' => $skills[ $skill ] );
		}

		$map = self::execution_map();
		if ( ! isset( $map[ $name ] ) ) {
			return new WP_Error( 'unknown_tool', 'Unknown MCP tool: ' . $name, array( 'status' => 404 ) );
		}
		$route = $map[ $name ][0];
		$method = $map[ $name ][1];
		return self::dispatch( $method, $route, 'GET' === $method ? $arguments : array(), 'GET' === $method ? array() : $arguments );
	}

	private static function execution_map() {
		return array(
			'site_info' => array( '/wpvibe/v1/site-info', 'GET' ),
			'registered_meta' => array( '/wpvibe/v1/registered-meta', 'GET' ),
			'read_file' => array( '/wpvibe/v1/file/read', 'POST' ),
			'list_files' => array( '/wpvibe/v1/file/list', 'GET' ),
			'search_files' => array( '/wpvibe/v1/file/search', 'POST' ),
			'get_file_outline' => array( '/wpvibe/v1/file/outline', 'POST' ),
			'edit_file' => array( '/wpvibe/v1/file/edit', 'POST' ),
			'write_file' => array( '/wpvibe/v1/file/write', 'POST' ),
			'delete_file' => array( '/wpvibe/v1/file/delete', 'POST' ),
			'search_content' => array( '/wpvibe/v1/content/search', 'POST' ),
			'edit_content' => array( '/wpvibe/v1/content/edit', 'POST' ),
			'create_draft_theme' => array( '/wpvibe/v1/draft-theme', 'POST' ),
			'get_preview_url' => array( '/wpvibe/v1/draft-theme/preview', 'GET' ),
			'publish_draft_theme' => array( '/wpvibe/v1/draft-theme/publish', 'POST' ),
			'delete_draft_theme' => array( '/wpvibe/v1/draft-theme/delete', 'POST' ),
			'run_wp_cli' => array( '/wpvibe/v1/cli/run', 'POST' ),
			'wp_cli_status' => array( '/wpvibe/v1/cli/status', 'GET' ),
			'upload_media' => array( '/wpvibe/v1/upload-media', 'POST' ),
			'get_page_html' => array( '/wpvibe/v1/rendered-html', 'POST' ),
			'navigate' => array( '/wpvibe/v1/navigate', 'POST' ),
			'create_classic_theme' => array( '/wpvibe/v1/create-classic-theme', 'POST' ),
			'audit_log' => array( '/wpvibe/v1/audit-log', 'GET' ),
			'elementor_widgets' => array( '/wpvibe/v1/elementor/widgets', 'GET' ),
			'elementor_schema' => array( '/wpvibe/v1/elementor/schema', 'GET' ),
			'elementor_save_page' => array( '/wpvibe/v1/elementor/save-page', 'POST' ),
			'elementor_save_template' => array( '/wpvibe/v1/elementor/save-template', 'POST' ),
		);
	}

	private static function rest_api( $args ) {
		$method = isset( $args['method'] ) ? strtoupper( sanitize_text_field( $args['method'] ) ) : 'GET';
		$path = isset( $args['path'] ) ? (string) $args['path'] : '';
		$path = '/' . ltrim( wp_parse_url( $path, PHP_URL_PATH ) ?: $path, '/' );
		if ( 0 === strpos( $path, '/wp-json/' ) ) {
			$path = substr( $path, 8 );
		}
		$blocked = array( '/wpvibe-direct/v1/mcp', '/wp/v2/users/me/application-passwords', '/application-passwords' );
		foreach ( $blocked as $needle ) {
			if ( false !== strpos( $path, $needle ) ) {
				return new WP_Error( 'blocked_route', 'This authentication-sensitive REST route is blocked by the direct MCP bridge.', array( 'status' => 403 ) );
			}
		}
		$params = isset( $args['params'] ) && is_array( $args['params'] ) ? $args['params'] : array();
		$body = isset( $args['body'] ) && is_array( $args['body'] ) ? $args['body'] : array();
		return self::dispatch( $method, $path, $params, $body );
	}

	private static function discover_routes( $args ) {
		$filter = isset( $args['filter'] ) ? strtolower( sanitize_text_field( $args['filter'] ) ) : '';
		$max = isset( $args['max_results'] ) ? max( 1, min( 500, absint( $args['max_results'] ) ) ) : 100;
		$routes = rest_get_server()->get_routes();
		$out = array();
		foreach ( $routes as $route => $handlers ) {
			if ( $filter && false === strpos( strtolower( $route ), $filter ) ) {
				continue;
			}
			$methods = array();
			foreach ( $handlers as $handler ) {
				if ( ! empty( $handler['methods'] ) && is_array( $handler['methods'] ) ) {
					$methods = array_merge( $methods, array_keys( array_filter( $handler['methods'] ) ) );
				}
			}
			$out[] = array( 'route' => $route, 'methods' => array_values( array_unique( $methods ) ) );
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return array( 'count' => count( $out ), 'routes' => $out );
	}

	public static function dispatch( $method, $route, $query = array(), $body = array() ) {
		$request = new WP_REST_Request( strtoupper( $method ), $route );
		if ( $query ) {
			$request->set_query_params( $query );
		}
		if ( $body ) {
			$request->set_body_params( $body );
			$request->set_header( 'content-type', 'application/json' );
		}
		$response = rest_do_request( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = rest_ensure_response( $response );
		$data = $response->get_data();
		$status = $response->get_status();
		if ( $status >= 400 ) {
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'WordPress REST request failed.';
			$code = is_array( $data ) && isset( $data['code'] ) ? $data['code'] : 'rest_error';
			return new WP_Error( $code, $message, array( 'status' => $status, 'response' => $data ) );
		}
		return array( 'status' => $status, 'data' => $data );
	}

	private static function route_exists( $route ) {
		return isset( rest_get_server()->get_routes()[ $route ] );
	}

	private static function route_tool( $name, $description, $method, $route, $props ) {
		$required = array();
		foreach ( $props as $key => $prop ) {
			if ( ! empty( $prop['_required'] ) ) {
				$required[] = $key;
				unset( $props[ $key ]['_required'] );
			}
		}
		return array(
			'name' => $name,
			'description' => $description,
			'inputSchema' => self::schema( $props, $required ),
			'_route' => $route,
			'_method' => $method,
		);
	}

	private static function content_props( $editing ) {
		$props = array(
			'target_type' => array( 'type' => 'string', 'enum' => array( 'post', 'meta', 'option' ), 'description' => 'Target storage type.', '_required' => true ),
			'post_id' => self::integer_prop( 'Post ID for post/meta targets.' ),
			'field' => self::string_prop( 'Post field such as post_content, post_excerpt, or post_title.' ),
			'meta_key' => self::string_prop( 'Meta key for a meta target.' ),
			'option_name' => self::string_prop( 'Option name for an option target.' ),
		);
		if ( $editing ) {
			$props['old_content'] = self::string_prop( 'Exact text to replace.', true );
			$props['new_content'] = self::string_prop( 'Replacement text.', true );
			$props['replace_all'] = self::boolean_prop( 'Replace every occurrence.' );
			$props['whole_word'] = self::boolean_prop( 'Match whole words only.' );
		} else {
			$props['pattern'] = self::string_prop( 'Text to search for.', true );
			$props['case_sensitive'] = self::boolean_prop( 'Use case-sensitive matching.' );
			$props['max_results'] = self::integer_prop( 'Maximum matches.' );
		}
		return $props;
	}

	private static function schema( $properties, $required = array() ) {
		foreach ( $properties as $key => $property ) {
			if ( ! empty( $property['_required'] ) && ! in_array( $key, $required, true ) ) {
				$required[] = $key;
			}
			unset( $property['_required'] );
			$properties[ $key ] = $property;
		}
		$out = array( 'type' => 'object', 'properties' => (object) $properties, 'additionalProperties' => false );
		if ( $required ) {
			$out['required'] = array_values( array_unique( $required ) );
		}
		return $out;
	}
	private static function string_prop( $description, $required = false ) { return array( 'type' => 'string', 'description' => $description, '_required' => $required ); }
	private static function integer_prop( $description, $required = false ) { return array( 'type' => 'integer', 'description' => $description, '_required' => $required ); }
	private static function boolean_prop( $description, $required = false ) { return array( 'type' => 'boolean', 'description' => $description, '_required' => $required ); }
	private static function array_prop( $description, $items = array(), $required = false ) { return array( 'type' => 'array', 'description' => $description, 'items' => (object) $items, '_required' => $required ); }

	public static function skills() {
		return array(
			'theme-redesign' => "Work read-first. Call site_info, create_draft_theme, list_files, then inspect only relevant files with read_file/search_files/get_file_outline. Make small edits with edit_file whenever possible. Check get_page_html and get_preview_url. Never publish_draft_theme until the user explicitly approves the preview. Preserve dynamic WordPress functions, menus, hooks, child-theme boundaries, accessibility, and responsive behavior.",
			'wpbakery' => "WPBakery content is stored as nested shortcodes in post_content. Read the target CPT/page via rest_api, preserve shortcode nesting, and use search_content/edit_content for precise changes. Avoid rebuilding unrelated rows. For styling, prefer scoped child-theme CSS. Clear cache after changes and inspect rendered HTML.",
			'elementor' => "Start with elementor_widgets and elementor_schema for widgets you will use. Save pages through elementor_save_page, not direct _elementor_data writes. Use stable element IDs, responsive settings, and valid root-element arrays. For Theme Builder templates use elementor_save_template and explicit conditions. Inspect frontend output afterward.",
			'content-management' => "Discover the post type and REST route first. Read current content and registered meta. Create new content as draft unless publication is explicitly requested. Use edit_content for small replacements rather than rewriting entire documents. Preserve slugs, taxonomy relationships, featured media, builder metadata, and custom fields.",
			'debugging' => "Reproduce and inspect before changing anything. Use site_info, discover_rest_routes, get_page_html, search_files, read_file, audit_log, and safe WP-CLI reads. Form a narrow hypothesis, make the smallest reversible change in a draft theme or draft content, then verify. Do not disable security or caching globally merely to hide a symptom.",
			'performance' => "Measure first. Inspect active plugins/theme, rendered HTML, image sizes, autoloaded options, cache state, and duplicate assets using safe reads. Prefer targeted fixes: image optimization, conditional enqueueing, cache purge, query reduction, and removing duplicate scripts. Do not delete plugins or database data without explicit approval and a backup.",
			'security' => "Use read-only inspection first. Never expose credentials, salts, tokens, application passwords, private keys, or personal data. Keep plugins/themes/core updated through normal WordPress flows. Do not weaken capability checks, file restrictions, nonces, HTTPS, or firewalls. For suspected compromise, preserve evidence and create backups before cleanup.",
			'woocommerce' => "Discover WooCommerce REST routes and product schemas first. Read products/orders before writes. Preserve IDs, SKUs, taxonomies, variations, prices, stock rules, and currency formatting. Bulk updates should be chunked and validated on a small sample. Never delete orders or customers through generic REST calls.",
		);
	}
}
