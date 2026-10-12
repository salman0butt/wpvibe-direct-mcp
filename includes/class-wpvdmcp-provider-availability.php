<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Adds non-secret provider readiness hints to public MCP tool definitions.
 * Discovery checks only registration state; it never executes a provider.
 */
final class WPVDMCP_Provider_Availability {
	const META_KEY = 'io.wpvibe.direct/provider';

	public static function decorate_tools( $tools ) {
		$out = array();
		foreach ( (array) $tools as $tool ) {
			if ( ! is_array( $tool ) ) {
				$out[] = $tool;
				continue;
			}
			$name = isset( $tool['name'] ) ? (string) $tool['name'] : '';
			$contract = self::contract( $name );
			if ( null !== $contract ) {
				$tool['_meta'] = isset( $tool['_meta'] ) && is_array( $tool['_meta'] ) ? $tool['_meta'] : array();
				$tool['_meta'][ self::META_KEY ] = self::metadata( $contract );
			}
			$out[] = $tool;
		}
		return $out;
	}

	private static function contract( $tool_name ) {
		$local_required = array(
			'page_audit' => 'page_audit',
			'audit_page' => 'page_audit',
			'search_images' => 'search_images',
			'render_browser' => 'render_browser',
		);
		if ( isset( $local_required[ $tool_name ] ) ) {
			return array( 'scope' => 'local', 'provider' => $local_required[ $tool_name ], 'mode' => 'required' );
		}

		if ( 'get_page_html' === $tool_name ) {
			return array(
				'scope' => 'local',
				'provider' => 'render_browser',
				'mode' => 'optional_same_site_fallback',
				'fallback' => 'wpvibe_rendered_html_same_site',
				'externalUrlRequiresProvider' => true,
			);
		}

		$reference_required = array(
			'screenshot_page',
			'seedprod_compile_page',
			'connect_site',
			'list_sites',
			'remove_site',
			'get_profile',
			'start_fleet_job',
			'show_fleet_dashboard',
			'use_usage_reset',
		);
		if ( in_array( $tool_name, $reference_required, true ) ) {
			return array( 'scope' => 'reference', 'provider' => $tool_name, 'mode' => 'required' );
		}

		return null;
	}

	private static function metadata( $contract ) {
		$scope = isset( $contract['scope'] ) ? (string) $contract['scope'] : 'local';
		$provider = isset( $contract['provider'] ) ? (string) $contract['provider'] : '';
		$mode = isset( $contract['mode'] ) ? (string) $contract['mode'] : 'required';
		$meta = array(
			'backed' => true,
			'configured' => self::configured( $scope, $provider ),
			'mode' => $mode,
			'provider' => $provider,
			'scope' => $scope,
		);
		if ( 'required' === $mode ) {
			$meta['failClosed'] = true;
		}
		if ( isset( $contract['fallback'] ) ) {
			$meta['fallback'] = (string) $contract['fallback'];
		}
		if ( isset( $contract['externalUrlRequiresProvider'] ) ) {
			$meta['externalUrlRequiresProvider'] = (bool) $contract['externalUrlRequiresProvider'];
		}
		return $meta;
	}

	private static function configured( $scope, $provider ) {
		if ( 'reference' === $scope ) {
			if ( isset( $GLOBALS['wpvdmcp_reference_providers'][ $provider ] ) && is_callable( $GLOBALS['wpvdmcp_reference_providers'][ $provider ] ) ) {
				return true;
			}
			$filter = 'wpvdmcp_reference_' . $provider;
		} else {
			if ( isset( $GLOBALS['wpvdmcp_providers'][ $provider ] ) && is_callable( $GLOBALS['wpvdmcp_providers'][ $provider ] ) ) {
				return true;
			}
			$filter = 'wpvdmcp_' . $provider;
		}
		return function_exists( 'has_filter' ) && (bool) has_filter( $filter );
	}
}
