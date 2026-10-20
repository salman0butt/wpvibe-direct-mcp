<?php
parity_test('WPCode snippets use a Direct reviewed-write safety bridge',function(){
    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    $tool=parity_tool('code_snippet');
    assert_true(is_array($tool),'code_snippet tool missing');
    assert_true(isset($tool['inputSchema']['properties']->approval_id),'code_snippet must expose one-time Direct approval_id for reviewed writes');
    if(!class_exists('WPVibe_Code_Snippet')){
        $enum=$tool['inputSchema']['properties']->action['enum']??array();
        assert_true(!in_array('delete',$enum,true),'retire actions must not be advertised without an upstream retire implementation');
    }
    assert_true(WPVDMCP_Parity::handles('code_snippet'),'code_snippet must be handled by Direct safety logic instead of dispatching the Worker-only REST route');
});

parity_test('WPCode reviewed writes preserve dormant classification approval binding and human-only activation',function(){
    if(!class_exists('WPCode_Snippet')){
        class WPCode_Snippet {}
    }
    if(!class_exists('WPVibe_Code_Snippet')){
        class WPVibe_Code_Snippet {
            public static $mode='review';
            public static $calls=array();
            public static function proof_subject($request){
                $action=$request->get_param('action');
                return in_array($action,array('deactivate','delete'),true) ? $action.':'.(int)$request->get_param('id') : (string)$request->get_param('code');
            }
            public static function handle($request,$dormant=false){
                self::$calls[]=array('dormant'=>(bool)$dormant,'params'=>$request->get_params());
                if('retire'===self::$mode){
                    $action=$request->get_param('action');
                    if('delete'===$action && $dormant){
                        return new WP_Error('snippet_review_required','Deleting a WPVibe-created snippet requires review.',array('status'=>409,'reason'=>'retire'));
                    }
                    return array('id'=>(int)$request->get_param('id'),'action'=>$action,'active'=>false,'dormant'=>(bool)$dormant);
                }
                if($dormant && 'review'===self::$mode){
                    return new WP_Error('snippet_review_required','Review this exact snippet write.',array('status'=>409,'reason'=>'not_disabled'));
                }
                return array('id'=>91,'active'=>false,'dormant'=>(bool)$dormant,'params'=>$request->get_params());
            }
        }
    }

    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    WPVibe_Code_Snippet::$mode='review';
    WPVibe_Code_Snippet::$calls=array();

    $args=array(
        'action'=>'update','id'=>7,'code'=>'<?php echo "review";','title'=>' Review me ',
        'code_type'=>'PHP Snippet','location'=>'Everywhere','insert_method'=>'Auto','active'=>true,
    );

    $GLOBALS['current_user_capabilities']['wpcode_edit_snippets']=false;
    $forbidden=WPVDMCP_Parity::execute('code_snippet',$args);
    assert_true(is_wp_error($forbidden),'missing WPCode capability must fail closed');
    assert_same('missing_capability',$forbidden->get_error_code(),'missing WPCode capability must use explicit safety error');
    assert_same(0,count(WPVibe_Code_Snippet::$calls),'capability denial must happen before the upstream handler');
    unset($GLOBALS['current_user_capabilities']['wpcode_edit_snippets']);

    $pending=WPVDMCP_Parity::execute('code_snippet',$args);
    assert_same('approval_required',$pending['status']??null,'review-required snippet write must pause for Direct approval');
    assert_same('not_disabled',$pending['reason']??null,'upstream review reason must be preserved');
    assert_same(1,count(WPVibe_Code_Snippet::$calls),'review classifier must execute exactly once before approval');
    assert_same(true,WPVibe_Code_Snippet::$calls[0]['dormant'],'first snippet execution must use upstream dormant semantics');
    assert_true(!array_key_exists('active',WPVibe_Code_Snippet::$calls[0]['params']),'model-provided activation flag must never reach upstream handler');
    assert_same('phpsnippet',WPVibe_Code_Snippet::$calls[0]['params']['code_type']??null,'REST-equivalent sanitizer must normalize code type');
    assert_same('everywhere',WPVibe_Code_Snippet::$calls[0]['params']['location']??null,'REST-equivalent sanitizer must normalize location');
    assert_same('auto',WPVibe_Code_Snippet::$calls[0]['params']['insert_method']??null,'REST-equivalent default/sanitizer must normalize insert method');

    assert_true(WPVDMCP_Approvals::approve($pending['approval_id'],1)===true,'snippet Direct approval accepted');
    $drift=$args;
    $drift['approval_id']=$pending['approval_id'];
    $drift['code']='<?php echo "changed";';
    $mismatch=WPVDMCP_Parity::execute('code_snippet',$drift);
    assert_true(is_wp_error($mismatch),'changed snippet payload must fail against the approved payload hash');
    assert_same('approval_payload_mismatch',$mismatch->get_error_code(),'snippet drift must fail with approval payload mismatch');
    assert_same(1,count(WPVibe_Code_Snippet::$calls),'payload mismatch must fail before approved execution');

    $approved=$args;
    $approved['approval_id']=$pending['approval_id'];
    $done=WPVDMCP_Parity::execute('code_snippet',$approved);
    assert_same(200,$done['status']??null,'approved reviewed snippet write should use upstream handler');
    assert_same(false,$done['data']['active']??null,'reviewed write must not activate a snippet');
    assert_same(false,$done['data']['dormant']??null,'approved reviewed write must use upstream approved semantics');
    assert_same(2,count(WPVibe_Code_Snippet::$calls),'approved write should invoke upstream handler exactly once after classification');
    assert_true(!array_key_exists('active',WPVibe_Code_Snippet::$calls[1]['params']),'approved path must not forward activation');

    $replay=WPVDMCP_Parity::execute('code_snippet',$approved);
    assert_true(is_wp_error($replay),'snippet approval must be single use');
    assert_same(2,count(WPVibe_Code_Snippet::$calls),'approval replay must fail before upstream execution');

    WPVibe_Code_Snippet::$mode='dormant';
    WPVibe_Code_Snippet::$calls=array();
    $dormant=WPVDMCP_Parity::execute('code_snippet',array(
        'action'=>'create','code'=>'body{}','title'=>'CSS','code_type'=>'css','location'=>'site_wide_header',
    ));
    assert_same(200,$dormant['status']??null,'qualifying dormant snippet write should execute without Direct approval');
    assert_same(true,$dormant['data']['dormant']??null,'qualifying snippet must stay on dormant upstream path');
    assert_same(1,count(WPVibe_Code_Snippet::$calls),'dormant success should not call the approved path');

    WPVibe_Code_Snippet::$mode='review';
    WPVibe_Code_Snippet::$calls=array();
    $GLOBALS['wp_options']['wpvibe_bypass_approvals']=array('enabled'=>true);
    $bypassed=WPVDMCP_Parity::execute('code_snippet',$args);
    assert_same(200,$bypassed['status']??null,'explicit site-owner approval bypass should preserve existing Direct behavior');
    assert_same(2,count(WPVibe_Code_Snippet::$calls),'bypass still requires upstream dormant classification before approved semantics');
    assert_same(true,WPVibe_Code_Snippet::$calls[0]['dormant'],'bypass must classify through dormant path first');
    assert_same(false,WPVibe_Code_Snippet::$calls[1]['dormant'],'bypass may use upstream approved handler only after review-required classification');
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
});

