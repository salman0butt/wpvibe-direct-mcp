<?php
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity-fields.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity-skills.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity-insights.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity.php';

$tests=array();
function parity_test($name,$fn){global $tests;$tests[$name]=$fn;}
function parity_tool($name){foreach(WPVDMCP_Parity::merge_definitions(WPVDMCP_Tools::definitions()) as $t){if(($t['name']??'')===$name)return $t;}return null;}

parity_test('server merges 1.2 parity tools',function(){
    set_test_routes(array('/wpvibe/v1/last-change'=>array('GET')));
    foreach(array('list_editable_fields','register_editable_field','validate_blocks','save_validated_blocks','inspect_media','site_intelligence','live_reload_status','seo_audit','create_skill','update_skill','delete_skill','integration_capabilities','page_audit','search_images','render_browser') as $name) assert_true(parity_tool($name)!==null,'missing '.$name);
});
parity_test('editable field persists and invalid type is rejected',function(){
    $GLOBALS['wp_current_user']=1;
    $a=WPVDMCP_Parity::execute('register_editable_field',array('post_type'=>'page','key'=>'hero_heading','config'=>array('type'=>'text','label'=>'Hero'))); assert_same('approval_required',$a['status']??null,'approval');
    WPVDMCP_Approvals::approve($a['approval_id'],1);
    $r=WPVDMCP_Parity::execute('register_editable_field',array('post_type'=>'page','key'=>'hero_heading','config'=>array('type'=>'text','label'=>'Hero'),'approval_id'=>$a['approval_id'])); assert_same(true,$r['persistent']??null,'persisted');
    $bad=WPVDMCP_Parity::execute('register_editable_field',array('post_type'=>'page','key'=>'bad','config'=>array('type'=>'script')));assert_true(is_wp_error($bad),'bad type');
});
parity_test('editable field registration fails closed when durable persistence fails',function(){
    $GLOBALS['wp_current_user']=1;
    $a=WPVDMCP_Parity::execute('register_editable_field',array('post_type'=>'page','key'=>'persist_failure','config'=>array('type'=>'text','label'=>'Must persist')));assert_same('approval_required',$a['status']??null,'approval');
    WPVDMCP_Approvals::approve($a['approval_id'],1);
    $GLOBALS['update_option_override']=function($key,$value){if($key===WPVDMCP_Parity_Fields::OPTION)return false;return null;};
    $r=WPVDMCP_Parity::execute('register_editable_field',array('post_type'=>'page','key'=>'persist_failure','config'=>array('type'=>'text','label'=>'Must persist'),'approval_id'=>$a['approval_id']));
    unset($GLOBALS['update_option_override']);
    assert_true(is_wp_error($r),'failed persistence must not be reported as registered');
    assert_same('editable_field_persist_failed',$r->get_error_code(),'persistence failure code');
});
parity_test('editable field registration rolls back durable state when native registration fails',function(){
    $GLOBALS['wp_current_user']=1;
    $a=WPVDMCP_Parity::execute('register_editable_field',array('post_type'=>'page','key'=>'native_failure','config'=>array('type'=>'text','label'=>'Native failure')));assert_same('approval_required',$a['status']??null,'approval');
    WPVDMCP_Approvals::approve($a['approval_id'],1);
    $GLOBALS['wpvibe_field_register_callback']=function(){return new WP_Error('native_registration_failed','Synthetic native registration failure.');};
    $r=WPVDMCP_Parity::execute('register_editable_field',array('post_type'=>'page','key'=>'native_failure','config'=>array('type'=>'text','label'=>'Native failure'),'approval_id'=>$a['approval_id']));
    unset($GLOBALS['wpvibe_field_register_callback']);
    assert_true(is_wp_error($r),'native registration failure must not be reported as registered');
    assert_same('native_registration_failed',$r->get_error_code(),'native registration error preserved');
    $stored=$GLOBALS['wp_options'][WPVDMCP_Parity_Fields::OPTION]['fields']??array();
    assert_true(!isset($stored['page:native_failure']),'failed native registration must roll back persisted definition');
});
parity_test('editable groups and settings use upstream safe subsets',function(){
    $GLOBALS['wp_current_user']=1;$g=WPVDMCP_Parity::execute('register_editable_group',array('post_type'=>'page','group_id'=>'hero','config'=>array('title'=>'Hero')));WPVDMCP_Approvals::approve($g['approval_id'],1);$gd=WPVDMCP_Parity::execute('register_editable_group',array('post_type'=>'page','group_id'=>'hero','config'=>array('title'=>'Hero'),'approval_id'=>$g['approval_id']));assert_same('group',$gd['kind']??null,'group');
    $bad=WPVDMCP_Parity::execute('register_editable_setting',array('key'=>'items','config'=>array('type'=>'repeater')));assert_true(is_wp_error($bad),'setting subset');
});
parity_test('recursive registered block validation covers Kadence and GenerateBlocks',function(){
    $GLOBALS['wpvdmcp_block_types']=array('core/group'=>array('attributes'=>array()),'kadence/rowlayout'=>array('attributes'=>array()),'generateblocks/container'=>array('attributes'=>array('items'=>array('type'=>'array'))));
    $r=WPVDMCP_Parity::execute('validate_blocks',array('strict'=>true,'blocks'=>array(array('blockName'=>'core/group','attrs'=>array(),'innerBlocks'=>array(array('blockName'=>'generateblocks/container','attrs'=>array('items'=>array()),'innerBlocks'=>array()))))));assert_same(true,$r['valid']??null,'valid');
    $bad=WPVDMCP_Parity::execute('validate_blocks',array('strict'=>true,'blocks'=>array(array('blockName'=>'unknown/x','attrs'=>array(),'innerBlocks'=>array()))));assert_same(false,$bad['valid']??null,'unknown invalid');
});
parity_test('validated block save rejects invalid and approval gates valid mutation',function(){
    $GLOBALS['wpvdmcp_block_types']=array('core/paragraph'=>array('attributes'=>array()));set_test_routes(array('/wp/v2/pages/9'=>array('POST')));
    $bad=WPVDMCP_Parity::execute('save_validated_blocks',array('rest_path'=>'/wp/v2/pages/9','blocks'=>array(array('blockName'=>'unknown/x')),'content'=>'x'));assert_same('invalid_blocks',$bad->get_error_code(),'reject invalid');
    $GLOBALS['wp_current_user']=1;$a=WPVDMCP_Parity::execute('save_validated_blocks',array('rest_path'=>'/wp/v2/pages/9','blocks'=>array(array('blockName'=>'core/paragraph')),'content'=>'<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->'));assert_same('approval_required',$a['status']??null,'approval');
    WPVDMCP_Approvals::approve($a['approval_id'],1);$GLOBALS['rest_dispatch_callback']=function($q){return new WP_REST_Response(array('id'=>9),200);};$done=WPVDMCP_Parity::execute('save_validated_blocks',array('rest_path'=>'/wp/v2/pages/9','blocks'=>array(array('blockName'=>'core/paragraph')),'content'=>'<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->','approval_id'=>$a['approval_id']));unset($GLOBALS['rest_dispatch_callback']);assert_same(9,$done['data']['id']??null,'saved');
});
parity_test('validated block save validates the exact serialized content instead of trusting a separate blocks array',function(){
    $GLOBALS['wpvdmcp_block_types']=array('core/paragraph'=>array('attributes'=>array()));set_test_routes(array('/wp/v2/pages/9'=>array('POST')));
    $mismatch=WPVDMCP_Parity::execute('save_validated_blocks',array(
        'rest_path'=>'/wp/v2/pages/9',
        'blocks'=>array(array('blockName'=>'core/paragraph')),
        'content'=>'<!-- wp:unknown/x --><p>Must not persist</p><!-- /wp:unknown/x -->',
    ));
    assert_true(is_wp_error($mismatch),'mismatched serialized content must be rejected');
    assert_same('invalid_blocks',$mismatch->get_error_code(),'exact serialized content must be the validation source');
});
parity_test('strict validated block save rejects non-whitespace freeform content',function(){
    $GLOBALS['wpvdmcp_block_types']=array('core/paragraph'=>array('attributes'=>array()));set_test_routes(array('/wp/v2/pages/9'=>array('POST')));
    $GLOBALS['parse_blocks_callback']=function($content){
        return array(array('blockName'=>null,'attrs'=>array(),'innerBlocks'=>array(),'innerHTML'=>'<script>alert(1)</script>','innerContent'=>array('<script>alert(1)</script>')));
    };
    $raw=WPVDMCP_Parity::execute('save_validated_blocks',array('rest_path'=>'/wp/v2/pages/9','content'=>'<script>alert(1)</script>'));
    unset($GLOBALS['parse_blocks_callback']);
    assert_true(is_wp_error($raw),'strict validated write must reject non-block freeform markup');
    assert_same('invalid_blocks',$raw->get_error_code(),'freeform validation failure code');
});
parity_test('saved skills CRUD is approval gated and builtins immutable',function(){
    $GLOBALS['wp_current_user']=1;$a=WPVDMCP_Parity::execute('create_skill',array('slug'=>'brand-flow','title'=>'Brand','instructions'=>'Inspect existing brand tokens, make minimal changes, verify output, and preserve rollback information.'));WPVDMCP_Approvals::approve($a['approval_id'],1);$c=WPVDMCP_Parity::execute('create_skill',array('slug'=>'brand-flow','title'=>'Brand','instructions'=>'Inspect existing brand tokens, make minimal changes, verify output, and preserve rollback information.','approval_id'=>$a['approval_id']));assert_same(1,$c['skill']['version']??null,'created');
    $b=WPVDMCP_Parity::execute('delete_skill',array('slug'=>'seo'));assert_true(is_wp_error($b),'builtin immutable');
});
parity_test('saved skill mutations fail closed when durable persistence fails',function(){
    $GLOBALS['wp_current_user']=1;
    $a=WPVDMCP_Parity::execute('create_skill',array('slug'=>'persist-skill-failure','title'=>'Persistence','instructions'=>'This saved workflow must not report success unless durable storage is verified.'));assert_same('approval_required',$a['status']??null,'approval');
    WPVDMCP_Approvals::approve($a['approval_id'],1);
    $GLOBALS['update_option_override']=function($key,$value){if($key===WPVDMCP_Parity_Skills::OPTION)return false;return null;};
    $r=WPVDMCP_Parity::execute('create_skill',array('slug'=>'persist-skill-failure','title'=>'Persistence','instructions'=>'This saved workflow must not report success unless durable storage is verified.','approval_id'=>$a['approval_id']));
    unset($GLOBALS['update_option_override']);
    assert_true(is_wp_error($r),'failed skill persistence must not be reported as created');
    assert_same('skill_persist_failed',$r->get_error_code(),'skill persistence failure code');
});
parity_test('saved skill update and delete require fresh approvals',function(){
    $GLOBALS['wp_current_user']=1;$u=WPVDMCP_Parity::execute('update_skill',array('slug'=>'brand-flow','instructions'=>'Updated brand workflow with discovery, safe editing, verification, approval, and rollback guidance.'));WPVDMCP_Approvals::approve($u['approval_id'],1);$ud=WPVDMCP_Parity::execute('update_skill',array('slug'=>'brand-flow','instructions'=>'Updated brand workflow with discovery, safe editing, verification, approval, and rollback guidance.','approval_id'=>$u['approval_id']));assert_same(2,$ud['skill']['version']??null,'version');
    $d=WPVDMCP_Parity::execute('delete_skill',array('slug'=>'brand-flow'));WPVDMCP_Approvals::approve($d['approval_id'],1);$dd=WPVDMCP_Parity::execute('delete_skill',array('slug'=>'brand-flow','approval_id'=>$d['approval_id']));assert_same('deleted',$dd['status']??null,'deleted');
});
parity_test('expanded skills load and are deep enough',function(){foreach(array('kadence','generatepress','generateblocks','editable-fields','block-validation','saved-skills','pdf-media','site-intelligence','live-reload','lighthouse','image-search') as $slug){$l=WPVDMCP_Parity_Skills::load($slug);assert_true(!is_wp_error($l),'missing '.$slug);assert_true(strlen($l['instructions']??'')>180,'shallow '.$slug);}});
parity_test('media PDF intelligence strips paths and bounds provider text',function(){
    $GLOBALS['wpvdmcp_media_items']=array(7=>array('id'=>7,'mime_type'=>'application/pdf','title'=>'Guide','url'=>'https://example.test/guide.pdf','file'=>'/var/www/private.pdf'));$GLOBALS['wpvdmcp_pdf_text']='Hello'.str_repeat('x',1000);$r=WPVDMCP_Parity::execute('inspect_media',array('attachment_id'=>7,'max_text_chars'=>100));assert_true(!isset($r['media']['file']),'path hidden');assert_same(100,strlen($r['text']??''),'bounded');
});
parity_test('media PDF text truncation never splits UTF-8 output',function(){
    $GLOBALS['wpvdmcp_media_items']=array(8=>array('id'=>8,'mime_type'=>'application/pdf','title'=>'Unicode','url'=>'https://example.test/unicode.pdf'));
    $GLOBALS['wpvdmcp_pdf_text']=str_repeat('a',99)."€";
    $r=WPVDMCP_Parity::execute('inspect_media',array('attachment_id'=>8,'max_text_chars'=>100));
    assert_true(isset($r['text']) && preg_match('//u',$r['text'])===1,'bounded PDF provider text must remain valid UTF-8');
    assert_same(str_repeat('a',99)."€",$r['text'],'max_text_chars must count characters rather than split UTF-8 bytes');
});
parity_test('site intelligence live reload and integrations are feature detected',function(){set_test_routes(array('/wpvibe/v1/last-change'=>array('GET'),'/wpvibe/v1/elementor/save-page'=>array('POST')));$i=WPVDMCP_Parity::execute('site_intelligence',array());assert_true(isset($i['php_version']),'php');assert_same(true,$i['live_reload_available']??null,'reload');assert_same(true,$i['integrations']['elementor']??null,'elementor');});
parity_test('SEO audit is read only and reports metadata gaps',function(){set_test_routes(array('/wpvibe/v1/rendered-html'=>array('POST')));$GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('html'=>'<html><head><title>Example</title><link rel="canonical" href="https://example.test/"></head><body><h1>Hello</h1><img src="x.jpg"></body></html>'),200);};$a=WPVDMCP_Parity::execute('seo_audit',array('path'=>'/'));unset($GLOBALS['rest_dispatch_callback']);assert_same('Example',$a['title']??null,'title');assert_same(true,$a['issues']['missing_meta_description']??null,'description');assert_same(1,$a['issues']['images_missing_alt']??null,'alt');});
parity_test('external cloud-adjacent tools never fabricate missing providers',function(){$none=WPVDMCP_Parity::execute('page_audit',array('url'=>'https://example.test'));assert_true(is_wp_error($none),'provider absent');$GLOBALS['wpvdmcp_providers']=array('page_audit'=>function($a){return array('performance'=>91);},'search_images'=>function($a){return array('results'=>array(array('url'=>'https://img.test/1.jpg')));},'render_browser'=>function($a){return array('html'=>'<div>Rendered</div>');});assert_same(91,WPVDMCP_Parity::execute('page_audit',array('url'=>'https://example.test'))['performance']??null,'audit');});
parity_test('load skill schema includes local saved skills',function(){$GLOBALS['wp_options']['wpvdmcp_saved_skills']=array('ops'=>array('slug'=>'ops','title'=>'Ops','instructions'=>'A durable workflow with discovery, execution, verification and rollback.','version'=>1));$t=parity_tool('load_skill');assert_true(in_array('ops',$t['inputSchema']['properties']->skill['enum']??array(),true),'saved schema');});
parity_test('modern MCP tools/list and tools/call route through parity',function(){
    $server=WPVDMCP_Server::instance();$list=$server->handle(test_request(array('jsonrpc'=>'2.0','id'=>1,'method'=>'tools/list','params'=>array('_meta'=>array('io.modelcontextprotocol/protocolVersion'=>'2026-07-28','io.modelcontextprotocol/clientCapabilities'=>(object)array()))),'2026-07-28'));$names=array_map(function($t){return $t['name'];},$list->get_data()['result']['tools']??array());assert_true(in_array('site_intelligence',$names,true),'list routed');
});

$fail=0;foreach($tests as $name=>$fn){try{$fn();echo "PASS $name\n";}catch(Throwable $e){$fail++;echo "FAIL $name: {$e->getMessage()}\n";}}echo sprintf("\n%d parity tests, %d failures\n",count($tests),$fail);exit($fail?1:0);
