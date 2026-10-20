<?php
/**
 * Deterministic routing-quality benchmark (no live WordPress site required).
 *
 * Usage: php tests/skill-routing-benchmark.php [--json] [--enforce]
 * Baselines reflect a curated set of intent examples, not production traffic.
 */
require __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-wpvdmcp-parity-skills.php';
require_once dirname( __DIR__ ) . '/includes/class-wpvdmcp-parity.php';

$fixture = require __DIR__ . '/fixtures/skill-routing-goldens.php';
$positive = isset( $fixture['positive'] ) && is_array( $fixture['positive'] ) ? $fixture['positive'] : array();
$negative = isset( $fixture['negative'] ) && is_array( $fixture['negative'] ) ? $fixture['negative'] : array();
$builtins = array_merge( array_keys( WPVDMCP_Skills::catalog() ), array_keys( WPVDMCP_Parity_Skills::extra_catalog() ) );
sort( $builtins );
$tested = array_keys( $positive );
sort( $tested );
if ( $tested !== $builtins ) {
	fwrite( STDERR, "Benchmark must cover exactly every built-in skill.\n" );
	exit( 2 );
}
$seen = array();
$total = 0;
$top1 = 0;
$top3 = 0;
$misses = array();
foreach ( $positive as $expected => $queries ) {
	if ( ! is_array( $queries ) || count( $queries ) !== 2 ) {
		fwrite( STDERR, "Each built-in skill needs two independent prompts: $expected\n" );
		exit( 2 );
	}
	foreach ( $queries as $query ) {
		if ( ! is_string( $query ) || strlen( trim( $query ) ) < 8 || isset( $seen[ strtolower( trim( $query ) ) ] ) ) {
			fwrite( STDERR, "Duplicate or unsuitable benchmark prompt: $query\n" );
			exit( 2 );
		}
		$seen[ strtolower( trim( $query ) ) ] = true;
		$result = WPVDMCP_Parity::execute( 'load_skill', array( 'query' => $query ) );
		if ( ! is_array( $result ) || ! isset( $result['matches'] ) || ! is_array( $result['matches'] ) ) {
			fwrite( STDERR, "Unexpected search result shape.\n" );
			exit( 2 );
		}
		$slugs = array();
		foreach ( $result['matches'] as $item ) {
			if ( ! is_array( $item ) || isset( $item['instructions'] ) || ! isset( $item['slug'] ) ) {
				fwrite( STDERR, "Search returned instructions or malformed metadata.\n" );
				exit( 2 );
			}
			$slugs[] = $item['slug'];
		}
		++$total;
		if ( isset( $slugs[0] ) && $slugs[0] === $expected ) { ++$top1; }
		if ( in_array( $expected, array_slice( $slugs, 0, 3 ), true ) ) { ++$top3; }
		if ( ! isset( $slugs[0] ) || $slugs[0] !== $expected ) {
			$misses[] = array( 'query' => $query, 'expected' => $expected, 'actual' => isset( $slugs[0] ) ? $slugs[0] : null, 'top3' => array_slice( $slugs, 0, 3 ) );
		}
	}
}
$negative_pass = 0;
$false_positives = array();
foreach ( $negative as $query ) {
	if ( ! is_string( $query ) || isset( $seen[ strtolower( trim( $query ) ) ] ) ) {
		fwrite( STDERR, "Duplicate or malformed negative benchmark query.\n" );
		exit( 2 );
	}
	$seen[ strtolower( trim( $query ) ) ] = true;
	$result = WPVDMCP_Parity::execute( 'load_skill', array( 'query' => $query ) );
	$matches = is_array( $result ) && isset( $result['matches'] ) && is_array( $result['matches'] ) ? $result['matches'] : null;
	if ( null === $matches ) {
		fwrite( STDERR, "Unexpected negative search result shape.\n" );
		exit( 2 );
	}
	if ( ! $matches ) { ++$negative_pass; }
	else { $false_positives[] = array( 'query' => $query, 'actual' => $matches[0]['slug'] ?? null ); }
}
if ( $total < 100 || count( $negative ) < 15 ) {
	fwrite( STDERR, "Benchmark requires 100+ positive and 15+ negative user requests.\n" );
	exit( 2 );
}
$metrics = array(
	'positive' => $total,
	'negative' => count( $negative ),
	'builtin_skills' => count( $builtins ),
	'top1_correct' => $top1,
	'top1_percent' => round( 100 * $top1 / $total, 1 ),
	'top3_correct' => $top3,
	'top3_percent' => round( 100 * $top3 / $total, 1 ),
	'no_match_correct' => $negative_pass,
	'no_match_percent' => round( 100 * $negative_pass / count( $negative ), 1 ),
	'misses' => $misses,
	'false_positives' => $false_positives,
);
if ( in_array( '--json', $argv, true ) ) {
	echo json_encode( $metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
} else {
	echo sprintf( "Skill routing: %d skills, %d positive, %d negative\n", count( $builtins ), $total, count( $negative ) );
	echo sprintf( "Top-1: %d/%d (%.1f%%); Top-3: %d/%d (%.1f%%); No-match: %d/%d (%.1f%%)\n", $top1, $total, $metrics['top1_percent'], $top3, $total, $metrics['top3_percent'], $negative_pass, count( $negative ), $metrics['no_match_percent'] );
	foreach ( $misses as $miss ) {
		echo sprintf( "MISS %s: expected=%s actual=%s\n", $miss['query'], $miss['expected'], $miss['actual'] ?? '(none)' );
	}
	foreach ( $false_positives as $miss ) {
		echo sprintf( "FALSE_POSITIVE %s: actual=%s\n", $miss['query'], $miss['actual'] ?? '(none)' );
	}
}
// Track quality, rather than just successful execution of the lookup tool.
if ( in_array( '--enforce', $argv, true ) && ( $metrics['top1_percent'] < 95 || $metrics['top3_percent'] < 97 || $metrics['no_match_percent'] < 100 ) ) {
	exit( 1 );
}
