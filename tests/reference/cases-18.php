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
                'secretAccessKey'=>'secret-access-key-value',
                'oauthConsumerKey'=>'oauth-consumer-key-value',
                'oauth_consumer_key'=>'oauth-consumer-key-value-2',
                'AWSAccessKeyId'=>'aws-acronym-access-value',
                'AWSSecretAccessKey'=>'aws-acronym-secret-value',
                'XApiKey'=>'x-acronym-api-value',
                'authorizationHeader'=>'Bearer contextual-secret',
                'authHeader'=>'Basic contextual-secret',
                'cookieHeader'=>'session=contextual-secret',
                'passwordValue'=>'contextual-password',
                'secretValue'=>'contextual-secret-value',
                'accessTokenValue'=>'contextual-access-token',
                'authenticationHeader'=>'Bearer contextual-authentication',
                'privateKeyPem'=>'-----BEGIN PRIVATE KEY----- contextual',
                'accessTokenData'=>'contextual-token-data',
                'httpAuthorization'=>'Bearer http-secret',
                'clientAuthorization'=>'Bearer client-secret',
                'requestAuthorization'=>'Bearer request-secret',
                'httpAuth'=>'user:http-password',
                'clientAuth'=>'client-auth-secret',
                'tokenValue'=>'generic-token-secret',
                'passwordHashValue'=>'password-hash-secret',
                'authorizationHeaderValue'=>'Bearer nested-wrapper-secret',
                'clientCredentials'=>array('id'=>'client-id','secret'=>'client-secret'),
                'userCredentials'=>'user-credential-secret',
                'oauthCredentials'=>'oauth-credential-secret',
                'stripeSecretKey'=>'stripe-secret-key-value',
                'apiSecretKey'=>'api-secret-key-value',
                'AWSCredentials'=>'AKIAEXAMPLE:SECRETEXAMPLE',
                'bearerCredentials'=>'Bearer EXAMPLE-TOKEN',
                'stripeCredentials'=>'sk_live_EXAMPLE',
                'clientSecrets'=>array('secret-one','secret-two'),
                'accessTokens'=>array('token-one','token-two'),
                'apiKeys'=>array('api-one','api-two'),
                'AWSCREDENTIALS'=>'AKIAUPPER:SECRETUPPER',
                'CLIENTSECRETS'=>array('upper-secret'),
                'ACCESSTOKENS'=>array('upper-token'),
                'APIKEYS'=>array('upper-api-key'),
                'AUTHORIZATIONHEADERVALUE'=>'Bearer UPPER-WRAPPED',
                'ACCESSTOKENVALUE'=>'UPPER-TOKEN-VALUE',
            )),
            'note'=>'This token wording is ordinary prose and must remain unchanged.',
            'token_count'=>7,
            'apiKeyCount'=>2,
            'consumer_key_count'=>3,
            'password_reset_required'=>true,
            'public_key'=>'public-material-is-not-a-private-key',
            'publicKeyPem'=>'-----BEGIN PUBLIC KEY----- safe-public-material',
            'supportsAuth'=>true,
            'requiresAuthorization'=>false,
            'supportsCredentials'=>true,
            'supportsTokens'=>true,
            'SUPPORTSCREDENTIALS'=>true,
            'HASAPIKEYS'=>true,
            'USESSECRETS'=>true,
            'oauthAuthorization'=>'https://auth.example.test/authorize',
            'tokenExpiresAt'=>'2026-10-12T00:00:00Z',
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
    assert_true(!isset($r['results'][0]['secretAccessKey']),'secret access key credential must be removed');
    assert_true(!isset($r['results'][0]['oauthConsumerKey']),'OAuth consumer key credential must be removed');
    assert_true(!isset($r['results'][0]['oauth_consumer_key']),'snake-case OAuth consumer key credential must be removed');
    assert_true(!isset($r['results'][0]['AWSAccessKeyId']),'leading-acronym AWS access key credential must be removed');
    assert_true(!isset($r['results'][0]['AWSSecretAccessKey']),'leading-acronym AWS secret access key credential must be removed');
    assert_true(!isset($r['results'][0]['XApiKey']),'leading-acronym API key credential must be removed');
    assert_true(!isset($r['results'][0]['authorizationHeader']),'authorization header credential must be removed');
    assert_true(!isset($r['results'][0]['authHeader']),'auth header credential must be removed');
    assert_true(!isset($r['results'][0]['cookieHeader']),'cookie header credential must be removed');
    assert_true(!isset($r['results'][0]['passwordValue']),'password value credential must be removed');
    assert_true(!isset($r['results'][0]['secretValue']),'secret value credential must be removed');
    assert_true(!isset($r['results'][0]['accessTokenValue']),'access token value credential must be removed');
    assert_true(!isset($r['results'][0]['authenticationHeader']),'authentication header credential must be removed');
    assert_true(!isset($r['results'][0]['privateKeyPem']),'private key PEM credential must be removed');
    assert_true(!isset($r['results'][0]['accessTokenData']),'access token data credential must be removed');
    assert_true(!isset($r['results'][0]['httpAuthorization']),'HTTP authorization credential must be removed');
    assert_true(!isset($r['results'][0]['clientAuthorization']),'client authorization credential must be removed');
    assert_true(!isset($r['results'][0]['requestAuthorization']),'request authorization credential must be removed');
    assert_true(!isset($r['results'][0]['httpAuth']),'HTTP auth credential must be removed');
    assert_true(!isset($r['results'][0]['clientAuth']),'client auth credential must be removed');
    assert_true(!isset($r['results'][0]['tokenValue']),'generic token value credential must be removed');
    assert_true(!isset($r['results'][0]['passwordHashValue']),'password hash value credential must be removed');
    assert_true(!isset($r['results'][0]['authorizationHeaderValue']),'wrapped authorization header value must be removed');
    assert_true(!isset($r['results'][0]['clientCredentials']),'client credentials must be removed');
    assert_true(!isset($r['results'][0]['userCredentials']),'user credentials must be removed');
    assert_true(!isset($r['results'][0]['oauthCredentials']),'OAuth credentials must be removed');
    assert_true(!isset($r['results'][0]['stripeSecretKey']),'provider-prefixed secret key must be removed');
    assert_true(!isset($r['results'][0]['apiSecretKey']),'API-prefixed secret key must be removed');
    assert_true(!isset($r['results'][0]['AWSCredentials']),'arbitrary AWS credentials field must be removed');
    assert_true(!isset($r['results'][0]['bearerCredentials']),'arbitrary bearer credentials field must be removed');
    assert_true(!isset($r['results'][0]['stripeCredentials']),'arbitrary provider credentials field must be removed');
    assert_true(!isset($r['results'][0]['clientSecrets']),'plural secrets field must be removed');
    assert_true(!isset($r['results'][0]['accessTokens']),'plural access tokens field must be removed');
    assert_true(!isset($r['results'][0]['apiKeys']),'plural API keys field must be removed');
    assert_true(!isset($r['results'][0]['AWSCREDENTIALS']),'compact uppercase credentials field must be removed');
    assert_true(!isset($r['results'][0]['CLIENTSECRETS']),'compact uppercase secrets field must be removed');
    assert_true(!isset($r['results'][0]['ACCESSTOKENS']),'compact uppercase access tokens field must be removed');
    assert_true(!isset($r['results'][0]['APIKEYS']),'compact uppercase API keys field must be removed');
    assert_true(!isset($r['results'][0]['AUTHORIZATIONHEADERVALUE']),'compact uppercase wrapped authorization field must be removed');
    assert_true(!isset($r['results'][0]['ACCESSTOKENVALUE']),'compact uppercase wrapped access token field must be removed');
    assert_same('kept',$r['results'][0]['nested']['safe']??null,'safe nested value preserved');
    assert_same('This token wording is ordinary prose and must remain unchanged.',$r['note']??null,'ordinary prose preserved');
    assert_same(7,$r['token_count']??null,'non-credential token_count preserved');
    assert_same(2,$r['apiKeyCount']??null,'API-key count metadata preserved');
    assert_same(3,$r['consumer_key_count']??null,'consumer-key count metadata preserved');
    assert_same(true,$r['password_reset_required']??null,'password reset state preserved');
    assert_same('public-material-is-not-a-private-key',$r['public_key']??null,'public key material preserved');
    assert_same('-----BEGIN PUBLIC KEY----- safe-public-material',$r['publicKeyPem']??null,'public key PEM material preserved');
    assert_same(true,$r['supportsAuth']??null,'auth capability metadata preserved');
    assert_same(false,$r['requiresAuthorization']??null,'authorization requirement metadata preserved');
    assert_same(true,$r['supportsCredentials']??null,'credentials capability metadata preserved');
    assert_same(true,$r['supportsTokens']??null,'token capability metadata preserved');
    assert_same(true,$r['SUPPORTSCREDENTIALS']??null,'uppercase credentials capability metadata preserved');
    assert_same(true,$r['HASAPIKEYS']??null,'uppercase API-key status metadata preserved');
    assert_same(true,$r['USESSECRETS']??null,'uppercase secret capability metadata preserved');
    assert_same('https://auth.example.test/authorize',$r['oauthAuthorization']??null,'OAuth authorization endpoint metadata preserved');
    assert_same('2026-10-12T00:00:00Z',$r['tokenExpiresAt']??null,'token expiry metadata preserved');
    assert_same(45,$r['_wpvdmcp_provider_output']['redacted_fields']??null,'redaction count reported');
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

