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
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
