<?php
/** Upload ticket and rollback partial-failure fault-injection regressions. */
parity_test('failed upload state acknowledgement without persistence retains the transition claim', function () {
    $before = $GLOBALS['wp_options'];
    $had_override = array_key_exists('set_transient_override',$GLOBALS);
    $prior_override = $had_override ? $GLOBALS['set_transient_override'] : null;
    try {
        $ticket = WPVDMCP_Upload::request(array('title'=>'Failed state must persist'));
        assert_true(!is_wp_error($ticket), 'created a valid disposable upload ticket');
        $parts=parse_url($ticket['upload_url']); parse_str($parts['query']??'', $query);
        $id=$ticket['upload_id']; $secret=$query['upload_token']??'';
        assert_same(true,WPVDMCP_Upload::begin_processing($id,$secret),'processing reserved');
        $GLOBALS['set_transient_override']=function($key,$record)use($id){
            return $key==='wpvdmcp_upload_'.$id && is_array($record) && ($record['status']??'')==='failed' ? true : null;
        };
        $method=new ReflectionMethod('WPVDMCP_Upload','fail_processing');
        if(PHP_VERSION_ID<80100)$method->setAccessible(true);
        $marked=$method->invoke(null,$id,$secret,new WP_Error('upload_test_failure','Test failed upload.'));
        assert_true(is_wp_error($marked),'failed upload must not pretend terminal status was stored');
        assert_same('upload_persist_failed',$marked->get_error_code(),'unpersisted failed state returns explicit durability error');
        assert_same('processing',WPVDMCP_Upload::check($id)['status']??null,'unpersisted failed state remains processing');
        $claim='wpvdmcp_upload_claim_'.hash('sha256',$id);
        assert_true(null!==get_option($claim,null),'uncertain failed-state persistence keeps claim until expiry');
    }finally{
        $GLOBALS['wp_options']=$before;
        if($had_override)$GLOBALS['set_transient_override']=$prior_override;else unset($GLOBALS['set_transient_override']);
    }
});

parity_test('failed WordPress attachment rollback discloses orphan IDs and original failure', function () {
    $had_override=array_key_exists('delete_attachment_override',$GLOBALS);
    $prior_override=$had_override?$GLOBALS['delete_attachment_override']:null;
    $had_deleted=array_key_exists('test_deleted_attachments',$GLOBALS);
    $prior_deleted=$had_deleted?$GLOBALS['test_deleted_attachments']:null;
    try{
        $GLOBALS['test_deleted_attachments']=array();
        $GLOBALS['delete_attachment_override']=function($id){return $id===601?false:true;};
        $method=new ReflectionMethod('WPVDMCP_Upload','rollback_error');
        if(PHP_VERSION_ID<80100)$method->setAccessible(true);
        $original=new WP_Error('unsupported_media_type','Second file refused.',array('status'=>415));
        $reported=$method->invoke(null,array(
            array('attachment_id'=>601,'filename'=>'first.png'),
            array('attachment_id'=>602,'filename'=>'another.png'),
        ),$original);
        assert_true(is_wp_error($reported),'rollback must return an explicit failure');
        assert_same('upload_rollback_failed',$reported->get_error_code(),'unsuccessful cleanup cannot be hidden by the original validation error');
        $data=$reported->get_error_data();
        assert_same(true,$data['partial_applied']??null,'partial media remains and requires manual attention');
        assert_same(array(601),$data['orphaned_attachment_ids']??null,'only undeleted attachments are disclosed for reconciliation');
        assert_same('unsupported_media_type',$data['original_error_code']??null,'preserve initial reason for debugging');
        assert_same(array(601,602),$GLOBALS['test_deleted_attachments'],'rollback attempted every attachment including after one failure');
    }finally{
        if($had_override)$GLOBALS['delete_attachment_override']=$prior_override;else unset($GLOBALS['delete_attachment_override']);
        if($had_deleted)$GLOBALS['test_deleted_attachments']=$prior_deleted;else unset($GLOBALS['test_deleted_attachments']);
    }
});

parity_test('inline MCP App upload keeps its form and iframe until a terminal ticket state', function () {
    $resource = WPVDMCP_MCP_Apps::read_resource('ui://wpvibe-direct/upload-panel');
    assert_true(!is_wp_error($resource), 'upload UI resource available');
    $html = (string) ($resource['contents'][0]['text'] ?? '');
    assert_true(strpos($html, 'setTimeout(r,1800)') === false, 'fixed 1.8-second iframe teardown can interrupt slow uploads');
    assert_true(strpos($html, "data.status==='processing'") !== false, 'inline uploader must explicitly handle the in-progress state');
    assert_true(strpos($html, 'if(terminal)') !== false, 'hidden upload form/iframe cleanup must be conditional on a verified terminal outcome');
    assert_true(strpos($html, 'form.remove()') !== false && strpos($html, 'frame.remove()') !== false, 'completed transfers should still clean up temporary elements');
});

parity_test('safe upload status exposes reconciliation IDs but not bearer or ticket secrets', function () {
    $before=$GLOBALS['wp_options'];
    try {
        $ticket=WPVDMCP_Upload::request(array('title'=>'Orphan disclosure after failed rollback'));
        assert_true(!is_wp_error($ticket),'ticket issued');
        $parts=parse_url($ticket['upload_url']); parse_str($parts['query']??'', $query);
        $id=$ticket['upload_id']; $secret=$query['upload_token']??'';
        assert_same(true,WPVDMCP_Upload::begin_processing($id,$secret),'ticket reserved');
        $reflection=new ReflectionMethod('WPVDMCP_Upload','fail_processing');
        if(PHP_VERSION_ID<80100)$reflection->setAccessible(true);
        $error=new WP_Error('upload_rollback_failed','Partial media cleanup failed.',array(
            'partial_applied'=>true,'orphaned_attachment_ids'=>array(601,0,602),'original_error_code'=>'unsupported_media_type',
        ));
        assert_same(true,$reflection->invoke(null,$id,$secret,$error),'persist failed status');
        $status=WPVDMCP_Upload::check($id);
        assert_same('failed',$status['status']??null,'status is failed');
        assert_same('upload_rollback_failed',$status['failure_code']??null,'status exposes failure code');
        assert_same(true,$status['partial_applied']??null,'status warns of partial Media Library side effects');
        assert_same(array(601,602),$status['orphaned_attachment_ids']??null,'status provides real IDs to manually reconcile');
        assert_true(!isset($status['secret_hash'])&&!isset($status['token_hash_fingerprint']),'status does not disclose bearer or browser secrets');
    }finally{$GLOBALS['wp_options']=$before;}
});
