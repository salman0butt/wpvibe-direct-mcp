<?php
parity_test('concurrent editable-field registrations cannot both succeed while dropping a sibling definition',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_override=array_key_exists('update_option_override',$GLOBALS);
    $override_before=$had_override?$GLOBALS['update_option_override']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $args_a=array(
            'post_type'=>'page',
            'key'=>'race_field_a',
            'config'=>array('type'=>'text','label'=>'Race A'),
        );
        $args_b=array(
            'post_type'=>'page',
            'key'=>'race_field_b',
            'config'=>array('type'=>'text','label'=>'Race B'),
        );

        $approval_a=WPVDMCP_Parity_Fields::execute('register_editable_field',$args_a);
        $approval_b=WPVDMCP_Parity_Fields::execute('register_editable_field',$args_b);
        assert_same('approval_required',$approval_a['status']??null,'first field requires approval');
        assert_same('approval_required',$approval_b['status']??null,'second field requires approval');
        assert_same(true,WPVDMCP_Approvals::approve($approval_a['approval_id'],1),'first field approval succeeds');
        assert_same(true,WPVDMCP_Approvals::approve($approval_b['approval_id'],1),'second field approval succeeds');

        $nested=null;
        $inside=false;
        $GLOBALS['update_option_override']=function($key,$value)use(&$nested,&$inside,$args_b,$approval_b){
            if($key!==WPVDMCP_Parity_Fields::OPTION||$inside)return null;
            $inside=true;
            $nested=WPVDMCP_Parity_Fields::execute('register_editable_field',array_merge($args_b,array('approval_id'=>$approval_b['approval_id'])));
            $inside=false;
            return null;
        };

        $outer=WPVDMCP_Parity_Fields::execute('register_editable_field',array_merge($args_a,array('approval_id'=>$approval_a['approval_id'])));
        $stored=get_option(WPVDMCP_Parity_Fields::OPTION,array());
        $fields=is_array($stored)&&isset($stored['fields'])&&is_array($stored['fields'])?$stored['fields']:array();
        $outer_ok=!is_wp_error($outer)&&true===($outer['persistent']??null);
        $nested_ok=!is_wp_error($nested)&&true===($nested['persistent']??null);

        assert_true($outer_ok||$nested_ok,'at least one concurrent field registration must complete');
        assert_true(is_wp_error($nested),'contending nested registration must fail closed');
        assert_same('editable_definition_mutation_in_progress',$nested->get_error_code(),'contending nested registration must lose on the shared mutation claim');
        $nested_approval=WPVDMCP_Approvals::status($approval_b['approval_id']);
        assert_same('approved',$nested_approval['status']??null,'losing contention must not consume its approval');
        if($outer_ok)assert_true(isset($fields['page:race_field_a']),'successful outer field must remain durable');
        if($nested_ok)assert_true(isset($fields['page:race_field_b']),'successful nested field must remain durable');
        if($outer_ok&&$nested_ok)assert_true(isset($fields['page:race_field_a'],$fields['page:race_field_b']),'two successful registrations must leave both durable definitions');
        assert_true(!array_key_exists(WPVDMCP_Parity_Fields::MUTATION_CLAIM,$GLOBALS['wp_options']),'mutation claim must be released after normal completion');
    }finally{
        if($had_override)$GLOBALS['update_option_override']=$override_before;
        else unset($GLOBALS['update_option_override']);
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});

parity_test('stale editable-definition mutation claim can be recovered without blocking approved writes forever',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    try{
        $GLOBALS['wp_current_user']=1;
        $args=array(
            'post_type'=>'page',
            'key'=>'stale_claim_recovery',
            'config'=>array('type'=>'text','label'=>'Recovered'),
        );
        $approval=WPVDMCP_Parity_Fields::execute('register_editable_field',$args);
        assert_same('approval_required',$approval['status']??null,'field requires approval');
        assert_same(true,WPVDMCP_Approvals::approve($approval['approval_id'],1),'field approval succeeds');

        $GLOBALS['wp_options'][WPVDMCP_Parity_Fields::MUTATION_CLAIM]=(time()-600).'|stale-owner';
        $result=WPVDMCP_Parity_Fields::execute('register_editable_field',array_merge($args,array('approval_id'=>$approval['approval_id'])));

        assert_true(!is_wp_error($result),'stale mutation claim must not permanently block an approved write');
        assert_same(true,$result['persistent']??null,'approved write must persist after stale claim recovery');
        $stored=get_option(WPVDMCP_Parity_Fields::OPTION,array());
        assert_true(isset($stored['fields']['page:stale_claim_recovery']),'recovered write must remain durable');
        assert_true(!array_key_exists(WPVDMCP_Parity_Fields::MUTATION_CLAIM,$GLOBALS['wp_options']),'recovered mutation claim must be released');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});
