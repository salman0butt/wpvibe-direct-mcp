<?php
require __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-wpvdmcp-abilities-compat.php';

set_test_routes(
	array(
		'/wp-abilities/v1/abilities' => array( 'GET' ),
	)
);
$GLOBALS['wp_current_user'] = 1;

$GLOBALS['rest_dispatch_callback'] = function ( $request ) {
	$route = $request->get_param( '_route' );
	if ( '/wp-abilities/v1/abilities/elementor/create-page' === $route ) {
		return new WP_REST_Response(
			array(
				'name' => 'elementor/create-page',
				'meta' => array(
					'annotations' => array(
						'readonly' => false,
						'destructive' => false,
						'idempotent' => false,
					),
				),
			),
			200
		);
	}
	if ( '/wp-abilities/v1/abilities/elementor/create-page/run' === $route ) {
		return new WP_REST_Response(
			array(
				'json' => $request->get_json_params(),
			),
			200
		);
	}
	return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'not found' ), 404 );
};

$input = array(
	'title' => 'WPVibe Direct MCP E2E',
	'post_type' => 'page',
);
$first = WPVDMCP_Abilities_Compat::execute(
	'run_ability',
	array(
		'ability_name' => 'elementor/create-page',
		'input' => $input,
	)
);
assert_same( 'approval_required', isset( $first['status'] ) ? $first['status'] : null, 'Ability mutation should require approval.' );
assert_true( ! empty( $first['approval_id'] ), 'Ability approval id should be returned.' );
assert_true( true === WPVDMCP_Approvals::approve( $first['approval_id'], 1 ), 'Ability approval should be accepted.' );

$second = WPVDMCP_Abilities_Compat::execute(
	'run_ability',
	array(
		'ability_name' => 'elementor/create-page',
		'input' => $input,
		'approval_id' => $first['approval_id'],
	)
);

unset( $GLOBALS['rest_dispatch_callback'] );

assert_true( ! is_wp_error( $second ), 'Approved ability should execute.' );
assert_same(
	$input,
	isset( $second['data']['json']['input'] ) ? $second['data']['json']['input'] : null,
	'Public run_ability bridge must expose POST input through WP_REST_Request::get_json_params().'
);

echo "PASS: Abilities compatibility bridge sends POST input as JSON.\n";
