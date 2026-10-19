<?php
parity_test('site intelligence incorporates upstream site health and performance diagnostics when available',function(){
    set_test_routes(array('/wpvibe/v1/site-info'=>array('GET'),'/wpvibe/v1/last-change'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('site_health'=>array('status'=>'good'),'performance'=>array('rest_ms'=>42),'plugins'=>array('vibe-ai'=>'1.20.3')),200);};
    $i=WPVDMCP_Parity::execute('site_intelligence',array());
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same('good',$i['site_health']['status']??null,'site health merged');
    assert_same(42,$i['performance']['rest_ms']??null,'performance merged');
});


parity_test('MCP Apps advertise inline approval and upload panels with readable UI resources',function(){
    $show=parity_tool('show_approval_panel');$upload=parity_tool('request_upload');
    assert_same('ui://wpvibe-direct/approval-panel',$show['_meta']['ui']['resourceUri']??null,'approval resource uri');
    assert_true(strpos($show['description']??'','read-only MCP App')!==false,'approval tool describes read-only panel and secure admin handoff');
    assert_same('ui://wpvibe-direct/upload-panel',$upload['_meta']['ui']['resourceUri']??null,'upload resource uri');
    assert_same(null,parity_tool('approval_decide'),'MCP bearer must never discover a self-approval tool');

    $server=WPVDMCP_Server::instance();
    $discover_req=test_request(array('jsonrpc'=>'2.0','id'=>'apps','method'=>'server/discover','params'=>array('_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28'))),'2026-07-28');
    $discover=WPVDMCP_MCP_App_Transport::filter_response($server->handle($discover_req),null,$discover_req);
    $caps=$discover->get_data()['result']['capabilities']??array();
    assert_true(in_array('text/html;profile=mcp-app',$caps['extensions']['io.modelcontextprotocol/ui']['mimeTypes']??array(),true),'MCP Apps extension advertised');

    $list_req=test_request(array('jsonrpc'=>'2.0','id'=>'rl','method'=>'resources/list','params'=>array('_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28'))),'2026-07-28');
    $list=WPVDMCP_MCP_App_Transport::filter_response($server->handle($list_req),null,$list_req);
    $uris=array_map(function($r){return $r['uri']??'';},$list->get_data()['result']['resources']??array());
    assert_true(in_array('ui://wpvibe-direct/approval-panel',$uris,true),'approval resource listed');
    assert_true(in_array('ui://wpvibe-direct/upload-panel',$uris,true),'upload resource listed');

    $read_req=test_request(array('jsonrpc'=>'2.0','id'=>'rr','method'=>'resources/read','params'=>array('uri'=>'ui://wpvibe-direct/approval-panel','_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28'))),'2026-07-28');
    $read=WPVDMCP_MCP_App_Transport::filter_response($server->handle($read_req),null,$read_req);
    $content=$read->get_data()['result']['contents'][0]??array();
    assert_same('text/html;profile=mcp-app',$content['mimeType']??null,'app mime');
    assert_true(strpos($content['text']??'','Review in WordPress admin')!==false,'approval panel links to authenticated wp-admin');
    assert_true(strpos($content['text']??'',"name:'approval_decide'")===false,'approval panel never issues bearer-authorized decisions');
});

