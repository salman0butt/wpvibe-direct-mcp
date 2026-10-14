<?php
parity_test('concurrent saved-skill creates cannot report success after losing a sibling mutation',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_override=array_key_exists('update_option_override',$GLOBALS);
    $override_before=$had_override?$GLOBALS['update_option_override']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $args_a=array(
            'slug'=>'race-a',
            'title'=>'Race A',
            'instructions'=>'A concurrent saved-skill mutation must never silently overwrite a successful sibling mutation.',
        );
        $args_b=array(
            'slug'=>'race-b',
            'title'=>'Race B',
            'instructions'=>'A concurrent saved-skill mutation must never silently overwrite a successful sibling mutation.',
        );

        $approval_a=WPVDMCP_Parity_Skills::execute('create_skill',$args_a);
        $approval_b=WPVDMCP_Parity_Skills::execute('create_skill',$args_b);
        assert_same('approval_required',$approval_a['status']??null,'first create requires approval');
        assert_same('approval_required',$approval_b['status']??null,'second create requires approval');
        assert_same(true,WPVDMCP_Approvals::approve($approval_a['approval_id'],1),'first create approval succeeds');
        assert_same(true,WPVDMCP_Approvals::approve($approval_b['approval_id'],1),'second create approval succeeds');

        $nested=null;
        $inside=false;
        $GLOBALS['update_option_override']=function($key,$value)use(&$nested,&$inside,$args_b,$approval_b){
            if($key!==WPVDMCP_Parity_Skills::OPTION||$inside)return null;
            $inside=true;
            $nested=WPVDMCP_Parity_Skills::execute('create_skill',array_merge($args_b,array('approval_id'=>$approval_b['approval_id'])));
            $inside=false;
            return null;
        };

        $outer=WPVDMCP_Parity_Skills::execute('create_skill',array_merge($args_a,array('approval_id'=>$approval_a['approval_id'])));
        $saved=WPVDMCP_Parity_Skills::saved();
        $outer_ok=!is_wp_error($outer)&&'created'===($outer['status']??null);
        $nested_ok=!is_wp_error($nested)&&'created'===($nested['status']??null);

        assert_true($outer_ok||$nested_ok,'at least one concurrent create must complete');
        if($outer_ok)assert_true(isset($saved['race-a']),'successful outer create must remain durable');
        if($nested_ok)assert_true(isset($saved['race-b']),'successful nested create must remain durable');
        if($outer_ok&&$nested_ok)assert_true(isset($saved['race-a'],$saved['race-b']),'two successful creates must leave both durable records');
    }finally{
        if($had_override)$GLOBALS['update_option_override']=$override_before;
        else unset($GLOBALS['update_option_override']);
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});
