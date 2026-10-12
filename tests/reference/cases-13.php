<?php
parity_test('reference manifest records the current Direct-only armored retry extension',function(){
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $extensions=$manifest['site_local_extensions']??array();
    assert_true(in_array('call_armored',$extensions,true),'call_armored must be recorded as a Direct-only local extension');
});
