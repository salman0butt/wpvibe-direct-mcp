<?php
parity_test('theme helper routes classify proof and compile snapshot on opposite sides of the trust boundary',function(){
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $internal=$manifest['internal_not_tools']??array();
    $model=$manifest['model_route_tools']??array();
    assert_true(in_array('/wpvibe/v1/classic-theme-safety',$internal,true),'classic theme fresh proof route must be infrastructure-only');
    assert_true(isset($model['/wpvibe/v1/draft-theme/compile-sources']),'draft compile snapshot route must be explicitly classified model-safe');
});

parity_test('known-method JSON-RPC notifications execute without emitting responses',function(){
    $server=WPVDMCP_Server::instance();

    $single=$server->handle(test_request(array(
        'jsonrpc'=>'2.0',
        'method'=>'tools/list',
        'params'=>array(),
    )));
    assert_same(202,$single->get_status(),'single known-method notification must return HTTP 202');
    assert_same(null,$single->get_data(),'single known-method notification must not emit a JSON-RPC response body');

    $batch=$server->handle(test_request(array(
        array('jsonrpc'=>'2.0','method'=>'tools/list','params'=>array()),
        array('jsonrpc'=>'2.0','id'=>'req-1','method'=>'ping','params'=>array()),
    )));
    assert_same(200,$batch->get_status(),'mixed legacy batch status');
    $data=$batch->get_data();
    assert_same(1,count($data),'notification result must be omitted from a mixed batch response');
    assert_same('req-1',$data[0]['id']??null,'remaining batch response must belong to the request item');
});

parity_test('corrupted activity option cannot fatal a tool call',function(){
    update_option('wpvdmcp_activity','corrupted-legacy-value',false);

    $response=WPVDMCP_Server::instance()->handle(test_request(array(
        'jsonrpc'=>'2.0',
        'id'=>'activity-log-corrupt',
        'method'=>'tools/call',
        'params'=>array('name'=>'definitely_missing_tool','arguments'=>array()),
    )));
    $data=response_data($response);

    assert_same('activity-log-corrupt',$data['id']??null,'tool response must still be returned when prior activity state is malformed');
    assert_same(true,$data['result']['isError']??null,'missing test tool remains an ordinary tool error');
    $activity=get_option('wpvdmcp_activity',array());
    assert_true(is_array($activity),'activity logger must recover malformed stored state to an array');
    assert_same('definitely_missing_tool',$activity[0]['tool']??null,'current tool call must still be recorded after recovery');
});
