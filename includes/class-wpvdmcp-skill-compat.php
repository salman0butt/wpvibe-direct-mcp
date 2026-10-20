<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Public load_skill compatibility: catalog, name/query lookup and saved resources. */
final class WPVDMCP_Skill_Compat {
	public static function definition() {
		$slugs = array();
		foreach ( WPVDMCP_Parity_Skills::metadata() as $row ) {
			if ( ! empty( $row['slug'] ) ) { $slugs[] = (string) $row['slug']; }
		}
		$schema = array(
			'type' => 'object',
			'properties' => (object) array(
				'skill' => array( 'type' => 'string', 'enum' => array_values( array_unique( $slugs ) ), 'description' => 'Direct MCP skill identifier.' ),
				'name' => array( 'type' => 'string', 'description' => 'WPVibe-compatible skill name/slug.' ),
				'query' => array( 'type' => 'string', 'description' => 'Search skill slug, title, or description without loading every body.' ),
				'resource' => array( 'type' => 'string', 'description' => 'Exact saved reference filename to read from the selected local skill.' ),
			),
			'additionalProperties' => false,
		);
		return array( 'name' => 'load_skill', 'description' => 'List, search, load, or read a saved skill reference. No arguments returns bounded catalog metadata; name and skill are aliases; query returns metadata-only matches.', 'inputSchema' => $schema );
	}

	public static function execute( $args ) {
		$args = is_array( $args ) ? $args : array();
		$name = '';
		if ( ! empty( $args['name'] ) ) { $name = sanitize_key( $args['name'] ); }
		elseif ( ! empty( $args['skill'] ) ) { $name = sanitize_key( $args['skill'] ); }
		$query = isset( $args['query'] ) ? trim( (string) $args['query'] ) : '';
		$resource = isset( $args['resource'] ) ? sanitize_file_name( $args['resource'] ) : '';

		if ( '' === $name && '' === $query && '' === $resource ) {
			return array( 'skills' => WPVDMCP_Parity_Skills::metadata() );
		}

		if ( '' === $name && '' !== $query ) {
			$matches = self::search( $query );
			if ( '' === $resource ) { return array( 'query' => $query, 'matches' => $matches ); }
			$name = self::select_resource_match( $query, $matches );
			if ( '' === $name ) {
				return new WP_Error( 'skill_resource_ambiguous', 'resource lookup by query requires one exact or unambiguous skill match.', array( 'status' => 409, 'matches' => $matches ) );
			}
		}

		if ( '' === $name ) {
			return new WP_Error( 'invalid_skill_lookup', 'Provide skill/name, query, or no arguments for the catalog.', array( 'status' => 400 ) );
		}

		$loaded = WPVDMCP_Parity_Skills::load( $name );
		if ( is_wp_error( $loaded ) ) { return $loaded; }
		if ( '' === $resource ) { return $loaded; }
		return self::resource( $name, $loaded, $resource );
	}

