<?php
parity_test('malformed saved-skill slug cannot break tools discovery',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $GLOBALS['wp_options'][$key]=array(
            'broken'=>array(
                'slug'=>(object)array('unexpected'=>'shape'),
                'title'=>'Broken',
                'description'=>'Corrupt persisted slug.',
                'min_wordpress'=>'6.0',
                'min_wpvibe'=>'1.0.0',
                'version'=>1,
                'instructions'=>'A valid saved workflow body.',
                'reference_files'=>array(),
            ),
            'valid'=>array(
                'slug'=>'valid',
                'title'=>'Valid',
                'description'=>'Valid saved skill.',
                'min_wordpress'=>'6.0',
                'min_wpvibe'=>'1.0.0',
                'version'=>1,
                'instructions'=>'A valid saved workflow with discovery, execution, verification, and rollback guidance.',
                'reference_files'=>array(),
            ),
        );

        $response=WPVDMCP_Server::instance()->handle(test_request(array(
            'jsonrpc'=>'2.0',
            'id'=>'saved-skill-core-corrupt',
            'method'=>'tools/list',
            'params'=>array(),
        )));
        $tools=$response->get_data()['result']['tools']??array();
        $names=array_map(function($tool){return $tool['name']??'';},$tools);
        assert_true(in_array('load_skill',$names,true),'normal tools discovery must survive corrupt saved-skill metadata');

        $saved=WPVDMCP_Parity_Skills::saved();
        assert_true(!isset($saved['broken']),'corrupt saved-skill slug must be filtered');
        assert_true(isset($saved['valid']),'valid saved-skill sibling must remain available');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
