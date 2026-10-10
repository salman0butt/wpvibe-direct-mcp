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
    $first=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/delete-idempotent','input'=>array('id'=>7)));
    assert_same('approval_required',$first['status']??null,'approval required');
    WPVDMCP_Approvals::approve($first['approval_id'],1);
    $second=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/delete-idempotent','input'=>array('id'=>7),'approval_id'=>$first['approval_id']));
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
    $first=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/destructive-action','input'=>array('id'=>7)));
    assert_same('approval_required',$first['status']??null,'approval required');
    WPVDMCP_Approvals::approve($first['approval_id'],1);
    $second=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/destructive-action','input'=>array('id'=>7),'approval_id'=>$first['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same('POST',$second['data']['method']??null,'destructive non-idempotent uses POST');
});
