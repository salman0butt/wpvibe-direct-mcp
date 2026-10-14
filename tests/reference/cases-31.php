<?php
parity_test('object saved-skill instructions fail closed before update coercion',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $GLOBALS['wp_options'][$key]=array(
            'broken'=>array(
                'slug'=>'broken',
                'title'=>'Broken',
                'description'=>'Corrupt persisted instructions.',
                'instructions'=>(object)array('unexpected'=>'shape'),
                'version'=>1,
                'reference_files'=>array(),
            ),
            'valid'=>array(
                'slug'=>'valid',
                'title'=>'Valid',
                'description'=>'Valid saved skill.',
                'instructions'=>'A valid saved workflow with discovery, execution, verification, and rollback guidance.',
                'version'=>1,
                'reference_files'=>array(),
            ),
        );

        $result=WPVDMCP_Parity_Skills::execute('update_skill',array('slug'=>'broken','title'=>'Updated'));
        assert_true(is_wp_error($result),'corrupt saved-skill instructions must fail closed');
        assert_same('skill_not_found',$result->get_error_code(),'corrupt saved-skill instructions failure code');

        $loaded=WPVDMCP_Parity_Skills::load('valid');
        assert_true(is_array($loaded),'valid saved-skill sibling must remain loadable');
        assert_same('A valid saved workflow with discovery, execution, verification, and rollback guidance.',$loaded['instructions'],'valid saved-skill sibling instructions');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
