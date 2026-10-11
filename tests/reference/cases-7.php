<?php
parity_test('search images accepts WPVibe count and orientation while preserving Direct per_page',function(){
    $tool=parity_tool('search_images');$props=$tool['inputSchema']['properties'];
    foreach(array('query','count','per_page','orientation') as $key)assert_true(isset($props->$key),'search_images missing '.$key);
    assert_same(array('landscape','portrait','squarish'),$props->orientation['enum']??null,'orientation enum');
    $seen=null;
    $GLOBALS['wpvdmcp_providers']=array('search_images'=>function($args)use(&$seen){$seen=$args;return array('results'=>array());});
    $r=WPVDMCP_Parity::execute('search_images',array('query'=>'modern dental clinic','count'=>20,'orientation'=>'landscape'));
    unset($GLOBALS['wpvdmcp_providers']);
    assert_true(!is_wp_error($r),'search provider executes');
    assert_same(10,$seen['count']??null,'count capped to public max');
    assert_same(10,$seen['per_page']??null,'per_page normalized');
    assert_same('landscape',$seen['orientation']??null,'orientation forwarded');
});

parity_test('audit page accepts current WPVibe options and normalizes page_url',function(){
    $tool=parity_tool('audit_page');$props=$tool['inputSchema']['properties'];
    foreach(array('url','page_url','strategy','categories','refresh') as $key)assert_true(isset($props->$key),'audit_page missing '.$key);
    assert_same(array('mobile','desktop'),$props->strategy['enum']??null,'audit strategy enum');
    $seen=null;
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use(&$seen){$seen=$args;return array('score'=>91);});
    $r=WPVDMCP_Parity::execute('audit_page',array('page_url'=>'https://example.test/pricing/','strategy'=>'desktop','categories'=>array('performance','accessibility'),'refresh'=>true));
    unset($GLOBALS['wpvdmcp_providers']);
    assert_same(91,$r['score']??null,'audit provider result');
    assert_same('https://example.test/pricing/',$seen['url']??null,'page_url normalized to url');
    assert_same(array('performance','accessibility'),$seen['categories']??null,'categories forwarded');
    assert_same(true,$seen['refresh']??null,'refresh forwarded');
});

parity_test('audit page defaults to local site root when no URL is supplied',function(){
    $seen=null;
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args)use(&$seen){$seen=$args;return array('ok'=>true);});
    $r=WPVDMCP_Parity::execute('audit_page',array());
    unset($GLOBALS['wpvdmcp_providers']);
    assert_true(!is_wp_error($r),'site-root audit should execute');
    assert_true(!empty($seen['url']),'site-root URL supplied to provider');
});

parity_test('get page html accepts path or current WPVibe url selector max_length contract',function(){
    $tool=parity_tool('get_page_html');$props=$tool['inputSchema']['properties'];
    foreach(array('path','url','selector','max_length') as $key)assert_true(isset($props->$key),'get_page_html missing '.$key);
    $seen=null;
    $GLOBALS['wpvdmcp_providers']=array('render_browser'=>function($args)use(&$seen){$seen=$args;return array('html'=>str_repeat('x',200),'javascript_executed'=>true);});
    $r=WPVDMCP_Parity::execute('get_page_html',array('url'=>'https://example.test/about/','selector'=>'main','max_length'=>50));
    unset($GLOBALS['wpvdmcp_providers']);
    assert_same('https://example.test/about/',$seen['url']??null,'absolute URL forwarded');
    assert_same('main',$seen['selector']??null,'selector forwarded');
    assert_same(50,$seen['max_length']??null,'max length forwarded');
    assert_same(50,strlen($r['html']??''),'HTML bounded');
    assert_same(true,$r['html_truncated']??null,'truncation reported');
    assert_same(200,$r['html_full_length']??null,'full size reported');
});

parity_test('get page html server fallback accepts same-site absolute URL but rejects external URL without browser provider',function(){
    set_test_routes(array('/wpvibe/v1/rendered-html'=>array('POST')));
    unset($GLOBALS['wpvdmcp_providers']);
    $seen_path='';
    $GLOBALS['rest_dispatch_callback']=function($r)use(&$seen_path){$seen_path=$r->get_params()['path']??'';return new WP_REST_Response(array('html'=>'<main>Same site</main>'),200);};
    $same=WPVDMCP_Parity::execute('get_page_html',array('url'=>'https://example.test/about/'));
    assert_true(!is_wp_error($same),'same-site URL native fallback');
    assert_same('/about/',$seen_path,'same-site URL converted to path');
    $external=WPVDMCP_Parity::execute('get_page_html',array('url'=>'https://external.example/about/'));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(is_wp_error($external),'external URL needs browser provider');
    assert_same('provider_unavailable',$external->get_error_code(),'external fallback error');
});

parity_test('connect site and fleet schemas match current hosted public options without fabricating providers',function(){
    $connect=parity_tool('connect_site');$cp=$connect['inputSchema']['properties'];
    assert_true(isset($cp->site_url)&&isset($cp->auth),'connect_site current options');
    $fleet=parity_tool('start_fleet_job');$fp=$fleet['inputSchema']['properties'];
    foreach(array('plan','sites','steps','confirm','idempotency_key') as $key)assert_true(isset($fp->$key),'start_fleet_job missing '.$key);
    assert_true(isset($fleet['inputSchema']['anyOf']),'fleet accepts legacy plan or current sites+steps');

    $seen=null;
    $GLOBALS['wpvdmcp_reference_providers']=array('start_fleet_job'=>function($args)use(&$seen){$seen=$args;return array('job_id'=>'fj_123','status'=>'working');});
    $r=WPVDMCP_Parity::execute('start_fleet_job',array('sites'=>array('https://example.test'),'steps'=>array(array('kind'=>'plugin_update')),'confirm'=>true,'idempotency_key'=>'job-1'));
    unset($GLOBALS['wpvdmcp_reference_providers']);
    assert_same('fj_123',$r['job_id']??null,'fleet provider result');
    assert_same('plugin_update',$seen['steps'][0]['kind']??null,'fleet steps forwarded');
    assert_same(true,$seen['confirm']??null,'fleet confirm forwarded');
});

parity_test('provider-backed public tools still fail closed when no provider is configured',function(){
    unset($GLOBALS['wpvdmcp_reference_providers'],$GLOBALS['wpvdmcp_providers']);
    foreach(array(
        array('search_images',array('query'=>'office')),
        array('audit_page',array('page_url'=>'https://example.test/')),
        array('start_fleet_job',array('sites'=>array('https://example.test'),'steps'=>array(array('kind'=>'plugin_update')))),
        array('connect_site',array('site_url'=>'https://example.test'))
    ) as $case){
        $r=WPVDMCP_Parity::execute($case[0],$case[1]);
        assert_true(is_wp_error($r),'provider required '.$case[0]);
        assert_same('provider_unavailable',$r->get_error_code(),'provider error '.$case[0]);
    }
});
