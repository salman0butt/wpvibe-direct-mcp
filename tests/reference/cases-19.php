<?php
if(!function_exists('has_filter')){
    function has_filter($tag){
        $registered=array_key_exists($tag,$GLOBALS['wpvdmcp_test_filter_callbacks']??array())||array_key_exists($tag,$GLOBALS['wpvdmcp_test_filter_values']??array());
        if(!$registered)return false;
        return array_key_exists($tag,$GLOBALS['wpvdmcp_test_filter_priorities']??array())?$GLOBALS['wpvdmcp_test_filter_priorities'][$tag]:10;
    }
}
if(!function_exists('apply_filters')){
    function apply_filters($tag,$value){
        $args=func_get_args();array_shift($args);
        if(isset($GLOBALS['wpvdmcp_test_filter_callbacks'][$tag])&&is_callable($GLOBALS['wpvdmcp_test_filter_callbacks'][$tag]))return call_user_func_array($GLOBALS['wpvdmcp_test_filter_callbacks'][$tag],$args);
        return array_key_exists($tag,$GLOBALS['wpvdmcp_test_filter_values']??array())?$GLOBALS['wpvdmcp_test_filter_values'][$tag]:$value;
    }
}

function provider_meta_tool($name){
    foreach(WPVDMCP_Parity::merge_definitions(WPVDMCP_Tools::definitions()) as $tool){
        if(($tool['name']??'')===$name)return $tool;
    }
    return null;
}

parity_test('provider-backed tools remain visible and advertise missing provider readiness',function(){
    unset($GLOBALS['wpvdmcp_providers'],$GLOBALS['wpvdmcp_reference_providers'],$GLOBALS['wpvdmcp_test_filter_callbacks'],$GLOBALS['wpvdmcp_test_filter_values']);
    $required=array(
        'page_audit'=>'page_audit',
        'audit_page'=>'page_audit',
        'search_images'=>'search_images',
        'render_browser'=>'render_browser',
        'screenshot_page'=>'screenshot_page',
        'seedprod_compile_page'=>'seedprod_compile_page',
        'connect_site'=>'connect_site',
        'list_sites'=>'list_sites',
        'remove_site'=>'remove_site',
        'get_profile'=>'get_profile',
        'start_fleet_job'=>'start_fleet_job',
        'show_fleet_dashboard'=>'show_fleet_dashboard',
        'use_usage_reset'=>'use_usage_reset',
    );
    foreach($required as$name=>$provider){
        $tool=provider_meta_tool($name);
        assert_true(is_array($tool),'provider-backed tool remains listed '.$name);
        $meta=$tool['_meta']['io.wpvibe.direct/provider']??array();
        assert_same(true,$meta['backed']??null,'provider-backed metadata '.$name);
        assert_same(false,$meta['configured']??null,'provider missing metadata '.$name);
        assert_same('required',$meta['mode']??null,'required provider mode '.$name);
        assert_same(true,$meta['failClosed']??null,'required provider failClosed '.$name);
        assert_same($provider,$meta['provider']??null,'provider name '.$name);
    }
});

parity_test('get_page_html advertises optional provider and same-site native fallback',function(){
    unset($GLOBALS['wpvdmcp_providers'],$GLOBALS['wpvdmcp_test_filter_callbacks'],$GLOBALS['wpvdmcp_test_filter_values']);
    $tool=provider_meta_tool('get_page_html');
    assert_true(is_array($tool),'get_page_html remains listed');
    $meta=$tool['_meta']['io.wpvibe.direct/provider']??array();
    assert_same(true,$meta['backed']??null,'browser provider-backed');
    assert_same(false,$meta['configured']??null,'browser provider absent');
    assert_same('optional_same_site_fallback',$meta['mode']??null,'hybrid mode');
    assert_same('render_browser',$meta['provider']??null,'browser provider name');
    assert_same('wpvibe_rendered_html_same_site',$meta['fallback']??null,'same-site fallback');
    assert_same(true,$meta['externalUrlRequiresProvider']??null,'external URL requires browser provider');
});

parity_test('tools discovery detects provider callbacks without invoking them',function(){
    $calls=0;
    $GLOBALS['wpvdmcp_providers']=array(
        'page_audit'=>function($args)use(&$calls){$calls++;return array('score'=>99);},
        'render_browser'=>function($args)use(&$calls){$calls++;return array('html'=>'<main>Rendered</main>');},
    );
    $GLOBALS['wpvdmcp_reference_providers']=array(
        'get_profile'=>function($args)use(&$calls){$calls++;return array('name'=>'Owner');},
    );
    $page=provider_meta_tool('page_audit');
    $html=provider_meta_tool('get_page_html');
    $profile=provider_meta_tool('get_profile');
    unset($GLOBALS['wpvdmcp_providers'],$GLOBALS['wpvdmcp_reference_providers']);
    assert_same(0,$calls,'tools/list metadata never invokes callbacks');
    assert_same(true,$page['_meta']['io.wpvibe.direct/provider']['configured']??null,'local callback detected');
    assert_same(true,$html['_meta']['io.wpvibe.direct/provider']['configured']??null,'hybrid callback detected');
    assert_same(true,$profile['_meta']['io.wpvibe.direct/provider']['configured']??null,'reference callback detected');
});