parity_test('provider output bounds oversized map keys without losing safe siblings',function(){
    $huge_key=str_repeat('k',70000);
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use($huge_key){
        return array('container'=>array($huge_key=>'value','safe'=>'kept'));
    });
    $r=WPVDMCP_Parity::execute('audit_page',array('page_url'=>'https://example.test/'));
    unset($GLOBALS['wpvdmcp_providers']);

    assert_same('kept',$r['container']['safe']??null,'safe sibling after oversized key is preserved');
    foreach(array_keys($r['container']??array()) as $key)assert_true(!is_string($key)||strlen($key)<=65536,'provider map key must respect single-string bound');
    assert_same(true,$r['_wpvdmcp_provider_output']['truncated']??null,'oversized key is reported as truncation');
    assert_true(($r['_wpvdmcp_provider_output']['dropped_items']??0)>=1,'oversized key item is counted as dropped');
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

parity_test('provider success output accounts for MCP pretty-print expansion',function(){
    $row=array();
    for($i=0;$i<100;$i++)$row['k'.$i]='x';
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use($row){
        return array('rows'=>array_fill(0,200,$row));
    });
    $payload=array('jsonrpc'=>'2.0','id'=>'provider-success-pretty','method'=>'tools/call','params'=>array('name'=>'audit_page','arguments'=>array('page_url'=>'https://example.test/')));
    $response=WPVDMCP_Server::instance()->handle(test_request($payload));
    unset($GLOBALS['wpvdmcp_providers']);

    $body=response_data($response);
    $text=$body['result']['content'][0]['text']??null;
    assert_true(is_string($text),'provider success must serialize to model-visible text');
    assert_true(strlen($text)<=262144,'model-visible provider success must account for pretty-print expansion');
    $structured=$body['result']['structuredContent']??array();
    assert_same(true,$structured['_wpvdmcp_provider_output']['serialized_limit']??null,'pretty-print expansion triggers serialized-limit fallback');
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

parity_test('provider WP_Error bounds the complete model-visible error envelope',function(){
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args){
        return new WP_Error(
            str_repeat('e',400000),
            "invalid-utf8-\xFF-message",
            array('status'=>502,'detail'=>str_repeat('z',240000))
        );
    });
    $payload=array('jsonrpc'=>'2.0','id'=>'provider-error-envelope','method'=>'tools/call','params'=>array('name'=>'audit_page','arguments'=>array('page_url'=>'https://example.test/')));
    $response=WPVDMCP_Server::instance()->handle(test_request($payload));
    unset($GLOBALS['wpvdmcp_providers']);

    $body=response_data($response);
    $text=$body['result']['content'][0]['text']??null;
    assert_true(is_string($text),'provider error must serialize to model-visible text');
    assert_true(strlen($text)<=262144,'complete model-visible provider error must stay within the 262144-byte ceiling');
    $decoded=json_decode($text,true);
    assert_true(is_array($decoded),'provider error text must remain valid JSON after invalid UTF-8 input');
    assert_true(strlen((string)($decoded['code']??''))<=256,'provider error code is bounded');
    assert_same(true,$decoded['data']['_wpvdmcp_provider_output']['truncated']??null,'provider error envelope truncation is explicit');
});

