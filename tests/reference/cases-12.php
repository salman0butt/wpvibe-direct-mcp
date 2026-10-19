<?php
parity_test('armored tool calls are bounded exposed-tool retries that preserve normal approval safety',function(){
    $tool=parity_tool('call_armored');
    assert_true(is_array($tool),'call_armored must be exposed as a model-facing retry tool');
    $props=(array)($tool['inputSchema']['properties']??array());
    assert_true(isset($props['tool_name'],$props['arguments_base64']),'call_armored schema must require target and armored arguments');

    set_test_routes(array('/wp/v2/pages/12'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $dispatches=0;
    $GLOBALS['rest_dispatch_callback']=function($request) use (&$dispatches){
        $dispatches++;
        return new WP_REST_Response(array(
            'id'=>12,
            'content'=>$request->get_param('content'),
            'method'=>$request->get_param('_method'),
        ),200);
    };

    $code="<?php echo 'WAF-safe payload'; ?>";
    $inner=array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('content'=>$code));
    $first=WPVDMCP_Parity::execute('call_armored',array(
        'tool_name'=>'rest_api',
        'arguments_base64'=>base64_encode(wp_json_encode($inner)),
    ));
    assert_true(is_array($first),'armored normal tool result should be returned unchanged');
    assert_same('approval_required',$first['status']??null,'armoring must not bypass a normal write approval');
    assert_same(0,$dispatches,'armored write must not dispatch before approval');
    $approval_id=$first['approval_id']??'';
    assert_true($approval_id!=='','armored write approval id missing');
    assert_same(true,WPVDMCP_Approvals::approve($approval_id,1),'approval setup failed');

    $inner['approval_id']=$approval_id;
    $done=WPVDMCP_Parity::execute('call_armored',array(
        'tool_name'=>'rest_api',
        'arguments_base64'=>base64_encode(wp_json_encode($inner)),
    ));
    assert_true(is_array($done),'approved armored write should complete through the normal handler');
    assert_same(200,$done['status']??null,'approved armored write status mismatch');
    assert_same($code,$done['data']['content']??null,'decoded UTF-8/code payload must arrive unchanged');
    assert_same(1,$dispatches,'approved armored write must dispatch exactly once');

    $replay=WPVDMCP_Parity::execute('call_armored',array(
        'tool_name'=>'rest_api',
        'arguments_base64'=>base64_encode(wp_json_encode($inner)),
    ));
    assert_true(is_wp_error($replay),'armored retry must preserve one-time approval replay rejection');
    assert_same(1,$dispatches,'approval replay must not redispatch the write');

    $bad64=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'site_info','arguments_base64'=>'***not-base64***'));
    assert_true(is_wp_error($bad64),'invalid base64 must be rejected');
    assert_same('invalid_armored_payload',$bad64->get_error_code(),'invalid base64 error code mismatch');

    $array_json=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'site_info','arguments_base64'=>base64_encode('[1,2,3]')));
    assert_true(is_wp_error($array_json),'decoded JSON arrays must be rejected');
    assert_same('invalid_armored_payload',$array_json->get_error_code(),'decoded object requirement mismatch');

    $too_large=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'site_info','arguments_base64'=>str_repeat('A',5*1024*1024)));
    assert_true(is_wp_error($too_large),'oversized armored payload must fail closed before decode/dispatch');
    assert_same('invalid_armored_payload',$too_large->get_error_code(),'oversized payload error code mismatch');

    foreach(array('call_armored','approval_decide') as $target){
        $blocked=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>$target,'arguments_base64'=>base64_encode('{}')));
        assert_true(is_wp_error($blocked),'forbidden armored target '.$target.' must fail');
        assert_true(in_array($blocked->get_error_code(),array('armored_target_forbidden','unknown_armored_target'),true),'armored internal target must remain blocked: '.$target);
    }
    $unknown=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'internal_secret_tool','arguments_base64'=>base64_encode('{}')));
    assert_true(is_wp_error($unknown),'non-exposed armored target must fail');
    assert_same('unknown_armored_target',$unknown->get_error_code(),'unknown armored target error mismatch');

    $GLOBALS['rest_dispatch_callback']=null;
});
