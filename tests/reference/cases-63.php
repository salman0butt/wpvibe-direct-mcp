<?php
require_once dirname(__DIR__, 2) . '/includes/class-wpvdmcp-mcp-apps.php';

parity_test('modern MCP Apps resource reads provide complete result and private cache hints', function() {
    foreach (array(WPVDMCP_MCP_Apps::APPROVAL_URI, WPVDMCP_MCP_Apps::UPLOAD_URI) as $uri) {
        $payload = array('jsonrpc'=>'2.0', 'id'=>'read-modern', 'method'=>'resources/read', 'params'=>array('uri'=>$uri));
        $request = test_request($payload, WPVDMCP_Server::MODERN_PROTOCOL);
        $response = WPVDMCP_Server::instance()->handle($request);
        $filtered = WPVDMCP_MCP_App_Transport::filter_response($response, null, $request);
        $result = response_data($filtered)['result'] ?? array();
        assert_same('complete', $result['resultType'] ?? null, 'Modern resource response needs resultType');
        assert_same(0, $result['ttlMs'] ?? null, 'UI resource must not be cached');
        assert_same('private', $result['cacheScope'] ?? null, 'UI resources must not use a shared cache');
        assert_same($uri, $result['contents'][0]['uri'] ?? null, 'Resource URI must be preserved');
        assert_same(WPVDMCP_MCP_Apps::MIME_TYPE, $result['contents'][0]['mimeType'] ?? null, 'MCP App MIME type');
        assert_true(!empty($result['contents'][0]['text']), 'MCP App content preserved');
    }
});

parity_test('legacy MCP Apps resource reads retain their original response shape', function() {
    $payload = array('jsonrpc'=>'2.0', 'id'=>'read-legacy', 'method'=>'resources/read', 'params'=>array('uri'=>WPVDMCP_MCP_Apps::APPROVAL_URI));
    $request = test_request($payload, '2025-11-25');
    $filtered = WPVDMCP_MCP_App_Transport::filter_response(WPVDMCP_Server::instance()->handle($request), null, $request);
    $result = response_data($filtered)['result'] ?? array();
    assert_true(isset($result['contents'][0]['text']), 'Legacy resource content returned');
    assert_true(!isset($result['resultType']), 'Legacy resource response has no discriminator');
    assert_true(!isset($result['ttlMs']), 'Legacy resource response does not add modern cache hints');
    assert_true(!isset($result['cacheScope']), 'Legacy resource response does not add modern cache hints');
});

parity_test('modern generic RPC results include required resultType', function() {
    $payload = array('jsonrpc'=>'2.0', 'id'=>'prompt-modern', 'method'=>'prompts/get', 'params'=>array('name'=>'setup'));
    $request = test_request($payload, WPVDMCP_Server::MODERN_PROTOCOL);
    $result = response_data(WPVDMCP_Server::instance()->handle($request))['result'] ?? array();
    assert_same('complete', $result['resultType'] ?? null, 'Modern prompts/get requires a result discriminator');
    assert_true(!empty($result['messages']), 'Prompts remain accessible');
});