parity_test('MCP Apps are self-contained and load no external runtime code',function(){
    foreach(array(
        'ui://wpvibe-direct/approval-panel',
        'ui://wpvibe-direct/upload-panel',
    ) as $uri){
        $resource=WPVDMCP_MCP_Apps::read_resource($uri);
        assert_true(!is_wp_error($resource),'MCP App resource must load: '.$uri);
        $content=$resource['contents'][0]??array();
        $html=(string)($content['text']??'');
        $domains=$content['_meta']['ui']['csp']['resourceDomains']??array();
        assert_same(array(),array_values((array)$domains),'MCP App resourceDomains must not allow external code: '.$uri);
        assert_true(!preg_match('/(?:src|href)\\s*=\\s*["\\\']https?:\\/\\/|(?:from|import)\\s*["\\\']https?:\\/\\//i',$html),'MCP App must not load or import external HTTP(S) runtime code: '.$uri);
        assert_true(strpos($html,"'ui/initialize'")!==false,'MCP App keeps the UI initialize handshake: '.$uri);
        assert_true(strpos($html,"'ui/notifications/initialized'")!==false,'MCP App keeps the initialized notification: '.$uri);
        assert_true(strpos($html,"'ui/notifications/tool-result'")!==false,'MCP App keeps tool-result delivery: '.$uri);
        assert_true(strpos($html,"'tools/call'")!==false,'MCP App keeps app-to-server tool calls: '.$uri);
        assert_true(strpos($html,'event.source!==window.parent')!==false,'MCP App ignores messages from non-parent windows: '.$uri);
        assert_true(strpos($html,'const requestTimeoutMs=60000')!==false,'MCP App keeps the SDK-equivalent 60 second request timeout: '.$uri);
        assert_true(strpos($html,"root.style.width='fit-content'")!==false,'MCP App measures natural fit-content width before resize notification: '.$uri);
        assert_true(strpos($html,'width===lastWidth&&height===lastHeight')!==false,'MCP App suppresses duplicate resize notifications: '.$uri);
    }
});

parity_test('modern MCP rejects JSON-RPC batches before app-only approval metadata can be minted',function(){
    $GLOBALS['wp_current_user']=1;
    $pending=WPVDMCP_Approvals::request('delete_user',array('user_id'=>77),'Delete user #77');
    $payload=array(array(
        'jsonrpc'=>'2.0',
        'id'=>'batch-modern',
        'method'=>'tools/call',
        'params'=>array(
            'name'=>'show_approval_panel',
            'arguments'=>array('approval_id'=>$pending['approval_id']),
            '_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28'),
        ),
    ));
    $request=test_request($payload,'2026-07-28');
    $response=WPVDMCP_Server::instance()->handle($request);
    $data=response_data($response);
    assert_same(400,$response instanceof WP_REST_Response?$response->get_status():null,'modern batch must be rejected at the transport boundary');
    assert_same(-32600,$data['error']['code']??null,'modern batch rejection must use Invalid Request');
    assert_true(strpos(wp_json_encode($data),'wpvdmcpApprovalPanelToken')===false,'rejected modern batch must not mint or expose an approval panel token');
    $panel_key='transient:wpvdmcp_ui_panel_'.hash('sha256',$pending['approval_id']);
    assert_same(false,get_option($panel_key,false),'rejected modern batch must not execute show_approval_panel or mint panel state');
    $status=WPVDMCP_Approvals::status($pending['approval_id']);
    assert_same('pending',$status['status']??null,'rejected batch must leave the destructive approval pending');
});

parity_test('legacy MCP batches never mint approval authority as app metadata',function(){
    $GLOBALS['wp_current_user']=1;
    $pending=WPVDMCP_Approvals::request('delete_user',array('user_id'=>78),'Delete user #78');
    $payload=array(array(
        'jsonrpc'=>'2.0',
        'id'=>'batch-legacy',
        'method'=>'tools/call',
        'params'=>array(
            'name'=>'show_approval_panel',
            'arguments'=>array('approval_id'=>$pending['approval_id']),
        ),
    ));
    $request=test_request($payload,'2024-11-05');
    $response=WPVDMCP_Server::instance()->handle($request);
    $response=WPVDMCP_MCP_App_Transport::filter_response($response,null,$request);
    $data=response_data($response);
    $result=$data[0]['result']??array();
    assert_true(!isset($result['structuredContent']['__mcp_meta']),'legacy batch must strip app-only metadata from structuredContent');
    assert_true(empty($result['_meta']['wpvdmcpApprovalPanelToken']),'legacy batch must not mint bearer-accessible approval authority');
    assert_true(!empty($result['structuredContent']['approval_url']),'legacy batch retains secure WordPress approval URL');
    assert_true(strpos($result['content'][0]['text']??'','wpvdmcpApprovalPanelToken')===false,'legacy batch model-visible text must not contain the approval token');
});

