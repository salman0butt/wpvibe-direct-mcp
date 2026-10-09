<?php
parity_test('audited reference WPVibe tool names are all exposed',function(){
    $reference=array('audit_page','check_approval_status','check_upload','code_snippet','connect_site','create_classic_theme','create_draft_theme','delete_draft_theme','delete_file','discover_abilities','edit_file','get_ability_info','get_file_outline','get_page_html','get_preview_url','get_profile','list_files','list_sites','load_skill','navigate','publish_draft_theme','read_file','remove_site','request_upload','rest_api','rest_api_write','run_ability','run_wp_cli','save_skill','search_files','search_images','show_approval_panel','show_fleet_dashboard','site_info','start_fleet_job','upload_media','write_file','use_usage_reset');
    set_test_routes(array('/wpvibe/v1/site-info'=>array('GET'),'/wpvibe/v1/file/read'=>array('POST'),'/wpvibe/v1/file/edit'=>array('POST'),'/wpvibe/v1/file/write'=>array('POST'),'/wpvibe/v1/file/delete'=>array('POST'),'/wpvibe/v1/file/list'=>array('GET'),'/wpvibe/v1/file/search'=>array('POST'),'/wpvibe/v1/file/outline'=>array('POST'),'/wpvibe/v1/draft-theme'=>array('POST'),'/wpvibe/v1/draft-theme/preview'=>array('GET'),'/wpvibe/v1/draft-theme/publish'=>array('POST'),'/wpvibe/v1/draft-theme/delete'=>array('POST'),'/wpvibe/v1/create-classic-theme-safe'=>array('POST'),'/wpvibe/v1/cli/run'=>array('POST'),'/wpvibe/v1/upload-media'=>array('POST'),'/wpvibe/v1/rendered-html'=>array('POST'),'/wpvibe/v1/navigate'=>array('POST'),'/wpvibe/v1/code-snippet/dormant'=>array('POST'),'/wp-abilities/v1/abilities'=>array('GET')));
    foreach($reference as $name) assert_true(parity_tool($name)!==null,'missing reference tool '.$name);
});

parity_test('reference local aliases preserve existing safety semantics',function(){
    $GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($args){return array('score'=>97,'url'=>$args['url']);});
    $audit=WPVDMCP_Parity::execute('audit_page',array('url'=>'https://example.test/page','strategy'=>'mobile'));
    assert_same(97,$audit['score']??null,'audit_page delegates');

    $bad=WPVDMCP_Parity::execute('rest_api_write',array('method'=>'GET','path'=>'/wp/v2/pages'));
    assert_true(is_wp_error($bad),'GET must be rejected');
    assert_same('invalid_method',$bad->get_error_code(),'GET rejection code');
    set_test_routes(array('/wp/v2/pages/12'=>array('POST')));
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('id'=>12,'method'=>$r->get_param('_method')),200);};
    $write=WPVDMCP_Parity::execute('rest_api_write',array('method'=>'POST','path'=>'/wp/v2/pages/12','body'=>array('title'=>'Updated')));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(12,$write['data']['id']??null,'write delegated');

    $GLOBALS['wp_current_user']=1;
    $first=WPVDMCP_Parity::execute('save_skill',array('slug'=>'reference-flow','title'=>'Reference Flow','instructions'=>'Inspect the target, use the narrowest safe tool, verify the result, and preserve rollback information.'));
    assert_same('approval_required',$first['status']??null,'save create approval');
    WPVDMCP_Approvals::approve($first['approval_id'],1);
    $created=WPVDMCP_Parity::execute('save_skill',array('slug'=>'reference-flow','title'=>'Reference Flow','instructions'=>'Inspect the target, use the narrowest safe tool, verify the result, and preserve rollback information.','approval_id'=>$first['approval_id']));
    assert_same(1,$created['skill']['version']??null,'save create');
    $second=WPVDMCP_Parity::execute('save_skill',array('slug'=>'reference-flow','instructions'=>'Inspect first, change only the requested target, verify the result, and keep explicit rollback information.'));
    assert_same('approval_required',$second['status']??null,'save update approval');
    WPVDMCP_Approvals::approve($second['approval_id'],1);
    $updated=WPVDMCP_Parity::execute('save_skill',array('slug'=>'reference-flow','instructions'=>'Inspect first, change only the requested target, verify the result, and keep explicit rollback information.','approval_id'=>$second['approval_id']));
    assert_same(2,$updated['skill']['version']??null,'save update');
    $builtin=WPVDMCP_Parity::execute('save_skill',array('slug'=>'seo','instructions'=>'Never overwrite builtins.'));
    assert_true(is_wp_error($builtin),'builtin immutable through alias');
});

