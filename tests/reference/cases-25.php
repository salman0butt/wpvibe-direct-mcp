<?php
parity_test('approval panel token stays bound to its persisted approval id',function(){
    $first=WPVDMCP_Approvals::request('delete_user',array('user_id'=>201),'Delete user #201');
    $second=WPVDMCP_Approvals::request('delete_user',array('user_id'=>202),'Delete user #202');
    $first_id=$first['approval_id']??'';
    $second_id=$second['approval_id']??'';
    assert_true($first_id!==''&&$second_id!=='','approval ids returned');

    $first_panel_key='transient:wpvdmcp_ui_panel_'.hash('sha256',$first_id);
    $second_panel_key='transient:wpvdmcp_ui_panel_'.hash('sha256',$second_id);
    $first_approval_key='transient:wpvdmcp_approval_'.$first_id;
    $second_approval_key='transient:wpvdmcp_approval_'.$second_id;
    try{
        $panel=WPVDMCP_MCP_Apps::execute('show_approval_panel',array('approval_id'=>$first_id));
        assert_true(!is_wp_error($panel),'source approval panel created');
        $token=$panel['__mcp_meta']['wpvdmcpApprovalPanelToken']??'';
        assert_true(is_string($token)&&strlen($token)>20,'source panel token returned');
        $stored=$GLOBALS['wp_options'][$first_panel_key]??null;
        assert_true(is_array($stored)&&is_array($stored['value']??null),'source panel record persisted');
        assert_same($first_id,$stored['value']['approval_id']??null,'source panel record bound to source approval');

        // Simulate corrupted/transplanted persisted panel state. The storage key
        // points at the second approval while the record remains bound to the first.
        $GLOBALS['wp_options'][$second_panel_key]=$stored;

        $result=WPVDMCP_MCP_Apps::execute('approval_decide',array(
            'approval_id'=>$second_id,
            'decision'=>'approve',
            'panel_token'=>$token,
        ));
        assert_true(is_wp_error($result),'transplanted panel token must fail closed');
        assert_same('panel_token_invalid',$result->get_error_code(),'transplanted panel token failure code');
        $second_status=WPVDMCP_Approvals::status($second_id);
        assert_same('pending',$second_status['status']??null,'transplanted panel token must not approve another receipt');
        assert_true(!array_key_exists($second_panel_key,$GLOBALS['wp_options']),'mismatched panel record must be invalidated');
    }finally{
        unset(
            $GLOBALS['wp_options'][$first_panel_key],
            $GLOBALS['wp_options'][$second_panel_key],
            $GLOBALS['wp_options'][$first_approval_key],
            $GLOBALS['wp_options'][$second_approval_key]
        );
    }
});