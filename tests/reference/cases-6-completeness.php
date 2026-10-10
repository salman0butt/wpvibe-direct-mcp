<?php

parity_test('runtime parity manifest records worker replacements and complete control-plane helpers', function () {
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $internal=$manifest['internal_not_tools']??array();
    foreach(array(
        '/wpvibe/v1/classic-theme-safety',
        '/wpvibe/v1/draft-theme/compile-sources',
        '/wpvibe/v1/cli/run-approved',
        '/wpvibe/v1/op-proof/check',
        '/wpvibe/v1/code-snippet',
        '/wpvibe/v1/builder-login',
        '/wpvibe/v1/detached/run',
        '/wpvibe/v1/self-update/run'
    ) as $route){
        assert_true(in_array($route,$internal,true),'internal route classification '.$route);
    }
    $replacements=$manifest['direct_worker_replacements']??array();
    foreach(array('rest_api_write','run_wp_cli','code_snippet') as $name){
        assert_true(isset($replacements[$name]),'worker replacement missing '.$name);
    }
    assert_same('approval_gated',$replacements['rest_api_write']['mode']??null,'REST write mode');
    assert_same('native_run_approved',$replacements['run_wp_cli']['mode']??null,'CLI approved execution mode');
    assert_same('native_dormant_handler',$replacements['code_snippet']['mode']??null,'WPCode mode');

    $extensions=$manifest['site_local_extensions']??array();
    assert_true(in_array('call_armored',$extensions,true),'armored retry extension recorded');
    assert_true(isset($manifest['elementor']) && is_array($manifest['elementor']),'Elementor contract recorded');
    assert_same('abilities_first',$manifest['elementor']['strategy']??null,'Elementor abilities-first strategy');
    assert_same('runtime_discovery',$manifest['wp_cli']['command_inventory']??null,'WP-CLI runtime inventory is authoritative');
});

parity_test('historical release workflow cannot auto-run on future main parity builds', function () {
    $path=dirname(__DIR__,2).'/.github/workflows/release.yml';
    $yaml=file_get_contents($path);
    assert_true(is_string($yaml)&&$yaml!=='','release workflow readable');
    assert_true(strpos($yaml,'workflow_dispatch')!==false,'release is explicitly dispatchable');
    assert_true(strpos($yaml,'workflow_run:')===false,'historical publisher no longer auto-runs after main CI');
});

parity_test('Elementor guidance covers Atomic prerequisites whole settings and live verification', function () {
    $skill=file_get_contents(dirname(__DIR__,2).'/skills/elementor.md');
    foreach(array('discover_abilities','Atomic Editor','replaces the complete settings object','page template','global variables','preview','live','cache') as $needle){
        assert_true(stripos($skill,$needle)!==false,'Elementor guidance missing '.$needle);
    }
});
