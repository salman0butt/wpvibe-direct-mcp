<?php
parity_test('WPCode snippets use a Direct reviewed-write safety bridge',function(){
    $tool=parity_tool('code_snippet');
    assert_true(is_array($tool),'code_snippet tool missing');
    assert_true(isset($tool['inputSchema']['properties']->approval_id),'code_snippet must expose one-time Direct approval_id for reviewed writes');
    assert_true(WPVDMCP_Parity::handles('code_snippet'),'code_snippet must be handled by Direct safety logic instead of dispatching the Worker-only REST route');
});
