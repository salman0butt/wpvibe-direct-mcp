<?php
parity_test('reference manifest records the current Direct-only armored retry extension',function(){
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $extensions=$manifest['site_local_extensions']??array();
    assert_true(in_array('call_armored',$extensions,true),'call_armored must be recorded as a Direct-only local extension');
});
parity_test('release workflow verifies the focused Abilities JSON regression before and after packaging',function(){
    $workflow=(string)file_get_contents(dirname(__DIR__,2).'/.github/workflows/release.yml');
    assert_same(2,substr_count($workflow,'php tests/abilities-json-body-run.php'),'release workflow must run the focused Abilities request-body regression on source and extracted package');
});
