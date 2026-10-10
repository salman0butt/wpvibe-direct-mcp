<?php

parity_test('destructive idempotent ability uses DELETE after approval', function () {
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['rest_dispatch_callback']=function($r){
        $route=$r->get_param('_route');
        if($route==='/wp-abilities/v1/abilities/demo/delete-idempotent'){
            return new WP_REST_Response(array('name'=>'demo/delete-idempotent','meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>true))),200);
        }
        if($route==='/wp-abilities/v1/abilities/demo/delete-idempotent/run'){
            return new WP_REST_Response(array('method'=>$r->get_param('_method')),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $first=WPVDMCP_Parity::execute('run_ability',array('name'=>'demo/delete-idempotent','input'=>array('id'=>7)));
    assert_same('approval_required',$first['status']??null,'approval required');
    WPVDMCP_Approvals::approve($first['approval_id'],1);
    $second=WPVDMCP_Parity::execute('run_ability',array('name'=>'demo/delete-idempotent','input'=>array('id'=>7),'approval_id'=>$first['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same('DELETE',$second['data']['method']??null,'destructive idempotent uses DELETE');
});

parity_test('destructive non-idempotent ability uses POST after approval', function () {
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['rest_dispatch_callback']=function($r){
        $route=$r->get_param('_route');
        if($route==='/wp-abilities/v1/abilities/demo/destructive-action'){
            return new WP_REST_Response(array('name'=>'demo/destructive-action','meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false))),200);
        }
        if($route==='/wp-abilities/v1/abilities/demo/destructive-action/run'){
            return new WP_REST_Response(array('method'=>$r->get_param('_method')),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $first=WPVDMCP_Parity::execute('run_ability',array('name'=>'demo/destructive-action','input'=>array('id'=>7)));
    assert_same('approval_required',$first['status']??null,'approval required');
    WPVDMCP_Approvals::approve($first['approval_id'],1);
    $second=WPVDMCP_Parity::execute('run_ability',array('name'=>'demo/destructive-action','input'=>array('id'=>7),'approval_id'=>$first['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same('POST',$second['data']['method']??null,'destructive non-idempotent uses POST');
});

parity_test('generic REST reads run directly but writes require payload-bound approval', function () {
    set_test_routes(array('/wp/v2/pages'=>array('GET'),'/wp/v2/pages/12'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['rest_dispatch_callback']=function($r){
        if($r->get_param('_route')==='/wp/v2/pages') return new WP_REST_Response(array(array('id'=>12)),200);
        return new WP_REST_Response(array('id'=>12,'method'=>$r->get_param('_method'),'title'=>$r->get_param('title')),200);
    };
    $read=WPVDMCP_Parity::execute('rest_api',array('method'=>'GET','path'=>'/wp/v2/pages'));
    assert_same(12,$read['data'][0]['id']??null,'GET executes immediately');

    $first=WPVDMCP_Parity::execute('rest_api',array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('title'=>'Approved title')));
    assert_same('approval_required',$first['status']??null,'write requires approval');
    WPVDMCP_Approvals::approve($first['approval_id'],1);

    $changed=WPVDMCP_Parity::execute('rest_api',array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('title'=>'Changed after approval'),'approval_id'=>$first['approval_id']));
    assert_true(is_wp_error($changed),'mutated body rejected');
    assert_same('approval_payload_mismatch',$changed->get_error_code(),'payload binding');

    $done=WPVDMCP_Parity::execute('rest_api',array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('title'=>'Approved title'),'approval_id'=>$first['approval_id']));
    assert_same(12,$done['data']['id']??null,'approved write executes');
    $replay=WPVDMCP_Parity::execute('rest_api',array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('title'=>'Approved title'),'approval_id'=>$first['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(is_wp_error($replay),'approval cannot replay');
    assert_same('approval_not_approved',$replay->get_error_code(),'replay rejected');
});

parity_test('generic REST write bypass follows installed WPVibe dangerous approval setting', function () {
    set_test_routes(array('/wp/v2/pages/12'=>array('PATCH')));
    $GLOBALS['wp_options']['wpvibe_bypass_approvals']=array('enabled'=>true);
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('id'=>12,'method'=>$r->get_param('_method')),200);};
    $done=WPVDMCP_Parity::execute('rest_api_write',array('method'=>'PATCH','path'=>'/wp/v2/pages/12','body'=>array('title'=>'No pause')));
    unset($GLOBALS['rest_dispatch_callback'],$GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    assert_same('PATCH',$done['data']['method']??null,'bypass write executes');
});

parity_test('generic REST cannot reach WPVibe or Direct control-plane routes', function () {
    set_test_routes(array());
    $blocked=array(
        '/wpvibe-direct/v1/health',
        '/wp/v2/users/1/application-passwords',
        '/wpvibe/v1/authorize',
        '/wpvibe/v1/authorize/preflight',
        '/wpvibe/v1/connection-status',
        '/wpvibe/v1/connection-check-challenge',
        '/wpvibe/v1/op-proof/check',
        '/wpvibe/v1/cli/run-approved',
        '/wpvibe/v1/code-snippet',
        '/wpvibe/v1/builder-login',
        '/wpvibe/v1/detached/run',
        '/wpvibe/v1/self-update/health',
        '/wpvibe/v1/self-update/run',
        '/wpvibe/v1/audit-log/record',
        '/wpvibe/v1/classic-theme-safety',
        '/wpvibe/v1/draft-theme/compile-sources'
    );
    foreach($blocked as $path){
        $result=WPVDMCP_Parity::execute('rest_api',array('method'=>'GET','path'=>$path));
        assert_true(is_wp_error($result),'blocked route expected for '.$path);
        assert_same('blocked_route',$result->get_error_code(),'blocked code '.$path);
    }
});