parity_test('legacy batch sanitizes app-only metadata while omitting notification responses with duplicate ids',function(){
    $GLOBALS['wp_current_user']=1;
    $a=WPVDMCP_Approvals::request('delete_user',array('user_id'=>79),'Delete user #79');
    $b=WPVDMCP_Approvals::request('delete_user',array('user_id'=>80),'Delete user #80');
    $payload=array(
        array('jsonrpc'=>'2.0','id'=>'dup','method'=>'ping','params'=>array()),
        array('jsonrpc'=>'2.0','id'=>'dup','method'=>'tools/call','params'=>array('name'=>'show_approval_panel','arguments'=>array('approval_id'=>$a['approval_id']))),
        array('jsonrpc'=>'2.0','method'=>'tools/call','params'=>array('name'=>'show_approval_panel','arguments'=>array('approval_id'=>$b['approval_id']))),
    );
    $request=test_request($payload,'2024-11-05');
    $response=WPVDMCP_Server::instance()->handle($request);
    $response=WPVDMCP_MCP_App_Transport::filter_response($response,null,$request);
    $data=response_data($response);
    assert_same(2,count((array)$data),'notification must not add a third batch response');
    $metadata_tokens=0;
    foreach((array)$data as $item){
        $result=$item['result']??array();
        if(!is_array($result)){continue;}
        assert_true(!isset($result['structuredContent']['__mcp_meta']),'legacy batch must strip app metadata regardless of request id ambiguity');
        assert_true(strpos($result['content'][0]['text']??'','wpvdmcpApprovalPanelToken')===false,'legacy batch text must never expose app approval token');
        if(!empty($result['_meta']['wpvdmcpApprovalPanelToken'])){$metadata_tokens++;}
    }
    assert_same(0,$metadata_tokens,'no legacy batch result may mint an approval credential');
});

parity_test('legacy batch sanitizes app metadata when the first batch item is malformed null',function(){
    $pending=WPVDMCP_Approvals::request('delete_user',array('user_id'=>82),'Delete user #82');
    $payload=array(
        null,
        array('jsonrpc'=>'2.0','id'=>'after-null','method'=>'tools/call','params'=>array('name'=>'show_approval_panel','arguments'=>array('approval_id'=>$pending['approval_id']))),
    );
    $request=test_request($payload,'2024-11-05');
    $response=WPVDMCP_Server::instance()->handle($request);
    $response=WPVDMCP_MCP_App_Transport::filter_response($response,null,$request);
    $encoded=wp_json_encode(response_data($response));
    assert_true(strpos($encoded,'__mcp_meta')===false,'malformed-first legacy batch must not retain app-only metadata in structured content');
    foreach((array)response_data($response) as $item){
        if(!is_array($item)||!isset($item['result'])||!is_array($item['result'])){continue;}
        assert_true(strpos($item['result']['content'][0]['text']??'','wpvdmcpApprovalPanelToken')===false,'malformed-first batch text must not expose panel token');
    }
});

parity_test('handshake-era batches reject versions where MCP removed JSON-RPC batching before tool execution',function(){
    foreach(array('2025-06-18','2025-11-25') as $version){
        $pending=WPVDMCP_Approvals::request('delete_user',array('user_id'=>81),'Delete user #81');
        $payload=array(
            array('jsonrpc'=>'2.0','id'=>'init-'.$version,'method'=>'initialize','params'=>array('protocolVersion'=>$version)),
            array('jsonrpc'=>'2.0','id'=>'tool-'.$version,'method'=>'tools/call','params'=>array('name'=>'show_approval_panel','arguments'=>array('approval_id'=>$pending['approval_id']))),
        );
        $request=test_request($payload);
        $response=WPVDMCP_Server::instance()->handle($request);
        $data=response_data($response);
        assert_same(400,$response instanceof WP_REST_Response?$response->get_status():null,'initialize batch must reject '.$version);
        assert_same(-32600,$data['error']['code']??null,'initialize batch must reject before negotiation for '.$version);
        $panel_key='transient:wpvdmcp_ui_panel_'.hash('sha256',$pending['approval_id']);
        assert_same(false,get_option($panel_key,false),'rejected initialize batch must not execute later tool for '.$version);
    }
});

