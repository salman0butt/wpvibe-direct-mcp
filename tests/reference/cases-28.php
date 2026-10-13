<?php
require_once dirname(__DIR__,2) . '/includes/class-wpvdmcp-mcp-apps.php';

parity_test('corrupt approval panel owner fails closed before owner comparison',function(){
    $payload=array('name'=>'demo/panel-owner-corrupt','input'=>array('value'=>4),'method'=>'POST');
    $approval=WPVDMCP_Approvals::request('run_ability',$payload,'Corrupt panel owner');
    $approval_id=$approval['approval_id']??'';
    assert_true($approval_id!=='','approval id returned');

    $panel=WPVDMCP_MCP_Apps::execute('show_approval_panel',array('approval_id'=>$approval_id));
    assert_true(!is_wp_error($panel),'panel token minted');
    $token=$panel['__mcp_meta']['wpvdmcpApprovalPanelToken']??'';
    assert_true($token!=='','panel token returned');

    $key='transient:wpvdmcp_ui_panel_'.hash('sha256',$approval_id);
    assert_true(isset($GLOBALS['wp_options'][$key]['value']),'panel record persisted');
    $GLOBALS['wp_options'][$key]['value']['owner_user_id']=(object)array('corrupt'=>true);

    $result=WPVDMCP_MCP_Apps::execute('approval_decide',array(
        'approval_id'=>$approval_id,
        'decision'=>'approve',
        'panel_token'=>$token,
    ));

    assert_true(is_wp_error($result),'corrupt panel owner must fail closed');
    assert_same('panel_token_invalid',$result->get_error_code(),'corrupt panel owner failure code');
    assert_true(!isset($GLOBALS['wp_options'][$key]),'corrupt panel record must be invalidated');
    $status=WPVDMCP_Approvals::status($approval_id);
    assert_same('pending',$status['status']??null,'corrupt panel owner must not approve the operation');
});