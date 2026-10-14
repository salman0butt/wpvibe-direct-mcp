<?php

parity_test('browser approval handler rejects missing or unknown decisions without mutating pending state',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $post_before=$_POST;
    $had_throw=array_key_exists('throw_on_redirect',$GLOBALS);
    $throw_before=$had_throw?$GLOBALS['throw_on_redirect']:null;
    $had_redirect=array_key_exists('last_redirect',$GLOBALS);
    $redirect_before=$had_redirect?$GLOBALS['last_redirect']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        foreach(array(null,'unexpected') as $decision){
            $approval=WPVDMCP_Approvals::request(
                'run_ability',
                array('name'=>'demo/admin-invalid-decision','input'=>array('decision'=>$decision),'method'=>'POST'),
                'Admin invalid decision'
            );
            $approval_id=$approval['approval_id']??'';
            assert_true($approval_id!=='','approval id returned');

            $_POST=array('approval_id'=>$approval_id);
            if(null!==$decision){
                $_POST['decision']=$decision;
            }
            $GLOBALS['throw_on_redirect']=true;
            $GLOBALS['last_redirect']='';
            try{
                WPVDMCP_Admin::instance()->approval_action();
                assert_true(false,'admin approval handler must redirect');
            }catch(RuntimeException $e){
                assert_same('redirect',$e->getMessage(),'redirect interception');
            }

            $status=WPVDMCP_Approvals::status($approval_id);
            assert_same('pending',$status['status']??null,'invalid browser decision must leave approval pending');
            assert_true(strpos((string)$GLOBALS['last_redirect'],'approval_error=1')!==false,'invalid browser decision must use error redirect');
        }
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        $_POST=$post_before;
        if($had_throw)$GLOBALS['throw_on_redirect']=$throw_before;else unset($GLOBALS['throw_on_redirect']);
        if($had_redirect)$GLOBALS['last_redirect']=$redirect_before;else unset($GLOBALS['last_redirect']);
    }
});
