<?php
if(!class_exists('WPVDMCP_Test_SavedSkill_Advisory_DB',false)){
    final class WPVDMCP_Test_SavedSkill_Advisory_DB{
        public $grant_lock;
        public $lock_count=0;
        public $get_lock_calls=0;
        public $release_lock_calls=0;
        public function __construct($grant_lock){$this->grant_lock=$grant_lock;}
        public function prepare($query,$name){return str_replace('%s',"'".$name."'",$query);}
        public function get_var($query){
            if(strpos($query,'GET_LOCK')!==false){
                $this->get_lock_calls++;
                if(!$this->grant_lock)return '0';
                $this->lock_count++;
                return '1';
            }
            if(strpos($query,'RELEASE_LOCK')!==false){
                $this->release_lock_calls++;
                if($this->lock_count>0){$this->lock_count--;return '1';}
                return null;
            }
            return null;
        }
    }
}

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
        $db=new WPVDMCP_Test_SavedSkill_Advisory_DB(true);
        $GLOBALS['wpdb']=$db;

        $result=WPVDMCP_Parity_Skills::execute('create_skill',array_merge($args,array('approval_id'=>$approval['approval_id'])));

        assert_true(!is_wp_error($result),'orphaned mutation claim must not permanently block an approved saved-skill write');
        assert_same('created',$result['status']??null,'approved saved-skill write must complete after orphan recovery');
        $stored=WPVDMCP_Parity_Skills::saved();
        assert_true(isset($stored['orphaned-claim-skill']),'recovered saved skill must remain durable');
        assert_true(!array_key_exists(WPVDMCP_Parity_Skills::MUTATION_CLAIM,$GLOBALS['wp_options']),'recovered saved-skill mutation claim must be released');
        assert_same(1,$db->get_lock_calls,'orphan recovery must acquire the advisory lock once');
        assert_same(1,$db->release_lock_calls,'completed orphan recovery must release the advisory lock once');
        assert_same(0,$db->lock_count,'completed orphan recovery must leave no advisory lock held');
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
        $db=new WPVDMCP_Test_SavedSkill_Advisory_DB(false);
        $GLOBALS['wpdb']=$db;

        $result=WPVDMCP_Parity_Skills::execute('create_skill',array_merge($args,array('approval_id'=>$approval['approval_id'])));

        assert_true(is_wp_error($result),'live mutation owner must keep exclusivity');
        assert_same('skill_mutation_in_progress',$result->get_error_code(),'live owner contention must fail closed');
        assert_same('live-request',$GLOBALS['wp_options'][WPVDMCP_Parity_Skills::MUTATION_CLAIM]??null,'live owner claim must remain unchanged');
        $approval_status=WPVDMCP_Approvals::status($approval['approval_id']);
        assert_same('approved',$approval_status['status']??null,'failed contention must not consume approval');
        assert_same(1,$db->get_lock_calls,'live-owner contention must attempt the advisory lock once');
        assert_same(0,$db->release_lock_calls,'failed advisory lock acquisition must not release another owner lock');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_wpdb)$GLOBALS['wpdb']=$wpdb_before;
        else unset($GLOBALS['wpdb']);
    }
});

parity_test('saved-skill fallback locking never steals an existing owner without database proof',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_wpdb=array_key_exists('wpdb',$GLOBALS);
    $wpdb_before=$had_wpdb?$GLOBALS['wpdb']:null;
    try{
        $GLOBALS['wp_current_user']=1;
        unset($GLOBALS['wpdb']);
        $args=array(
            'slug'=>'fallback-claim-skill',
            'title'=>'Fallback owner',
            'instructions'=>'Fallback saved-skill locking must never steal an existing claim without database proof.',
        );
        $approval=WPVDMCP_Parity_Skills::execute('create_skill',$args);
        assert_same('approval_required',$approval['status']??null,'saved skill requires approval');
        assert_same(true,WPVDMCP_Approvals::approve($approval['approval_id'],1),'saved-skill approval succeeds');
        $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::MUTATION_CLAIM]='fallback-live-request';

        $result=WPVDMCP_Parity_Skills::execute('create_skill',array_merge($args,array('approval_id'=>$approval['approval_id'])));

        assert_true(is_wp_error($result),'fallback contention must fail closed');
        assert_same('skill_mutation_in_progress',$result->get_error_code(),'fallback contention must report an active mutation');
        assert_same('fallback-live-request',$GLOBALS['wp_options'][WPVDMCP_Parity_Skills::MUTATION_CLAIM]??null,'fallback locking must not steal the existing claim');
        $approval_status=WPVDMCP_Approvals::status($approval['approval_id']);
        assert_same('approved',$approval_status['status']??null,'fallback contention must not consume approval');
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
        $db=new WPVDMCP_Test_SavedSkill_Advisory_DB(true);
        $GLOBALS['wpdb']=$db;
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
        assert_same(1,$db->get_lock_calls,'same-session guard must reject nested mutation before recursive GET_LOCK');
        assert_same(1,$db->release_lock_calls,'outer owner must release its advisory lock exactly once');
        assert_same(0,$db->lock_count,'outer completion must leave no advisory lock held');
    }finally{
        if($had_override)$GLOBALS['update_option_override']=$override_before;
        else unset($GLOBALS['update_option_override']);
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
        if($had_wpdb)$GLOBALS['wpdb']=$wpdb_before;
        else unset($GLOBALS['wpdb']);
    }
});