parity_test('provider WP_Error envelope accounts for JSON unicode escaping expansion',function(){
    $emoji=str_repeat("\xF0\x9F\x92\xA5",15000); // 60,000 UTF-8 bytes, ~180,000 JSON-escaped bytes.
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use($emoji){
        return new WP_Error('unicode_error','Unicode-heavy provider failure.',array(
            'part_a'=>$emoji,
            'part_b'=>$emoji,
            'part_c'=>$emoji,
            'part_d'=>$emoji,
        ));
    });
    $payload=array('jsonrpc'=>'2.0','id'=>'provider-error-unicode','method'=>'tools/call','params'=>array('name'=>'audit_page','arguments'=>array('page_url'=>'https://example.test/')));
    $response=WPVDMCP_Server::instance()->handle(test_request($payload));
    unset($GLOBALS['wpvdmcp_providers']);

    $body=response_data($response);
    $text=$body['result']['content'][0]['text']??null;
    assert_true(is_string($text),'unicode-heavy provider error must serialize');
    assert_true(strlen($text)<=262144,'model-visible provider error must account for JSON unicode escaping expansion');
    $decoded=json_decode($text,true);
    assert_true(is_array($decoded),'unicode-heavy provider error remains valid JSON');
    assert_same(true,$decoded['data']['_wpvdmcp_provider_output']['error_envelope_limit']??null,'unicode expansion triggers full-envelope fallback');
});

