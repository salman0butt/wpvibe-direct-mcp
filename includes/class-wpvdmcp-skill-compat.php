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
		$q = strtolower( trim( (string) $query ) );
		if ( '' === $q ) { return array(); }
		$terms = array();
		if ( preg_match( '/\s/', $q ) ) {
			$stop_words = array_flip( array( 'a', 'an', 'and', 'are', 'at', 'by', 'can', 'change', 'configure', 'create', 'do', 'edit', 'fix', 'for', 'from', 'how', 'i', 'in', 'is', 'manage', 'me', 'my', 'of', 'on', 'please', 'plugin', 'site', 'the', 'to', 'update', 'web', 'website', 'with', 'wordpress' ) );
			foreach ( preg_split( '/[^a-z0-9]+/', substr( $q, 0, 256 ), -1, PREG_SPLIT_NO_EMPTY ) as $term ) {
				if ( strlen( $term ) < 3 || isset( $stop_words[ $term ] ) ) { continue; }
				$terms[ $term ] = $term;
				if ( count( $terms ) >= 12 ) { break; }
			}
		}
		$matches = array();
		foreach ( WPVDMCP_Parity_Skills::metadata() as $row ) {
			$slug = strtolower( (string) ( $row['slug'] ?? '' ) );
			$title = strtolower( (string) ( $row['title'] ?? '' ) );
			$description = strtolower( (string) ( $row['description'] ?? '' ) );
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
			elseif ( 0 === strpos( $slug, $q ) ) { $score = 85; }
			elseif ( false !== strpos( $slug, $q ) ) { $score = 75; }
			elseif ( false !== strpos( $title, $q ) ) { $score = 65; }
			elseif ( '' !== $description && false !== strpos( $description, $q ) ) { $score = 45; }
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
		foreach ( $terms as $term ) {
			$score = 0;
			foreach ( is_array( $tags ) ? $tags : array() as $tag ) {
				if ( is_string( $tag ) && self::has_word( strtolower( $tag ), $term ) ) { $score = 40; break; }
			}
			if ( ! $score && self::has_word( $slug, $term ) ) { $score = 38; }
			if ( ! $score && self::has_word( $title, $term ) ) { $score = 36; }
			if ( ! $score && self::has_word( $description, $term ) ) { $score = 22; }
			if ( $score ) { $best = max( $best, $score ); ++$matched; }
		}
		return $best ? min( 44, $best + 2 * ( $matched - 1 ) ) : 0;
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
