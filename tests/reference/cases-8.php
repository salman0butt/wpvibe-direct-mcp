<?php
parity_test('load_skill schema accepts Direct skill and WPVibe name query resource forms',function(){
    $tool=parity_tool('load_skill');$props=$tool['inputSchema']['properties'];
    foreach(array('skill','name','query','resource') as $key)assert_true(isset($props->$key),'load_skill missing '.$key);
    assert_true(empty($tool['inputSchema']['required']??array()),'load_skill should allow no-arg catalog listing');
});

parity_test('load_skill with no arguments returns bounded catalog metadata',function(){
    $r=WPVDMCP_Parity::execute('load_skill',array());
    assert_true(!is_wp_error($r),'no-arg load_skill should list catalog');
    assert_true(isset($r['skills'])&&is_array($r['skills'])&&count($r['skills'])>10,'catalog metadata returned');
    foreach($r['skills'] as $row){assert_true(!isset($row['instructions']),'catalog must not inline full instructions');}
});

parity_test('load_skill name alias and Direct skill alias load the same builtin',function(){
    $a=WPVDMCP_Parity::execute('load_skill',array('name'=>'elementor'));
    $b=WPVDMCP_Parity::execute('load_skill',array('skill'=>'elementor'));
    assert_true(!is_wp_error($a)&&!is_wp_error($b),'aliases load builtin');
    assert_same('elementor',$a['skill']??null,'name alias slug');
    assert_same($a['instructions']??null,$b['instructions']??null,'aliases same instructions');
});

parity_test('load_skill query searches slug title and description without loading every body',function(){
    $r=WPVDMCP_Parity::execute('load_skill',array('query'=>'Elementor'));
    assert_true(!is_wp_error($r),'query search succeeds');
    assert_true(isset($r['matches'])&&is_array($r['matches'])&&count($r['matches'])>0,'query matches returned');
    $slugs=array_map(function($m){return $m['slug']??'';},$r['matches']);
    assert_true(in_array('elementor',$slugs,true),'elementor match present');
    foreach($r['matches'] as $row){assert_true(!isset($row['instructions']),'query results stay metadata-only');}
});

parity_test('load_skill resource reads bounded saved reference file by exact name',function(){
    $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION]=array(
        'brand-playbook'=>array(
            'slug'=>'brand-playbook','title'=>'Brand Playbook','description'=>'Brand rules',
            'min_wordpress'=>'6.0','min_wpvibe'=>'1.0.0','version'=>1,
            'instructions'=>'Use the saved brand reference before editing content.',
            'reference_files'=>array(
                array('name'=>'brand.md','mime_type'=>'text/markdown','content'=>'# Brand\nClear and practical.'),
                array('name'=>'voice.txt','mime_type'=>'text/plain','content'=>'Direct, concise, useful.'),
            ),
        ),
    );
    $r=WPVDMCP_Parity::execute('load_skill',array('name'=>'brand-playbook','resource'=>'brand.md'));
    unset($GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION]);
    assert_true(!is_wp_error($r),'saved resource loads');
    assert_same('brand-playbook',$r['skill']??null,'resource skill');
    assert_same('brand.md',$r['resource']['name']??null,'resource name');
    assert_same('text/markdown',$r['resource']['mime_type']??null,'resource mime');
    assert_true(strpos($r['resource']['content']??'','Clear')!==false,'resource content');
});

parity_test('load_skill resource fails explicitly when builtin has no named subresource',function(){
    $r=WPVDMCP_Parity::execute('load_skill',array('name'=>'elementor','resource'=>'missing.md'));
    assert_true(is_wp_error($r),'missing builtin resource should fail');
    assert_same('skill_resource_not_found',$r->get_error_code(),'missing resource error');
});

parity_test('load_skill query can select an exact slug and then read a saved resource',function(){
    $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION]=array(
        'ops-guide'=>array('slug'=>'ops-guide','title'=>'Ops Guide','description'=>'Operations notes','min_wordpress'=>'6.0','min_wpvibe'=>'1.0.0','version'=>1,'instructions'=>'Use this operations guide.','reference_files'=>array(array('name'=>'checklist.txt','mime_type'=>'text/plain','content'=>'verify, preview, publish'))),
    );
    $r=WPVDMCP_Parity::execute('load_skill',array('query'=>'ops-guide','resource'=>'checklist.txt'));
    unset($GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION]);
    assert_true(!is_wp_error($r),'exact query resource loads');
    assert_same('ops-guide',$r['skill']??null,'exact query selected skill');
    assert_same('checklist.txt',$r['resource']['name']??null,'query resource name');
});
