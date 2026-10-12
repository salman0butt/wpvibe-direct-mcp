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

parity_test('Elementor build composition preflights only after approval and rejects warnings before persistence',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $run_calls=array();
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$run_calls){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/elementor/build-composition'===$route){
            return new WP_REST_Response(array(
                'name'=>'elementor/build-composition',
                'meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false)),
            ),200);
        }
        if('/wp-abilities/v1/abilities/elementor/build-composition/run'===$route){
            $json=$r->get_json_params();
            $run_calls[]=$json['input']??null;
            return new WP_REST_Response(array(
                'success'=>true,
                'post_id'=>42,
                'warnings'=>array('[hero-title] Property "content" is not supported on element type "e-heading" and was skipped.'),
            ),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };

    $args=array(
        'ability_name'=>'elementor/build-composition',
        'input'=>array(
            'post_id'=>42,
            'xml_structure'=>'<e-heading configuration-id="hero-title"></e-heading>',
            'element_config'=>array('hero-title'=>array('content'=>'Dropped heading')),
        ),
    );
    $pending=WPVDMCP_Parity::execute('run_ability',$args);
    assert_same('approval_required',$pending['status']??null,'Elementor composition must request approval before calling mutation endpoint');
    assert_same(0,count($run_calls),'no build-composition run call may occur before approval');
    assert_true(true===WPVDMCP_Approvals::approve($pending['approval_id'],1),'composition approval should succeed');

    $result=WPVDMCP_Parity::execute('run_ability',$args+array('approval_id'=>$pending['approval_id']));

    assert_true(is_wp_error($result),'lossy Elementor preflight warnings must fail before persistence');
    assert_same('elementor_composition_preflight_failed',$result->get_error_code(),'strict Elementor preflight error code');
    assert_same(1,count($run_calls),'warning preflight should dispatch exactly once');
    assert_same(true,$run_calls[0]['dry_run']??null,'warning preflight must force dry_run');

    $replay=WPVDMCP_Parity::execute('run_ability',$args+array('approval_id'=>$pending['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(is_wp_error($replay),'consumed approval must not be reusable after failed preflight');
    assert_same('approval_not_approved',$replay->get_error_code(),'failed preflight still consumes one-time approval');
    assert_same(1,count($run_calls),'replayed approval must fail before another Elementor run call');
});

parity_test('Elementor build composition clean preflight runs again before approved write',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $run_calls=array();
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$run_calls){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/elementor/build-composition'===$route){
            return new WP_REST_Response(array(
                'name'=>'elementor/build-composition',
                'meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false)),
            ),200);
        }
        if('/wp-abilities/v1/abilities/elementor/build-composition/run'===$route){
            $json=$r->get_json_params();
            $input=$json['input']??array();
            $run_calls[]=$input;
            return new WP_REST_Response(array(
                'success'=>true,
                'post_id'=>42,
                'root_element_ids'=>!empty($input['dry_run'])?array():array('abc123'),
                'resolved_xml'=>'<e-heading configuration-id="hero-title" id="abc123"></e-heading>',
            ),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };

    $input=array(
        'post_id'=>42,
        'xml_structure'=>'<e-heading configuration-id="hero-title"></e-heading>',
        'element_config'=>array('hero-title'=>array('title'=>'Good heading')),
    );
    $pending=WPVDMCP_Parity::execute('run_ability',array('ability_name'=>'elementor/build-composition','input'=>$input));
    assert_same('approval_required',$pending['status']??null,'clean composition should request approval before preflight');
    assert_same(0,count($run_calls),'clean initial call must not invoke Elementor mutation endpoint before approval');
    assert_true(true===WPVDMCP_Approvals::approve($pending['approval_id'],1),'approval should succeed');

    $result=WPVDMCP_Parity::execute('run_ability',array(
        'ability_name'=>'elementor/build-composition',
        'input'=>$input,
        'approval_id'=>$pending['approval_id'],
    ));
    unset($GLOBALS['rest_dispatch_callback']);

    assert_true(!is_wp_error($result),'clean approved composition should execute');
    assert_same(2,count($run_calls),'approved call should preflight then write once');
    assert_same(true,$run_calls[0]['dry_run']??null,'approved call must run dry preflight first');
    assert_true(empty($run_calls[1]['dry_run']),'final write must use original non-dry-run input');
    assert_same('abc123',$result['data']['root_element_ids'][0]??null,'final build result preserved');
});

parity_test('Elementor explicit dry run preserves validation warnings without claiming a write',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/elementor/build-composition'===$route){
            return new WP_REST_Response(array(
                'name'=>'elementor/build-composition',
                'meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false)),
            ),200);
        }
        if('/wp-abilities/v1/abilities/elementor/build-composition/run'===$route){
            return new WP_REST_Response(array(
                'success'=>true,
                'post_id'=>42,
                'warnings'=>array('[hero-title] Property "not_real" is not supported and was skipped.'),
                'root_element_ids'=>array(),
            ),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };

    $GLOBALS['wp_options']['wpvibe_bypass_approvals']=array('enabled'=>true);
    $result=WPVDMCP_Parity::execute('run_ability',array(
        'ability_name'=>'elementor/build-composition',
        'input'=>array(
            'post_id'=>42,
            'xml_structure'=>'<e-heading configuration-id="hero-title"></e-heading>',
            'element_config'=>array('hero-title'=>array('not_real'=>'Dropped')),
            'dry_run'=>true,
        ),
    ));
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    unset($GLOBALS['rest_dispatch_callback']);

    assert_true(!is_wp_error($result),'explicit Elementor dry run should return validation output instead of write-failure semantics');
    assert_same(true,$result['data']['success']??null,'explicit dry run success result preserved');
    assert_same(1,count($result['data']['warnings']??array()),'explicit dry run warnings remain visible to the caller');
});

