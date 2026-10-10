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
			self::route_tool( 'read_file', 'Read a theme file or, when supported, a redacted read-only wp-content file. Theme reads use WPVibe active/draft semantics.', 'POST', '/wpvibe/v1/file/read', array(
				'scope' => self::enum_prop( array( 'theme', 'wp-content' ), 'Read scope. wp-content is read-only and requires WPVibe support.' ),
				'path' => self::string_prop( 'File path relative to the selected scope.', true ),
				'start_line' => self::integer_prop( 'First line, 1-based.' ),
				'end_line' => self::integer_prop( 'Last line, inclusive.' ),
			) ),
			self::route_tool( 'list_files', 'List files in WPVibe’s theme sandbox or redacted read-only wp-content scope.', 'GET', '/wpvibe/v1/file/list', array(
				'scope' => self::enum_prop( array( 'theme', 'wp-content' ), 'List scope.' ),
				'directory' => self::string_prop( 'Optional directory within wp-content scope.' ),
				'pattern' => self::string_prop( 'Optional WPVibe glob pattern. In 1.20.3 patterns match recursively within the selected root.' ),
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
			self::route_tool( 'write_file', 'Create or completely replace a file in WPVibe’s protected theme sandbox. PHP/SVG safety remains enforced by WPVibe.', 'POST', '/wpvibe/v1/file/write', array(
				'expected_source_hash' => self::string_prop( 'Optional 64-character compile-source SHA-256 returned by current WPVibe safety checks.' ),
				'path' => self::string_prop( 'Theme-relative file path.', true ),
				'content' => self::string_prop( 'Complete new file contents.', true ),
			) ),
			self::route_tool( 'delete_file', 'Delete a file from WPVibe’s draft-theme sandbox.', 'POST', '/wpvibe/v1/file/delete', array(
				'path' => self::string_prop( 'Theme-relative file path.', true ),
			) ),
			self::route_tool( 'search_content', 'Search within a post field, post meta value, or option before making a surgical edit.', 'POST', '/wpvibe/v1/content/search', self::content_props( false ) ),
			self::route_tool( 'edit_content', 'Surgically replace text inside a post field, post meta value, or allowed option with match-once safety by default.', 'POST', '/wpvibe/v1/content/edit', self::content_props( true ) ),
			self::route_tool( 'create_draft_theme', 'Clone the current theme into WPVibe’s isolated draft-theme sandbox.', 'POST', '/wpvibe/v1/draft-theme', array() ),
			self::route_tool( 'get_preview_url', 'Get a secure preview URL for the WPVibe draft theme.', 'GET', '/wpvibe/v1/draft-theme/preview', array() ),
			self::route_tool( 'publish_draft_theme', 'Publish the reviewed draft theme. Use only after preview and explicit user approval; current WPVibe can set aside live Site Editor customizations.', 'POST', '/wpvibe/v1/draft-theme/publish', array(
				'expected_source_hash' => self::string_prop( 'Optional current compile-source SHA-256 safety token.' ),
				'saved_customizations' => self::enum_prop( array( 'set_aside', 'keep' ), 'How live Site Editor customizations should be handled. Current WPVibe defaults to set_aside.' ),
			) ),
			self::route_tool( 'delete_draft_theme', 'Delete the current WPVibe draft theme without publishing.', 'POST', '/wpvibe/v1/draft-theme/delete', array() ),
			self::route_tool( 'run_wp_cli', 'Run an installed-WPVibe allowlisted WP-CLI-style command through native PHP dispatch. No shell/eval/eval-file execution is added by Direct MCP.', 'POST', '/wpvibe/v1/cli/run', array(
				'command' => self::string_prop( 'Allowlisted WP-CLI-style command.', true ),
				'confirm_write' => self::boolean_prop( 'Confirm a reversible write when WPVibe requires its second stage.' ),
			) ),
			self::route_tool( 'wp_cli_status', 'Check which native WP-CLI emulation features are available in the installed WPVibe version.', 'GET', '/wpvibe/v1/cli/status', array() ),
			self::local_tool( 'request_upload', 'Create a 30-minute, media-only one-time browser upload link for an image attached to the user device when the MCP client cannot transfer raw file bytes.', self::schema( array(
				'title' => self::string_prop( 'Optional attachment title.' ),
				'alt_text' => self::string_prop( 'Optional image alternative text.' ),
				'post_id' => self::integer_prop( 'Optional parent post ID.' ),
			) ) ),
			self::local_tool( 'check_upload', 'Check whether a browser upload request is waiting, ready, or expired and return safe Media Library attachment metadata.', self::schema( array(
				'upload_id' => self::string_prop( 'Upload ID returned by request_upload.', true ),
			), array( 'upload_id' ) ) ),
			self::route_tool( 'upload_media', 'Download a public HTTP(S) image URL into the WordPress media library through WPVibe’s SSRF/MIME/SVG protections. This is URL upload, not client-device upload.', 'POST', '/wpvibe/v1/upload-media', array(
				'url' => self::string_prop( 'Public HTTP(S) media URL.', true ),
				'title' => self::string_prop( 'Attachment title.' ),
				'alt_text' => self::string_prop( 'Image alternative text.' ),
				'post_id' => self::integer_prop( 'Optional parent post ID.' ),
			) ),
			self::route_tool( 'get_page_html', 'Fetch WPVibe server-rendered frontend HTML for a path. Do not assume this executes client-side JavaScript.', 'POST', '/wpvibe/v1/rendered-html', array(
				'path' => self::string_prop( 'Site-relative path such as /about/.' ),
			) ),
			self::route_tool( 'navigate', 'Ask WPVibe’s browser/live-reload helper to navigate the current admin user to a URL.', 'POST', '/wpvibe/v1/navigate', array(
				'url' => self::string_prop( 'URL on this WordPress site.', true ),
			) ),
			self::route_tool( 'create_classic_theme', 'Create a new classic WordPress theme through WPVibe’s scaffold and safety layer.', 'POST', self::preferred_classic_theme_route(), array(
				'theme_name' => self::string_prop( 'Human-readable theme name.', true ),
				'description' => self::string_prop( 'Theme description.' ),
			) ),
			self::route_tool( 'audit_log', 'Read WPVibe’s append-only activity/approval log.', 'GET', '/wpvibe/v1/audit-log', array(
				'limit' => self::integer_prop( 'Number of entries.' ),
				'offset' => self::integer_prop( 'Pagination offset.' ),
			) ),


			// WordPress 6.9+ Abilities API. Route availability is feature-detected.
			self::local_tool( 'discover_abilities', 'Discover REST-exposed WordPress Abilities with their schemas and annotations.', self::schema( array(
				'page' => self::integer_prop( 'Results page.' ),
				'per_page' => self::integer_prop( 'Results per page.' ),
				'category' => self::string_prop( 'Optional ability category.' ),
			) ), self::abilities_available() ),
			self::local_tool( 'get_ability_info', 'Get the schema, permissions metadata, and readonly/destructive/idempotent annotations for one WordPress Ability.', self::schema( array(
				'name' => self::string_prop( 'Ability name in namespace/ability format.', true ),
			), array( 'name' ) ), self::abilities_available() ),
			self::local_tool( 'run_ability', 'Run a REST-exposed WordPress Ability. Readonly abilities run immediately; write/destructive abilities require a browser-admin Direct MCP approval unless WPVibe Dangerously bypass approvals is already enabled by the site owner.', self::schema( array(
				'name' => self::string_prop( 'Ability name in namespace/ability format.', true ),
				'input' => array( 'description' => 'Ability input matching its declared JSON Schema.' ),
				'approval_id' => self::string_prop( 'One-time Direct MCP approval ID returned by an earlier call.' ),
			), array( 'name' ) ), self::abilities_available() ),

			// Elementor native routes.
			self::route_tool( 'elementor_widgets', 'List Elementor widgets and structural elements installed on the site.', 'GET', '/wpvibe/v1/elementor/widgets', array() ),
			self::route_tool( 'elementor_schema', 'Inspect the controls accepted by one Elementor widget or element.', 'GET', '/wpvibe/v1/elementor/schema', array(
				'slug' => self::string_prop( 'Elementor widget/element slug.', true ),
				'names' => self::string_prop( 'Optional comma-separated control names.' ),
				'prefix' => self::string_prop( 'Optional control-name prefix.' ),
			) ),
			self::route_tool( 'elementor_style_schema', 'Inspect common Elementor style controls.', 'GET', '/wpvibe/v1/elementor/style-schema', array(
				'names' => self::string_prop( 'Optional comma-separated control names.' ),
				'prefix' => self::string_prop( 'Optional control-name prefix.' ),
			) ),
			self::route_tool( 'elementor_save_page', 'Create or update an Elementor page using native Elementor document APIs.', 'POST', '/wpvibe/v1/elementor/save-page', array(
				'id' => self::integer_prop( 'Existing page ID; omit to create.' ),
				'title' => self::string_prop( 'Page title; required when creating.' ),
				'post_type' => self::string_prop( 'Post type, normally page.' ),
				'status' => self::enum_prop( array( 'draft', 'pending', 'private', 'publish' ), 'Target post status.' ),
				'template_type' => self::string_prop( 'Elementor document template type.' ),
				'data' => self::array_prop( 'Array of root Elementor elements.', array(), true ),
				'page_template' => self::string_prop( 'Optional WordPress page template such as elementor_canvas.' ),
			) ),
			self::route_tool( 'elementor_save_template', 'Create or update an Elementor Pro Theme Builder template and conditions.', 'POST', '/wpvibe/v1/elementor/save-template', array(
				'id' => self::integer_prop( 'Existing template ID; omit to create.' ),
				'title' => self::string_prop( 'Template title.' ),
				'type' => self::string_prop( 'Template type such as header, footer, single, archive, search-results, error-404, section, or popup.', true ),
				'status' => self::enum_prop( array( 'draft', 'publish' ), 'Target template status.' ),
				'data' => self::array_prop( 'Array of root Elementor elements.', array(), true ),
				'conditions' => self::array_prop( 'Elementor display conditions such as include/general.', array( 'type' => 'string' ) ),
			) ),

			// Beaver Builder native routes.
			self::route_tool( 'beaver_modules', 'List Beaver Builder modules available to native WPVibe saves.', 'GET', '/wpvibe/v1/beaver/modules', array() ),
			self::route_tool( 'beaver_schema', 'Inspect one Beaver Builder module schema.', 'GET', '/wpvibe/v1/beaver/schema', array(
				'slug' => self::string_prop( 'Beaver Builder module slug.', true ),
			) ),
			self::route_tool( 'beaver_save_page', 'Create or update a Beaver Builder page through WPVibe’s native Beaver save path.', 'POST', '/wpvibe/v1/beaver/save-page', array(
				'id' => self::integer_prop( 'Existing post ID; omit to create.' ),
				'title' => self::string_prop( 'Title for a new post.' ),
				'post_type' => self::string_prop( 'Target post type.' ),
				'status' => self::enum_prop( array( 'draft', 'pending', 'private', 'publish' ), 'Target status.' ),
				'data' => self::object_prop( 'Beaver Builder node map.', true ),
				'theme_layout_type' => self::string_prop( 'Optional Beaver Themer layout type.' ),
				'theme_layout_locations' => self::array_prop( 'Optional Beaver Themer locations.', array() ),
				'theme_layout_settings' => self::object_prop( 'Optional Beaver Themer settings.' ),
			) ),

			// Bricks native routes.
			self::route_tool( 'bricks_get_page', 'Read Bricks builder elements through WPVibe’s native integration.', 'GET', '/wpvibe/v1/bricks/get-page', array(
				'id' => self::integer_prop( 'Post ID.', true ),
			) ),
			self::route_tool( 'bricks_elements', 'List available Bricks element types.', 'GET', '/wpvibe/v1/bricks/elements', array() ),
			self::route_tool( 'bricks_save_page', 'Create or update a Bricks page through WPVibe’s native save/cache path.', 'POST', '/wpvibe/v1/bricks/save-page', array(
				'id' => self::integer_prop( 'Existing post ID; omit to create.' ),
				'title' => self::string_prop( 'Title for a new post.' ),
				'post_type' => self::string_prop( 'Target post type.' ),
				'status' => self::enum_prop( array( 'draft', 'pending', 'private', 'publish' ), 'Target status.' ),
				'elements' => self::array_prop( 'Flat Bricks element array.', array(), true ),
			) ),

			// Breakdance native routes.
			self::route_tool( 'breakdance_get_page', 'Read Breakdance tree data through WPVibe’s native integration.', 'GET', '/wpvibe/v1/breakdance/get-page', array(
				'id' => self::integer_prop( 'Post ID.', true ),
			) ),
			self::route_tool( 'breakdance_elements', 'List available Breakdance elements.', 'GET', '/wpvibe/v1/breakdance/elements', array() ),
			self::route_tool( 'breakdance_save_page', 'Create or update a Breakdance page through WPVibe’s native encoded tree and CSS refresh path.', 'POST', '/wpvibe/v1/breakdance/save-page', array(
				'id' => self::integer_prop( 'Existing post ID; omit to create.' ),
				'title' => self::string_prop( 'Title for a new post.' ),
				'post_type' => self::string_prop( 'Target post type.' ),
				'status' => self::enum_prop( array( 'draft', 'pending', 'private', 'publish' ), 'Target status.' ),
				'tree' => self::object_prop( 'Breakdance tree object.' ),
				'tree_json_string' => self::string_prop( 'Alternative exact JSON tree string.' ),
				'template' => self::enum_prop( array( 'blank_canvas', 'theme' ), 'Optional Breakdance page template.' ),
			) ),

			self::route_tool( 'code_snippet', 'Create or update a WPCode snippet through WPVibe’s dormant safety route. Executable snippets remain disabled; activation is human-only in wp-admin unless a genuine approved upstream flow is used.', 'POST', '/wpvibe/v1/code-snippet/dormant', self::snippet_props() ),


			array(
				'name' => 'show_approval_panel',
				'description' => 'Return the browser approval URL and current state for a Direct MCP approval. Inline hosted MCP App panels are not fabricated.',
				'inputSchema' => self::schema( array( 'approval_id' => self::string_prop( 'Direct MCP approval ID.', true ) ), array( 'approval_id' ) ),
			),
			array(
				'name' => 'check_approval_status',
				'description' => 'Check a Direct MCP browser approval status without changing it.',
				'inputSchema' => self::schema( array( 'approval_id' => self::string_prop( 'Direct MCP approval ID.', true ) ), array( 'approval_id' ) ),
			),

			array(
				'name' => 'rest_api',
				'description' => 'Call a registered WordPress REST API route internally as the token owner while preserving the destination route capability checks. Authentication/credential-management routes remain blocked.',
				'inputSchema' => self::schema( array(
					'method' => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), 'description' => 'HTTP method.' ),
					'path' => self::string_prop( 'REST route beginning with /, for example /wp/v2/pages/123.', true ),
					'params' => self::object_prop( 'Structured query parameters.' ),
					'body' => array( 'description' => 'JSON body object, or a JSON string for compatibility.', 'oneOf' => array( array( 'type' => 'object' ), array( 'type' => 'string' ) ) ),
					'fields' => self::array_prop( 'Optional selected response fields. Also forwarded as WordPress _fields on GET.', array( 'type' => 'string' ) ),
					'max_response_bytes' => self::integer_prop( 'Maximum encoded response size. Default 262144, hard cap 1048576 bytes.' ),
				), array( 'method', 'path' ) ),
			),
			array(
				'name' => 'discover_rest_routes',
				'description' => 'Discover registered WordPress REST routes, optionally filtered by namespace or text.',
				'inputSchema' => self::schema( array(
					'filter' => self::string_prop( 'Optional substring such as wc/v3, elementor, abilities, wp/v2, or wpvibe.' ),
					'max_results' => self::integer_prop( 'Maximum routes, default 100.' ),
				) ),
			),
			array(
				'name' => 'list_skills',
				'description' => 'List the built-in original workflow skills supplied by WPVibe Direct MCP.',
				'inputSchema' => self::schema( array() ),
			),
			array(
				'name' => 'load_skill',
				'description' => 'Load concise workflow instructions for a WordPress task before using tools.',
				'inputSchema' => self::schema( array(
					'skill' => array( 'type' => 'string', 'enum' => array_keys( WPVDMCP_Skills::catalog() ), 'description' => 'Skill identifier.' ),
				), array( 'skill' ) ),
			),
		);

		if ( WPVDMCP_Compatibility::route_prefix_exists( '/wpvibe/v1/op-receipt/' ) ) {
			$tools[] = array(
				'name' => 'check_operation_receipt',
				'description' => 'Check a WPVibe operation receipt by operation ID for reconciliation/status. This does not forge approval state.',
				'inputSchema' => self::schema( array( 'op_id' => self::string_prop( 'WPVibe operation ID.', true ) ), array( 'op_id' ) ),
			);
		}

		$available = array();
		foreach ( $tools as $tool ) {
			if ( isset( $tool['_available'] ) && ! $tool['_available'] ) {
				continue;
			}
			if ( empty( $tool['_route'] ) || WPVDMCP_Compatibility::route_exists( $tool['_route'] ) ) {
				unset( $tool['_route'], $tool['_method'], $tool['_available'] );
				$available[] = $tool;
			}
		}
		return $available;
	}

	public static function execute( $name, $arguments ) {
		$arguments = is_array( $arguments ) ? $arguments : array();
		if ( 'request_upload' === $name ) {
			return WPVDMCP_Upload::request( $arguments );
		}
		if ( 'check_upload' === $name ) {
			$upload_id = isset( $arguments['upload_id'] ) ? (string) $arguments['upload_id'] : '';
			return WPVDMCP_Upload::check( $upload_id );
		}
		if ( 'discover_abilities' === $name ) {
			return self::discover_abilities( $arguments );
		}
		if ( 'get_ability_info' === $name ) {
			return self::get_ability_info( $arguments );
		}
		if ( 'run_ability' === $name ) {
			return self::run_ability( $arguments );
		}
		if ( 'show_approval_panel' === $name || 'check_approval_status' === $name ) {
			$approval_id = isset( $arguments['approval_id'] ) ? (string) $arguments['approval_id'] : '';
			return WPVDMCP_Approvals::status( $approval_id );
		}
		if ( 'rest_api' === $name ) {
			return self::rest_api( $arguments );
		}
		if ( 'discover_rest_routes' === $name ) {
			return self::discover_routes( $arguments );
		}
		if ( 'list_skills' === $name ) {
			return array( 'skills' => WPVDMCP_Skills::list_metadata() );
		}
		if ( 'load_skill' === $name ) {
			$skill = isset( $arguments['skill'] ) ? sanitize_key( $arguments['skill'] ) : '';
			return WPVDMCP_Skills::load( $skill );
		}
		if ( 'check_operation_receipt' === $name ) {
			return self::operation_receipt( $arguments );
		}

		$map = self::execution_map();
		if ( ! isset( $map[ $name ] ) ) {
			return new WP_Error( 'unknown_tool', 'Unknown MCP tool: ' . $name, array( 'status' => 404 ) );
		}
		$route = $map[ $name ][0];
		$method = $map[ $name ][1];
		if ( ! WPVDMCP_Compatibility::route_exists( $route ) ) {
			return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose the REST route required for this tool.', array( 'status' => 404, 'route' => $route ) );
		}
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
			'create_classic_theme' => array( self::preferred_classic_theme_route(), 'POST' ),
			'audit_log' => array( '/wpvibe/v1/audit-log', 'GET' ),
			'elementor_widgets' => array( '/wpvibe/v1/elementor/widgets', 'GET' ),
			'elementor_schema' => array( '/wpvibe/v1/elementor/schema', 'GET' ),
			'elementor_style_schema' => array( '/wpvibe/v1/elementor/style-schema', 'GET' ),
			'elementor_save_page' => array( '/wpvibe/v1/elementor/save-page', 'POST' ),
			'elementor_save_template' => array( '/wpvibe/v1/elementor/save-template', 'POST' ),
			'beaver_modules' => array( '/wpvibe/v1/beaver/modules', 'GET' ),
			'beaver_schema' => array( '/wpvibe/v1/beaver/schema', 'GET' ),
			'beaver_save_page' => array( '/wpvibe/v1/beaver/save-page', 'POST' ),
			'bricks_get_page' => array( '/wpvibe/v1/bricks/get-page', 'GET' ),
			'bricks_elements' => array( '/wpvibe/v1/bricks/elements', 'GET' ),
			'bricks_save_page' => array( '/wpvibe/v1/bricks/save-page', 'POST' ),
			'breakdance_get_page' => array( '/wpvibe/v1/breakdance/get-page', 'GET' ),
			'breakdance_elements' => array( '/wpvibe/v1/breakdance/elements', 'GET' ),
			'breakdance_save_page' => array( '/wpvibe/v1/breakdance/save-page', 'POST' ),
			'code_snippet' => array( '/wpvibe/v1/code-snippet/dormant', 'POST' ),
		);
	}

	private static function preferred_classic_theme_route() {
		return WPVDMCP_Compatibility::route_exists( '/wpvibe/v1/create-classic-theme-safe' ) ? '/wpvibe/v1/create-classic-theme-safe' : '/wpvibe/v1/create-classic-theme';
	}

	private static function operation_receipt( $args ) {
		$op_id = isset( $args['op_id'] ) ? (string) $args['op_id'] : '';
		if ( ! preg_match( '/^[a-z0-9_.:]+$/i', $op_id ) ) {
			return new WP_Error( 'invalid_op_id', 'Operation ID contains invalid characters.', array( 'status' => 400 ) );
		}
		if ( ! WPVDMCP_Compatibility::route_prefix_exists( '/wpvibe/v1/op-receipt/' ) ) {
			return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose operation receipts.', array( 'status' => 404 ) );
		}
		return self::dispatch( 'GET', '/wpvibe/v1/op-receipt/' . rawurlencode( $op_id ) );
	}

	private static function abilities_available() {
		$version = WPVDMCP_Compatibility::wordpress_version();
		return WPVDMCP_Compatibility::route_exists( '/wp-abilities/v1/abilities' ) && ( class_exists( 'WP_Ability' ) || ( $version && version_compare( $version, '6.9', '>=' ) ) );
	}

	private static function validate_ability_name( $name ) {
		$name = (string) $name;
		return preg_match( '/^[A-Za-z0-9-]+\/[A-Za-z0-9-]+$/', $name ) ? $name : '';
	}

	private static function discover_abilities( $args ) {
		if ( ! self::abilities_available() ) {
			return new WP_Error( 'abilities_unavailable', 'WordPress Abilities REST API requires WordPress 6.9+ and an exposed /wp-abilities/v1/abilities route.', array( 'status' => 501, 'wordpress_version' => WPVDMCP_Compatibility::wordpress_version() ) );
		}
		$query = array();
		if ( isset( $args['page'] ) ) { $query['page'] = max( 1, absint( $args['page'] ) ); }
		if ( isset( $args['per_page'] ) ) { $query['per_page'] = max( 1, min( 100, absint( $args['per_page'] ) ) ); }
		if ( ! empty( $args['category'] ) ) { $query['category'] = sanitize_key( $args['category'] ); }
		return self::dispatch( 'GET', '/wp-abilities/v1/abilities', $query );
	}

	private static function get_ability_info( $args ) {
		if ( ! self::abilities_available() ) {
			return new WP_Error( 'abilities_unavailable', 'WordPress Abilities REST API requires WordPress 6.9+.', array( 'status' => 501 ) );
		}
		$name = self::validate_ability_name( isset( $args['name'] ) ? $args['name'] : '' );
		if ( ! $name ) {
			return new WP_Error( 'invalid_ability_name', 'Ability name must use namespace/ability format.', array( 'status' => 400 ) );
		}
		return self::dispatch( 'GET', '/wp-abilities/v1/abilities/' . $name );
	}

	private static function run_ability( $args ) {
		$name = self::validate_ability_name( isset( $args['name'] ) ? $args['name'] : '' );
		if ( ! $name ) {
			return new WP_Error( 'invalid_ability_name', 'Ability name must use namespace/ability format.', array( 'status' => 400 ) );
		}
		$info = self::get_ability_info( array( 'name' => $name ) );
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		$ability = isset( $info['data'] ) && is_array( $info['data'] ) ? $info['data'] : array();
		$annotations = isset( $ability['meta']['annotations'] ) && is_array( $ability['meta']['annotations'] ) ? $ability['meta']['annotations'] : array();
		$readonly = ! empty( $annotations['readonly'] );
		$destructive = ! empty( $annotations['destructive'] );
		$idempotent = ! empty( $annotations['idempotent'] );
		$method = $readonly ? 'GET' : ( $destructive && $idempotent ? 'DELETE' : 'POST' );
		$input = array_key_exists( 'input', $args ) ? $args['input'] : null;
		$approval_payload = array( 'name' => $name, 'input' => $input, 'method' => $method );

		if ( ! $readonly && ! WPVDMCP_Approvals::bypass_enabled() ) {
			$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			if ( ! $approval_id ) {
				$approval = WPVDMCP_Approvals::request( 'run_ability', $approval_payload, sprintf( '%s ability %s', $method, $name ) );
				if ( is_wp_error( $approval ) ) { return $approval; }
				return array_merge( $approval, array( 'status' => 'approval_required', 'approval_status' => isset( $approval['status'] ) ? $approval['status'] : 'pending', 'ability' => $name, 'annotations' => $annotations ) );
			}
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'run_ability', $approval_payload );
			if ( is_wp_error( $consumed ) ) { return $consumed; }
		}

		$route = '/wp-abilities/v1/abilities/' . $name . '/run';
		if ( 'GET' === $method || 'DELETE' === $method ) {
			return self::dispatch( $method, $route, array( 'input' => $input ) );
		}
		return self::dispatch( 'POST', $route, array(), array( 'input' => $input ) );
	}

	private static function rest_api( $args ) {
		$method = isset( $args['method'] ) ? strtoupper( sanitize_text_field( $args['method'] ) ) : 'GET';
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return new WP_Error( 'invalid_method', 'Unsupported REST method.', array( 'status' => 400 ) );
		}
		$path = isset( $args['path'] ) ? (string) $args['path'] : '';
		$parsed = wp_parse_url( $path, PHP_URL_PATH );
		$path = '/' . ltrim( false !== $parsed && null !== $parsed ? $parsed : $path, '/' );
		if ( 0 === strpos( $path, '/wp-json/' ) ) {
			$path = substr( $path, 8 );
		}
		if ( self::is_blocked_rest_path( $path ) ) {
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

		$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? array_values( array_filter( array_map( 'sanitize_key', $args['fields'] ) ) ) : array();
		if ( 'GET' === $method && $fields ) {
			$params['_fields'] = implode( ',', $fields );
		}
		$result = self::dispatch( $method, $path, $params, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $fields ) {
			$result['data'] = self::select_fields( $result['data'], $fields );
		}
		$max = isset( $args['max_response_bytes'] ) ? absint( $args['max_response_bytes'] ) : 262144;
		$max = max( 1024, min( 1048576, $max ) );
		$encoded = wp_json_encode( $result['data'] );
		if ( is_string( $encoded ) && strlen( $encoded ) > $max ) {
			return new WP_Error( 'response_too_large', 'The REST response exceeds the Direct MCP response limit. Request fewer records or use fields/_fields.', array( 'status' => 413, 'bytes' => strlen( $encoded ), 'limit' => $max ) );
		}
		return $result;
	}

	private static function is_blocked_rest_path( $path ) {
		$lower = strtolower( $path );
		if ( 0 === strpos( $lower, '/wpvibe-direct/v1/' ) ) {
			return true;
		}
		if ( false !== strpos( $lower, '/application-passwords' ) ) {
			return true;
		}
		return false;
	}

	private static function select_fields( $data, $fields ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
		if ( $is_list ) {
			$out = array();
			foreach ( $data as $row ) {
				$out[] = self::select_fields( $row, $fields );
			}
			return $out;
		}
		$out = array();
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$out[ $field ] = $data[ $field ];
			}
		}
		return $out;
	}

	private static function discover_routes( $args ) {
		$filter = isset( $args['filter'] ) ? strtolower( sanitize_text_field( $args['filter'] ) ) : '';
		$max = isset( $args['max_results'] ) ? max( 1, min( 500, absint( $args['max_results'] ) ) ) : 100;
		$out = array();
		foreach ( WPVDMCP_Compatibility::routes() as $route => $handlers ) {
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

	private static function local_tool( $name, $description, $schema, $available = true ) {
		return array( 'name' => $name, 'description' => $description, 'inputSchema' => $schema, '_available' => (bool) $available );
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
			$props['replace_all'] = self::boolean_prop( 'Replace every occurrence. Default false keeps match-once safety.' );
			$props['whole_word'] = self::boolean_prop( 'Match whole words only.' );
		} else {
			$props['pattern'] = self::string_prop( 'Text to search for.', true );
			$props['case_sensitive'] = self::boolean_prop( 'Use case-sensitive matching.' );
			$props['max_results'] = self::integer_prop( 'Maximum matches.' );
		}
		return $props;
	}

	private static function snippet_props() {
		return array(
			'action' => self::enum_prop( array( 'create', 'update' ), 'Create or update a dormant WPCode snippet.' ),
			'id' => self::integer_prop( 'Existing snippet ID for update.' ),
			'code' => self::string_prop( 'Exact snippet source code.', true ),
			'title' => self::string_prop( 'Snippet title.', true ),
			'code_type' => self::string_prop( 'WPCode code type.', true ),
			'location' => self::string_prop( 'WPCode location.', true ),
			'insert_method' => self::string_prop( 'WPCode insert method, default auto.' ),
		);
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
	private static function object_prop( $description, $required = false ) { return array( 'type' => 'object', 'description' => $description, '_required' => $required ); }
	private static function enum_prop( $values, $description, $required = false ) { return array( 'type' => 'string', 'enum' => array_values( $values ), 'description' => $description, '_required' => $required ); }

	public static function skills() {
		$out = array();
		foreach ( WPVDMCP_Skills::catalog() as $slug => $meta ) {
			$loaded = WPVDMCP_Skills::load( $slug );
			if ( ! is_wp_error( $loaded ) ) {
				$out[ $slug ] = $loaded['instructions'];
			}
		}
		return $out;
	}
}
