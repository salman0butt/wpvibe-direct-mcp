<?php
require_once dirname(__DIR__,2) . '/includes/class-wpvdmcp-mcp-apps.php';
parity_test('legacy panel owner corruption never grants bearer approval',function(){
    $before=$GLOBALS['wp_options'];
    try{
        $approval=WPVDMCP_Approvals::request('run_ability',array('name'=>'demo/panel-owner-corrupt','input'=>array('value'=>4),'method'=>'POST'),'Legacy panel owner');
        $id=$approval['approval_id'];
        set_transient('wpvdmcp_ui_panel_'.hash('sha256',$id),array('token_hash'=>hash('sha256','obsolete'),'approval_id'=>$id,'owner_user_id'=>(object)array('corrupt'=>true),'expires_at'=>time()+900),900);
        $result=WPVDMCP_MCP_Apps::execute('approval_decide',array('approval_id'=>$id,'decision'=>'approve','panel_token'=>'obsolete'));
        assert_true(is_wp_error($result),'obsolete bearer decision must fail');
        assert_same('approval_requires_browser',$result->get_error_code(),'only authenticated WordPress admin may approve');
        assert_same('pending',WPVDMCP_Approvals::status($id)['status']??null,'corrupt legacy owner cannot approve');
    }finally{$GLOBALS['wp_options']=$before;}
});
