<?php
/**
 * Upstream WPVibe 1.20.4/1.20.5 additive REST schema contracts.
 * These assertions intentionally fail against Direct MCP 1.5.0.
 */
parity_test('Bricks 1.20.4 templates support the upstream template_type contract', function(){
    set_test_routes(array('/wpvibe/v1/bricks/save-page'=>array('POST'),'/wpvibe/v1/bricks/get-page'=>array('GET')));
    $save=parity_tool('bricks_save_page');
    assert_true(is_array($save),'Bricks save tool exposed when native route exists');
    $props=$save['inputSchema']['properties']??null;
    assert_true(is_object($props)&&isset($props->template_type),'Bricks save-page must advertise template_type so header/footer can be stored in Bricks template areas');
    $description=(string)($props->template_type['description']??'');
    assert_true(strpos($description,'bricks_template')!==false,'template_type must be documented as applicable only to bricks_template posts');
    $skill=WPVDMCP_Parity::execute('load_skill',array('skill'=>'bricks'));
    assert_true(strpos($skill['instructions']??'','template_type')!==false,'Bricks skill must explain template_type and header/footer persistence');
});

parity_test('Beaver 1.20.4 allows listing schemas and layout nodes without a module slug', function(){
    set_test_routes(array('/wpvibe/v1/beaver/schema'=>array('GET')));
    $schema=parity_tool('beaver_schema');
    assert_true(is_array($schema),'Beaver schema tool exposed');
    assert_true(!in_array('slug',$schema['inputSchema']['required']??array(),true),'Beaver schema slug optional in upstream 1.20.4');
    $skill=WPVDMCP_Parity::execute('load_skill',array('skill'=>'beaver-builder'));
    assert_true(strpos($skill['instructions']??'','column')!==false,'Beaver skill should explain optional row/column-group/column layout nodes');
});

parity_test('upstream routes hidden from REST index do not appear in model route discovery',function(){
    set_test_routes(array('/wpvibe/v1/builder-login'=>array('POST'),'/wpvibe/v1/site-info'=>array('GET')));
    $GLOBALS['wp_routes']['/wpvibe/v1/builder-login'][0]['show_in_index']=false;
    assert_true(WPVDMCP_Compatibility::route_exists('/wpvibe/v1/builder-login'),'internal SeedProd builder-login remains available for the trusted provider bridge');
    $list=WPVDMCP_Tools::execute('discover_rest_routes',array('filter'=>'wpvibe/v1'));
    $paths=array_column($list['routes']??array(),'route');
    assert_true(!in_array('/wpvibe/v1/builder-login',$paths,true),'hidden builder-login must not be advertised to generic agents');
    assert_true(in_array('/wpvibe/v1/site-info',$paths,true),'ordinary public route remains visible');
});
