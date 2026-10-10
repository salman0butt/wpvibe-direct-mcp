<?php

if (!class_exists('WPCode_Snippet')) {
    class WPCode_Snippet {}
}
if (!class_exists('WPVibe_Code_Snippet')) {
    class WPVibe_Code_Snippet {
        public static $calls=array();
        public static function handle($request,$dormant=false){
            self::$calls[]=array('dormant'=>$dormant,'code'=>$request->get_param('code'),'active'=>$request->get_param('active'));
            return array('id'=>55,'status'=>'draft','active'=>false,'dormant'=>$dormant,'code'=>$request->get_param('code'));
        }
    }
}

parity_test('dormant WPCode uses local native handler instead of hosted op-proof route', function () {
    set_test_routes(array('/wpvibe/v1/code-snippet/dormant'=>array('POST')));
    WPVibe_Code_Snippet::$calls=array();
    $GLOBALS['rest_dispatch_callback']=function($r){
        return new WP_Error('op_proof_required','Hosted worker proof required.',array('status'=>401));
    };
    $result=WPVDMCP_Parity::execute('code_snippet',array(
        'action'=>'create','title'=>'Safe diagnostic','code'=>'add_action("init", function () {});','code_type'=>'php','location'=>'everywhere'
    ));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(55,$result['data']['id']??null,'local handler result');
    assert_same(true,WPVibe_Code_Snippet::$calls[0]['dormant']??null,'dormant native mode');
    assert_same(false,$result['data']['active']??null,'snippet stays inactive');
    assert_true(empty(WPVibe_Code_Snippet::$calls[0]['active']),'activation was never requested');
});
