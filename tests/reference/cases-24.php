<?php
parity_test('corrupt upload tickets fail closed before status serialization',function(){
    $id='up_'.str_repeat('b',32);
    $transient_key='transient:wpvdmcp_upload_'.$id;
    $claim_key='wpvdmcp_upload_claim_'.hash('sha256',$id);
    $had_transient=array_key_exists($transient_key,$GLOBALS['wp_options']);
    $previous_transient=$had_transient?$GLOBALS['wp_options'][$transient_key]:null;
    $had_claim=array_key_exists($claim_key,$GLOBALS['wp_options']);
    $previous_claim=$had_claim?$GLOBALS['wp_options'][$claim_key]:null;
    try{
        $GLOBALS['wp_options'][$transient_key]=array(
            'value'=>array(
                'id'=>$id,
                'secret_hash'=>'',
                'owner_user_id'=>1,
                'status'=>'ready',
                'created_at'=>time(),
                'expires_at'=>time()+600,
                'post_id'=>0,
                'title'=>'',
                'alt_text'=>'',
                'files'=>'corrupt',
            ),
            'expires'=>time()+600,
        );
        $result=WPVDMCP_Upload::check($id);
        assert_true(is_wp_error($result),'corrupt upload ticket must fail closed');
        assert_same('upload_corrupt',$result->get_error_code(),'corrupt upload failure code');
        assert_true(!array_key_exists($transient_key,$GLOBALS['wp_options']),'corrupt upload ticket must be invalidated');
        assert_true(!array_key_exists($claim_key,$GLOBALS['wp_options']),'corrupt upload ticket must not leave a transition claim');
    }finally{
        if($had_transient)$GLOBALS['wp_options'][$transient_key]=$previous_transient;else unset($GLOBALS['wp_options'][$transient_key]);
        if($had_claim)$GLOBALS['wp_options'][$claim_key]=$previous_claim;else unset($GLOBALS['wp_options'][$claim_key]);
    }
});