<?php
parity_test('Abilities public schemas accept WPVibe and Direct names',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $info=parity_tool('get_ability_info');
    $run=parity_tool('run_ability');
    $show=parity_tool('show_approval_panel');
    $check=parity_tool('check_approval_status');
    foreach(array('name','ability_name') as $key){
        assert_true(isset($info['inputSchema']['properties']->$key),'get_ability_info missing '.$key);
        assert_true(isset($run['inputSchema']['properties']->$key),'run_ability missing '.$key);
    }
    foreach(array('input','input_json','readonly','destructive','idempotent','approval_id') as $key){
        assert_true(isset($run['inputSchema']['properties']->$key),'run_ability missing '.$key);
    }
    assert_true(isset($run['inputSchema']['anyOf']),'run_ability must require name or ability_name');
    assert_true(isset($info['inputSchema']['anyOf']),'get_ability_info must require name or ability_name');
    foreach(array('approval_id','op_id') as $key){
        assert_true(isset($show['inputSchema']['properties']->$key),'show_approval_panel missing '.$key);
        assert_true(isset($check['inputSchema']['properties']->$key),'check_approval_status missing '.$key);
    }
});

parity_test('get ability info accepts ability_name alias',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $seen='';
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$seen){
        $seen=$r->get_param('_route');
        return new WP_REST_Response(array('name'=>'elementor/get-page-structure','meta'=>array('annotations'=>array('readonly'=>true))),200);
    };
    $result=WPVDMCP_Parity::execute('get_ability_info',array('ability_name'=>'elementor/get-page-structure'));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!is_wp_error($result),'ability_name alias should resolve');
    assert_same('/wp-abilities/v1/abilities/elementor/get-page-structure',$seen,'ability info route');
});

parity_test('run ability decodes input_json and structured input wins when both are present',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $run_input=null;
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$run_input){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/demo/read'===$route){
            return new WP_REST_Response(array('name'=>'demo/read','meta'=>array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true))),200);
        }
        if('/wp-abilities/v1/abilities/demo/read/run'===$route){
            $run_input=$r->get_query_params()['input']??null;
            return new WP_REST_Response(array('ok'=>true,'input'=>$run_input),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $legacy=WPVDMCP_Parity::execute('run_ability',array('ability_name'=>'demo/read','input_json'=>'{"value":2}'));
    assert_same(2,$legacy['data']['input']['value']??null,'legacy input_json decoded');
    $modern=WPVDMCP_Parity::execute('run_ability',array('ability_name'=>'demo/read','input'=>array('value'=>3),'input_json'=>'{"value":2}'));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(3,$modern['data']['input']['value']??null,'structured input wins');
});

parity_test('run ability rejects invalid input_json before dispatch',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $called=0;
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$called){$called++;return new WP_REST_Response(array(),200);};
    $bad=WPVDMCP_Parity::execute('run_ability',array('ability_name'=>'demo/read','input_json'=>'{bad json'));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(is_wp_error($bad),'invalid input_json should fail');
    assert_same('invalid_ability_input_json',$bad->get_error_code(),'invalid input_json code');
    assert_same(0,$called,'invalid input_json must not dispatch');
});

parity_test('ability metadata overrides conflicting fallback annotation hints',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $method='';
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$method){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/demo/read'===$route){
            return new WP_REST_Response(array('name'=>'demo/read','meta'=>array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true))),200);
        }
        if('/wp-abilities/v1/abilities/demo/read/run'===$route){
            $method=$r->get_param('_method');
            return new WP_REST_Response(array('method'=>$method),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $result=WPVDMCP_Parity::execute('run_ability',array(
        'ability_name'=>'demo/read','readonly'=>false,'destructive'=>true,'idempotent'=>true,'input'=>array()
    ));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!is_wp_error($result),'metadata-backed ability should run');
    assert_same('GET',$method,'server metadata must win over caller hints');
});

parity_test('ability fallback hints are used only when metadata fetch fails',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $method='';
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$method){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/demo/fallback'===$route){
            return new WP_REST_Response(array('code'=>'metadata_unavailable','message'=>'metadata unavailable'),502);
        }
        if('/wp-abilities/v1/abilities/demo/fallback/run'===$route){
            $method=$r->get_param('_method');
            return new WP_REST_Response(array('method'=>$method),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $result=WPVDMCP_Parity::execute('run_ability',array('ability_name'=>'demo/fallback','readonly'=>true,'input'=>array('id'=>1)));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!is_wp_error($result),'fallback hint should allow run when metadata fetch fails');
    assert_same('GET',$method,'readonly fallback uses GET');
});

parity_test('approval status tools accept op_id alias and fleet ids delegate to fleet provider',function(){
    $GLOBALS['wp_current_user']=1;
    $pending=WPVDMCP_Approvals::request('demo_delete',array('id'=>9),'Delete demo #9');
    $shown=WPVDMCP_Parity::execute('show_approval_panel',array('op_id'=>$pending['approval_id']));
    assert_same($pending['approval_id'],$shown['approval_id']??null,'show panel op_id alias');
    $checked=WPVDMCP_Parity::execute('check_approval_status',array('op_id'=>$pending['approval_id']));
    assert_same('pending',$checked['status']??null,'check status op_id alias');

    $GLOBALS['wpvdmcp_reference_providers']=array('show_fleet_dashboard'=>function($args){return array('job_id'=>$args['job_id']??'','status'=>'working');});
    $fleet=WPVDMCP_Parity::execute('check_approval_status',array('op_id'=>'fj_demo123'));
    unset($GLOBALS['wpvdmcp_reference_providers']);
    assert_same('fj_demo123',$fleet['job_id']??null,'fleet id delegated');
    assert_same('working',$fleet['status']??null,'fleet status delegated');
});
