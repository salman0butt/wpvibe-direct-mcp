<?php

if (!class_exists('WPCode_Snippet')) {
    class WPCode_Snippet {}
}
if (!class_exists('WPVibe_Code_Snippet')) {
    class WPVibe_Code_Snippet {
        public static $calls=array();
        public static function handle($request,$dormant=false){
            self::$calls[]=array('dormant'=>$dormant,'code'=>$request->get_param('code'),'active'=>$request->get_param('active'));
            return array('id'=>55,'status'=>'draft','active'=>false,'dormant'=>$dormant,'code'=>$request->get_param('code'));
        }
    }
}

parity_test('dormant WPCode uses local native handler instead of hosted op-proof route', function () {
    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    WPVibe_Code_Snippet::$calls=array();
    $GLOBALS['rest_dispatch_callback']=function($r){
        return new WP_Error('op_proof_required','Hosted worker proof required.',array('status'=>401));
    };
    $result=WPVDMCP_Parity::execute('code_snippet',array(
        'action'=>'create','title'=>'Safe diagnostic','code'=>'add_action("init", function () {});','code_type'=>'php','location'=>'everywhere'
    ));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(55,$result['data']['id']??null,'local handler result');
    assert_same(true,WPVibe_Code_Snippet::$calls[0]['dormant']??null,'dormant native mode');
    assert_same(false,$result['data']['active']??null,'snippet stays inactive');
    assert_true(empty(WPVibe_Code_Snippet::$calls[0]['active']),'activation was never requested');
});

parity_test('armored call decodes object arguments and uses the normal public tool handler', function () {
    set_test_routes(array('/wp/v2/pages'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array(array('id'=>44,'route'=>$r->get_param('_route'))),200);};
    $encoded=base64_encode(wp_json_encode(array('method'=>'GET','path'=>'/wp/v2/pages')));
    $result=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'rest_api','arguments_base64'=>$encoded));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(44,$result['data'][0]['id']??null,'normal REST handler reached');
});

parity_test('armored call rejects invalid payloads recursion app-only helpers and unknown targets', function () {
    $bad64=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'site_info','arguments_base64'=>'***not-base64***'));
    assert_true(is_wp_error($bad64),'invalid base64 rejected');
    assert_same('invalid_armored_payload',$bad64->get_error_code(),'invalid base64 code');

    $array_json=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'site_info','arguments_base64'=>base64_encode('[1,2,3]')));
    assert_true(is_wp_error($array_json),'array JSON rejected');
    assert_same('invalid_armored_payload',$array_json->get_error_code(),'object required');

    foreach(array('call_armored','approval_decide') as $target){
        $blocked=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>$target,'arguments_base64'=>base64_encode('{}')));
        assert_true(is_wp_error($blocked),'forbidden target '.$target);
        assert_same('armored_target_forbidden',$blocked->get_error_code(),'forbidden code '.$target);
    }
    $unknown=WPVDMCP_Parity::execute('call_armored',array('tool_name'=>'internal_secret_tool','arguments_base64'=>base64_encode('{}')));
    assert_true(is_wp_error($unknown),'unknown target rejected');
    assert_same('unknown_armored_target',$unknown->get_error_code(),'unknown code');
});
