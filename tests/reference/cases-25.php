<?php
parity_test('approval review never mints a token or changes another approval',function(){
    $options_before=$GLOBALS['wp_options'];
    try{
        $first=WPVDMCP_Approvals::request('delete_user',array('user_id'=>201),'Delete user #201');
        $second=WPVDMCP_Approvals::request('delete_user',array('user_id'=>202),'Delete user #202');
        $first_id=$first['approval_id'];$second_id=$second['approval_id'];
        $panel=WPVDMCP_MCP_Apps::execute('show_approval_panel',array('approval_id'=>$first_id));
        assert_true(!is_wp_error($panel),'approval review returned');
        assert_same($first_id,$panel['approval_id']??null,'review stays bound to requested approval');
        assert_true(!empty($panel['approval_url']),'review links to authenticated WordPress admin');
        assert_true(!isset($panel['__mcp_meta']),'review must not expose a bearer-usable token');
        assert_true(!isset($GLOBALS['wp_options']['transient:wpvdmcp_ui_panel_'.hash('sha256',$first_id)]),'review must not persist a bearer-usable token');
        assert_same('pending',WPVDMCP_Approvals::status($second_id)['status']??null,'unrelated approval remains pending');
    }finally{$GLOBALS['wp_options']=$options_before;}
});
