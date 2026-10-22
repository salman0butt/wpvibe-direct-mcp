<?php
require_once dirname(__DIR__, 2) . '/includes/class-wpvdmcp-mcp-apps.php';

// ChatGPT validates JSON wire types, not PHP array shape. JSON [] is not a CSP dictionary.
parity_test('MCP App approval resource serializes empty CSP as a JSON object', function() {
    $payload = array('jsonrpc'=>'2.0', 'id'=>'csp-approval', 'method'=>'resources/read', 'params'=>array('uri'=>WPVDMCP_MCP_Apps::APPROVAL_URI));
    $request = test_request($payload, WPVDMCP_Server::MODERN_PROTOCOL);
    $response = WPVDMCP_MCP_App_Transport::filter_response(WPVDMCP_Server::instance()->handle($request), null, $request);
    $wire = json_decode(wp_json_encode(response_data($response)));
    assert_true(is_object($wire) && isset($wire->result->contents[0]->_meta->ui->csp), 'Approval resource has CSP');
    $csp = $wire->result->contents[0]->_meta->ui->csp;
    assert_true(is_object($csp), 'MCP App approval CSP must be JSON object, not array');
    assert_same(array(), get_object_vars($csp), 'Approval CSP has no unnecessary permissions');
});

parity_test('MCP App upload resource serializes CSP dictionary and connectDomains correctly', function() {
    $payload = array('jsonrpc'=>'2.0', 'id'=>'csp-upload', 'method'=>'resources/read', 'params'=>array('uri'=>WPVDMCP_MCP_Apps::UPLOAD_URI));
    $request = test_request($payload, WPVDMCP_Server::MODERN_PROTOCOL);
    $response = WPVDMCP_MCP_App_Transport::filter_response(WPVDMCP_Server::instance()->handle($request), null, $request);
    $wire = json_decode(wp_json_encode(response_data($response)));
    assert_true(is_object($wire) && isset($wire->result->contents[0]->_meta->ui->csp), 'Upload resource has CSP');
    $csp = $wire->result->contents[0]->_meta->ui->csp;
    assert_true(is_object($csp), 'MCP App upload CSP must be JSON object, not array');
    assert_true(isset($csp->connectDomains) && is_array($csp->connectDomains), 'Upload CSP must preserve connectDomains array');
    assert_same(array('https://example.test'), $csp->connectDomains, 'Upload CSP restricts connections to site origin');
});
