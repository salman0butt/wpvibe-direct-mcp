<?php
if (!class_exists('WPVibe_CLI')) {
    class WPVibe_CLI {
        public static $approved_calls=array();
        public function run_approved($command,$confirm_write=false,$approved_state=null){
            self::$approved_calls[]=array($command,$confirm_write,$approved_state);
            return new WP_REST_Response(array(
                'command'=>'wp user delete',
                'tier'=>'write',
                'exit_code'=>0,
                'stdout'=>'Deleted user 7.',
                'stderr'=>'',
                'execution_time_ms'=>3,
            ),200);
        }
    }
}

parity_test('destructive WP-CLI uses one-time Direct approval and exact upstream snapshot',function(){
    set_test_routes(array('/wpvibe/v1/cli/run'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    WPVibe_CLI::$approved_calls=array();

    $dry_run=array(
        'command'=>'wp user delete 7 --yes',
        'users'=>array(array('id'=>7,'login'=>'delete-me')),
    );
    $GLOBALS['rest_dispatch_callback']=function($request) use ($dry_run){
        if ('/wpvibe/v1/cli/run'===$request->get_param('_route')) {
            return new WP_REST_Response(array(
                'code'=>'approval_required',
                'message'=>'Permanent user deletion needs approval.',
                'data'=>array(
                    'status'=>409,
                    'error_type'=>'approval_flow',
                    'retryable'=>true,
                    'operation'=>'user_delete',
                    'dry_run'=>$dry_run,
                    'command'=>'wp user delete',
                ),
            ),409);
        }
        return new WP_REST_Response(array('ok'=>true),200);
    };

    $call=function($args){
        return WPVDMCP_Parity::handles('run_wp_cli')
            ? WPVDMCP_Parity::execute('run_wp_cli',$args)
            : WPVDMCP_Tools::execute('run_wp_cli',$args);
    };

    $first=$call(array('command'=>'user delete 7 --yes'));
    assert_true(is_array($first),'destructive CLI must be converted from the upstream approval error into a Direct approval response');
    assert_same('approval_required',$first['status']??null,'Direct approval status missing');
    assert_same('user_delete',$first['operation']??null,'upstream operation must be preserved');
    assert_same($dry_run,$first['dry_run']??null,'exact upstream dry-run must be preserved');
    assert_true(!empty($first['approval_id']),'Direct approval ID missing');
    assert_same(0,count(WPVibe_CLI::$approved_calls),'native approved executor must not run before browser approval');

    $tool=parity_tool('run_wp_cli');
    $properties=(array)($tool['inputSchema']['properties']??array());
    assert_true(isset($properties['approval_id']),'run_wp_cli public schema must expose the one-time Direct approval ID');

    $approval_id=$first['approval_id'];
    assert_same(true,WPVDMCP_Approvals::approve($approval_id,1),'browser approval setup failed');

    $mismatch=$call(array('command'=>'user delete 8 --yes','approval_id'=>$approval_id));
    assert_true(is_wp_error($mismatch),'approval must be bound to the exact command and snapshot');
    assert_same('approval_payload_mismatch',$mismatch->get_error_code(),'changed command must not reuse approval');
    assert_same(0,count(WPVibe_CLI::$approved_calls),'payload mismatch must not reach native approved executor');

    $approved=$call(array('command'=>'user delete 7 --yes','approval_id'=>$approval_id));
    assert_true(is_array($approved),'approved destructive CLI must return normalized Direct MCP data');
    assert_same(200,$approved['status']??null,'approved native CLI status mismatch');
    assert_same('Deleted user 7.',$approved['data']['stdout']??null,'native approved CLI result was not normalized');
    assert_same(1,count(WPVibe_CLI::$approved_calls),'approved native executor must run exactly once');
    $native=WPVibe_CLI::$approved_calls[0];
    assert_same('user delete 7 --yes',$native[0],'native approved command mismatch');
    assert_same(false,$native[1],'confirm_write mismatch');
    $snapshot=json_decode($native[2],true);
    assert_same('user_delete',$snapshot['operation']??null,'approved operation snapshot mismatch');
    assert_same($dry_run,$snapshot['dry_run']??null,'approved dry-run snapshot mismatch');

    $replay=$call(array('command'=>'user delete 7 --yes','approval_id'=>$approval_id));
    assert_true(is_wp_error($replay),'consumed CLI approval must reject replay');
    assert_same(1,count(WPVibe_CLI::$approved_calls),'replay must not execute destructive command twice');

    $GLOBALS['wp_options']['wpvibe_bypass_approvals']=array('enabled'=>true);
    $bypassed=$call(array('command'=>'user delete 7 --yes'));
    assert_true(is_array($bypassed),'explicit WPVibe dangerous bypass should execute the currently classified snapshot locally');
    assert_same(2,count(WPVibe_CLI::$approved_calls),'dangerous bypass should skip Direct approval exactly once');

    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $GLOBALS['rest_dispatch_callback']=null;
});

parity_test('destructive WP-CLI fails closed when its approved snapshot cannot be persisted',function(){
    set_test_routes(array('/wpvibe/v1/cli/run'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    WPVibe_CLI::$approved_calls=array();
    $dry_run=array('command'=>'wp user delete 8 --yes','users'=>array(array('id'=>8,'login'=>'persist-me')));
    $GLOBALS['rest_dispatch_callback']=function($request) use ($dry_run){
        if('/wpvibe/v1/cli/run'===$request->get_param('_route')) return new WP_REST_Response(array(
            'code'=>'approval_required','message'=>'Permanent user deletion needs approval.',
            'data'=>array('status'=>409,'operation'=>'user_delete','dry_run'=>$dry_run,'command'=>'wp user delete'),
        ),409);
        return new WP_REST_Response(array('ok'=>true),200);
    };
    $GLOBALS['set_transient_override']=function($key,$value,$ttl){if(strpos($key,'wpvdmcp_cli_snapshot_')===0)return false;return null;};
    $result=WPVDMCP_Parity::execute('run_wp_cli',array('command'=>'user delete 8 --yes'));
    unset($GLOBALS['set_transient_override']);
    $GLOBALS['rest_dispatch_callback']=null;
    assert_true(is_wp_error($result),'missing durable CLI snapshot must not return an unusable approval');
    assert_same('approval_snapshot_persist_failed',$result->get_error_code(),'CLI snapshot persistence failure code');
    assert_same(0,count(WPVibe_CLI::$approved_calls),'snapshot persistence failure must not execute destructive CLI');
});