parity_test('tools discovery detects provider filters without executing them',function(){
    $calls=0;
    unset($GLOBALS['wpvdmcp_providers'],$GLOBALS['wpvdmcp_reference_providers']);
    $GLOBALS['wpvdmcp_test_filter_callbacks']=array(
        'wpvdmcp_search_images'=>function($value,$args=null)use(&$calls){$calls++;return array('results'=>array());},
        'wpvdmcp_reference_screenshot_page'=>function($value,$args=null)use(&$calls){$calls++;return array('url'=>'https://img.test/s.png');},
    );
    $GLOBALS['wpvdmcp_test_filter_priorities']=array('wpvdmcp_search_images'=>0,'wpvdmcp_reference_screenshot_page'=>0);
    $images=provider_meta_tool('search_images');
    $shot=provider_meta_tool('screenshot_page');
    unset($GLOBALS['wpvdmcp_test_filter_callbacks'],$GLOBALS['wpvdmcp_test_filter_priorities']);
    assert_same(0,$calls,'tools/list metadata never executes provider filters');
    assert_same(true,$images['_meta']['io.wpvibe.direct/provider']['configured']??null,'local priority-zero filter detected');
    assert_same(true,$shot['_meta']['io.wpvibe.direct/provider']['configured']??null,'reference priority-zero filter detected');
});

parity_test('provider metadata composes with existing MCP Apps metadata and omits native tools',function(){
    unset($GLOBALS['wpvdmcp_providers'],$GLOBALS['wpvdmcp_reference_providers'],$GLOBALS['wpvdmcp_test_filter_callbacks'],$GLOBALS['wpvdmcp_test_filter_values']);
    $show=provider_meta_tool('show_approval_panel');
    assert_same('ui://wpvibe-direct/approval-panel',$show['_meta']['ui']['resourceUri']??null,'MCP Apps UI metadata preserved');
    assert_true(!isset($show['_meta']['io.wpvibe.direct/provider']),'MCP Apps native tool has no provider metadata');
    foreach(array('site_intelligence','inspect_media','rest_api') as$name){
        $tool=provider_meta_tool($name);
        assert_true(is_array($tool),'native tool listed '.$name);
        assert_true(!isset($tool['_meta']['io.wpvibe.direct/provider']),'native tool has no provider metadata '.$name);
    }
});

parity_test('provider availability metadata does not change fail-closed execution',function(){
    unset($GLOBALS['wpvdmcp_providers'],$GLOBALS['wpvdmcp_reference_providers'],$GLOBALS['wpvdmcp_test_filter_callbacks'],$GLOBALS['wpvdmcp_test_filter_values']);
    $audit=WPVDMCP_Parity::execute('audit_page',array('page_url'=>'https://example.test/'));
    $profile=WPVDMCP_Parity::execute('get_profile',array());
    assert_true(is_wp_error($audit),'audit still fails closed');
    assert_same('provider_unavailable',$audit->get_error_code(),'audit fail-closed code');
    assert_true(is_wp_error($profile),'profile still fails closed');
    assert_same('provider_unavailable',$profile->get_error_code(),'profile fail-closed code');
});

parity_test('MCP tools list exposes only non-secret provider readiness metadata',function(){
    $calls=0;
    $secret='SHOULD-NOT-APPEAR-IN-TOOLS-LIST';
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use(&$calls,$secret){$calls++;return array('api_key'=>$secret);});
    $payload=array('jsonrpc'=>'2.0','id'=>'provider-meta-list','method'=>'tools/list','params'=>array());
    $response=WPVDMCP_Server::instance()->handle(test_request($payload));
    unset($GLOBALS['wpvdmcp_providers']);
    $body=response_data($response);
    $tools=$body['result']['tools']??array();
    $audit=null;
    foreach($tools as$tool)if(($tool['name']??'')==='audit_page'){$audit=$tool;break;}
    assert_true(is_array($audit),'audit_page appears in actual tools/list');
    assert_same(true,$audit['_meta']['io.wpvibe.direct/provider']['configured']??null,'actual tools/list exposes readiness');
    assert_same(0,$calls,'actual tools/list does not invoke provider');
    assert_true(strpos(json_encode($body),$secret)===false,'actual tools/list contains no provider-return secret');
});
