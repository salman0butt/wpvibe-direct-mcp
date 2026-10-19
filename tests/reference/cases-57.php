<?php

parity_test('idless MCP tools/call never creates an upload ticket or drops its result', function() {
    $options_before = $GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = hash('sha256', 'test-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id'] = 1;
        $server = WPVDMCP_Server::instance();
        foreach (array('2026-07-28', '2025-11-25', '2024-11-05') as $version) {
            $payload = array('jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => array(
                'name' => 'request_upload', 'arguments' => array('title' => 'Must not mint ticket'),
            ));
            $before = array_keys(array_filter($GLOBALS['wp_options'], function($value, $key) {
                return 0 === strpos((string)$key, 'transient:wpvdmcp_upload_up_');
            }, ARRAY_FILTER_USE_BOTH));
            $response = $server->handle(test_request($payload, $version));
            $data = response_data($response);
            assert_same(400, $response->get_status(), 'idless tools/call should reject with HTTP 400 for '.$version);
            assert_same(-32600, $data['error']['code'] ?? null, 'invalid request error for '.$version);
            assert_true(array_key_exists('id', $data), 'invalid request error must use a null JSON-RPC id for '.$version);
            assert_same(null, $data['id'], 'idless request error id for '.$version);
            $after = array_keys(array_filter($GLOBALS['wp_options'], function($value, $key) {
                return 0 === strpos((string)$key, 'transient:wpvdmcp_upload_up_');
            }, ARRAY_FILTER_USE_BOTH));
            assert_same($before, $after, 'invalid tools/call must never create a media upload ticket for '.$version);
        }
    } finally { $GLOBALS['wp_options'] = $options_before; }
});

parity_test('legacy mixed batch rejects idless write before dispatch but preserves notifications and requests', function() {
    $options_before = $GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = hash('sha256', 'test-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id'] = 1;
        $batch = array(
            array('jsonrpc'=>'2.0', 'method'=>'notifications/initialized', 'params'=>array()),
            array('jsonrpc'=>'2.0', 'method'=>'tools/call', 'params'=>array('name'=>'request_upload', 'arguments'=>array())),
            array('jsonrpc'=>'2.0', 'id'=>'supported', 'method'=>'tools/list', 'params'=>array()),
        );
        $response = WPVDMCP_Server::instance()->handle(test_request($batch, '2024-11-05'));
        $data = response_data($response);
        assert_same(200, $response->get_status(), 'mixed legacy batch should return validation error and valid request');
        assert_same(2, count($data), 'no response for notification, one error for idless call and one result for id-bearing request');
        assert_same(-32600, $data[0]['error']['code'] ?? null, 'idless write rejected within batch');
        assert_true(array_key_exists('id',$data[0]) && null === $data[0]['id'], 'error response uses a null request id');
        assert_same('supported', $data[1]['id'] ?? null, 'valid later request retains its id');
        assert_true(!empty($data[1]['result']['tools']), 'valid list request still works after invalid call');
        foreach (array_keys($GLOBALS['wp_options']) as $key) {
            assert_true(0 !== strpos((string)$key, 'transient:wpvdmcp_upload_up_'), 'idless write in mixed batch must not mint upload ticket');
        }
    } finally { $GLOBALS['wp_options'] = $options_before; }
});

parity_test('id-bearing MCP tools/call remains usable and actual notifications return HTTP 202', function() {
    $options_before = $GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = hash('sha256', 'test-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id'] = 1;
        $request = array('jsonrpc'=>'2.0', 'id'=>'ticket-id', 'method'=>'tools/call', 'params'=>array(
            'name'=>'request_upload', 'arguments'=>array('title'=>'Real ticket'),
        ));
        $response = WPVDMCP_Server::instance()->handle(test_request($request, '2025-11-25'));
        $data = response_data($response);
        assert_same('ticket-id', $data['id'] ?? null, 'id-bearing call receives a result');
        assert_true(!empty($data['result']['structuredContent']['upload_id']), 'valid call returns a media ticket');
        $notification = WPVDMCP_Server::instance()->handle(test_request(array('jsonrpc'=>'2.0', 'method'=>'notifications/initialized', 'params'=>array()), '2025-11-25'));
        assert_same(202, $notification->get_status(), 'genuine notification remains accepted with no response');
        assert_same(null, response_data($notification), 'notification has no JSON-RPC result');
    } finally { $GLOBALS['wp_options'] = $options_before; }
});
