<?php
parity_test('corrupt approval receipts fail closed before payload comparison',function(){
    $id='ap_'.str_repeat('a',32);
    $transient_key='transient:wpvdmcp_approval_'.$id;
    $claim_key='wpvdmcp_approval_claim_'.hash('sha256',$id);
    $had_transient=array_key_exists($transient_key,$GLOBALS['wp_options']);
    $previous_transient=$had_transient?$GLOBALS['wp_options'][$transient_key]:null;
    $had_claim=array_key_exists($claim_key,$GLOBALS['wp_options']);
    $previous_claim=$had_claim?$GLOBALS['wp_options'][$claim_key]:null;
    try{
        $GLOBALS['wp_options'][$transient_key]=array(
            'value'=>array(
                'id'=>$id,
                'owner_user_id'=>1,
                'operation'=>'run_ability',
                'status'=>'approved',
                'summary'=>'Corrupt receipt',
                'display_payload'=>array(),
                'created_at'=>time(),
                'expires_at'=>time()+600,
            ),
            'expires'=>time()+600,
        );
        $result=WPVDMCP_Approvals::consume($id,'run_ability',array('name'=>'demo/corrupt','method'=>'POST'));
        assert_true(is_wp_error($result),'corrupt approval receipt must fail closed');
        assert_same('approval_corrupt',$result->get_error_code(),'corrupt approval failure code');
        assert_true(!array_key_exists($claim_key,$GLOBALS['wp_options']),'corrupt receipt must not leave a consumption claim behind');
    }finally{
        if($had_transient)$GLOBALS['wp_options'][$transient_key]=$previous_transient;else unset($GLOBALS['wp_options'][$transient_key]);
        if($had_claim)$GLOBALS['wp_options'][$claim_key]=$previous_claim;else unset($GLOBALS['wp_options'][$claim_key]);
    }
});
