<?php
require __DIR__ . '/bootstrap.php';

$tests = array();
function test_case($name, $fn) { global $tests; $tests[$name] = $fn; }

test_case('modern server/discover advertises current and legacy versions with server meta', function () {
    $server = WPVDMCP_Server::instance();
    $payload = array(
        'jsonrpc'=>'2.0','id'=>'d1','method'=>'server/discover',
        'params'=>array('_meta'=>array(
            'io.modelcontextprotocol/protocolVersion'=>'2026-07-28',
            'io.modelcontextprotocol/clientCapabilities'=>(object)array(),
            'io.modelcontextprotocol/clientInfo'=>array('name'=>'tests','version'=>'1'),
        )),
    );
    $response = $server->handle(test_request($payload,'2026-07-28'));
    assert_same(200, $response->get_status(), 'discover status');
    $data = $response->get_data();
    assert_same('complete', $data['result']['resultType'] ?? null, 'discover resultType');
    assert_true(in_array('2026-07-28', $data['result']['supportedVersions'] ?? array(), true), 'modern version advertised');
    assert_true(in_array('2025-11-25', $data['result']['supportedVersions'] ?? array(), true), 'legacy version advertised');
    assert_same('wpvibe-direct-mcp', $data['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'] ?? null, 'serverInfo meta');
});

test_case('unsupported modern version returns -32022 and HTTP 400', function () {
    $server = WPVDMCP_Server::instance();
    $payload = array(
        'jsonrpc'=>'2.0','id'=>9,'method'=>'tools/list',
        'params'=>array('_meta'=>array(
            'io.modelcontextprotocol/protocolVersion'=>'2099-01-01',
            'io.modelcontextprotocol/clientCapabilities'=>(object)array(),
        )),
    );
    $response = $server->handle(test_request($payload,'2099-01-01'));
    assert_same(400, $response->get_status(), 'unsupported version HTTP status');
    $data=$response->get_data();
    assert_same(-32022,$data['error']['code'] ?? null,'unsupported version code');
    assert_same('2099-01-01',$data['error']['data']['requested'] ?? null,'requested version echoed');
});

test_case('legacy initialize negotiates instead of echoing unknown version', function () {
    $server=WPVDMCP_Server::instance();
    $payload=array('jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>array('protocolVersion'=>'1900-01-01','capabilities'=>(object)array(),'clientInfo'=>array('name'=>'legacy','version'=>'1')));
    $response=$server->handle(test_request($payload));
    $data=$response->get_data();
    assert_same('2025-11-25',$data['result']['protocolVersion'] ?? null,'legacy fallback protocol');
});

test_case('notification-only batch remains HTTP 202', function () {
    $server=WPVDMCP_Server::instance();
    $payload=array(
        array('jsonrpc'=>'2.0','method'=>'notifications/initialized','params'=>(object)array()),
        array('jsonrpc'=>'2.0','method'=>'notifications/cancelled','params'=>array('requestId'=>1)),
    );
    $response=$server->handle(test_request($payload));
    assert_same(202,$response->get_status(),'notification batch status');
});

test_case('OPTIONS advertises modern routing headers', function () {
    $server=WPVDMCP_Server::instance();
    $response=$server->options();
    $headers=$response->get_headers();
    $allow=$headers['access-control-allow-headers'] ?? '';
    assert_true(stripos($allow,'Mcp-Method')!==false,'Mcp-Method allowed');
    assert_true(stripos($allow,'Mcp-Name')!==false,'Mcp-Name allowed');
});


test_case('current file and publish schemas expose upstream 1.20 fields', function () {
    set_test_routes(array(
        '/wpvibe/v1/file/read'=>array('POST'), '/wpvibe/v1/file/list'=>array('GET'),
        '/wpvibe/v1/file/write'=>array('POST'), '/wpvibe/v1/draft-theme/publish'=>array('POST'),
    ));
    $read=tool_by_name('read_file');
    $list=tool_by_name('list_files');
    $write=tool_by_name('write_file');
    $publish=tool_by_name('publish_draft_theme');
    assert_true(isset($read['inputSchema']['properties']->scope),'read_file scope');
    assert_true(isset($list['inputSchema']['properties']->directory),'list_files directory');
    assert_true(isset($write['inputSchema']['properties']->expected_source_hash),'write_file source hash');
    assert_true(isset($publish['inputSchema']['properties']->saved_customizations),'publish saved_customizations');
});

test_case('dedicated upstream builders are exposed only when native routes exist', function () {
    set_test_routes(array(
        '/wpvibe/v1/beaver/save-page'=>array('POST'), '/wpvibe/v1/beaver/modules'=>array('GET'), '/wpvibe/v1/beaver/schema'=>array('GET'),
        '/wpvibe/v1/bricks/save-page'=>array('POST'), '/wpvibe/v1/bricks/get-page'=>array('GET'), '/wpvibe/v1/bricks/elements'=>array('GET'),
        '/wpvibe/v1/breakdance/save-page'=>array('POST'), '/wpvibe/v1/breakdance/get-page'=>array('GET'), '/wpvibe/v1/breakdance/elements'=>array('GET'),
    ));
    foreach(array('beaver_save_page','beaver_modules','beaver_schema','bricks_save_page','bricks_get_page','bricks_elements','breakdance_save_page','breakdance_get_page','breakdance_elements') as $name){
        assert_true(tool_by_name($name)!==null,'missing builder tool '.$name);
    }
    assert_true(tool_by_name('elementor_save_page')===null,'elementor hidden without route');
});

test_case('WPCode snippet tool uses the dormant upstream route when available', function () {
    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    $tool=tool_by_name('code_snippet');
    assert_true($tool!==null,'code_snippet exposed');
    $props=$tool['inputSchema']['properties'];
    assert_true(isset($props->code) && isset($props->code_type) && isset($props->location),'snippet schema');
});

test_case('generic REST blocks direct MCP and application-password credential routes', function () {
    set_test_routes(array());
    foreach(array('/wpvibe-direct/v1/health','/wp/v2/users/1/application-passwords','/wp/v2/users/me/application-passwords') as $path){
        $result=WPVDMCP_Tools::execute('rest_api',array('method'=>'GET','path'=>$path));
        assert_true(is_wp_error($result),'blocked route expected for '.$path);
        assert_same('blocked_route',$result->get_error_code(),'blocked code '.$path);
    }
});

test_case('generic REST has response limiting and selected fields controls', function () {
    set_test_routes(array('/wp/v2/pages'=>array('GET')));
    $tool=tool_by_name('rest_api');
    $props=$tool['inputSchema']['properties'];
    assert_true(isset($props->fields),'rest_api fields');
    assert_true(isset($props->max_response_bytes),'rest_api response limit');
});


test_case('abilities tools appear when WordPress abilities REST namespace is available', function () {
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    foreach(array('discover_abilities','get_ability_info','run_ability') as $name){
        assert_true(tool_by_name($name)!==null,'missing abilities tool '.$name);
    }
});

test_case('readonly ability executes with GET and preserves input', function () {
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){
        $route=$r->get_param('_route');
        if($route==='/wp-abilities/v1/abilities/core/get-site-info'){
            return new WP_REST_Response(array('name'=>'core/get-site-info','meta'=>array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true))),200);
        }
        if($route==='/wp-abilities/v1/abilities/core/get-site-info/run'){
            return new WP_REST_Response(array('method'=>$r->get_param('_method'),'query'=>$r->get_query_params()),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $result=WPVDMCP_Tools::execute('run_ability',array('name'=>'core/get-site-info','input'=>array('fields'=>array('name'))));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!is_wp_error($result),'readonly ability result');
    assert_same('GET',$result['data']['method'] ?? null,'readonly uses GET');
    assert_same(array('fields'=>array('name')),$result['data']['query']['input'] ?? null,'readonly input');
});

