<?php
parity_test('upstream 1.20.3 route inventory classifies every model or infrastructure route',function(){
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $internal=$manifest['internal_not_tools']??array();
    foreach(array(
        '/wpvibe/v1/ping','/wpvibe/v1/health','/wpvibe/v1/connection-check-challenge',
        '/wpvibe/v1/audit-log/record','/wpvibe/v1/cli/run-approved','/wpvibe/v1/authorize',
        '/wpvibe/v1/authorize/preflight','/wpvibe/v1/connection-status','/wpvibe/v1/op-proof/check',
        '/wpvibe/v1/code-snippet','/wpvibe/v1/builder-login','/wpvibe/v1/detached/run',
        '/wpvibe/v1/self-update/health','/wpvibe/v1/self-update/run'
    ) as $route){
        assert_true(in_array($route,$internal,true),'unclassified infrastructure route '.$route);
    }
    $model_routes=$manifest['model_route_tools']??array();
    foreach(array(
        '/wpvibe/v1/site-info','/wpvibe/v1/registered-meta','/wpvibe/v1/file/read','/wpvibe/v1/file/list',
        '/wpvibe/v1/file/search','/wpvibe/v1/file/outline','/wpvibe/v1/file/edit','/wpvibe/v1/file/write',
        '/wpvibe/v1/file/delete','/wpvibe/v1/content/search','/wpvibe/v1/content/edit','/wpvibe/v1/draft-theme',
        '/wpvibe/v1/draft-theme/preview','/wpvibe/v1/draft-theme/publish','/wpvibe/v1/draft-theme/delete',
        '/wpvibe/v1/cli/run','/wpvibe/v1/cli/status','/wpvibe/v1/upload-media','/wpvibe/v1/rendered-html',
        '/wpvibe/v1/navigate','/wpvibe/v1/audit-log','/wpvibe/v1/op-receipt/{op_id}',
        '/wpvibe/v1/create-classic-theme-safe','/wpvibe/v1/last-change',
        '/wpvibe/v1/elementor/widgets','/wpvibe/v1/elementor/schema','/wpvibe/v1/elementor/style-schema',
        '/wpvibe/v1/elementor/save-page','/wpvibe/v1/elementor/save-template',
        '/wpvibe/v1/beaver/modules','/wpvibe/v1/beaver/schema','/wpvibe/v1/beaver/save-page',
        '/wpvibe/v1/bricks/get-page','/wpvibe/v1/bricks/elements','/wpvibe/v1/bricks/save-page',
        '/wpvibe/v1/breakdance/get-page','/wpvibe/v1/breakdance/elements','/wpvibe/v1/breakdance/save-page',
        '/wpvibe/v1/code-snippet/dormant'
    ) as $route){
        assert_true(isset($model_routes[$route]),'unclassified model route '.$route);
    }
});

parity_test('reference manifest covers infrastructure-only routes and MCP options',function(){
    $m=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    foreach(array('/wpvibe/v1/ping','/wpvibe/v1/health','/wpvibe/v1/connection-check-challenge','/wpvibe/v1/audit-log/record','/wpvibe/v1/cli/run-approved','/wpvibe/v1/authorize','/wpvibe/v1/authorize/preflight','/wpvibe/v1/connection-status','/wpvibe/v1/op-proof/check','/wpvibe/v1/code-snippet','/wpvibe/v1/builder-login','/wpvibe/v1/detached/run','/wpvibe/v1/self-update/health','/wpvibe/v1/self-update/run') as $route){
        assert_true(in_array($route,$m['internal_not_tools']??array(),true),'internal route missing '.$route);
    }
    assert_same('2026-07-28',$m['mcp']['primary_protocol']??null,'primary MCP version');
    assert_true(in_array('mcp-apps',$m['mcp']['extensions']??array(),true),'MCP Apps manifest');
});

parity_test('Divi and SeedProd skills describe audited native refresh and compile paths',function(){
    $divi=WPVDMCP_Parity::execute('load_skill',array('skill'=>'divi'));
    assert_true(strpos($divi['instructions']??'','edit_content')!==false,'Divi skill uses audited content path');
    assert_true(strpos($divi['instructions']??'','et_divi_save_post')!==false,'Divi refresh hook documented');
    assert_true(strpos($divi['instructions']??'','post meta add')!==false,'Divi Theme Builder multi-value linkage documented');
    $seed=WPVDMCP_Parity::execute('load_skill',array('skill'=>'seedprod'));
    assert_true(strpos($seed['instructions']??'','seedprod_compile_page')!==false,'SeedProd compile workflow documented');
    assert_true(strpos($seed['instructions']??'','builder-login')!==false,'SeedProd internal primitive documented');
});

parity_test('Elementor v4 skill covers current abilities, Atomic boundaries, settings, preview and live verification',function(){
    $skill=WPVDMCP_Parity::execute('load_skill',array('skill'=>'elementor'));
    $text=$skill['instructions']??'';
    assert_true(strpos($text,'discover_abilities')!==false,'Elementor v4 abilities discovery documented');
    foreach(array(
        'elementor/get-page-structure','elementor/create-page','elementor/build-composition','elementor/manage-elements',
        'elementor/update-page-settings','elementor/publish-document','elementor/create-preview-link',
        'elementor/manage-global-variable','elementor/manage-classes','elementor/manage-default-styles',
        'elementor/list-widget-schemas','elementor/get-widget-schema','elementor/list-components',
        'elementor/list-dynamic-tags','elementor/list-resources','elementor/read-resource'
    ) as $ability){
        assert_true(strpos($text,$ability)!==false,'Elementor skill missing current ability '.$ability);
    }
    assert_true(strpos(strtolower($text),'replaces')!==false,'Elementor replace-not-merge warning documented');
    assert_true(strpos($text,'Atomic Editor')!==false,'Elementor Atomic capability boundary documented');
    assert_true(strpos($text,'elementor_save_page')!==false,'Elementor native fallback documented');
    assert_true(strpos($text,'elementor_save_template')!==false,'Elementor Pro template fallback documented');
    assert_true(strpos($text,'get_page_html')!==false,'Elementor rendered verification documented');
    assert_true(strpos($text,'screenshot_page')!==false,'Elementor visual verification documented');
});

