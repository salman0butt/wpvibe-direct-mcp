<?php

parity_test('malformed approval bypass state cannot skip a REST write approval',function(){
    $options_before=$GLOBALS['wp_options'];
    $routes_before=$GLOBALS['wp_routes'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_dispatch=array_key_exists('rest_dispatch_callback',$GLOBALS);
    $dispatch_before=$had_dispatch?$GLOBALS['rest_dispatch_callback']:null;
    try{
        set_test_routes(array('/wp/v2/pages/12'=>array('POST')));
        $GLOBALS['wp_current_user']=1;
        $GLOBALS['wp_options']['wpvibe_bypass_approvals']=array('enabled'=>'false');
        $calls=0;
        $GLOBALS['rest_dispatch_callback']=function($request) use (&$calls){
            $calls++;
            return new WP_REST_Response(array('id'=>12),200);
        };

        $result=WPVDMCP_Parity::execute('rest_api',array(
            'method'=>'POST',
            'path'=>'/wp/v2/pages/12',
            'body'=>array('title'=>'Must still require approval'),
        ));

        assert_same('approval_required',$result['status']??null,'malformed bypass state must require browser approval');
        assert_same(0,$calls,'malformed bypass state must not dispatch the REST write');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_routes']=$routes_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_dispatch)$GLOBALS['rest_dispatch_callback']=$dispatch_before;else unset($GLOBALS['rest_dispatch_callback']);
    }
});