parity_test('empty batch is rejected for post-batching MCP versions',function(){
    $request=test_request(array(),'2025-06-18');
    $response=WPVDMCP_Server::instance()->handle($request);
    $data=response_data($response);
    assert_same(400,$response instanceof WP_REST_Response?$response->get_status():null,'empty post-2025-06-18 batch must be rejected');
    assert_same(-32600,$data['error']['code']??null,'empty batch rejection must use Invalid Request');
});

parity_test('inline approval stays review-only and preserves browser approval',function(){
    $GLOBALS['wp_current_user']=1;
    $pending=WPVDMCP_Approvals::request('delete_user',array('user_id'=>55,'password'=>'redact-me'),'Delete user #55');
    $panel=WPVDMCP_Parity::execute('show_approval_panel',array('approval_id'=>$pending['approval_id']));
    assert_true(!isset($panel['__mcp_meta']),'read-only panel must not mint approval authority');
    assert_true(!empty($panel['approval_url']),'browser-admin approval URL returned');
    assert_true(!isset($panel['display_payload']['password'])||'[REDACTED]'===($panel['display_payload']['password']??null),'secret display redacted');
    $attempt=WPVDMCP_Parity::execute('approval_decide',array('approval_id'=>$pending['approval_id'],'decision'=>'approve','panel_token'=>'anything'));
    assert_true(is_wp_error($attempt),'bearer-only approval must be refused');
    assert_same('approval_requires_browser',$attempt->get_error_code(),'approval requires browser-admin credentials');
    assert_same('pending',WPVDMCP_Approvals::status($pending['approval_id'])['status']??null,'pending receipt remains pending');
});

parity_test('read-only approval panel does not depend on bearer-accessible token persistence',function(){
    $GLOBALS['wp_current_user']=1;
    $pending=WPVDMCP_Approvals::request('delete_user',array('user_id'=>56),'Delete user #56');
    $GLOBALS['set_transient_override']=function($key,$value,$ttl) use ($pending){
        if($key==='wpvdmcp_ui_panel_'.hash('sha256',$pending['approval_id'])) return false;
        return null;
    };
    $panel=WPVDMCP_Parity::execute('show_approval_panel',array('approval_id'=>$pending['approval_id']));
    unset($GLOBALS['set_transient_override']);
    assert_true(!is_wp_error($panel),'read-only panel needs no token persistence');
    assert_true(!isset($panel['__mcp_meta']),'read-only panel never emits a panel token');
    assert_true(!empty($panel['approval_url']),'secure wp-admin URL remains available');
});

parity_test('screenshot capability is provider backed and never fabricates image output',function(){
    unset($GLOBALS['wpvdmcp_reference_providers']);
    $missing=WPVDMCP_Parity::execute('screenshot_page',array('url'=>'https://example.test/'));
    assert_true(is_wp_error($missing),'screenshot provider required');
    assert_same('provider_unavailable',$missing->get_error_code(),'screenshot provider code');
    $GLOBALS['wpvdmcp_reference_providers']=array('screenshot_page'=>function($args){return array('mime_type'=>'image/png','url'=>'https://cdn.test/shot.png','viewport'=>array('width'=>$args['width']??0));});
    $shot=WPVDMCP_Parity::execute('screenshot_page',array('url'=>'https://example.test/','width'=>1440,'height'=>900));
    assert_same('image/png',$shot['mime_type']??null,'screenshot returned');
    assert_same(1440,$shot['viewport']['width']??null,'viewport forwarded');
});

