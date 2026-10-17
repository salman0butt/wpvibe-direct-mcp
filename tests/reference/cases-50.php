<?php
parity_test('orphaned saved-skill mutation claim cannot block approved writes forever',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_wpdb=array_key_exists('wpdb',$GLOBALS);
    $wpdb_before=$had_wpdb?$GLOBALS['wpdb']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $args=array(
            'slug'=>'orphaned-claim-skill',
            'title'=>'Orphan recovery',
            'instructions'=>'An orphaned saved-skill mutation claim must not block approved writes forever.',
        );
        $approval=WPVDMCP_Parity_Skills::execute('create_skill',$args);
        assert_same('approval_required',$approval['status']??null,'saved skill requires approval');
        assert_same(true,WPVDMCP_Approvals::approve($approval['approval_id'],1),'saved-skill approval succeeds');
        $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::MUTATION_CLAIM]='orphaned-request';
        $GLOBALS['wpdb']=new WPVDMCP_Test_Advisory_DB(true);

        $result=WPVDMCP_Parity_Skills::execute('create_skill',array_merge($args,array('approval_id'=>$approval['approval_id'])));

        assert_true(!is_wp_error($result),'orphaned mutation claim must not permanently block an approved saved-skill write');
        assert_same('created',$result['status']??null,'approved saved-skill write must complete after orphan recovery');
        $stored=WPVDMCP_Parity_Skills::saved();
        assert_true(isset($stored['orphaned-claim-skill']),'recovered saved skill must remain durable');
        assert_true(!array_key_exists(WPVDMCP_Parity_Skills::MUTATION_CLAIM,$GLOBALS['wp_options']),'recovered saved-skill mutation claim must be released');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_wpdb)$GLOBALS['wpdb']=$wpdb_before;
        else unset($GLOBALS['wpdb']);
    }
});

parity_test('live saved-skill mutation owner is never stolen while its database lock is held',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_wpdb=array_key_exists('wpdb',$GLOBALS);
    $wpdb_before=$had_wpdb?$GLOBALS['wpdb']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $args=array(
            'slug'=>'live-claim-skill',
            'title'=>'Live owner',
            'instructions'=>'A live saved-skill mutation owner must retain exclusive access.',
        );
        $approval=WPVDMCP_Parity_Skills::execute('create_skill',$args);
        assert_same('approval_required',$approval['status']??null,'saved skill requires approval');
        assert_same(true,WPVDMCP_Approvals::approve($approval['approval_id'],1),'saved-skill approval succeeds');
        $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::MUTATION_CLAIM]='live-request';
        $GLOBALS['wpdb']=new WPVDMCP_Test_Advisory_DB(false);

        $result=WPVDMCP_Parity_Skills::execute('create_skill',array_merge($args,array('approval_id'=>$approval['approval_id'])));

        assert_true(is_wp_error($result),'live mutation owner must keep exclusivity');
        assert_same('skill_mutation_in_progress',$result->get_error_code(),'live owner contention must fail closed');
        assert_same('live-request',$GLOBALS['wp_options'][WPVDMCP_Parity_Skills::MUTATION_CLAIM]??null,'live owner claim must remain unchanged');
        $approval_status=WPVDMCP_Approvals::status($approval['approval_id']);
        assert_same('approved',$approval_status['status']??null,'failed contention must not consume approval');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_wpdb)$GLOBALS['wpdb']=$wpdb_before;
        else unset($GLOBALS['wpdb']);
    }
});

parity_test('same database session cannot reenter saved-skill mutation while an owner is active',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_wpdb=array_key_exists('wpdb',$GLOBALS);
    $wpdb_before=$had_wpdb?$GLOBALS['wpdb']:null;
    $had_override=array_key_exists('update_option_override',$GLOBALS);
    $override_before=$had_override?$GLOBALS['update_option_override']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        $GLOBALS['wpdb']=new WPVDMCP_Test_Advisory_DB(true);
        $args_a=array(
            'slug'=>'same-session-a',
            'title'=>'Outer owner',
            'instructions'=>'The outer saved-skill mutation must retain ownership during same-session reentry.',
        );
        $args_b=array(
            'slug'=>'same-session-b',
            'title'=>'Nested contender',
            'instructions'=>'The nested saved-skill mutation must fail closed while the outer owner is active.',
        );
        $approval_a=WPVDMCP_Parity_Skills::execute('create_skill',$args_a);
        $approval_b=WPVDMCP_Parity_Skills::execute('create_skill',$args_b);
        assert_same(true,WPVDMCP_Approvals::approve($approval_a['approval_id'],1),'outer saved-skill approval succeeds');
        assert_same(true,WPVDMCP_Approvals::approve($approval_b['approval_id'],1),'nested saved-skill approval succeeds');
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

        assert_true(!is_wp_error($outer),'outer saved-skill mutation must complete');
        assert_same('created',$outer['status']??null,'outer saved-skill mutation must retain ownership');
        assert_true(is_wp_error($nested),'same-session nested saved-skill mutation must fail closed');
        assert_same('skill_mutation_in_progress',$nested->get_error_code(),'same-session contender must not steal the live claim');
        $stored=WPVDMCP_Parity_Skills::saved();
        assert_true(isset($stored['same-session-a']),'outer saved skill must remain durable');
        assert_true(!isset($stored['same-session-b']),'nested saved skill must not be persisted');
        $nested_approval=WPVDMCP_Approvals::status($approval_b['approval_id']);
        assert_same('approved',$nested_approval['status']??null,'failed nested contention must not consume approval');
        assert_true(!array_key_exists(WPVDMCP_Parity_Skills::MUTATION_CLAIM,$GLOBALS['wp_options']),'outer completion must release the saved-skill claim');
    }finally{
        if($had_override)$GLOBALS['update_option_override']=$override_before;
        else unset($GLOBALS['update_option_override']);
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_wpdb)$GLOBALS['wpdb']=$wpdb_before;
        else unset($GLOBALS['wpdb']);
    }
});
