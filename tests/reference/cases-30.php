<?php
parity_test('malformed saved-skill reference records cannot break metadata discovery',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $GLOBALS['wp_options'][$key]=array(
            'broken'=>array(
                'slug'=>'broken',
                'title'=>'Broken',
                'instructions'=>'This record is intentionally corrupted below.',
                'version'=>1,
                'reference_files'=>array('legacy-corrupt-reference'),
            ),
            'valid'=>array(
                'slug'=>'valid',
                'title'=>'Valid',
                'instructions'=>'A valid saved workflow with discovery, execution, verification, and rollback guidance.',
                'version'=>1,
                'reference_files'=>array(array('name'=>'note.txt','mime_type'=>'text/plain','content'=>'safe reference')),
            ),
        );
        $metadata=WPVDMCP_Parity_Skills::metadata();
        $slugs=array_map(function($item){return is_array($item)?($item['slug']??''):'';},$metadata);
        assert_true(in_array('valid',$slugs,true),'valid saved-skill sibling must remain discoverable');
        assert_true(!in_array('broken',$slugs,true),'saved skill with malformed reference record must be ignored');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
