<?php
parity_test('corrupt one-time token display state cannot fatal wp-admin rendering',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $get_before=$_GET;
    $output_level_before=ob_get_level();
    try{
        $GLOBALS['wp_current_user']=1;
        $_GET=array();
        set_transient('wpvdmcp_new_token_1',(object)array('secret'=>'must-not-render'),5*MINUTE_IN_SECONDS);

        ob_start();
        try{
            WPVDMCP_Admin::instance()->render();
            $corrupt_html=ob_get_clean();
        }catch(Throwable $e){
            if(ob_get_level()>$output_level_before)ob_end_clean();
            throw new RuntimeException('corrupt one-time token display state must not break wp-admin rendering: '.$e->getMessage());
        }

        assert_true(strpos($corrupt_html,'id="wpvd-token"')===false,'corrupt one-time token state must not render a token input');
        assert_same(false,get_transient('wpvdmcp_new_token_1'),'corrupt one-time token state must be consumed');

        $valid_token='valid-one-time-token';
        update_option('wpvdmcp_token_hash',hash('sha256',$valid_token));
        update_option('wpvdmcp_user_id','1');
        set_transient('wpvdmcp_new_token_1',$valid_token,5*MINUTE_IN_SECONDS);
        ob_start();
        WPVDMCP_Admin::instance()->render();
        $valid_html=ob_get_clean();
        assert_true(strpos($valid_html,'id="wpvd-token"')!==false,'valid one-time token still renders its token input');
        assert_true(strpos($valid_html,$valid_token)!==false,'valid one-time token remains visible exactly for its display request');
        assert_same(false,get_transient('wpvdmcp_new_token_1'),'valid one-time token is still consumed after rendering');
    }finally{
        while(ob_get_level()>$output_level_before)ob_end_clean();
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        $_GET=$get_before;
    }
});