parity_test('saved skills persist descriptions and bounded text reference files',function(){
    $GLOBALS['wp_current_user']=1;
    $args=array('slug'=>'brand-playbook','title'=>'Brand Playbook','description'=>'Keep brand voice consistent.','instructions'=>'Use the attached brand reference before writing.','reference_files'=>array(array('name'=>'brand.md','mime_type'=>'text/markdown','content'=>'# Brand\\nClear, concise, practical.')));
    $a=WPVDMCP_Parity::execute('save_skill',$args);assert_same('approval_required',$a['status']??null,'reference skill approval');
    WPVDMCP_Approvals::approve($a['approval_id'],1);$args['approval_id']=$a['approval_id'];
    $saved=WPVDMCP_Parity::execute('save_skill',$args);
    assert_same('Keep brand voice consistent.',$saved['skill']['description']??null,'description persisted');
    assert_same('brand.md',$saved['skill']['reference_files'][0]['name']??null,'reference file persisted');
    $loaded=WPVDMCP_Parity::execute('load_skill',array('skill'=>'brand-playbook'));
    assert_true(strpos($loaded['reference_files'][0]['content']??'','Clear')!==false,'reference content loadable');
    $too_many=$args;$too_many['slug']='too-many-refs';unset($too_many['approval_id']);$too_many['reference_files']=array_fill(0,11,array('name'=>'x.md','mime_type'=>'text/markdown','content'=>'x'));
    $bad=WPVDMCP_Parity::execute('save_skill',$too_many);assert_true(is_wp_error($bad),'reference file count bounded');
});

parity_test('rest_api_write schema mirrors hardened REST body and field shapes',function(){
    $tool=parity_tool('rest_api_write');$props=(array)($tool['inputSchema']['properties']??array());
    assert_same(array('POST','PUT','PATCH','DELETE'),$props['method']['enum']??null,'write methods exact');
    assert_true(isset($props['fields']['oneOf'])&&count($props['fields']['oneOf'])===2,'fields supports array or string compatibility forms');
    $types=array_map(function($shape){return $shape['type']??'';},$props['fields']['oneOf']);
    sort($types);
    assert_same(array('array','string'),$types,'fields compatibility types');
    assert_true(isset($props['body']['oneOf'])&&count($props['body']['oneOf'])===2,'body supports object or JSON string');
});


parity_test('reference manifest records live website capabilities and cookbook coverage',function(){
    $m=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $features=$m['features_page']??array();
    foreach(array('full_rest_api','wp_cli','theme_builder','draft_preview_publish','site_intelligence','lighthouse','plugin_abilities','images_media','live_reload','saved_skills','interactive_panels','validated_block_output','bulk_operations_sql','safe_code_snippets','every_site','editable_fields') as $feature){
        assert_true(in_array($feature,$features,true),'features-page capability missing '.$feature);
    }
    $cookbook=$m['cookbook_integrations']??array();
    foreach(array('aioseo','charitable','duplicator','easy-digital-downloads','fluentcart','fluentcommunity','fluentcrm','lifterlms','memberpress','merchant','pushengage','smash-balloon','woocommerce','wpcode','wpforms','yoast-seo','beaver-builder','breakdance','bricks','divi','elementor','generateblocks','seedprod','botiga','generatepress','kadence') as $slug){
        assert_true(in_array($slug,$cookbook,true),'cookbook integration missing '.$slug);
        $skill=WPVDMCP_Parity::execute('load_skill',array('skill'=>$slug));
        assert_true(!is_wp_error($skill),'cookbook skill must load '.$slug);
    }
    assert_true(!empty($m['reference_sources']['features']),'features source recorded');
    assert_true(!empty($m['reference_sources']['tools_reference']),'tools source recorded');
    assert_true(!empty($m['reference_sources']['upstream_github']),'upstream source recorded');
});
