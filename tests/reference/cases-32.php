<?php
parity_test('corrupt approval owner fails closed before receipt consumption',function(){
    $id='ap_'.str_repeat('c',32);
    $transient_key='transient:wpvdmcp_approval_'.$id;
    $claim_key='wpvdmcp_approval_claim_'.hash('sha256',$id);
    $had_transient=array_key_exists($transient_key,$GLOBALS['wp_options']);
    $previous_transient=$had_transient?$GLOBALS['wp_options'][$transient_key]:null;
    $had_claim=array_key_exists($claim_key,$GLOBALS['wp_options']);
    $previous_claim=$had_claim?$GLOBALS['wp_options'][$claim_key]:null;
    $payload=array('method'=>'POST','name'=>'demo/owner-corrupt');
    try{
        $GLOBALS['wp_options'][$transient_key]=array(
            'value'=>array(
                'id'=>$id,
                'owner_user_id'=>(object)array('corrupt'=>true),
                'operation'=>'run_ability',
                'payload_hash'=>hash('sha256',wp_json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
                'status'=>'approved',
                'summary'=>'Corrupt approval owner',
                'display_payload'=>array(),
                'created_at'=>time(),
                'expires_at'=>time()+600,
            ),
            'expires'=>time()+600,
        );

        $result=WPVDMCP_Approvals::consume($id,'run_ability',$payload);

        assert_true(is_wp_error($result),'corrupt approval owner must fail closed');
        assert_same('approval_corrupt',$result->get_error_code(),'corrupt approval owner failure code');
        assert_true(!array_key_exists($transient_key,$GLOBALS['wp_options']),'corrupt approval receipt must be invalidated');
        assert_true(!array_key_exists($claim_key,$GLOBALS['wp_options']),'corrupt approval receipt must not leave a consumption claim');
    }finally{
        if($had_transient)$GLOBALS['wp_options'][$transient_key]=$previous_transient;else unset($GLOBALS['wp_options'][$transient_key]);
        if($had_claim)$GLOBALS['wp_options'][$claim_key]=$previous_claim;else unset($GLOBALS['wp_options'][$claim_key]);
    }
});
