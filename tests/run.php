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

test_case('destructive ability uses DELETE after approval', function () {
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
    assert_same('DELETE',$second['data']['method'] ?? null,'destructive uses DELETE');
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


test_case('authentication accepts bearer X header and query token and rejects missing or wrong tokens', function () {
    $server=WPVDMCP_Server::instance();
    $payload=array('jsonrpc'=>'2.0','id'=>41,'method'=>'tools/list','params'=>array());

    $missing=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $missing->set_json_params($payload);
    $m=$server->handle($missing); assert_true(is_wp_error($m),'missing auth returns WP_Error'); assert_same('missing_mcp_token',$m->get_error_code(),'missing token code');

    $wrong=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $wrong->set_header('authorization','Bearer wrong'); $wrong->set_json_params($payload);
    $w=$server->handle($wrong); assert_true(is_wp_error($w),'wrong auth returns WP_Error'); assert_same('invalid_mcp_token',$w->get_error_code(),'wrong token code');

    $x=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $x->set_header('x-wpvibe-direct-token','test-token'); $x->set_json_params($payload);
    $xr=$server->handle($x); assert_true(!is_wp_error($xr),'X header accepted');

    $q=new WP_REST_Request('POST','/wpvibe-direct/v1/mcp'); $q->set_query_params(array('token'=>'test-token')); $q->set_json_params($payload);
    $qr=$server->handle($q); assert_true(!is_wp_error($qr),'query token accepted');
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
