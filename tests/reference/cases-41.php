<?php

parity_test('concurrent reject cannot also succeed while approval wins',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_hook=array_key_exists('get_transient_after_read',$GLOBALS);
    $hook_before=$had_hook?$GLOBALS['get_transient_after_read']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $payload=array('name'=>'demo/decision-race-approve','input'=>array('value'=>42),'method'=>'POST');
        $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Concurrent approval decision');
        $approval_id=$approval['approval_id']??'';
        assert_true($approval_id!=='','approval id returned');

        $nested=null;
        $armed=true;
        $GLOBALS['get_transient_after_read']=function($key,$value) use (&$nested,&$armed,$approval_id){
            if(!$armed || strpos((string)$key,'wpvdmcp_approval_')!==0 || !is_array($value) || ($value['id']??'')!==$approval_id)return;
            $armed=false;
            $nested=WPVDMCP_Approvals::reject($approval_id,1);
        };
        $outer=WPVDMCP_Approvals::approve($approval_id,1);
        unset($GLOBALS['get_transient_after_read']);

        $successes=($outer===true?1:0)+($nested===true?1:0);
        assert_same(1,$successes,'concurrent approve and reject must not both report success');
        assert_true(is_wp_error($outer)||is_wp_error($nested),'the losing decision must fail closed');
        $status=WPVDMCP_Approvals::status($approval_id);
        assert_same($outer===true?'approved':'rejected',$status['status']??null,'durable state must match the sole successful decision');
    }finally{
        if($had_hook)$GLOBALS['get_transient_after_read']=$hook_before;else unset($GLOBALS['get_transient_after_read']);
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});

parity_test('concurrent approval cannot also succeed while rejection wins',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_hook=array_key_exists('get_transient_after_read',$GLOBALS);
    $hook_before=$had_hook?$GLOBALS['get_transient_after_read']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $payload=array('name'=>'demo/decision-race-reject','input'=>array('value'=>43),'method'=>'POST');
        $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Concurrent rejection decision');
        $approval_id=$approval['approval_id']??'';
        assert_true($approval_id!=='','approval id returned');

        $nested=null;
        $armed=true;
        $GLOBALS['get_transient_after_read']=function($key,$value) use (&$nested,&$armed,$approval_id){
            if(!$armed || strpos((string)$key,'wpvdmcp_approval_')!==0 || !is_array($value) || ($value['id']??'')!==$approval_id)return;
            $armed=false;
            $nested=WPVDMCP_Approvals::approve($approval_id,1);
        };
        $outer=WPVDMCP_Approvals::reject($approval_id,1);
        unset($GLOBALS['get_transient_after_read']);

        $successes=($outer===true?1:0)+($nested===true?1:0);
        assert_same(1,$successes,'concurrent reject and approve must not both report success');
        assert_true(is_wp_error($outer)||is_wp_error($nested),'the losing decision must fail closed');
        $status=WPVDMCP_Approvals::status($approval_id);
        assert_same($outer===true?'rejected':'approved',$status['status']??null,'durable state must match the sole successful decision');
    }finally{
        if($had_hook)$GLOBALS['get_transient_after_read']=$hook_before;else unset($GLOBALS['get_transient_after_read']);
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});
