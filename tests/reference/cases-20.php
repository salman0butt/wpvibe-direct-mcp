<?php
parity_test('authenticated requests throttle last-active diagnostic writes and heal skew',function(){
    $server=WPVDMCP_Server::instance();
    $payload=array('jsonrpc'=>'2.0','id'=>'last-active-throttle','method'=>'tools/list','params'=>array());

    $fresh=time()-15;
    $GLOBALS['wp_options']['wpvdmcp_last_active']=$fresh;
    $fresh_response=$server->handle(test_request($payload));
    assert_true(!is_wp_error($fresh_response),'fresh authenticated request succeeds');
    assert_same($fresh,(int)($GLOBALS['wp_options']['wpvdmcp_last_active']??0),'fresh last-active timestamp is not rewritten');

    unset($GLOBALS['wp_options']['wpvdmcp_last_active']);
    $before_missing=time();
    $missing_response=$server->handle(test_request($payload));
    $missing_updated=(int)($GLOBALS['wp_options']['wpvdmcp_last_active']??0);
    assert_true(!is_wp_error($missing_response),'missing-timestamp authenticated request succeeds');
    assert_true($missing_updated>=$before_missing && $missing_updated<=time(),'missing last-active timestamp is initialized to current time');

    $stale=time()-120;
    $GLOBALS['wp_options']['wpvdmcp_last_active']=$stale;
    $before_stale=time();
    $stale_response=$server->handle(test_request($payload));
    $stale_updated=(int)($GLOBALS['wp_options']['wpvdmcp_last_active']??0);
    assert_true(!is_wp_error($stale_response),'stale authenticated request succeeds');
    assert_true($stale_updated>=$before_stale && $stale_updated<=time(),'stale last-active timestamp refreshes to current time');

    $future=time()+3600;
    $GLOBALS['wp_options']['wpvdmcp_last_active']=$future;
    $before_future=time();
    $future_response=$server->handle(test_request($payload));
    $future_updated=(int)($GLOBALS['wp_options']['wpvdmcp_last_active']??0);
    assert_true(!is_wp_error($future_response),'future-skew authenticated request succeeds');
    assert_true($future_updated>=$before_future && $future_updated<=time(),'future-skew last-active timestamp heals to current time');
});

if (!class_exists('WPVDMCP_Elementor_Document_Test_Stub')) {
    class WPVDMCP_Elementor_Document_Test_Stub {
        public function get_meta($key){
            if('_elementor_page_settings'!==$key)return null;
            return $GLOBALS['elementor_page_settings_stub']??array();
        }
    }
    class WPVDMCP_Elementor_Documents_Test_Stub {
        public function get($post_id){
            return 42===(int)$post_id ? new WPVDMCP_Elementor_Document_Test_Stub() : null;
        }
    }
    class WPVDMCP_Elementor_Plugin_Test_Stub {
        public static $instance;
    }
}
if (!class_exists('Elementor\\Plugin',false)) {
    class_alias('WPVDMCP_Elementor_Plugin_Test_Stub','Elementor\\Plugin');
}

parity_test('Elementor partial page settings preserve latest stored siblings after approval',function(){
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    WPVDMCP_Elementor_Plugin_Test_Stub::$instance=(object)array('documents'=>new WPVDMCP_Elementor_Documents_Test_Stub());
    $GLOBALS['elementor_page_settings_stub']=array(
        'padding'=>array('unit'=>'px','top'=>'13','right'=>'13','bottom'=>'13','left'=>'13','isLinked'=>true),
        'margin'=>array('unit'=>'px','top'=>'23','right'=>'23','bottom'=>'23','left'=>'23','isLinked'=>true),
        'background_background'=>'classic',
    );
    $run_inputs=array();
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$run_inputs){
        $route=$r->get_param('_route');
        if('/wp-abilities/v1/abilities/elementor/update-page-settings'===$route){
            return new WP_REST_Response(array(
                'name'=>'elementor/update-page-settings',
                'meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false)),
            ),200);
        }
        if('/wp-abilities/v1/abilities/elementor/update-page-settings/run'===$route){
            $json=$r->get_json_params();
            $run_inputs[]=$json['input']??null;
            return new WP_REST_Response(array('success'=>true,'post_id'=>42),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };

    $input=array(
        'post_id'=>42,
        'settings'=>array('margin'=>array('unit'=>'px','top'=>'37','right'=>'37','bottom'=>'37','left'=>'37','isLinked'=>true)),
    );
    $pending=WPVDMCP_Parity::execute('run_ability',array('ability_name'=>'elementor/update-page-settings','input'=>$input));
    assert_same('approval_required',$pending['status']??null,'page-settings mutation should request approval');
    assert_same(0,count($run_inputs),'Elementor page-settings endpoint must not run before approval');
    assert_true(true===WPVDMCP_Approvals::approve($pending['approval_id'],1),'page-settings approval should succeed');

    // Simulate an unrelated setting changing while approval is pending. Direct
    // should merge against the latest stored document settings at execution time.
    $GLOBALS['elementor_page_settings_stub']['background_background']='gradient';
    $result=WPVDMCP_Parity::execute('run_ability',array(
        'ability_name'=>'elementor/update-page-settings',
        'input'=>$input,
        'approval_id'=>$pending['approval_id'],
    ));
    unset($GLOBALS['rest_dispatch_callback']);

    assert_true(!is_wp_error($result),'approved partial page-settings mutation should execute');
    assert_same(1,count($run_inputs),'approved page-settings mutation should dispatch once');
    $effective=$run_inputs[0]['settings']??array();
    assert_same('37',$effective['margin']['top']??null,'requested margin must win merge');
    assert_same('13',$effective['padding']['top']??null,'untouched stored padding must be preserved');
    assert_same('gradient',$effective['background_background']??null,'latest unrelated stored setting must be preserved');
});