parity_test('provider string truncation never splits a valid UTF-8 code point',function(){
    $emoji=str_repeat("\xF0\x9F\x92\xA5",2000);
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use($emoji){
        return new WP_Error('utf8_error',$emoji,array('status'=>502));
    });
    $payload=array('jsonrpc'=>'2.0','id'=>'provider-error-utf8-truncate','method'=>'tools/call','params'=>array('name'=>'audit_page','arguments'=>array('page_url'=>'https://example.test/')));
    $response=WPVDMCP_Server::instance()->handle(test_request($payload));
    unset($GLOBALS['wpvdmcp_providers']);

    $body=response_data($response);
    $text=$body['result']['content'][0]['text']??null;
    assert_true(is_string($text),'UTF-8 provider error must remain serializable text after truncation');
    assert_true(false!==json_decode($text,true) || 'false'===$text,'UTF-8 provider error text must remain JSON');
    $decoded=json_decode($text,true);
    assert_true(is_array($decoded),'UTF-8 provider error text decodes as a structured error');
    assert_true(1===preg_match('//u',(string)($decoded['message']??'')),'truncated provider error message remains valid UTF-8');
    assert_true(strlen((string)($decoded['message']??''))<=4096,'truncated provider error message stays within byte cap');
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

parity_test('JSON-encodable internal provider objects preserve their safe representation',function(){
    $GLOBALS['wpvdmcp_reference_providers']=array('get_profile'=>function($args){
        return new ArrayObject(array('name'=>'Owner','plan'=>'pro','apiKey'=>'array-object-secret'));
    });
    $r=WPVDMCP_Parity::execute('get_profile',array());
    unset($GLOBALS['wpvdmcp_reference_providers']);

    assert_true(is_array($r),'JSON-encodable internal object normalized to array');
    assert_same('Owner',$r['name']??null,'internal object safe field preserved');
    assert_same('pro',$r['plan']??null,'internal object second safe field preserved');
    assert_true(!isset($r['apiKey']),'internal object credential removed');
    assert_same(true,$r['_wpvdmcp_provider_output']['object_normalized']??null,'internal object normalization reported');
});

parity_test('JsonSerializable provider objects recurse through returned object representations',function(){
    $GLOBALS['wpvdmcp_reference_providers']=array('get_profile'=>function($args){
        return new class implements JsonSerializable {
            public function jsonSerialize(){
                $nested=new stdClass();$nested->plan='pro';$nested->apiKey='json-secret';
                $out=new stdClass();$out->name='Owner';$out->nested=$nested;
                return $out;
            }
        };
    });
    $previous_reporting=error_reporting();
    error_reporting($previous_reporting & ~E_DEPRECATED);
    try{
        $r=WPVDMCP_Parity::execute('get_profile',array());
    }finally{
        error_reporting($previous_reporting);
        unset($GLOBALS['wpvdmcp_reference_providers']);
    }

    assert_true(is_array($r),'JsonSerializable object representation normalized to array');
    assert_same('Owner',$r['name']??null,'JsonSerializable safe field preserved');
    assert_same('pro',$r['nested']['plan']??null,'nested JsonSerializable object representation preserved');
    assert_true(!isset($r['nested']['apiKey']),'nested JsonSerializable credential removed');
});

parity_test('WordPress filter provider branches use the shared output sanitizer',function(){
    if(!function_exists('apply_filters')){
        function apply_filters($tag,$value){
            return array_key_exists($tag,$GLOBALS['wpvdmcp_test_filter_values']??array())?$GLOBALS['wpvdmcp_test_filter_values'][$tag]:$value;
        }
    }
    if(!function_exists('has_filter')){
        function has_filter($tag){return array_key_exists($tag,$GLOBALS['wpvdmcp_test_filter_values']??array());}
    }
    unset($GLOBALS['wpvdmcp_providers'],$GLOBALS['wpvdmcp_reference_providers']);
    $GLOBALS['wpvdmcp_test_filter_values']=array(
        'wpvdmcp_search_images'=>array('safe'=>'local-filter','api_key'=>'local-filter-secret'),
        'wpvdmcp_reference_get_profile'=>array('safe'=>'reference-filter','oauthConsumerKey'=>'reference-filter-secret'),
    );

    $local=WPVDMCP_Parity::execute('search_images',array('query'=>'filter'));
    $reference=WPVDMCP_Parity::execute('get_profile',array());
    unset($GLOBALS['wpvdmcp_test_filter_values']);

    assert_same('local-filter',$local['safe']??null,'local filter safe output preserved');
    assert_true(!isset($local['api_key']),'local filter credential redacted');
    assert_same('reference-filter',$reference['safe']??null,'reference filter safe output preserved');
    assert_true(!isset($reference['oauthConsumerKey']),'reference filter credential redacted');
});
