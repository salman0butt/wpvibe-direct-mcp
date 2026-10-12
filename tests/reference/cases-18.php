<?php
parity_test('provider outputs redact credential-shaped keys without redacting normal prose',function(){
    $GLOBALS['wpvdmcp_providers']=array('search_images'=>function($args){
        return array(
            'results'=>array(array(
                'url'=>'https://img.test/one.jpg',
                'api_key'=>'local-key-value',
                'Authorization'=>'Bearer local-value',
                'nested'=>array('refreshToken'=>'refresh-value','safe'=>'kept'),
                'consumerKey'=>'consumer-key-value',
                'awsAccessKeyId'=>'aws-access-value',
            )),
            'note'=>'This token wording is ordinary prose and must remain unchanged.',
            'token_count'=>7,
            '_wpvdmcp_provider_output'=>array('redacted_fields'=>0,'truncated'=>false),
        );
    });
    $r=WPVDMCP_Parity::execute('search_images',array('query'=>'clinic'));
    unset($GLOBALS['wpvdmcp_providers']);

    assert_true(!isset($r['results'][0]['api_key']),'api_key must be removed');
    assert_true(!isset($r['results'][0]['Authorization']),'Authorization must be removed');
    assert_true(!isset($r['results'][0]['nested']['refreshToken']),'camelCase token key must be removed');
    assert_true(!isset($r['results'][0]['consumerKey']),'consumer key credential must be removed');
    assert_true(!isset($r['results'][0]['awsAccessKeyId']),'access key id credential must be removed');
    assert_same('kept',$r['results'][0]['nested']['safe']??null,'safe nested value preserved');
    assert_same('This token wording is ordinary prose and must remain unchanged.',$r['note']??null,'ordinary prose preserved');
    assert_same(7,$r['token_count']??null,'non-credential token_count preserved');
    assert_same(5,$r['_wpvdmcp_provider_output']['redacted_fields']??null,'redaction count reported');
    assert_same(true,$r['_wpvdmcp_provider_output']['reserved_metadata_removed']??null,'provider cannot spoof sanitizer metadata');
});

parity_test('provider outputs bound strings collections and nesting with explicit metadata',function(){
    $deep='leaf';
    for($i=0;$i<12;$i++)$deep=array('level_'.$i=>$deep);
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use($deep){
        return array(
            'score'=>91,
            'huge'=>str_repeat('x',1500000),
            'items'=>range(1,250),
            'deep'=>$deep,
            'chunks'=>array_fill(0,10,str_repeat('c',50000)),
        );
    });
    $r=WPVDMCP_Parity::execute('audit_page',array('page_url'=>'https://example.test/'));
    unset($GLOBALS['wpvdmcp_providers']);

    assert_true(strlen($r['huge']??'')<=65536,'single provider string bounded');
    assert_true(strpos($r['huge']??'','[truncated by WPVibe Direct MCP]')!==false,'string truncation is visible');
    assert_same(200,count($r['items']??array()),'collection capped to 200 items');
    assert_true(strpos(json_encode($r['deep']??array()),'[truncated: provider output depth limit]')!==false,'depth limit is visible');
    assert_same(true,$r['_wpvdmcp_provider_output']['truncated']??null,'truncation metadata reported');
    assert_true(($r['_wpvdmcp_provider_output']['dropped_items']??0)>=50,'dropped collection items counted');
    assert_same(true,$r['_wpvdmcp_provider_output']['depth_limited']??null,'depth metadata reported');
    assert_true(strlen(json_encode($r))<=262144,'serialized provider payload stays within the 262144-byte total limit');
});

parity_test('provider output fails closed when JSON escaping would exceed the serialized total limit',function(){
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args){
        return array('chunks'=>array_fill(0,10,str_repeat("\x01",50000)));
    });
    $r=WPVDMCP_Parity::execute('audit_page',array('page_url'=>'https://example.test/'));
    unset($GLOBALS['wpvdmcp_providers']);

    assert_true(strlen(json_encode($r))<=262144,'escaped JSON remains within total provider-output limit');
    assert_same(true,$r['_wpvdmcp_provider_output']['serialized_limit']??null,'serialized-limit fallback is explicit');
    assert_same(true,$r['_wpvdmcp_provider_output']['truncated']??null,'serialized-limit fallback reports truncation');
});

parity_test('hosted reference providers use the same output sanitizer',function(){
    $GLOBALS['wpvdmcp_reference_providers']=array('get_profile'=>function($args){
        return array(
            'name'=>'Owner',
            'account'=>array('plan'=>'pro','client_secret'=>'hosted-secret'),
            'apiKey'=>'hosted-key',
        );
    });
    $r=WPVDMCP_Parity::execute('get_profile',array());
    unset($GLOBALS['wpvdmcp_reference_providers']);

    assert_same('Owner',$r['name']??null,'safe profile data preserved');
    assert_same('pro',$r['account']['plan']??null,'safe nested hosted data preserved');
    assert_true(!isset($r['account']['client_secret']),'nested hosted secret removed');
    assert_true(!isset($r['apiKey']),'camelCase hosted key removed');
    assert_same(2,$r['_wpvdmcp_provider_output']['redacted_fields']??null,'hosted redaction metadata reported');
});

parity_test('provider WP_Error data is redacted and bounded without changing its code',function(){
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args){
        return new WP_Error(
            'audit_failed',
            str_repeat('m',5000),
            array(
                'status'=>502,
                'api_key'=>'error-key',
                'detail'=>str_repeat('d',90000),
                'token_count'=>12,
            )
        );
    });
    $r=WPVDMCP_Parity::execute('audit_page',array('page_url'=>'https://example.test/'));
    unset($GLOBALS['wpvdmcp_providers']);

    assert_true(is_wp_error($r),'provider error preserved as WP_Error');
    assert_same('audit_failed',$r->get_error_code(),'provider error code preserved');
    assert_true(strlen($r->get_error_message())<=4096,'provider error message bounded');
    $data=$r->get_error_data();
    assert_same(502,$data['status']??null,'provider status preserved');
    assert_true(!isset($data['api_key']),'provider error credential removed');
    assert_true(strlen($data['detail']??'')<=65536,'provider error detail bounded');
    assert_same(12,$data['token_count']??null,'safe error data preserved');
    assert_same(true,$data['_wpvdmcp_provider_output']['truncated']??null,'provider error truncation metadata reported');
    assert_same(1,$data['_wpvdmcp_provider_output']['redacted_fields']??null,'provider error redaction metadata reported');
});

parity_test('provider object output is normalized before nested credential filtering',function(){
    $GLOBALS['wpvdmcp_reference_providers']=array('get_profile'=>function($args){
        $nested=new stdClass();$nested->role='admin';$nested->password='object-secret';
        $profile=new stdClass();$profile->name='Owner';$profile->nested=$nested;
        return $profile;
    });
    $r=WPVDMCP_Parity::execute('get_profile',array());
    unset($GLOBALS['wpvdmcp_reference_providers']);

    assert_true(is_array($r),'provider object normalized to an MCP-safe array');
    assert_same('Owner',$r['name']??null,'object safe data preserved');
    assert_same('admin',$r['nested']['role']??null,'nested object safe data preserved');
    assert_true(!isset($r['nested']['password']),'nested object credential removed');
    assert_same(true,$r['_wpvdmcp_provider_output']['object_normalized']??null,'object normalization reported');
});