parity_test('Elementor manage elements partial failures are not reported as successful MCP mutations',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['rest_dispatch_callback']=function($r){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/elementor/manage-elements'===$route){
            return new WP_REST_Response(array(
                'name'=>'elementor/manage-elements',
                'meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false)),
            ),200);
        }
        if('/wp-abilities/v1/abilities/elementor/manage-elements/run'===$route){
            return new WP_REST_Response(array(
                'status'=>'partial_error',
                'post_id'=>42,
                'results'=>array(
                    array('index'=>0,'action'=>'update','status'=>'ok','element_id'=>'abc123'),
                    array('index'=>1,'action'=>'update','status'=>'error','element_id'=>'def456','code'=>'elementor_v3_not_supported','message'=>'Cannot update this element.'),
                ),
            ),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };

    $input=array(
        'post_id'=>42,
        'operations'=>array(
            array('action'=>'update','element_id'=>'abc123','settings'=>array('title'=>'Updated')),
            array('action'=>'update','element_id'=>'def456','settings'=>array('title'=>'Dropped')),
        ),
    );
    $pending=WPVDMCP_Parity::execute('run_ability',array('ability_name'=>'elementor/manage-elements','input'=>$input));
    assert_same('approval_required',$pending['status']??null,'manage-elements write should request approval');
    assert_true(true===WPVDMCP_Approvals::approve($pending['approval_id'],1),'manage-elements approval should succeed');
    $result=WPVDMCP_Parity::execute('run_ability',array(
        'ability_name'=>'elementor/manage-elements',
        'input'=>$input,
        'approval_id'=>$pending['approval_id'],
    ));
    unset($GLOBALS['rest_dispatch_callback']);

    assert_true(is_wp_error($result),'partial Elementor element mutation must surface as an MCP error');
    assert_same('elementor_manage_elements_partial_failure',$result->get_error_code(),'partial Elementor mutation error code');
    $data=$result->get_error_data();
    assert_same(true,$data['partial_applied']??null,'error must disclose that sibling operations may already be applied');
    assert_same('partial_error',$data['response']['status']??null,'upstream partial result retained for repair');
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
