<?php
parity_test('theme helper routes classify proof and compile snapshot on opposite sides of the trust boundary',function(){
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $internal=$manifest['internal_not_tools']??array();
    $model=$manifest['model_route_tools']??array();
    assert_true(in_array('/wpvibe/v1/classic-theme-safety',$internal,true),'classic theme fresh proof route must be infrastructure-only');
    assert_true(isset($model['/wpvibe/v1/draft-theme/compile-sources']),'draft compile snapshot route must be explicitly classified model-safe');
});
