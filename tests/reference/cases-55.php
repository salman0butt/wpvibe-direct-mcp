<?php

parity_test('MCP bearer cannot mint and use an approval-panel token to approve its own write', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $routes_before = $GLOBALS['wp_routes'];
    $had_dispatch = array_key_exists('rest_dispatch_callback', $GLOBALS);
    $dispatch_before = $had_dispatch ? $GLOBALS['rest_dispatch_callback'] : null;
    try {
        $GLOBALS['wp_current_user'] = 0;
        unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
        set_test_routes(array('/wp/v2/pages/10' => array('POST')));
        $writes = 0;
        $GLOBALS['rest_dispatch_callback'] = function($request) use (&$writes) {
            $writes++;
            return new WP_REST_Response(array('id' => 10), 200);
        };
        $call = function($name, $args, $id) {
            $req = test_request(array(
                'jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call',
                'params' => array('name' => $name, 'arguments' => $args),
            ), '2025-11-25');
            $response = WPVDMCP_Server::instance()->handle($req);
            return WPVDMCP_MCP_App_Transport::filter_response($response, null, $req)->get_data()['result'] ?? array();
        };
        $write = array('method' => 'POST', 'path' => '/wp/v2/pages/10', 'body' => array('title' => 'Secure write'));
        $pending = $call('rest_api_write', $write, 'request-approval');
        $approval_id = $pending['structuredContent']['approval_id'] ?? '';
        assert_same('approval_required', $pending['structuredContent']['status'] ?? null, 'protected write must require human review');
        assert_true(is_string($approval_id) && $approval_id !== '', 'pending approval id is returned');
        assert_same(false, WPVDMCP_Approvals::bypass_enabled(), 'site approval bypass must remain disabled');

        $panel = $call('show_approval_panel', array('approval_id' => $approval_id), 'open-panel');
        assert_same('pending', $panel['structuredContent']['status'] ?? null, 'MCP bearer may inspect the pending approval');
        assert_true(!empty($panel['structuredContent']['approval_url']), 'secure WordPress admin approval URL remains available');
        $token = $panel['_meta']['wpvdmcpApprovalPanelToken'] ?? '';
        $decide = $call('approval_decide', array('approval_id' => $approval_id, 'decision' => 'approve', 'panel_token' => $token), 'decide-with-bearer');
        assert_same(true, $decide['isError'] ?? null, 'MCP bearer may not decide a protected write, even with an inline panel token');
        assert_same('pending', WPVDMCP_Approvals::status($approval_id)['status'] ?? null, 'approval must remain pending after bearer-only decision');

        $write['approval_id'] = $approval_id;
        $retry = $call('rest_api_write', $write, 'retry-before-admin');
        assert_same(true, $retry['isError'] ?? null, 'protected write cannot execute without independent wp-admin approval');
        assert_same(0, $writes, 'bearer-only workflow must not reach WordPress write dispatch');
        assert_true(empty($token), 'the MCP endpoint must never mint a bearer-reachable approval credential');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        $GLOBALS['wp_routes'] = $routes_before;
        if ($had_dispatch) $GLOBALS['rest_dispatch_callback'] = $dispatch_before;
        else unset($GLOBALS['rest_dispatch_callback']);
    }
});

parity_test('legacy approval-panel token state cannot authorize a later MCP decision', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    try {
        $GLOBALS['wp_current_user'] = 0;
        $pending = WPVDMCP_Approvals::request('delete_user', array('user_id' => 190), 'Delete user #190');
        $id = $pending['approval_id'];
        $secret = 'ui_legacy_secret_not_authority';
        set_transient('wpvdmcp_ui_panel_' . hash('sha256', $id), array(
            'token_hash' => hash('sha256', $secret), 'approval_id' => $id,
            'owner_user_id' => 1, 'expires_at' => time() + 900,
        ), 900);
        $result = WPVDMCP_MCP_Apps::execute('approval_decide', array(
            'approval_id' => $id, 'decision' => 'approve', 'panel_token' => $secret,
        ));
        assert_true(is_wp_error($result), 'preexisting panel token cannot authorize decisions without browser-admin identity');
        assert_same('approval_requires_browser', $result->get_error_code(), 'approval must be referred to independent wp-admin authorization');
        assert_same('pending', WPVDMCP_Approvals::status($id)['status'] ?? null, 'legacy minted token cannot transition approval state');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
    }
});

parity_test('WordPress browser approval shows the exact redacted change before requesting consent', function() {
    $options_before = $GLOBALS['wp_options'];
    try {
        $pending = WPVDMCP_Approvals::request('rest_api_write', array(
            'method' => 'POST', 'path' => '/wp/v2/pages/10',
            'body' => array('title' => 'Change only homepage heading', 'password' => 'NEVER_SHOW_APPROVAL_SECRET'),
        ), 'POST /wp/v2/pages/10');
        $method = new ReflectionMethod('WPVDMCP_Admin', 'render_approval');
        if (PHP_VERSION_ID < 80100) $method->setAccessible(true);
        $starting_level = ob_get_level();
        ob_start();
        try { $method->invoke(WPVDMCP_Admin::instance(), $pending['approval_id']); $html = ob_get_clean(); }
        finally { while (ob_get_level() > $starting_level) ob_end_clean(); }
        assert_true(strpos($html, 'Change only homepage heading') !== false, 'human approver must see actual redacted write payload');
        assert_true(strpos($html, 'NEVER_SHOW_APPROVAL_SECRET') === false, 'rendered review must never reveal secret values');
        assert_true(strpos($html, '[REDACTED]') !== false, 'secret fields are displayed as redacted');
        assert_true(strpos($html, 'wpvdmcp_approval') !== false, 'nonce-protected WordPress approval form still present');
    } finally { $GLOBALS['wp_options'] = $options_before; }
});
