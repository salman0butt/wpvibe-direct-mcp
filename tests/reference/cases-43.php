<?php

parity_test('malformed upload ticket owner fails closed before public secret validation',function(){
    $options_before=$GLOBALS['wp_options'];
    try{
        $id='up_'.str_repeat('c',32);
        $secret='owner-shape-secret';
        $transient_key='transient:wpvdmcp_upload_'.$id;
        $GLOBALS['wp_options'][$transient_key]=array(
            'value'=>array(
                'id'=>$id,
                'secret_hash'=>hash('sha256',$secret),
                'owner_user_id'=>'1abc',
                'status'=>'waiting',
                'created_at'=>time(),
                'expires_at'=>time()+600,
                'post_id'=>0,
                'title'=>'',
                'alt_text'=>'',
                'files'=>array(),
            ),
            'expires'=>time()+600,
        );

        $result=WPVDMCP_Upload::validate_ticket($id,$secret);
        assert_true(is_wp_error($result),'malformed upload ticket owner must fail closed');
        assert_same('upload_corrupt',$result->get_error_code(),'malformed upload owner failure code');
        assert_true(!array_key_exists($transient_key,$GLOBALS['wp_options']),'malformed upload ticket must be invalidated');
    }finally{
        $GLOBALS['wp_options']=$options_before;
    }
});