parity_test('WPCode 1.20.4+ retire actions are discoverable and never rewritten to create',function(){
    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    $definition=parity_tool('code_snippet');
    $properties=$definition['inputSchema']['properties']??null;
    $action=is_object($properties) ? ($properties->action??array()) : array();
    assert_true(in_array('deactivate',$action['enum']??array(),true),'deactivate action must be discoverable');
    assert_true(in_array('delete',$action['enum']??array(),true),'delete action must be discoverable');
    $required=$definition['inputSchema']['required']??array();
    foreach(array('code','title','code_type','location') as $name){
        assert_true(!in_array($name,$required,true),'retire actions must not require source fields: '.$name);
    }

    $GLOBALS['wp_current_user']=1;
    WPVibe_Code_Snippet::$mode='retire';
    WPVibe_Code_Snippet::$calls=array();
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    foreach(array(
        array('action'=>'delete','id'=>0),
        array('action'=>'deactivate','id'=>0),
        array('action'=>'trash','id'=>91),
        array('action'=>'delete','id'=>91,'code'=>'<?php echo "not part of retirement";'),
    ) as $bad){
        $reject=WPVDMCP_Parity::execute('code_snippet',$bad);
        assert_true(is_wp_error($reject),'invalid retire action or fields must fail before upstream handler');
        assert_same(0,count(WPVibe_Code_Snippet::$calls),'invalid retire payload cannot call upstream');
    }
    $off=WPVDMCP_Parity::execute('code_snippet',array('action'=>'deactivate','id'=>91));
    assert_same(200,$off['status']??null,'deactivate must complete without approval');
    assert_same('deactivate',$off['data']['action']??null,'deactivate action must reach upstream unchanged');
    assert_same(false,$off['data']['active']??null,'deactivate must report disabled');
    assert_same(1,count(WPVibe_Code_Snippet::$calls),'deactivation uses one dormant upstream invocation');
    assert_same('deactivate',WPVibe_Code_Snippet::$calls[0]['params']['action']??null,'deactivate must not fall back to create');
    assert_true(!isset(WPVibe_Code_Snippet::$calls[0]['params']['code']) || ''===WPVibe_Code_Snippet::$calls[0]['params']['code'],'retire must not require code');
    assert_same(true,WPVibe_Code_Snippet::$calls[0]['dormant'],'deactivation uses no-approval upstream path');

    $pending=WPVDMCP_Parity::execute('code_snippet',array('action'=>'delete','id'=>91));
    assert_same('approval_required',$pending['status']??null,'delete must require approval');
    assert_same('retire',$pending['reason']??null,'upstream review reason must survive');
    assert_same('delete',WPVibe_Code_Snippet::$calls[1]['params']['action']??null,'delete classifier must not fall back to create');
    assert_same(true,WPVibe_Code_Snippet::$calls[1]['dormant'],'delete must first classify in dormant mode');

    WPVDMCP_Approvals::approve($pending['approval_id'],1);
    $wrong=WPVDMCP_Parity::execute('code_snippet',array('action'=>'delete','id'=>92,'approval_id'=>$pending['approval_id']));
    assert_true(is_wp_error($wrong),'approved delete must be ID-bound');
    assert_same('approval_payload_mismatch',$wrong->get_error_code(),'delete ID drift rejected');
    assert_same(2,count(WPVibe_Code_Snippet::$calls),'ID drift must cause no new side effect');
    $done=WPVDMCP_Parity::execute('code_snippet',array('action'=>'delete','id'=>91,'approval_id'=>$pending['approval_id']));
    assert_same(200,$done['status']??null,'approved delete must complete');
    assert_same('delete',$done['data']['action']??null,'approved delete reaches upstream action');
    assert_same(false,WPVibe_Code_Snippet::$calls[2]['dormant'],'only approved path may delete');
    $again=WPVDMCP_Parity::execute('code_snippet',array('action'=>'delete','id'=>91,'approval_id'=>$pending['approval_id']));
    assert_true(is_wp_error($again),'delete approval must be one-time');
    assert_same(3,count(WPVibe_Code_Snippet::$calls),'replayed delete cannot execute');
    WPVibe_Code_Snippet::$mode='review';
});

parity_test('WPCode retirement fails closed if the upstream execution cache still contains the snippet',function(){
    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    $before=$GLOBALS['wp_options'];
    try {
        WPVibe_Code_Snippet::$mode='retire';
        WPVibe_Code_Snippet::$calls=array();
        $GLOBALS['wp_options']['wpcode_snippets']=array('global'=>array(array('id'=>91)));
        $result=WPVDMCP_Parity::execute('code_snippet',array('action'=>'deactivate','id'=>91));
        assert_true(is_wp_error($result),'native active:false is not sufficient when WPCode cache still executes the snippet');
        assert_same('snippet_cache_still_active',$result->get_error_code(),'cache conflict must be an actionable explicit error');
        assert_same(1,count(WPVibe_Code_Snippet::$calls),'do not attempt another deactivation side effect');
        unset($GLOBALS['wp_options']['wpcode_snippets']);
        $cleared=WPVDMCP_Parity::execute('code_snippet',array('action'=>'deactivate','id'=>91));
        assert_same(200,$cleared['status']??null,'cached snippet absent allows verified inactive response');
    } finally {
        $GLOBALS['wp_options']=$before;
        WPVibe_Code_Snippet::$mode='review';
    }
});
