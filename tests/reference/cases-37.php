<?php
parity_test('saved-skill option key stays bound to persisted slug identity',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $GLOBALS['wp_options'][$key]=array(
            'custom-route'=>array(
                'slug'=>'different-identity',
                'title'=>'Mismatched',
                'description'=>'Corrupt persisted identity.',
                'min_wordpress'=>'6.0',
                'min_wpvibe'=>'1.0.0',
                'version'=>1,
                'instructions'=>'MISMATCHED SAVED BODY',
                'reference_files'=>array(),
            ),
            'custom-ok'=>array(
                'slug'=>'custom-ok',
                'title'=>'Custom OK',
                'description'=>'Valid saved sibling.',
                'min_wordpress'=>'6.0',
                'min_wpvibe'=>'1.0.0',
                'version'=>1,
                'instructions'=>'VALID CUSTOM SAVED BODY',
                'reference_files'=>array(),
            ),
        );

        $saved=WPVDMCP_Parity_Skills::saved();
        assert_true(!isset($saved['custom-route']),'mismatched saved-skill key and slug must be filtered');
        assert_true(isset($saved['custom-ok']),'valid saved-skill sibling must remain available');

        $valid=WPVDMCP_Server::instance()->handle(test_request(array(
            'jsonrpc'=>'2.0','id'=>'saved-skill-identity-valid','method'=>'tools/call',
            'params'=>array('name'=>'load_skill','arguments'=>array('name'=>'custom-ok')),
        )))->get_data()['result']['structuredContent']??array();
        assert_same('VALID CUSTOM SAVED BODY',$valid['instructions']??null,'valid saved-skill sibling must remain loadable');

        $query=WPVDMCP_Server::instance()->handle(test_request(array(
            'jsonrpc'=>'2.0','id'=>'saved-skill-identity-query','method'=>'tools/call',
            'params'=>array('name'=>'load_skill','arguments'=>array('query'=>'different-identity')),
        )))->get_data()['result']['structuredContent']??array();
        assert_same(array(),$query['matches']??array(),'mismatched persisted identity must not appear in discovery');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
