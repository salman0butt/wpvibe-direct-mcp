<?php
parity_test('query-token compatibility is explicit opt in and is never force-reset on plugin load',function(){
    $main=(string)file_get_contents(dirname(__DIR__,2).'/wpvibe-direct-mcp.php');
    $server=(string)file_get_contents(dirname(__DIR__,2).'/includes/class-wpvdmcp-server.php');
    $admin=(string)file_get_contents(dirname(__DIR__,2).'/includes/class-wpvdmcp-admin.php');

    assert_true(strpos($main,"delete_option( 'wpvdmcp_allow_query_token' )")===false,'plugin load must not delete query-token opt-in');
    assert_true(strpos($server,"get_option( 'wpvdmcp_allow_query_token'")!==false,'authentication must read explicit query-token opt-in');
    assert_true(strpos($admin,'name="allow_query_token"')!==false,'site owner must be able to control query-token compatibility');

    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    assert_true(in_array('query-token-explicit-opt-in',$manifest['mcp']['auth']??array(),true),'manifest must describe query-token auth as explicit opt-in');
});