parity_test('hosted reference tools fail closed and accept explicit user-owned providers',function(){
    unset($GLOBALS['wpvdmcp_reference_providers']);
    foreach(array('connect_site','list_sites','remove_site','get_profile','start_fleet_job','show_fleet_dashboard','use_usage_reset') as $name){
        $args=array();
        if('connect_site'===$name)$args=array('site_url'=>'https://site.test');
        if('remove_site'===$name)$args=array('site_id'=>'site-1');
        if('start_fleet_job'===$name)$args=array('plan'=>array('action'=>'update'));
        $r=WPVDMCP_Parity::execute($name,$args);
        assert_true(is_wp_error($r),'provider required '.$name);
        assert_same('provider_unavailable',$r->get_error_code(),'provider code '.$name);
        assert_same(true,$r->get_error_data()['hosted_boundary']??null,'hosted boundary '.$name);
    }
    $GLOBALS['wpvdmcp_reference_providers']=array(
        'list_sites'=>function($args){return array('sites'=>array(array('id'=>'site-1')));},
        'get_profile'=>function($args){return array('name'=>'Owner');},
    );
    assert_same('site-1',WPVDMCP_Parity::execute('list_sites',array())['sites'][0]['id']??null,'provider list');
    assert_same('Owner',WPVDMCP_Parity::execute('get_profile',array())['name']??null,'provider profile');
});

parity_test('reference parity manifest classifies every audited tool and internal boundaries',function(){
    $tool=parity_tool('reference_parity_manifest');
    assert_true($tool!==null,'manifest tool exposed');
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $by=array();foreach($manifest['tools']??array() as $row)$by[$row['name']]=$row;
    foreach(array('site_info','audit_page','rest_api_write','save_skill','connect_site','use_usage_reset') as $name)assert_true(isset($by[$name]),'manifest missing '.$name);
    assert_same('provider_backed',$by['connect_site']['status']??null,'connect classification');
    assert_same('local_equivalent',$by['audit_page']['status']??null,'audit classification');
    assert_true(in_array('/wpvibe/v1/cli/run-approved',$manifest['internal_not_tools']??array(),true),'approved CLI remains internal');
});

parity_test('last change helper delegates only when upstream live reload route exists',function(){
    set_test_routes(array('/wpvibe/v1/last-change'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('changed'=>true,'cursor'=>'c2','since'=>$r->get_query_params()['since']??null),200);};
    $r=WPVDMCP_Parity::execute('get_last_change',array('since'=>'c1'));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(true,$r['data']['changed']??null,'change returned');
    assert_same('c1',$r['data']['since']??null,'since forwarded');
    set_test_routes(array());
    $missing=WPVDMCP_Parity::execute('get_last_change',array());
    assert_true(is_wp_error($missing),'missing route error');
    assert_same('tool_unavailable',$missing->get_error_code(),'missing route code');
});

parity_test('get_page_html prefers configured JavaScript browser provider and falls back to WPVibe route',function(){
    set_test_routes(array('/wpvibe/v1/rendered-html'=>array('POST')));
    $GLOBALS['wpvdmcp_providers']=array('render_browser'=>function($args){return array('html'=>'<main data-js="1">Rendered</main>','javascript_executed'=>true,'received_path'=>$args['path']??null);});
    $js=WPVDMCP_Parity::execute('get_page_html',array('path'=>'/app/'));
    assert_same(true,$js['javascript_executed']??null,'browser provider used');
    assert_same('/app/',$js['received_path']??null,'path forwarded');
    unset($GLOBALS['wpvdmcp_providers']);
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('html'=>'<main>Server</main>'),200);};
    $fallback=WPVDMCP_Parity::execute('get_page_html',array('path'=>'/app/'));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same('<main>Server</main>',$fallback['data']['html']??null,'upstream fallback');
});

parity_test('SeedProd compile never exposes raw builder login and requires approval plus browser provider',function(){
    set_test_routes(array('/wpvibe/v1/builder-login'=>array('POST')));
    unset($GLOBALS['wpvdmcp_reference_providers']);
    $missing=WPVDMCP_Parity::execute('seedprod_compile_page',array('page_id'=>77));
    assert_true(is_wp_error($missing),'provider required');
    assert_same('provider_unavailable',$missing->get_error_code(),'provider missing code');

    $GLOBALS['wpvdmcp_reference_providers']=array('seedprod_compile_page'=>function($args){return array('compiled'=>true,'page_id'=>$args['page_id'],'saw_login'=>!empty($args['login_url']));});
    $GLOBALS['wp_current_user']=1;
    $approval=WPVDMCP_Parity::execute('seedprod_compile_page',array('page_id'=>77));
    assert_same('approval_required',$approval['status']??null,'compile approval');
    WPVDMCP_Approvals::approve($approval['approval_id'],1);
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('login_url'=>'https://example.test/?secret=one-time','builder_url'=>'https://example.test/wp-admin/seedprod','expires_in'=>120),200);};
    $done=WPVDMCP_Parity::execute('seedprod_compile_page',array('page_id'=>77,'approval_id'=>$approval['approval_id']));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(true,$done['compiled']??null,'compiled');
    assert_same(true,$done['saw_login']??null,'trusted provider got login');
    assert_true(!isset($done['login_url']),'login URL not leaked to model');
});