	private static function search( $query ) {
		$q = strtolower( substr( trim( (string) $query ), 0, 256 ) );
		if ( '' === $q ) { return array(); }
		$catalog = WPVDMCP_Parity_Skills::metadata();
		// Explicit identifiers and user-owned titles always take priority over
		// inferred task scope, including a saved skill named after a platform.
		$exact_identifier = false;
		foreach ( $catalog as $candidate ) {
			if ( strtolower( (string) ( $candidate['slug'] ?? '' ) ) === $q || strtolower( (string) ( $candidate['title'] ?? '' ) ) === $q ) {
				$exact_identifier = true;
				break;
			}
		}
		// Avoid routing explicitly offsite work into a WordPress-only tool catalog.
		// A WordPress.com exclusion is not a denial that a self-hosted site uses WordPress.
		$scope_query = preg_replace( '/\b(?:not|no|non|outside|without)[\s-]+wordpress\.com\b/', ' ', $q );
		$not_wordpress = 1 === preg_match( '/\b(?:not|no|non|outside|without)(?:[\s-]+(?:a|an|use|using|of|on|the|is|built)){0,4}[\s-]+wordpress\b/', $scope_query );
		// Third-party services can also be CONTENT on WordPress pages. Named WP
		// integrations are positive context even when the query omits 'WordPress'.
		$wp_integration = 1 === preg_match( '/\b(?:woocommerce|elementor|gutenberg|amelia|wpforms|yoast|smash[\s-]+balloon|beaver[\s-]+builder|breakdance|bricks|divi|seedprod|wpbakery|kadence|generatepress|generateblocks|fluentcart|fluentcrm|cartflows|rank[\s-]*math|lifterlms|memberpress|duplicator|wpcode|seopress|aioseo|botiga|optinmonster|adtribes|pagelayer|elementskit)\b/', $scope_query );
		$wordpress_scope = ! $not_wordpress && ( self::has_phrase( $scope_query, 'wordpress' ) || $wp_integration );
		$external_platform = 1 === preg_match( '/\b(?:youtube|shopify|instagram|tiktok|facebook|linkedin|discord|slack)\b/', $q );
		$external_booking = 1 === preg_match( '/\b(?:hotel|conference)\b/', $q ) &&
			1 === preg_match( '/\b(?:booking|appointments?|reservations?|rooms?)\b/', $q );
		if ( ! $exact_identifier && ( $not_wordpress || ( ! $wordpress_scope && ( $external_platform || $external_booking ) ) ) ) { return array(); }
		$terms = array();
		if ( preg_match( '/\s/', $q ) ) {
			$stop_words = array_flip( array( 'a', 'all', 'an', 'and', 'are', 'at', 'by', 'can', 'change', 'configure', 'create', 'do', 'edit', 'email', 'fix', 'for', 'from', 'how', 'i', 'in', 'is', 'manage', 'me', 'my', 'of', 'on', 'page', 'please', 'plugin', 'site', 'the', 'to', 'update', 'web', 'website', 'with', 'wordpress' ) );
			foreach ( preg_split( '/[^a-z0-9]+/', substr( $q, 0, 256 ), -1, PREG_SPLIT_NO_EMPTY ) as $term ) {
				if ( strlen( $term ) < 3 || isset( $stop_words[ $term ] ) ) { continue; }
				$terms[ $term ] = $term;
				if ( count( $terms ) >= 12 ) { break; }
			}
		}
		$matches = array();
		$normalized_query = trim( preg_replace( '/[^a-z0-9]+/', ' ', $q ) );
		foreach ( $catalog as $row ) {
			$slug = strtolower( (string) ( $row['slug'] ?? '' ) );
			$title = strtolower( (string) ( $row['title'] ?? '' ) );
			$description = strtolower( (string) ( $row['description'] ?? '' ) );
			$slug_phrase = str_replace( '-', ' ', $slug );
			if ( $terms && 1 === preg_match( '/\b(?:not|no|without|avoid)(?:\s+(?:use|using|choose|install|enable|select|a|an|the|plugin)){0,3}\s+' . preg_quote( $slug_phrase, '/' ) . '\b/', $normalized_query ) ) { continue; }
			$tag_match = false;
			if ( isset( $row['tags'] ) && is_array( $row['tags'] ) ) {
				foreach ( $row['tags'] as $tag ) {
					if ( is_string( $tag ) && strtolower( $tag ) === $q ) {
						$tag_match = true;
						break;
					}
				}
			}
			$score = 0;
			if ( $slug === $q ) { $score = 100; }
			elseif ( $title === $q ) { $score = 95; }
			elseif ( $tag_match ) { $score = 90; }
			elseif ( false !== strpos( $slug, '-' ) && $q === str_replace( '-', '', $slug ) ) { $score = 93; }
			elseif ( $terms && self::has_phrase( $q, $slug_phrase ) && false !== strpos( $slug_phrase, ' ' ) ) { $score = 92; }
			elseif ( $terms && reset( $terms ) === $slug && 'merchant' !== $slug ) { $score = 89; }
			elseif ( $terms && false !== strpos( $slug, '-' ) && self::has_phrase( $q, str_replace( '-', '', $slug ) ) ) { $score = 88; }
			elseif ( $terms && self::has_phrase( $q, $slug_phrase ) ) { $score = 80 + min( 9, (int) floor( strlen( $slug ) / 2 ) ); }
			elseif ( $terms && self::has_phrase( $q, $title ) ) { $score = false !== strpos( $title, ' ' ) ? 87 : 79; }
			elseif ( 0 === strpos( $slug, $q ) ) { $score = 85; }
			elseif ( false !== strpos( $slug, $q ) ) { $score = 75; }
			elseif ( false !== strpos( $title, $q ) ) { $score = 65; }
			elseif ( '' !== $description && false !== strpos( $description, $q ) ) { $score = 45; }
			if ( $terms && $score < 87 && isset( $row['tags'] ) && is_array( $row['tags'] ) ) {
				foreach ( $row['tags'] as $tag ) {
					if ( is_string( $tag ) && false !== strpos( trim( $tag ), ' ' ) && self::has_phrase( $q, strtolower( $tag ) ) ) {
						$score = max( $score, 86 );
					}
				}
			}
			if ( ! $score && $terms ) { $score = self::term_score( $terms, $slug, $title, $description, $row['tags'] ?? array() ); }
			if ( ! $score ) { continue; }
			$copy = $row;
			unset( $copy['instructions'] );
			$copy['score'] = $score;
			$matches[] = $copy;
		}
		usort( $matches, function( $a, $b ) {
			$score = (int) ( $b['score'] ?? 0 ) - (int) ( $a['score'] ?? 0 );
			return 0 !== $score ? $score : strcmp( (string) ( $a['slug'] ?? '' ), (string) ( $b['slug'] ?? '' ) );
		} );
		return array_slice( $matches, 0, 20 );
	}

