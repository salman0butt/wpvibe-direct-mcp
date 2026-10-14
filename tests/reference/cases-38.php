<?php
parity_test('malformed saved-skill versions fail closed before update arithmetic',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $base=array(
            'title'=>'Version fixture',
            'description'=>'Persisted version fixture.',
            'min_wordpress'=>'6.0',
            'min_wpvibe'=>'1.0.0',
            'instructions'=>'A saved workflow with enough content for safe version-boundary testing.',
            'reference_files'=>array(),
        );
        $row=function($slug,$version,$include=true)use($base){
            $record=array_merge($base,array('slug'=>$slug));
            if($include)$record['version']=$version;
            return $record;
        };
        $GLOBALS['wp_options'][$key]=array(
            'bad-object'=>$row('bad-object',(object)array('unexpected'=>'shape')),
            'bad-array'=>$row('bad-array',array(1)),
            'bad-string'=>$row('bad-string','7'),
            'bad-negative'=>$row('bad-negative',-1),
            'bad-overflow'=>$row('bad-overflow',PHP_INT_MAX),
            'legacy-missing'=>$row('legacy-missing',null,false),
            'valid'=>$row('valid',7),
        );

        $saved=WPVDMCP_Parity_Skills::saved();
        foreach(array('bad-object','bad-array','bad-string','bad-negative','bad-overflow') as $slug){
            assert_true(!isset($saved[$slug]),'invalid persisted version must be filtered: '.$slug);
        }
        assert_true(isset($saved['legacy-missing']),'legacy saved skill without version must remain available');
        assert_true(isset($saved['valid']),'valid integer version must remain available');

        $result=WPVDMCP_Parity_Skills::execute('update_skill',array('slug'=>'bad-object','title'=>'Updated'));
        assert_true(is_wp_error($result),'corrupt saved-skill version must fail closed before update');
        assert_same('skill_not_found',$result->get_error_code(),'corrupt saved-skill version failure code');

        $legacy=WPVDMCP_Parity_Skills::execute('update_skill',array(
            'slug'=>'legacy-missing',
            'instructions'=>'Updated legacy workflow with discovery, execution, verification, and rollback guidance.',
        ));
        assert_same('approval_required',$legacy['status']??null,'legacy saved skill without version must remain updatable');
        assert_same(true,WPVDMCP_Approvals::approve($legacy['approval_id'],1),'legacy saved skill approval must succeed');
        $legacy_done=WPVDMCP_Parity_Skills::execute('update_skill',array(
            'slug'=>'legacy-missing',
            'instructions'=>'Updated legacy workflow with discovery, execution, verification, and rollback guidance.',
            'approval_id'=>$legacy['approval_id'],
        ));
        assert_same(1,$legacy_done['skill']['version']??null,'legacy saved skill update must start versioning at one');
        assert_true(isset(WPVDMCP_Parity_Skills::saved()['legacy-missing']),'updated legacy saved skill must remain discoverable');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});

parity_test('saved-skill version ceiling fails before approval and persistence',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    try{
        $record=array(
            'slug'=>'version-ceiling',
            'title'=>'Version ceiling',
            'description'=>'Persisted version ceiling fixture.',
            'min_wordpress'=>'6.0',
            'min_wpvibe'=>'1.0.0',
            'version'=>PHP_INT_MAX-1,
            'instructions'=>'A saved workflow at the highest version that can still be represented without overflow.',
            'reference_files'=>array(),
        );
        $GLOBALS['wp_options'][$key]=array('version-ceiling'=>$record);

        $result=WPVDMCP_Parity_Skills::execute('update_skill',array(
            'slug'=>'version-ceiling',
            'title'=>'Updated version ceiling',
        ));
        if(is_array($result)&&'approval_required'===($result['status']??null)){
            assert_same(true,WPVDMCP_Approvals::approve($result['approval_id'],1),'version ceiling approval setup must succeed');
            $result=WPVDMCP_Parity_Skills::execute('update_skill',array(
                'slug'=>'version-ceiling',
                'title'=>'Updated version ceiling',
                'approval_id'=>$result['approval_id'],
            ));
        }

        assert_same($record,$GLOBALS['wp_options'][$key]['version-ceiling']??null,'version ceiling update must not mutate durable state');
        assert_true(is_wp_error($result),'version ceiling update must fail closed');
        assert_same('skill_version_limit',$result->get_error_code(),'version ceiling failure code');
        assert_true(isset(WPVDMCP_Parity_Skills::saved()['version-ceiling']),'version ceiling record must remain visible after rejection');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
