<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Machine-readable audited WPVibe reference surface, isolated from execution adapters. */
final class WPVDMCP_Reference_Manifest {
	public static function audited_tools() {
		return array(
			'audit_page','check_approval_status','check_upload','code_snippet','connect_site','create_classic_theme','create_draft_theme',
			'delete_draft_theme','delete_file','discover_abilities','edit_file','get_ability_info','get_file_outline','get_page_html',
			'get_preview_url','get_profile','list_files','list_sites','load_skill','navigate','publish_draft_theme','read_file','remove_site',
			'request_upload','rest_api','rest_api_write','run_ability','run_wp_cli','save_skill','search_files','search_images','show_approval_panel',
			'show_fleet_dashboard','site_info','start_fleet_job','upload_media','write_file','use_usage_reset'
		);
	}

	public static function build( $hosted_provider_tools ) {
		$provider = array_merge( $hosted_provider_tools, array( 'search_images' ) );
		$local = array( 'audit_page','check_approval_status','check_upload','discover_abilities','get_ability_info','load_skill','request_upload','rest_api','rest_api_write','run_ability','save_skill','show_approval_panel' );
		$tools = array();
		foreach ( self::audited_tools() as $name ) {
			$status = in_array( $name, $provider, true ) ? 'provider_backed' : ( in_array( $name, $local, true ) ? 'local_equivalent' : 'native_route' );
			$tools[] = array(
				'name' => $name,
				'status' => $status,
				'implementation' => 'provider_backed' === $status ? 'user-owned provider; no WPVibe private credentials bundled' : ( 'local_equivalent' === $status ? 'Direct MCP safe equivalent/alias' : 'installed WPVibe/WordPress route' ),
				'source' => 'audited public WPVibe MCP surface 2026-10-10',
			);
		}
		return array(
			'audited_at' => '2026-10-10',
			'upstream_version' => '1.20.3',
			'upstream_commit' => '8f303926ae11179e38bc0ecf2a87e3cbd18984c6',
			'reference_sources' => array(
				'features' => 'https://wpvibe.ai/features/',
				'tools_reference' => 'https://wpvibe.ai/docs/tools-reference/',
				'cookbook' => 'https://wpvibe.ai/cookbook/',
				'works_with_ai' => 'https://wpvibe.ai/works-with/',
				'upstream_github' => 'https://github.com/awesomemotive/wpvibe-ai-mcp/tree/8f303926ae11179e38bc0ecf2a87e3cbd18984c6',
			),
			'features_page' => array(
				'full_rest_api','wp_cli','theme_builder','draft_preview_publish','site_intelligence','lighthouse','plugin_abilities','images_media',
				'live_reload','saved_skills','interactive_panels','validated_block_output','bulk_operations_sql','safe_code_snippets','every_site','editable_fields',
			),
			'cookbook_integrations' => array(
				'aioseo','charitable','duplicator','easy-digital-downloads','fluentcart','fluentcommunity','fluentcrm','lifterlms','memberpress','merchant',
				'pushengage','smash-balloon','woocommerce','wpcode','wpforms','yoast-seo','beaver-builder','breakdance','bricks','divi','elementor','generateblocks',
				'seedprod','botiga','generatepress','kadence',
			),
			'works_with_ai_integrations' => array(
				'abilities-plugin','rank-math','aioseo','seopress','yoast-seo','smash-balloon','memberpress','charitable','duplicator','pushengage',
				'easy-digital-downloads','lifterlms','wpforms','kit-convertkit','modern-cart','cartflows','pagelayer','elementskit','amelia','adtribes-product-feed',
				'fluentcart','fluentcommunity','fluentcrm','merchant','woocommerce','wpcode','sugar-calendar','optinmonster','botiga','elementor','beaver-builder',
				'bricks','breakdance','divi','seedprod','generatepress','generateblocks','kadence',
			),
			'tools' => $tools,
			'internal_not_tools' => array(
				'/wpvibe/v1/ping',
				'/wpvibe/v1/health',
				'/wpvibe/v1/connection-check-challenge',
				'/wpvibe/v1/audit-log/record',
				'/wpvibe/v1/cli/run-approved',
				'/wpvibe/v1/authorize',
				'/wpvibe/v1/authorize/preflight',
				'/wpvibe/v1/connection-status',
				'/wpvibe/v1/op-proof/check',
				'/wpvibe/v1/code-snippet',
				'/wpvibe/v1/builder-login',
				'/wpvibe/v1/detached/run',
				'/wpvibe/v1/self-update/health',
				'/wpvibe/v1/self-update/run',
			),
			'model_route_tools' => array(
				'/wpvibe/v1/site-info' => 'site_info',
				'/wpvibe/v1/registered-meta' => 'registered_meta',
				'/wpvibe/v1/file/read' => 'read_file',
				'/wpvibe/v1/file/list' => 'list_files',
				'/wpvibe/v1/file/search' => 'search_files',
				'/wpvibe/v1/file/outline' => 'get_file_outline',
				'/wpvibe/v1/file/edit' => 'edit_file',
				'/wpvibe/v1/file/write' => 'write_file',
				'/wpvibe/v1/file/delete' => 'delete_file',
				'/wpvibe/v1/content/search' => 'search_content',
				'/wpvibe/v1/content/edit' => 'edit_content',
				'/wpvibe/v1/draft-theme' => 'create_draft_theme (POST); delete_draft_theme legacy DELETE',
				'/wpvibe/v1/draft-theme/preview' => 'get_preview_url',
				'/wpvibe/v1/draft-theme/publish' => 'publish_draft_theme',
				'/wpvibe/v1/draft-theme/delete' => 'delete_draft_theme',
				'/wpvibe/v1/cli/run' => 'run_wp_cli',
				'/wpvibe/v1/cli/status' => 'wp_cli_status',
				'/wpvibe/v1/upload-media' => 'upload_media',
				'/wpvibe/v1/rendered-html' => 'get_page_html fallback',
				'/wpvibe/v1/navigate' => 'navigate',
				'/wpvibe/v1/audit-log' => 'audit_log',
				'/wpvibe/v1/op-receipt/{op_id}' => 'check_operation_receipt',
				'/wpvibe/v1/create-classic-theme' => 'create_classic_theme legacy alias',
				'/wpvibe/v1/create-classic-theme-safe' => 'create_classic_theme',
				'/wpvibe/v1/last-change' => 'get_last_change / live_reload_status',
				'/wpvibe/v1/elementor/widgets' => 'elementor_widgets',
				'/wpvibe/v1/elementor/schema' => 'elementor_schema',
				'/wpvibe/v1/elementor/style-schema' => 'elementor_style_schema',
				'/wpvibe/v1/elementor/save-page' => 'elementor_save_page',
				'/wpvibe/v1/elementor/save-template' => 'elementor_save_template',
				'/wpvibe/v1/beaver/modules' => 'beaver_modules',
				'/wpvibe/v1/beaver/schema' => 'beaver_schema',
				'/wpvibe/v1/beaver/save-page' => 'beaver_save_page',
				'/wpvibe/v1/bricks/get-page' => 'bricks_get_page',
				'/wpvibe/v1/bricks/elements' => 'bricks_elements',
				'/wpvibe/v1/bricks/save-page' => 'bricks_save_page',
				'/wpvibe/v1/breakdance/get-page' => 'breakdance_get_page',
				'/wpvibe/v1/breakdance/elements' => 'breakdance_elements',
				'/wpvibe/v1/breakdance/save-page' => 'breakdance_save_page',
				'/wpvibe/v1/code-snippet/dormant' => 'code_snippet',
			),
			'site_local_extensions' => array( 'get_last_change', 'seedprod_compile_page', 'screenshot_page', 'reference_parity_manifest' ),
			'mcp' => array(
				'primary_protocol' => '2026-07-28',
				'legacy_protocols' => array( '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' ),
				'transport' => 'stateless-streamable-http-json',
				'auth' => array( 'bearer', 'x-wpvibe-direct-token', 'query-token-compatibility' ),
				'primitives' => array( 'tools', 'prompts', 'resources' ),
				'extensions' => array( 'mcp-apps' ),
			),
		);
	}
}
