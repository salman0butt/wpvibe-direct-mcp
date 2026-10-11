<?php
require __DIR__ . '/bootstrap.php';

$GLOBALS['rest_dispatch_callback'] = function ( $request ) {
	return new WP_REST_Response(
		array(
			'json' => $request->get_json_params(),
			'params' => $request->get_params(),
		),
		200
	);
};

$result = WPVDMCP_Tools::dispatch(
	'POST',
	'/wp-abilities/v1/abilities/elementor/create-page/run',
	array(),
	array(
		'input' => array(
			'title' => 'WPVibe Direct MCP E2E',
			'post_type' => 'page',
		),
	)
);

unset( $GLOBALS['rest_dispatch_callback'] );

assert_true( ! is_wp_error( $result ), 'Abilities dispatch should return a response.' );
assert_same(
	array(
		'title' => 'WPVibe Direct MCP E2E',
		'post_type' => 'page',
	),
	isset( $result['data']['json']['input'] ) ? $result['data']['json']['input'] : null,
	'POST Abilities input must be available through WP_REST_Request::get_json_params().'
);

echo "PASS: Abilities POST input is encoded as a JSON request body.\n";
