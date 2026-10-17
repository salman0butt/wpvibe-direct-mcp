<?php
parity_test('orphaned token mutation claim cannot block emergency token revocation forever',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_wpdb=array_key_exists('wpdb',$GLOBALS);
    $wpdb_before=$had_wpdb?$GLOBALS['wpdb']:null;
    $had_throw=array_key_exists('throw_on_redirect',$GLOBALS);
    $throw_before=$had_throw?$GLOBALS['throw_on_redirect']:null;
    $had_redirect=array_key_exists('last_redirect',$GLOBALS);
    $redirect_before=$had_redirect?$GLOBALS['last_redirect']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','orphaned-claim-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $GLOBALS['wp_options']['wpvdmcp_token_mutation_claim']='orphaned-request';
        $GLOBALS['wpdb']=new WPVDMCP_Test_Advisory_DB(true);
        $GLOBALS['throw_on_redirect']=true;

        try{
            WPVDMCP_Admin::instance()->revoke_token();
        }catch(RuntimeException $e){
            assert_same('redirect',$e->getMessage(),'token revocation redirects');
        }

        assert_true(strpos($GLOBALS['last_redirect']??'','token_revoked=1')!==false,'orphaned claim must not permanently block emergency token revocation');
        assert_true(!array_key_exists('wpvdmcp_token_hash',$GLOBALS['wp_options'])||''===($GLOBALS['wp_options']['wpvdmcp_token_hash']??null),'recovered revocation must leave no usable token hash');
        assert_true(!array_key_exists('wpvdmcp_user_id',$GLOBALS['wp_options'])||0===($GLOBALS['wp_options']['wpvdmcp_user_id']??null),'recovered revocation must leave no active token owner');
        assert_true(!array_key_exists('wpvdmcp_token_mutation_claim',$GLOBALS['wp_options']),'recovered token mutation claim must be released');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_wpdb)$GLOBALS['wpdb']=$wpdb_before;
        else unset($GLOBALS['wpdb']);
        if($had_throw)$GLOBALS['throw_on_redirect']=$throw_before;
        else unset($GLOBALS['throw_on_redirect']);
        if($had_redirect)$GLOBALS['last_redirect']=$redirect_before;
        else unset($GLOBALS['last_redirect']);
    }
});

parity_test('live token mutation owner is never stolen while its database lock is held',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_wpdb=array_key_exists('wpdb',$GLOBALS);
    $wpdb_before=$had_wpdb?$GLOBALS['wpdb']:null;
    $had_throw=array_key_exists('throw_on_redirect',$GLOBALS);
    $throw_before=$had_throw?$GLOBALS['throw_on_redirect']:null;
    $had_redirect=array_key_exists('last_redirect',$GLOBALS);
    $redirect_before=$had_redirect?$GLOBALS['last_redirect']:null;
    try{
        $hash=hash('sha256','live-claim-token');
        $GLOBALS['wp_current_user']=1;
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=$hash;
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $GLOBALS['wp_options']['wpvdmcp_token_mutation_claim']='live-request';
        $GLOBALS['wpdb']=new WPVDMCP_Test_Advisory_DB(false);
        $GLOBALS['throw_on_redirect']=true;

        try{
            WPVDMCP_Admin::instance()->revoke_token();
        }catch(RuntimeException $e){
            assert_same('redirect',$e->getMessage(),'busy token revocation redirects');
        }

        assert_true(strpos($GLOBALS['last_redirect']??'','token_revoke_error=1')!==false,'live database lock must keep the competing revocation fail closed');
        assert_same($hash,$GLOBALS['wp_options']['wpvdmcp_token_hash']??null,'competing revocation must not change the live token hash');
        assert_same(1,$GLOBALS['wp_options']['wpvdmcp_user_id']??null,'competing revocation must not change the live token owner');
        assert_same('live-request',$GLOBALS['wp_options']['wpvdmcp_token_mutation_claim']??null,'competing revocation must not steal the live claim');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_wpdb)$GLOBALS['wpdb']=$wpdb_before;
        else unset($GLOBALS['wpdb']);
        if($had_throw)$GLOBALS['throw_on_redirect']=$throw_before;
        else unset($GLOBALS['throw_on_redirect']);
        if($had_redirect)$GLOBALS['last_redirect']=$redirect_before;
        else unset($GLOBALS['last_redirect']);
    }
});
