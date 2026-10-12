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
