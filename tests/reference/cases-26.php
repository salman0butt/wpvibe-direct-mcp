<?php
parity_test('corrupt token hash option fails closed before authentication comparison',function(){
    $old_hash=$GLOBALS['wp_options']['wpvdmcp_token_hash']??null;
    $old_user=$GLOBALS['wp_options']['wpvdmcp_user_id']??null;
    $old_current=$GLOBALS['wp_current_user'];
    try{
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=(object)array('corrupt'=>true);
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $GLOBALS['wp_current_user']=0;

        $request=new WP_REST_Request('GET','/wpvibe-direct/v1/health');
        $request->set_header('authorization','Bearer test-token');
        $result=WPVDMCP_Server::instance()->status($request);

        assert_true(is_wp_error($result),'corrupt stored token hash must fail closed');
        assert_same('mcp_not_configured',$result->get_error_code(),'corrupt stored token hash failure code');
        assert_same(0,$GLOBALS['wp_current_user'],'corrupt auth state must not authenticate a WordPress user');
    }finally{
        if($old_hash===null) unset($GLOBALS['wp_options']['wpvdmcp_token_hash']); else $GLOBALS['wp_options']['wpvdmcp_token_hash']=$old_hash;
        if($old_user===null) unset($GLOBALS['wp_options']['wpvdmcp_user_id']); else $GLOBALS['wp_options']['wpvdmcp_user_id']=$old_user;
        $GLOBALS['wp_current_user']=$old_current;
    }
});