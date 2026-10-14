<?php
parity_test('malformed saved-skill description cannot break query discovery',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $GLOBALS['wp_options'][$key]=array(
            'broken'=>array(
                'slug'=>'broken',
                'title'=>'Broken',
                'description'=>(object)array('unexpected'=>'shape'),
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
            'id'=>'saved-skill-description-corrupt',
            'method'=>'tools/call',
            'params'=>array(
                'name'=>'load_skill',
                'arguments'=>array('query'=>'valid'),
            ),
        )));
        $structured=$response->get_data()['result']['structuredContent']??array();
        $matches=$structured['matches']??array();
        $slugs=array_map(function($row){return $row['slug']??'';},$matches);
        assert_true(in_array('valid',$slugs,true),'valid saved-skill sibling must remain queryable');

        $saved=WPVDMCP_Parity_Skills::saved();
        assert_true(!isset($saved['broken']),'corrupt saved-skill description must be filtered');
        assert_true(isset($saved['valid']),'valid saved-skill sibling must remain available');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
