<?php
parity_test('persisted saved skills cannot shadow immutable skill namespaces',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $trusted_core=WPVDMCP_Skills::load('elementor');
        $trusted_extra=WPVDMCP_Parity_Skills::load('kadence');
        assert_true(!is_wp_error($trusted_core),'elementor builtin fixture must load');
        assert_true(!is_wp_error($trusted_extra),'kadence extra fixture must load');

        $GLOBALS['wp_options'][$key]=array(
            'elementor'=>array(
                'slug'=>'elementor',
                'title'=>'Shadow Elementor',
                'description'=>'Persisted core shadow.',
                'min_wordpress'=>'6.0',
                'min_wpvibe'=>'1.0.0',
                'version'=>1,
                'instructions'=>'PERSISTED CORE SHADOW CONTENT',
                'reference_files'=>array(),
            ),
            'kadence'=>array(
                'slug'=>'kadence',
                'title'=>'Shadow Kadence',
                'description'=>'Persisted extra-catalog shadow.',
                'min_wordpress'=>'6.0',
                'min_wpvibe'=>'1.0.0',
                'version'=>1,
                'instructions'=>'PERSISTED EXTRA SHADOW CONTENT',
                'reference_files'=>array(),
            ),
            'custom-ok'=>array(
                'slug'=>'custom-ok',
                'title'=>'Custom OK',
                'description'=>'Legitimate local skill.',
                'min_wordpress'=>'6.0',
                'min_wpvibe'=>'1.0.0',
                'version'=>1,
                'instructions'=>'LEGITIMATE CUSTOM SAVED CONTENT',
                'reference_files'=>array(),
            ),
        );

        $call=function($name){
            return WPVDMCP_Server::instance()->handle(test_request(array(
                'jsonrpc'=>'2.0',
                'id'=>'saved-skill-reserved-'.$name,
                'method'=>'tools/call',
                'params'=>array('name'=>'load_skill','arguments'=>array('name'=>$name)),
            )))->get_data()['result']['structuredContent']??array();
        };

        $core=$call('elementor');
        assert_same($trusted_core['instructions']??null,$core['instructions']??null,'persisted saved skill must not shadow core builtin instructions');
        $extra=$call('kadence');
        assert_same($trusted_extra['instructions']??null,$extra['instructions']??null,'persisted saved skill must not shadow extra-catalog instructions');
        $custom=$call('custom-ok');
        assert_same('LEGITIMATE CUSTOM SAVED CONTENT',$custom['instructions']??null,'unrelated valid saved skill must remain loadable');

        $saved=WPVDMCP_Parity_Skills::saved();
        assert_true(!isset($saved['elementor']),'core builtin collision must be filtered from saved state');
        assert_true(!isset($saved['kadence']),'extra-catalog collision must be filtered from saved state');
        assert_true(isset($saved['custom-ok']),'valid custom sibling must remain available');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
