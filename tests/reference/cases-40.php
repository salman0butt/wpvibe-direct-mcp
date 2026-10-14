<?php

parity_test('approval rejection cannot rewrite an approved receipt',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    try{
        $GLOBALS['wp_current_user']=1;
        $payload=array('name'=>'demo/reject-approved','input'=>array('value'=>40),'method'=>'POST');
        $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Reject approved receipt');
        $approval_id=$approval['approval_id']??'';
        assert_true($approval_id!=='','approval id returned');
        assert_true(WPVDMCP_Approvals::approve($approval_id,1)===true,'approval accepted');

        $rejected=WPVDMCP_Approvals::reject($approval_id,1);
        assert_true(is_wp_error($rejected),'approved receipt must reject a stale rejection decision');
        assert_same('approval_not_pending',$rejected->get_error_code(),'approved rejection error code');
        $status=WPVDMCP_Approvals::status($approval_id);
        assert_same('approved',$status['status']??null,'stale rejection must not rewrite approved state');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});

parity_test('approval rejection loses to an in-progress approved consume',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_hook=array_key_exists('get_transient_after_read',$GLOBALS);
    $hook_before=$had_hook?$GLOBALS['get_transient_after_read']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $payload=array('name'=>'demo/reject-consume-race','input'=>array('value'=>41),'method'=>'POST');
        $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Reject consume race');
        $approval_id=$approval['approval_id']??'';
        assert_true($approval_id!=='','approval id returned');
        assert_true(WPVDMCP_Approvals::approve($approval_id,1)===true,'approval accepted');

        $approval_reads=0;
        $nested_reject=null;
        $armed=true;
        $GLOBALS['get_transient_after_read']=function($key,$value) use (&$approval_reads,&$nested_reject,&$armed,$approval_id){
            if(!$armed || strpos((string)$key,'wpvdmcp_approval_')!==0 || !is_array($value) || ($value['id']??'')!==$approval_id)return;
            $approval_reads++;
            if($approval_reads!==2)return;
            $armed=false;
            $nested_reject=WPVDMCP_Approvals::reject($approval_id,1);
        };
        $consumed=WPVDMCP_Approvals::consume($approval_id,'run_ability',$payload);
        unset($GLOBALS['get_transient_after_read']);

        assert_true($consumed===true,'approved consume must still complete');
        assert_true(is_wp_error($nested_reject),'rejection racing an approved consume must fail closed');
        assert_same('approval_not_pending',$nested_reject->get_error_code(),'racing rejection error code');
        $status=WPVDMCP_Approvals::status($approval_id);
        assert_same('consumed',$status['status']??null,'consume winner must leave terminal consumed state');
    }finally{
        if($had_hook)$GLOBALS['get_transient_after_read']=$hook_before;else unset($GLOBALS['get_transient_after_read']);
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});
