<?php

function wpvdmcp_upload_ticket_secret_from_url($url) {
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);
    return $query['upload_token'] ?? '';
}

parity_test('revoked token owner invalidates outstanding browser upload ticket',function(){
    $options_before=$GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $created=WPVDMCP_Upload::request(array('title'=>'Revocation proof'));
        assert_true(!is_wp_error($created),'ticket created for active owner');
        $id=$created['upload_id'];
        $secret=wpvdmcp_upload_ticket_secret_from_url($created['upload_url']);
        $GLOBALS['wp_options']['wpvdmcp_user_id']=0;
        $GLOBALS['wp_options']['wpvdmcp_token_hash']='';
        $status=WPVDMCP_Upload::check($id);
        assert_true(is_wp_error($status),'MCP status rejects revoked owner');
        $valid=WPVDMCP_Upload::validate_ticket($id,$secret);
        assert_true(is_wp_error($valid),'browser upload ticket must reject the same revoked owner');
        assert_same('upload_owner_changed',$valid->get_error_code(),'revoked ticket error');
        $started=WPVDMCP_Upload::begin_processing($id,$secret);
        assert_true(is_wp_error($started),'revoked owner cannot reserve media creation');
        assert_same('upload_owner_changed',$started->get_error_code(),'revoked owner reservation error');
        assert_true(!isset($GLOBALS['wp_options']['wpvdmcp_upload_claim_'.hash('sha256',$id)]),'revoked owner must not claim upload side effects');
    }finally{$GLOBALS['wp_options']=$options_before;}
});

parity_test('rotated token invalidates previously issued browser upload ticket',function(){
    $options_before=$GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','original-token');
        $ticket=WPVDMCP_Upload::request(array());
        $id=$ticket['upload_id'];$secret=wpvdmcp_upload_ticket_secret_from_url($ticket['upload_url']);
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','rotated-token');
        $result=WPVDMCP_Upload::validate_ticket($id,$secret);
        assert_true(is_wp_error($result),'rotating the MCP bearer revokes prior browser upload link');
        assert_same('upload_owner_changed',$result->get_error_code(),'rotated ticket error');
        assert_true(is_wp_error(WPVDMCP_Upload::begin_processing($id,$secret)),'rotated ticket cannot create media');
    } finally {$GLOBALS['wp_options']=$options_before;}
});

parity_test('legacy upload ticket without credential binding fails closed after upgrade',function(){
    $options_before=$GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $ticket=WPVDMCP_Upload::request(array());
        $id=$ticket['upload_id'];$secret=wpvdmcp_upload_ticket_secret_from_url($ticket['upload_url']);
        unset($GLOBALS['wp_options']['transient:wpvdmcp_upload_'.$id]['value']['token_hash_fingerprint']);
        $result=WPVDMCP_Upload::validate_ticket($id,$secret);
        assert_true(is_wp_error($result),'unbound pre-hardening ticket cannot create media after upgrade');
        assert_same('upload_corrupt',$result->get_error_code(),'unbound ticket is invalidated');
    } finally {$GLOBALS['wp_options']=$options_before;}
});

parity_test('browser ticket issuance rejects set-transient success without a stored ticket',function(){
    $options_before=$GLOBALS['wp_options'];
    $had_override=array_key_exists('set_transient_override',$GLOBALS);
    $override_before=$had_override?$GLOBALS['set_transient_override']:null;
    try {
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $GLOBALS['set_transient_override']=function($key,$record,$ttl){
            return strpos($key,'wpvdmcp_upload_')===0 && is_array($record) && ($record['status']??'')==='waiting'?true:null;
        };
        $result=WPVDMCP_Upload::request(array());
        assert_true(is_wp_error($result),'nonpersisted browser ticket must not return an unusable URL');
        assert_same('upload_persist_failed',$result->get_error_code(),'ticket issuance persistence error');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        if($had_override)$GLOBALS['set_transient_override']=$override_before;
        else unset($GLOBALS['set_transient_override']);
    }
});

parity_test('upload reservation does not trust a transient write acknowledged without persistence',function(){
    $options_before=$GLOBALS['wp_options'];
    $had_override=array_key_exists('set_transient_override',$GLOBALS);
    $override_before=$had_override?$GLOBALS['set_transient_override']:null;
    try {
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $created=WPVDMCP_Upload::request(array());
        $id=$created['upload_id'];$secret=wpvdmcp_upload_ticket_secret_from_url($created['upload_url']);
        $GLOBALS['set_transient_override']=function($key,$record,$ttl)use($id){
            return $key==='wpvdmcp_upload_'.$id&&is_array($record)&&($record['status']??'')==='processing'?true:null;
        };
        $started=WPVDMCP_Upload::begin_processing($id,$secret);
        assert_true(is_wp_error($started),'unpersisted reservation must reject media creation');
        assert_same('upload_persist_failed',$started->get_error_code(),'unpersisted reservation error code');
        assert_same('waiting',WPVDMCP_Upload::check($id)['status']??null,'storage remains waiting');
        $retried=WPVDMCP_Upload::begin_processing($id,$secret);
        assert_true(is_wp_error($retried),'uncertain reservation cannot retry while prior claim is in progress');
        assert_same('upload_in_progress',$retried->get_error_code(),'uncertain reservation retained claim');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        if($had_override)$GLOBALS['set_transient_override']=$override_before;
        else unset($GLOBALS['set_transient_override']);
    }
});

parity_test('upload finalization does not trust an unpersisted ready state',function(){
    $options_before=$GLOBALS['wp_options'];
    $had_override=array_key_exists('set_transient_override',$GLOBALS);
    $override_before=$had_override?$GLOBALS['set_transient_override']:null;
    try {
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $created=WPVDMCP_Upload::request(array());
        $id=$created['upload_id'];$secret=wpvdmcp_upload_ticket_secret_from_url($created['upload_url']);
        assert_same(true,WPVDMCP_Upload::begin_processing($id,$secret),'reserve ticket first');
        $files=array(array('attachment_id'=>802,'filename'=>'safe.png','mime_type'=>'image/png','url'=>'https://example.test/uploads/safe.png','width'=>16,'height'=>16));
        $GLOBALS['set_transient_override']=function($key,$record,$ttl)use($id){
            return $key==='wpvdmcp_upload_'.$id&&is_array($record)&&($record['status']??'')==='ready'?true:null;
        };
        $done=WPVDMCP_Upload::finalize($id,$secret,$files);
        assert_true(is_wp_error($done),'unpersisted completion cannot report success');
        assert_same('upload_persist_failed',$done->get_error_code(),'unpersisted completion error');
        assert_same('processing',WPVDMCP_Upload::check($id)['status']??null,'storage remains processing');
        $retried=WPVDMCP_Upload::finalize($id,$secret,$files);
        assert_true(is_wp_error($retried),'uncertain completion must retain claim');
        assert_same('upload_in_progress',$retried->get_error_code(),'uncertain completion retained claim');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        if($had_override)$GLOBALS['set_transient_override']=$override_before;
        else unset($GLOBALS['set_transient_override']);
    }
});
