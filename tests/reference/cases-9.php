<?php
parity_test('generic REST blocks WPVibe worker control-plane routes before dispatch or approval',function(){
    $routes=array(
        '/wpvibe/v1/authorize',
        '/wpvibe/v1/authorize/preflight',
        '/wpvibe/v1/connection-status',
        '/wpvibe/v1/connection-check-challenge',
        '/wpvibe/v1/op-proof/check',
        '/wpvibe/v1/cli/run-approved',
        '/wpvibe/v1/code-snippet',
        '/wpvibe/v1/builder-login',
        '/wpvibe/v1/detached/run',
        '/wpvibe/v1/audit-log/record',
        '/wpvibe/v1/classic-theme-safety',
        '/wpvibe/v1/self-update/health',
        '/wpvibe/v1/self-update/run'
    );
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    foreach($routes as $route){
        $read=WPVDMCP_Parity::execute('rest_api',array('method'=>'GET','path'=>$route));
        assert_true(is_wp_error($read),'worker/control-plane GET must be blocked: '.$route);
        assert_same('blocked_route',$read->get_error_code(),'unexpected GET error for '.$route);

        $write=WPVDMCP_Parity::execute('rest_api',array('method'=>'POST','path'=>$route,'body'=>array()));
        assert_true(is_wp_error($write),'worker/control-plane POST must be blocked before approval: '.$route);
        assert_same('blocked_route',$write->get_error_code(),'unexpected POST error for '.$route);
    }
});

parity_test('generic REST keeps the draft compile snapshot model-accessible',function(){
    set_test_routes(array('/wpvibe/v1/draft-theme/compile-sources'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $calls=0;
    $GLOBALS['rest_dispatch_callback']=function($request) use (&$calls){
        $calls++;
        return new WP_REST_Response(array('source_hash'=>str_repeat('a',64),'files'=>array('style.css')),200);
    };
    $result=WPVDMCP_Parity::execute('rest_api',array('method'=>'GET','path'=>'/wpvibe/v1/draft-theme/compile-sources'));
    assert_true(!is_wp_error($result),'draft compile snapshot is a safe read primitive, not a worker proof route');
    assert_same(str_repeat('a',64),$result['data']['source_hash']??null,'draft compile snapshot hash should pass through');
    assert_same(1,$calls,'draft compile snapshot should dispatch exactly once');
    $GLOBALS['rest_dispatch_callback']=null;
});