parity_test('current WPVibe Works with AI plugin playbooks are all loadable',function(){
    $slugs=array(
        'abilities-plugin','rank-math','aioseo','seopress','yoast-seo','smash-balloon',
        'memberpress','charitable','duplicator','pushengage','easy-digital-downloads',
        'lifterlms','wpforms','kit-convertkit','modern-cart','cartflows','pagelayer',
        'elementskit','amelia','adtribes-product-feed','fluentcart','fluentcommunity',
        'fluentcrm','merchant','woocommerce','wpcode','sugar-calendar','optinmonster','botiga',
        'elementor','beaver-builder','bricks','breakdance','divi','seedprod','generatepress',
        'generateblocks','kadence'
    );
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $audited=$manifest['works_with_ai_integrations']??array();
    foreach($slugs as $slug){
        assert_true(in_array($slug,$audited,true),'Works-with manifest missing: '.$slug);
        $skill=WPVDMCP_Parity::execute('load_skill',array('skill'=>$slug));
        assert_true(!is_wp_error($skill),'skill must load: '.$slug);
        assert_true(strlen($skill['instructions']??'')>180,'skill must contain a real workflow: '.$slug);
    }
});

parity_test('release metadata is WPVibe Direct MCP 1.3.1',function(){
    $main=(string)file_get_contents(dirname(__DIR__,2).'/wpvibe-direct-mcp.php');
    $readme=(string)file_get_contents(dirname(__DIR__,2).'/readme.txt');
    assert_true(strpos($main,'Version:     1.3.1')!==false,'plugin header 1.3.1');
    assert_true(strpos($main,"WPVDMCP_VERSION', '1.3.1")!==false,'version constant 1.3.1');
    assert_true(strpos($readme,'Stable tag: 1.3.1')!==false,'readme stable tag 1.3.1');
});

parity_test('1.3 skills identify their release source',function(){
    $catalog=WPVDMCP_Parity_Skills::extra_catalog();
    assert_true(isset($catalog['abilities-plugin']),'abilities-plugin missing');
    assert_same('builtin-1.3',$catalog['abilities-plugin']['source']??'','1.3 skill source is stale');
});

parity_test('generic REST schemas expose approval IDs for writes',function(){
    $rest=parity_tool('rest_api');
    $write=parity_tool('rest_api_write');
    assert_true(isset($rest['inputSchema']['properties']->approval_id),'rest_api approval_id missing');
    assert_true(isset($write['inputSchema']['properties']->approval_id),'rest_api_write approval_id missing');
});

parity_test('generic REST writes require one-time approval unless bypass is enabled',function(){
    set_test_routes(array('/wp/v2/pages/12'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $calls=0;
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$calls){
        $calls++;
        return new WP_REST_Response(array('id'=>12,'method'=>$r->get_param('_method'),'body'=>$r->get_json_params()),200);
    };
    $args=array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('title'=>'Updated'));
    $first=WPVDMCP_Tools::execute('rest_api',$args);
    assert_same('approval_required',$first['status']??null,'REST write should request approval');
    assert_same(0,$calls,'REST write must not dispatch before approval');
    assert_true(WPVDMCP_Approvals::approve($first['approval_id'],1)===true,'REST approval accepted');
    $args['approval_id']=$first['approval_id'];
    $second=WPVDMCP_Tools::execute('rest_api',$args);
    assert_same(12,$second['data']['id']??null,'approved REST write dispatched');
    assert_same(1,$calls,'approved REST write dispatched once');
    $replay=WPVDMCP_Tools::execute('rest_api',$args);
    assert_true(is_wp_error($replay),'REST approval must be single use');

    $GLOBALS['wp_options']['wpvibe_bypass_approvals']=array('enabled'=>true);
    $bypass=WPVDMCP_Tools::execute('rest_api',array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('title'=>'Bypassed')));
    assert_same(12,$bypass['data']['id']??null,'bypass executes REST write');
    assert_same(2,$calls,'bypass dispatches directly');
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals'],$GLOBALS['rest_dispatch_callback']);
});

parity_test('core content REST deletes trash instead of honoring force true',function(){
    set_test_routes(array('/wp/v2/pages/12'=>array('DELETE')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $GLOBALS['rest_dispatch_callback']=function($r){
        return new WP_REST_Response(array('query'=>$r->get_query_params(),'method'=>$r->get_param('_method')),200);
    };
    $args=array('method'=>'DELETE','path'=>'/wp/v2/pages/12','params'=>array('force'=>true));
    $first=WPVDMCP_Tools::execute('rest_api',$args);
    assert_same('approval_required',$first['status']??null,'REST delete should request approval');
    assert_true(WPVDMCP_Approvals::approve($first['approval_id'],1)===true,'REST delete approval accepted');
    $args['approval_id']=$first['approval_id'];
    $done=WPVDMCP_Tools::execute('rest_api',$args);
    $query=$done['data']['query']??array();
    assert_true(!isset($query['force']) || !$query['force'],'force=true must not reach trashable core content delete');
    unset($GLOBALS['rest_dispatch_callback']);
});
