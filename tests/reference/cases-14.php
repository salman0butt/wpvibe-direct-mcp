<?php
parity_test('WPCode snippets use a Direct reviewed-write safety bridge',function(){
    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    $tool=parity_tool('code_snippet');
    assert_true(is_array($tool),'code_snippet tool missing');
    assert_true(isset($tool['inputSchema']['properties']->approval_id),'code_snippet must expose one-time Direct approval_id for reviewed writes');
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
            public static function handle($request,$dormant=false){
                self::$calls[]=array('dormant'=>(bool)$dormant,'params'=>$request->get_params());
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