	/** Lower-confidence phrase fallback; full slug/title/tag/description matches always win. */
	private static function term_score( $terms, $slug, $title, $description, $tags ) {
		$best = 0;
		$matched = 0;
		$discriminative = 0;
		$ambiguous_terms = array_flip( array( 'api', 'image', 'images', 'live', 'saved', 'search', 'server', 'stock', 'video' ) );
		foreach ( $terms as $term ) {
			$score = 0;
			foreach ( is_array( $tags ) ? $tags : array() as $tag ) {
				if ( ! is_string( $tag ) ) { continue; }
				$normalized_tag = strtolower( $tag );
				if ( self::has_word( $normalized_tag, $term ) && ( false === strpos( trim( $normalized_tag ), ' ' ) || self::has_phrase( implode( ' ', $terms ), $normalized_tag ) ) ) { $score = 40; break; }
			}
			if ( ! $score && self::has_word( $slug, $term ) ) { $score = 38; }
			if ( ! $score && self::has_word( $title, $term ) ) { $score = 36; }
			if ( ! $score && self::has_word( $description, $term ) ) { $score = 22; }
			if ( $score ) {
				$best = max( $best, $score );
				++$matched;
				if ( ! isset( $ambiguous_terms[ $term ] ) ) { ++$discriminative; }
			}
		}
		return $best >= 36 && $discriminative ? min( 44, $best + 2 * ( $matched - 1 ) ) : 0;
	}

	private static function has_phrase( $query, $phrase ) {
		$normalized_query = ' ' . trim( preg_replace( '/[^a-z0-9]+/', ' ', $query ) ) . ' ';
		$normalized_phrase = trim( preg_replace( '/[^a-z0-9]+/', ' ', $phrase ) );
		return '' !== $normalized_phrase && false !== strpos( $normalized_query, ' ' . $normalized_phrase . ' ' );
	}

	/** Match a standalone task word, never an accidental substring like form in performance. */
	private static function has_word( $text, $term ) {
		return '' !== $text && 1 === preg_match( '/(^|[^a-z0-9])' . preg_quote( $term, '/' ) . '($|[^a-z0-9])/', $text );
	}

	private static function select_resource_match( $query, $matches ) {
		$q = sanitize_key( $query );
		foreach ( $matches as $row ) {
			if ( isset( $row['slug'] ) && sanitize_key( $row['slug'] ) === $q ) { return sanitize_key( $row['slug'] ); }
		}
		return 1 === count( $matches ) && ! empty( $matches[0]['slug'] ) ? sanitize_key( $matches[0]['slug'] ) : '';
	}

	private static function resource( $slug, $loaded, $name ) {
		$refs = isset( $loaded['reference_files'] ) && is_array( $loaded['reference_files'] ) ? $loaded['reference_files'] : array();
		foreach ( $refs as $ref ) {
			if ( ! is_array( $ref ) || (string) ( $ref['name'] ?? '' ) !== $name ) { continue; }
			$content = (string) ( $ref['content'] ?? '' );
			$max = WPVDMCP_Parity_Skills::MAX_REFERENCE_BYTES;
			$out = array(
				'name' => (string) $ref['name'],
				'mime_type' => (string) ( $ref['mime_type'] ?? 'text/plain' ),
				'content' => substr( $content, 0, $max ),
			);
			if ( strlen( $content ) > $max ) { $out['truncated'] = true; $out['full_bytes'] = strlen( $content ); }
			return array( 'skill' => $slug, 'metadata' => $loaded['metadata'] ?? array(), 'resource' => $out );
		}
		return new WP_Error( 'skill_resource_not_found', 'The selected skill does not contain that saved reference resource.', array( 'status' => 404, 'skill' => $slug, 'resource' => $name ) );
	}
}