test_case('write ability requires browser approval then executes POST once', function () {
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $calls=array();
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$calls){
        $route=$r->get_param('_route'); $calls[]=$route.' '.$r->get_param('_method');
        if($route==='/wp-abilities/v1/abilities/demo/update'){
            return new WP_REST_Response(array('name'=>'demo/update','meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>false,'idempotent'=>true))),200);
        }
        if($route==='/wp-abilities/v1/abilities/demo/update/run'){
            return new WP_REST_Response(array('ran'=>true,'method'=>$r->get_param('_method')),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $first=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/update','input'=>array('value'=>2)));
    assert_same('approval_required',$first['status'] ?? null,'write approval required');
    $approval_id=$first['approval_id'] ?? '';
    assert_true($approval_id!=='','approval id returned');
    $approved=WPVDMCP_Approvals::approve($approval_id,1);
    assert_true($approved===true,'approval accepted');
    $second=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/update','input'=>array('value'=>2),'approval_id'=>$approval_id));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!is_wp_error($second),'approved write executes');
    assert_same('POST',$second['data']['method'] ?? null,'write uses POST');
    $replay=WPVDMCP_Approvals::consume($approval_id,'run_ability',array('name'=>'demo/update','input'=>array('value'=>2),'method'=>'POST'));
    assert_true(is_wp_error($replay),'approval replay rejected');
});

test_case('one-time approval cannot be consumed twice across an interleaved race', function () {
    $GLOBALS['wp_current_user']=1;
    $payload=array('name'=>'demo/race','input'=>array('value'=>9),'method'=>'POST');
    $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Race test');
    $approval_id=$approval['approval_id'] ?? '';
    assert_true($approval_id!=='','approval id returned');
    assert_true(WPVDMCP_Approvals::approve($approval_id,1)===true,'approval accepted');

    $nested=null;
    $armed=true;
    $GLOBALS['get_transient_after_read']=function($key,$value) use (&$nested,&$armed,$approval_id,$payload){
        if(!$armed || strpos($key,'wpvdmcp_approval_')!==0 || !is_array($value) || ($value['id']??'')!==$approval_id){return;}
        $armed=false;
        $nested=WPVDMCP_Approvals::consume($approval_id,'run_ability',$payload);
    };
    $outer=WPVDMCP_Approvals::consume($approval_id,'run_ability',$payload);
    unset($GLOBALS['get_transient_after_read']);

    $successes=($outer===true?1:0)+($nested===true?1:0);
    assert_same(1,$successes,'exactly one interleaved consumer may claim a one-time approval');
    assert_true(is_wp_error($outer)||is_wp_error($nested),'the losing consumer must fail closed');
    $status=WPVDMCP_Approvals::status($approval_id);
    assert_same('consumed',$status['status']??null,'winning consumer must leave the approval consumed');
});

test_case('approval request fails closed when pending receipt cannot be persisted', function () {
    $GLOBALS['set_transient_override']=function($key,$value,$ttl){
        if(strpos($key,'wpvdmcp_approval_')===0 && is_array($value) && ($value['status']??'')==='pending') return false;
        return null;
    };
    $result=WPVDMCP_Approvals::request('run_ability',array('name'=>'demo/request-persist','input'=>array('value'=>1),'method'=>'POST'),'Request persistence failure');
    unset($GLOBALS['set_transient_override']);
    assert_true(is_wp_error($result),'unpersisted approval request must fail closed');
    assert_same('approval_persist_failed',$result->get_error_code(),'request persistence failure code');
});

test_case('approval decisions fail closed when decision state cannot be persisted', function () {
    $payload=array('name'=>'demo/decision-persist','input'=>array('value'=>2),'method'=>'POST');
    $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Decision persistence failure');
    $approval_id=$approval['approval_id']??'';
    $GLOBALS['set_transient_override']=function($key,$value,$ttl) use ($approval_id){
        if($key==='wpvdmcp_approval_'.$approval_id && is_array($value) && in_array($value['status']??'',array('approved','rejected'),true)) return false;
        return null;
    };
    $approved=WPVDMCP_Approvals::approve($approval_id,1);
    assert_true(is_wp_error($approved),'unpersisted approval decision must fail closed');
    assert_same('approval_persist_failed',$approved->get_error_code(),'approve persistence failure code');
    $rejected=WPVDMCP_Approvals::reject($approval_id,1);
    unset($GLOBALS['set_transient_override']);
    assert_true(is_wp_error($rejected),'unpersisted rejection decision must fail closed');
    assert_same('approval_persist_failed',$rejected->get_error_code(),'reject persistence failure code');
    $status=WPVDMCP_Approvals::status($approval_id);
    assert_same('pending',$status['status']??null,'failed decision persistence leaves receipt pending');
});

test_case('approval consumer never steals an existing claim based only on claim age', function () {
    $payload=array('name'=>'demo/stale-claim','input'=>array('value'=>10),'method'=>'POST');
    $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Stale claim test');
    $approval_id=$approval['approval_id'] ?? '';
    assert_true(WPVDMCP_Approvals::approve($approval_id,1)===true,'approval accepted');
    $claim_key='wpvdmcp_approval_claim_'.hash('sha256',$approval_id);
    assert_true(add_option($claim_key,array('claimed_at'=>time()-3600),'',false),'test claim installed');
    $result=WPVDMCP_Approvals::consume($approval_id,'run_ability',$payload);
    assert_true(is_wp_error($result),'existing claim must fail closed even when it looks old');
    assert_same('approval_in_progress',$result->get_error_code(),'existing claim must not be stolen by age');
    $status=WPVDMCP_Approvals::status($approval_id);
    assert_same('approved',$status['status']??null,'failed claim steal must leave approval unused');
    delete_option($claim_key);
});

test_case('approval consume fails closed and retains claim when consumed state cannot be persisted', function () {
    $payload=array('name'=>'demo/persist-failure','input'=>array('value'=>11),'method'=>'POST');
    $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Persistence failure test');
    $approval_id=$approval['approval_id'] ?? '';
    assert_true(WPVDMCP_Approvals::approve($approval_id,1)===true,'approval accepted');
    $GLOBALS['set_transient_override']=function($key,$value,$ttl) use ($approval_id){
        if($key==='wpvdmcp_approval_'.$approval_id && is_array($value) && ($value['status']??'')==='consumed')return false;
        return null;
    };
    $first=WPVDMCP_Approvals::consume($approval_id,'run_ability',$payload);
    unset($GLOBALS['set_transient_override']);
    assert_true(is_wp_error($first),'failed consumed-state persistence must not report success');
    assert_same('approval_persist_failed',$first->get_error_code(),'persistence failure must be explicit');
    $second=WPVDMCP_Approvals::consume($approval_id,'run_ability',$payload);
    assert_true(is_wp_error($second),'retained claim must block a later consumer after uncertain persistence');
    assert_same('approval_in_progress',$second->get_error_code(),'later consumer must fail closed behind retained claim');
    $status=WPVDMCP_Approvals::status($approval_id);
    assert_same('approved',$status['status']??null,'failed persistence leaves underlying receipt approved but locked');
});

test_case('idempotent destructive ability uses DELETE after approval', function () {
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['rest_dispatch_callback']=function($r){
        $route=$r->get_param('_route');
        if($route==='/wp-abilities/v1/abilities/demo/remove'){
            return new WP_REST_Response(array('name'=>'demo/remove','meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>true))),200);
        }
        if($route==='/wp-abilities/v1/abilities/demo/remove/run'){
            return new WP_REST_Response(array('method'=>$r->get_param('_method')),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $first=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/remove','input'=>array('id'=>7)));
    WPVDMCP_Approvals::approve($first['approval_id'],1);
    $second=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/remove','input'=>array('id'=>7),'approval_id'=>$first['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same('DELETE',$second['data']['method'] ?? null,'idempotent destructive uses DELETE');
});


test_case('non-idempotent destructive ability uses POST after approval', function () {
    set_test_routes(array('/wp-abilities/v1/abilities'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['rest_dispatch_callback']=function($r){
        $route=$r->get_param('_route');
        if($route==='/wp-abilities/v1/abilities/demo/remove'){
            return new WP_REST_Response(array('name'=>'demo/remove','meta'=>array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false))),200);
        }
        if($route==='/wp-abilities/v1/abilities/demo/remove/run'){
            return new WP_REST_Response(array('method'=>$r->get_param('_method')),200);
        }
        return new WP_REST_Response(array('code'=>'not_found','message'=>'not found'),404);
    };
    $first=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/remove','input'=>array('id'=>7)));
    WPVDMCP_Approvals::approve($first['approval_id'],1);
    $second=WPVDMCP_Tools::execute('run_ability',array('name'=>'demo/remove','input'=>array('id'=>7),'approval_id'=>$first['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same('POST',$second['data']['method'] ?? null,'non-idempotent destructive uses POST');
});


test_case('device upload tools expose expiring browser transfer flow', function () {
    $request=tool_by_name('request_upload');
    $check=tool_by_name('check_upload');
    assert_true($request!==null,'request_upload exposed');
    assert_true($check!==null,'check_upload exposed');
    $result=WPVDMCP_Tools::execute('request_upload',array('title'=>'Brand logo','alt_text'=>'Brand','post_id'=>42));
    assert_true(!is_wp_error($result),'request upload succeeds');
    assert_true(strpos($result['upload_id'] ?? '','up_')===0,'upload id');
    assert_true(strpos($result['upload_url'] ?? '','test-token')===false,'main MCP token never exposed');
    assert_same('waiting',$result['status'] ?? null,'waiting state');
    $stored=get_option('transient:wpvdmcp_upload_'.$result['upload_id'],false);
    assert_true($stored!==false,'ticket stored');
    assert_true(empty($stored['value']['secret']),'plaintext secret not stored');
    assert_true(!empty($stored['value']['secret_hash']),'secret hash stored');
});

test_case('device upload request fails closed when ticket state cannot be persisted', function () {
    $GLOBALS['set_transient_override']=function($key,$value,$ttl){
        if(strpos($key,'wpvdmcp_upload_')===0 && is_array($value) && ($value['status']??'')==='waiting') return false;
        return null;
    };
    $result=WPVDMCP_Upload::request(array('title'=>'Must persist'));
    unset($GLOBALS['set_transient_override']);
    assert_true(is_wp_error($result),'unpersisted upload ticket must not return a dead browser URL');
    assert_same('upload_persist_failed',$result->get_error_code(),'upload request persistence failure code');
});

test_case('device upload ticket secret is scoped and single-use', function () {
    $result=WPVDMCP_Upload::request(array());
    $parts=parse_url($result['upload_url']);
    parse_str($parts['query'] ?? '',$query);
    $secret=$query['upload_token'] ?? '';
    assert_true($secret!=='','upload secret in browser URL');
    assert_true(WPVDMCP_Upload::validate_ticket($result['upload_id'],$secret)===true,'correct secret validates');
    assert_true(is_wp_error(WPVDMCP_Upload::validate_ticket($result['upload_id'],'wrong')),'wrong secret rejected');
    $files=array(array('attachment_id'=>77,'filename'=>'logo.png','mime_type'=>'image/png','url'=>'https://example.test/uploads/logo.png','width'=>120,'height'=>80));
    assert_true(WPVDMCP_Upload::finalize($result['upload_id'],$secret,$files)===true,'ticket finalized');
    assert_true(is_wp_error(WPVDMCP_Upload::validate_ticket($result['upload_id'],$secret)),'used ticket cannot replay');
    $status=WPVDMCP_Upload::check($result['upload_id']);
    assert_same('ready',$status['status'] ?? null,'ready state');
    assert_true(!isset($status['files'][0]['tmp_name']) && !isset($status['files'][0]['path']),'filesystem path never exposed');
});

test_case('upload ticket finalization cannot succeed twice across an interleaved race', function () {
    $result=WPVDMCP_Upload::request(array());
    $parts=parse_url($result['upload_url']);
    parse_str($parts['query'] ?? '',$query);
    $secret=$query['upload_token'] ?? '';
    $files=array(array('attachment_id'=>88,'filename'=>'race.png','mime_type'=>'image/png','url'=>'https://example.test/uploads/race.png','width'=>64,'height'=>64));
    $nested=null;
    $armed=true;
    $GLOBALS['get_transient_after_read']=function($key,$value) use (&$nested,&$armed,$result,$secret,$files){
        if(!$armed || strpos($key,'wpvdmcp_upload_')!==0 || !is_array($value) || ($value['id']??'')!==$result['upload_id']){return;}
        $armed=false;
        $nested=WPVDMCP_Upload::finalize($result['upload_id'],$secret,$files);
    };
    $outer=WPVDMCP_Upload::finalize($result['upload_id'],$secret,$files);
    unset($GLOBALS['get_transient_after_read']);
    $successes=($outer===true?1:0)+($nested===true?1:0);
    assert_same(1,$successes,'exactly one interleaved finalizer may close an upload ticket');
    assert_true(is_wp_error($outer)||is_wp_error($nested),'losing finalizer must fail closed');
});

test_case('browser upload atomically claims a waiting ticket before media side effects', function () {
    $result=WPVDMCP_Upload::request(array());
    $parts=parse_url($result['upload_url']);
    parse_str($parts['query'] ?? '',$query);
    $secret=$query['upload_token'] ?? '';
    $nested=null;
    $armed=true;
    $GLOBALS['get_transient_after_read']=function($key,$value) use (&$nested,&$armed,$result,$secret){
        if(!$armed || strpos($key,'wpvdmcp_upload_')!==0 || !is_array($value) || ($value['id']??'')!==$result['upload_id']){return;}
        $armed=false;
        $nested=WPVDMCP_Upload::begin_processing($result['upload_id'],$secret);
    };
    $outer=WPVDMCP_Upload::begin_processing($result['upload_id'],$secret);
    unset($GLOBALS['get_transient_after_read']);
    $successes=($outer===true?1:0)+($nested===true?1:0);
    assert_same(1,$successes,'exactly one browser request may move a waiting ticket into processing');
    assert_true(is_wp_error($outer)||is_wp_error($nested),'losing browser claim must fail closed');
    $status=WPVDMCP_Upload::check($result['upload_id']);
    assert_same('processing',$status['status']??null,'winning claim must persist processing before media work');
});

test_case('upload processing transition fails closed when reservation cannot be persisted', function () {
    $result=WPVDMCP_Upload::request(array());
    $parts=parse_url($result['upload_url']);
    parse_str($parts['query'] ?? '',$query);
    $secret=$query['upload_token'] ?? '';
    $GLOBALS['set_transient_override']=function($key,$value,$ttl) use ($result){
        if($key==='wpvdmcp_upload_'.$result['upload_id'] && is_array($value) && ($value['status']??'')==='processing')return false;
        return null;
    };
    $first=WPVDMCP_Upload::begin_processing($result['upload_id'],$secret);
    unset($GLOBALS['set_transient_override']);
    assert_true(is_wp_error($first),'failed processing persistence must not report success');
    assert_same('upload_persist_failed',$first->get_error_code(),'reservation persistence failure must be explicit');
    $second=WPVDMCP_Upload::begin_processing($result['upload_id'],$secret);
    assert_true(is_wp_error($second),'uncertain reservation must retain transition claim');
    assert_same('upload_in_progress',$second->get_error_code(),'retained transition claim must block retry');
    $status=WPVDMCP_Upload::check($result['upload_id']);
    assert_same('waiting',$status['status']??null,'failed reservation write leaves the underlying ticket waiting but locked');
    delete_option('wpvdmcp_upload_claim_'.hash('sha256',$result['upload_id']));
});

test_case('upload finalization fails closed when ready state cannot be persisted', function () {
    $result=WPVDMCP_Upload::request(array());
    $parts=parse_url($result['upload_url']);
    parse_str($parts['query'] ?? '',$query);
    $secret=$query['upload_token'] ?? '';
    assert_true(WPVDMCP_Upload::begin_processing($result['upload_id'],$secret)===true,'ticket reserved');
    $files=array(array('attachment_id'=>89,'filename'=>'persist.png','mime_type'=>'image/png','url'=>'https://example.test/uploads/persist.png','width'=>64,'height'=>64));
    $GLOBALS['set_transient_override']=function($key,$value,$ttl) use ($result){
        if($key==='wpvdmcp_upload_'.$result['upload_id'] && is_array($value) && ($value['status']??'')==='ready')return false;
        return null;
    };
    $first=WPVDMCP_Upload::finalize($result['upload_id'],$secret,$files);
    unset($GLOBALS['set_transient_override']);
    assert_true(is_wp_error($first),'failed ready-state persistence must not report success');
    assert_same('upload_persist_failed',$first->get_error_code(),'ready persistence failure must be explicit');
    $second=WPVDMCP_Upload::finalize($result['upload_id'],$secret,$files);
    assert_true(is_wp_error($second),'uncertain completion must retain transition claim');
    assert_same('upload_in_progress',$second->get_error_code(),'retained transition claim must block second finalizer');
    $status=WPVDMCP_Upload::check($result['upload_id']);
    assert_same('processing',$status['status']??null,'failed ready write leaves ticket processing but locked');
    delete_option('wpvdmcp_upload_claim_'.hash('sha256',$result['upload_id']));
});

test_case('multi-file browser upload rolls back earlier attachments when a later file fails', function () {
    $one=tempnam(sys_get_temp_dir(),'wpvdmcp-one-');
    $two=tempnam(sys_get_temp_dir(),'wpvdmcp-two-');
    file_put_contents($one,'fake-png-one');
    file_put_contents($two,'fake-html-two');
    $GLOBALS['test_image_mimes']=array(basename($one)=>'image/png',basename($two)=>'text/html');
    $GLOBALS['test_created_attachments']=array();
    $GLOBALS['test_deleted_attachments']=array();
    $GLOBALS['test_next_attachment_id']=601;
    $files=array(
        'name'=>array('one.png','two.html'),
        'type'=>array('image/png','text/html'),
        'tmp_name'=>array($one,$two),
        'error'=>array(UPLOAD_ERR_OK,UPLOAD_ERR_OK),
        'size'=>array(filesize($one),filesize($two)),
    );
    $method=new ReflectionMethod('WPVDMCP_Upload','handle_files');
    if(PHP_VERSION_ID<80100){$method->setAccessible(true);}
    $result=$method->invoke(null,array('post_id'=>0,'title'=>'','alt_text'=>''),$files);
    @unlink($one);@unlink($two);
    unset($GLOBALS['test_image_mimes'],$GLOBALS['test_next_attachment_id']);
    assert_true(is_wp_error($result),'later invalid file must fail the upload');
    assert_same(array(601),$GLOBALS['test_created_attachments'],'first attachment must have been created before the synthetic later failure');
    assert_same(array(601),$GLOBALS['test_deleted_attachments'],'earlier attachment must be rolled back after later failure');
});

test_case('device upload allows only explicit raster image MIME types', function () {
    foreach(array('image/jpeg','image/png','image/gif','image/webp') as $mime){assert_true(WPVDMCP_Upload::is_allowed_raster_mime($mime),'allowed '.$mime);}
    foreach(array('text/html','application/x-httpd-php','application/javascript','image/svg+xml') as $mime){assert_true(!WPVDMCP_Upload::is_allowed_raster_mime($mime),'blocked '.$mime);}
});


test_case('skills catalog covers production WordPress workflows with version metadata', function () {
    $required=array('setup','theme-redesign','classic-themes','block-themes','gutenberg','elementor','elementor-atomic','beaver-builder','bricks','breakdance','divi','seedprod','wpbakery','woocommerce','content-editing','rest-api','abilities-api','custom-fields','seo','caching','performance','debugging','security','media','accessibility');
    $catalog=WPVDMCP_Skills::catalog();
    foreach($required as $slug){
        assert_true(isset($catalog[$slug]),'missing skill '.$slug);
        assert_true(isset($catalog[$slug]['min_wordpress']) && isset($catalog[$slug]['min_wpvibe']),'version metadata '.$slug);
    }
});

test_case('list_skills returns metadata and load_skill returns file instructions', function () {
    $listed=WPVDMCP_Tools::execute('list_skills',array());
    assert_true(isset($listed['skills'][0]['slug']),'list_skills metadata');
    $loaded=WPVDMCP_Tools::execute('load_skill',array('skill'=>'media'));
    assert_same('media',$loaded['skill'] ?? null,'media skill slug');
    assert_true(strlen($loaded['instructions'] ?? '')>80,'media instructions loaded');
});

test_case('compatibility summary reports device upload and hosted-only boundaries', function () {
    $summary=WPVDMCP_Compatibility::capability_summary();
    assert_true(isset($summary['device_upload']) && $summary['device_upload']===true,'device upload available');
    assert_true(isset($summary['hosted_only']) && is_array($summary['hosted_only']),'hosted only list');
});


test_case('admin status snapshot exposes compatibility and media support without secrets', function () {
    set_test_routes(array('/wpvibe/v1/site-info'=>array('GET'),'/wpvibe/v1/upload-media'=>array('POST')));
    $status=WPVDMCP_Admin::status_snapshot();
    assert_same(WPVDMCP_VERSION,$status['direct_version'] ?? null,'direct version');
    assert_same(WPVIBE_VERSION,$status['wpvibe_version'] ?? null,'wpvibe version');
    assert_true(($status['tool_count'] ?? 0)>0,'tool count');
    assert_true(($status['capabilities']['device_upload'] ?? false)===true,'device upload indicator');
    assert_true(!isset($status['token']) && !isset($status['token_hash']),'snapshot does not expose token material');
});


test_case('modern requests require matching Mcp-Method and named calls require Mcp-Name', function () {
    $payload=array('jsonrpc'=>'2.0','id'=>31,'method'=>'tools/call','params'=>array('name'=>'list_skills','arguments'=>array(),'_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28','io.modelcontextprotocol/clientInfo'=>array('name'=>'tests','version'=>'1'))));
    $missing=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp');
    $missing->set_header('authorization','Bearer test-token');
    $missing->set_header('content-type','application/json');
    $missing->set_header('mcp-protocol-version','2026-07-28');
    $missing->set_json_params($payload);
    $r=WPVDMCP_Server::instance()->handle($missing);
    assert_same(400,$r->get_status(),'missing routing header HTTP status');
    assert_same(-32020,$r->get_data()['error']['code'] ?? null,'missing routing header code');

    $mismatch=test_request($payload,'2026-07-28');
    $mismatch->set_header('mcp-name','wrong_tool');
    $r2=WPVDMCP_Server::instance()->handle($mismatch);
    assert_same(400,$r2->get_status(),'mismatched name HTTP status');
    assert_same(-32020,$r2->get_data()['error']['code'] ?? null,'mismatched name code');
});

test_case('modern list results carry cache hints and modern ping is not a spec method', function () {
    $list=array('jsonrpc'=>'2.0','id'=>32,'method'=>'tools/list','params'=>array('_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28','io.modelcontextprotocol/clientInfo'=>array('name'=>'tests','version'=>'1'))));
    $r=WPVDMCP_Server::instance()->handle(test_request($list,'2026-07-28'));
    $result=$r->get_data()['result'] ?? array();
    assert_true(isset($result['ttlMs']) && isset($result['cacheScope']),'modern list cache hints');
    $ping=array('jsonrpc'=>'2.0','id'=>33,'method'=>'ping','params'=>array('_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28','io.modelcontextprotocol/clientInfo'=>array('name'=>'tests','version'=>'1'))));
    $p=WPVDMCP_Server::instance()->handle(test_request($ping,'2026-07-28'));
    assert_same(-32601,$p->get_data()['error']['code'] ?? null,'modern ping rejected');
});


test_case('authentication keeps bearer and X header while rejecting missing or wrong tokens', function () {
    $server=WPVDMCP_Server::instance();
    $payload=array('jsonrpc'=>'2.0','id'=>41,'method'=>'tools/list','params'=>array());

    $missing=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $missing->set_json_params($payload);
    $m=$server->handle($missing); assert_true(is_wp_error($m),'missing auth returns WP_Error'); assert_same('missing_mcp_token',$m->get_error_code(),'missing token code');

    $wrong=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $wrong->set_header('authorization','Bearer wrong'); $wrong->set_json_params($payload);
    $w=$server->handle($wrong); assert_true(is_wp_error($w),'wrong auth returns WP_Error'); assert_same('invalid_mcp_token',$w->get_error_code(),'wrong token code');

    $bearer=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $bearer->set_header('authorization','Bearer test-token'); $bearer->set_json_params($payload);
    $br=$server->handle($bearer); assert_true(!is_wp_error($br),'Bearer header accepted');

    $x=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $x->set_header('x-wpvibe-direct-token','test-token'); $x->set_json_params($payload);
    $xr=$server->handle($x); assert_true(!is_wp_error($xr),'X header accepted');
});

test_case('query token authentication is disabled by default', function () {
    $server=WPVDMCP_Server::instance();
    $payload=array('jsonrpc'=>'2.0','id'=>411,'method'=>'tools/list','params'=>array());
    unset($GLOBALS['wp_options']['wpvdmcp_allow_query_token']);

    $q=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $q->set_query_params(array('token'=>'test-token')); $q->set_json_params($payload);
    $qr=$server->handle($q); assert_true(is_wp_error($qr),'query token must fail closed by default'); assert_same('query_mcp_token_disabled',$qr->get_error_code(),'query token disabled code');

    $legacy=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $legacy->set_query_params(array('access_token'=>'test-token')); $legacy->set_json_params($payload);
    $lr=$server->handle($legacy); assert_true(is_wp_error($lr),'access_token must fail closed by default'); assert_same('query_mcp_token_disabled',$lr->get_error_code(),'access_token disabled code');
});

test_case('query token authentication works only after explicit site-owner opt in', function () {
    $server=WPVDMCP_Server::instance();
    $payload=array('jsonrpc'=>'2.0','id'=>412,'method'=>'tools/list','params'=>array());
    $GLOBALS['wp_options']['wpvdmcp_allow_query_token']='1';

    $q=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $q->set_query_params(array('token'=>'test-token')); $q->set_json_params($payload);
    $qr=$server->handle($q); assert_true(!is_wp_error($qr),'query token accepted after opt in');

    $legacy=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $legacy->set_query_params(array('access_token'=>'test-token')); $legacy->set_json_params($payload);
    $lr=$server->handle($legacy); assert_true(!is_wp_error($lr),'access_token accepted after opt in');

    unset($GLOBALS['wp_options']['wpvdmcp_allow_query_token']);
});

test_case('admin status and settings expose query token compatibility as opt in', function () {
    unset($GLOBALS['wp_options']['wpvdmcp_allow_query_token']);
    $status=WPVDMCP_Admin::status_snapshot();
    assert_same(false,$status['query_token_enabled']??null,'query token status defaults off');

    $GLOBALS['wp_options']['wpvdmcp_allow_query_token']='1';
    $enabled=WPVDMCP_Admin::status_snapshot();
    assert_same(true,$enabled['query_token_enabled']??null,'query token status reflects opt in');

    ob_start(); WPVDMCP_Admin::instance()->render(); $html=ob_get_clean();
    assert_true(strpos($html,'name="allow_query_token"')!==false,'settings render query-token opt-in checkbox');
    assert_true(strpos($html,'query-string tokens can')!==false,'settings retain query-token leak warning');

    unset($GLOBALS['wp_options']['wpvdmcp_allow_query_token']);
});

test_case('admin settings persist query token compatibility opt in and opt out', function () {
    $admin=WPVDMCP_Admin::instance();
    $GLOBALS['throw_on_redirect']=true;
    $previous_post=$_POST;

    $_POST=array('enabled'=>'1','allow_query_token'=>'1');
    try { $admin->save(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'opt-in save redirects'); }
    assert_same('1',$GLOBALS['wp_options']['wpvdmcp_allow_query_token']??null,'opt-in save persists enabled state');

    $_POST=array('enabled'=>'1');
    try { $admin->save(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'opt-out save redirects'); }
    assert_same('0',$GLOBALS['wp_options']['wpvdmcp_allow_query_token']??null,'opt-out save persists disabled state');

    $_POST=$previous_post;
    unset($GLOBALS['throw_on_redirect']);
    unset($GLOBALS['wp_options']['wpvdmcp_allow_query_token']);
});

test_case('admin settings fail closed and roll back when query-token opt out cannot persist', function () {
    $admin=WPVDMCP_Admin::instance();
    $GLOBALS['wp_options']['wpvdmcp_enabled']='1';
    $GLOBALS['wp_options']['wpvdmcp_allow_query_token']='1';
    $GLOBALS['throw_on_redirect']=true;
    $previous_post=$_POST;
    $_POST=array();
    $GLOBALS['update_option_override']=function($key,$value){if($key==='wpvdmcp_allow_query_token' && $value==='0')return false;return null;};
    try { $admin->save(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'failed settings save redirects'); }
    unset($GLOBALS['update_option_override'],$GLOBALS['throw_on_redirect']);
    $_POST=$previous_post;
    assert_true(strpos($GLOBALS['last_redirect']??'','settings_error=1')!==false,'failed opt-out must report a settings persistence error');
    assert_same('1',$GLOBALS['wp_options']['wpvdmcp_enabled']??null,'failed settings save restores previous enabled state');
    assert_same('1',$GLOBALS['wp_options']['wpvdmcp_allow_query_token']??null,'failed settings save restores previous query-token state');
    unset($GLOBALS['wp_options']['wpvdmcp_allow_query_token'],$GLOBALS['last_redirect']);
});

test_case('admin token regeneration rolls back partial credential persistence before exposing a token', function () {
    $admin=WPVDMCP_Admin::instance();
    $old_hash=$GLOBALS['wp_options']['wpvdmcp_token_hash'];
    $old_owner=$GLOBALS['wp_options']['wpvdmcp_user_id'];
    $GLOBALS['wp_current_user']=2;
    $GLOBALS['throw_on_redirect']=true;
    $GLOBALS['update_option_override']=function($key,$value){
        if($key==='wpvdmcp_user_id' && (int)$value===2) return false;
        return null;
    };
    try { $admin->generate_token(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'failed regeneration redirects'); }
    unset($GLOBALS['update_option_override'],$GLOBALS['throw_on_redirect']);
    $GLOBALS['wp_current_user']=0;
    assert_same($old_hash,$GLOBALS['wp_options']['wpvdmcp_token_hash']??null,'failed regeneration restores previous token hash');
    assert_same($old_owner,$GLOBALS['wp_options']['wpvdmcp_user_id']??null,'failed regeneration restores previous owner');
    assert_same(false,get_transient('wpvdmcp_new_token_2'),'failed regeneration must not expose the unusable new token');
});

test_case('admin token regeneration rolls back when one-time token display state cannot persist', function () {
    $admin=WPVDMCP_Admin::instance();
    $old_hash=$GLOBALS['wp_options']['wpvdmcp_token_hash'];
    $old_owner=$GLOBALS['wp_options']['wpvdmcp_user_id'];
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['throw_on_redirect']=true;
    $GLOBALS['set_transient_override']=function($key,$value,$ttl){if($key==='wpvdmcp_new_token_1')return false;return null;};
    try { $admin->generate_token(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'failed token display persistence redirects'); }
    unset($GLOBALS['set_transient_override'],$GLOBALS['throw_on_redirect']);
    $GLOBALS['wp_current_user']=0;
    assert_same($old_hash,$GLOBALS['wp_options']['wpvdmcp_token_hash']??null,'display persistence failure restores previous token hash');
    assert_same($old_owner,$GLOBALS['wp_options']['wpvdmcp_user_id']??null,'display persistence failure restores previous owner');
    assert_true(strpos($GLOBALS['last_redirect']??'','token_error=1')!==false,'display persistence failure reports token error');
    unset($GLOBALS['last_redirect']);
});

test_case('admin token rollback never exposes the previous token under the replacement owner', function () {
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_update_override=array_key_exists('update_option_override',$GLOBALS);
    $update_override_before=$had_update_override?$GLOBALS['update_option_override']:null;
    $had_transient_override=array_key_exists('set_transient_override',$GLOBALS);
    $transient_override_before=$had_transient_override?$GLOBALS['set_transient_override']:null;
    $had_throw=array_key_exists('throw_on_redirect',$GLOBALS);
    $throw_before=$had_throw?$GLOBALS['throw_on_redirect']:null;
    try {
        $admin=WPVDMCP_Admin::instance();
        $old_token='previous-token';
        $old_hash=hash('sha256',$old_token);
        $old_owner=99;
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=$old_hash;
        $GLOBALS['wp_options']['wpvdmcp_user_id']=$old_owner;
        $GLOBALS['wp_current_user']=1;
        $GLOBALS['throw_on_redirect']=true;
        $GLOBALS['set_transient_override']=function($key,$value,$ttl){
            if($key==='wpvdmcp_new_token_1') return false;
            return null;
        };

        $nested_result=null;
        $nested_user=null;
        $saw_old_hash_restore=false;
        $GLOBALS['update_option_override']=function($key,$value) use ($old_hash,$old_token,&$nested_result,&$nested_user,&$saw_old_hash_restore){
            if($key==='wpvdmcp_token_hash' && $value===$old_hash && ($GLOBALS['wp_options']['wpvdmcp_token_hash']??null)!==$old_hash){
                $saw_old_hash_restore=true;
                $GLOBALS['wp_options'][$key]=$value;
                $GLOBALS['wp_current_user']=0;
                $request=new WP_REST_Request('GET','/wpvibe-direct/v1/health');
                $request->set_header('authorization','Bearer '.$old_token);
                $nested_result=WPVDMCP_Server::instance()->status($request);
                $nested_user=$GLOBALS['wp_current_user'];
                return true;
            }
            return null;
        };

        try { $admin->generate_token(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'failed token display persistence redirects'); }

        assert_true($saw_old_hash_restore,'regression must observe the old token hash being restored');
        assert_true(is_wp_error($nested_result),'previous token must stay unusable until its previous owner is restored');
        assert_same('invalid_mcp_user',$nested_result->get_error_code(),'rollback window must expose the previous owner before the previous token hash');
        assert_same(0,$nested_user,'failed nested authentication must not assume the replacement owner');
        assert_same($old_hash,$GLOBALS['wp_options']['wpvdmcp_token_hash']??null,'rollback restores the previous token hash');
        assert_same($old_owner,$GLOBALS['wp_options']['wpvdmcp_user_id']??null,'rollback restores the previous token owner');
    } finally {
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_update_override)$GLOBALS['update_option_override']=$update_override_before;else unset($GLOBALS['update_option_override']);
        if($had_transient_override)$GLOBALS['set_transient_override']=$transient_override_before;else unset($GLOBALS['set_transient_override']);
        if($had_throw)$GLOBALS['throw_on_redirect']=$throw_before;else unset($GLOBALS['throw_on_redirect']);
    }
});

test_case('admin token revoke uses a fail-closed fallback when option deletion fails', function () {
    $admin=WPVDMCP_Admin::instance();
    $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','revocable-token');
    $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['throw_on_redirect']=true;
    $GLOBALS['delete_option_override']=function($key){if(in_array($key,array('wpvdmcp_token_hash','wpvdmcp_user_id'),true))return false;return null;};
    try { $admin->revoke_token(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'revoke redirects'); }
    unset($GLOBALS['delete_option_override'],$GLOBALS['throw_on_redirect']);
    $GLOBALS['wp_current_user']=0;
    assert_same('',$GLOBALS['wp_options']['wpvdmcp_token_hash']??null,'failed delete falls back to an unusable empty token hash');
    assert_same(0,(int)($GLOBALS['wp_options']['wpvdmcp_user_id']??-1),'failed delete falls back to an unusable owner id');
    assert_true(strpos($GLOBALS['last_redirect']??'','token_revoked=1')!==false,'verified fallback revocation may report success');
    $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','test-token');
    $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
    unset($GLOBALS['last_redirect']);
});

test_case('admin token revoke reports failure when neither delete nor fail-closed fallback can persist', function () {
    $admin=WPVDMCP_Admin::instance();
    $old_hash=hash('sha256','still-active-token');
    $GLOBALS['wp_options']['wpvdmcp_token_hash']=$old_hash;
    $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
    $GLOBALS['wp_current_user']=1;
    $GLOBALS['throw_on_redirect']=true;
    $GLOBALS['delete_option_override']=function($key){if(in_array($key,array('wpvdmcp_token_hash','wpvdmcp_user_id'),true))return false;return null;};
    $GLOBALS['update_option_override']=function($key,$value){if(($key==='wpvdmcp_token_hash' && $value==='')||($key==='wpvdmcp_user_id' && (int)$value===0))return false;return null;};
    try { $admin->revoke_token(); } catch (RuntimeException $e) { assert_same('redirect',$e->getMessage(),'failed revoke redirects'); }
    unset($GLOBALS['delete_option_override'],$GLOBALS['update_option_override'],$GLOBALS['throw_on_redirect']);
    $GLOBALS['wp_current_user']=0;
    assert_same($old_hash,$GLOBALS['wp_options']['wpvdmcp_token_hash']??null,'failed revoke does not falsely mutate stored hash in fixture');
    assert_true(strpos($GLOBALS['last_redirect']??'','token_revoke_error=1')!==false,'unpersistable revoke must report an explicit error');
    $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','test-token');
    $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
    unset($GLOBALS['last_redirect']);
});

test_case('admin render surfaces credential and settings persistence errors', function () {
    $admin=WPVDMCP_Admin::instance();
    $old_get=$_GET;
    $_GET=array('token_error'=>'1','token_revoke_error'=>'1','settings_error'=>'1');
    ob_start();
    $admin->render();
    $html=ob_get_clean();
    $_GET=$old_get;
    assert_true(strpos($html,'could not be saved')!==false,'token generation persistence error notice missing');
    assert_true(strpos($html,'could not be fully revoked')!==false,'token revoke persistence error notice missing');
    assert_true(strpos($html,'settings could not be saved')!==false,'settings persistence error notice missing');
});

test_case('authentication rejects revoked token and owner who lost administrator access', function () {
    $server=WPVDMCP_Server::instance();
    $payload=array('jsonrpc'=>'2.0','id'=>42,'method'=>'tools/list','params'=>array());
    $hash=$GLOBALS['wp_options']['wpvdmcp_token_hash'];
    unset($GLOBALS['wp_options']['wpvdmcp_token_hash']);
    $revoked=$server->handle(test_request($payload));
    assert_true(is_wp_error($revoked),'revoked token rejected'); assert_same('mcp_not_configured',$revoked->get_error_code(),'revoked code');
    $GLOBALS['wp_options']['wpvdmcp_token_hash']=$hash;
    $GLOBALS['user_can_manage_options']=false;
    $lost=$server->handle(test_request($payload));
    $GLOBALS['user_can_manage_options']=true;
    assert_true(is_wp_error($lost),'owner access loss rejected'); assert_same('invalid_mcp_user',$lost->get_error_code(),'owner access code');
});

test_case('transport handles invalid payload unknown method legacy GET and authenticated health', function () {
    $server=WPVDMCP_Server::instance();
    $bad=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $bad->set_header('authorization','Bearer test-token'); $bad->set_json_params(null);
    $br=$server->handle($bad); assert_same(400,$br->get_status(),'invalid payload HTTP'); assert_same(-32700,$br->get_data()['error']['code'] ?? null,'invalid payload code');
    $unknown=$server->handle(test_request(array('jsonrpc'=>'2.0','id'=>43,'method'=>'does/not/exist','params'=>array())));
    assert_same(-32601,$unknown->get_data()['error']['code'] ?? null,'unknown method');
    assert_same(405,$server->stream_get()->get_status(),'GET 405');
    $health=$server->status(test_request(array()));
    assert_true(!is_wp_error($health),'health auth'); assert_same('WPVibe Direct MCP',$health->get_data()['name'] ?? null,'health name');
});

$fail=0;
foreach($tests as $name=>$fn){
    try{$fn();echo "PASS $name\n";}catch(Throwable $e){$fail++;echo "FAIL $name: {$e->getMessage()}\n";}
}
echo sprintf("\n%d tests, %d failures\n",count($tests),$fail);
exit($fail?1:0);
