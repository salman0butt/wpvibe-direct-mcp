<?php
parity_test('approval consume verifies durable consumed readback before allowing execution', function () {
    $previous_options = $GLOBALS['wp_options'];
    try {
        $payload = array('name'=>'demo/false-positive-persist','input'=>array('value'=>12),'method'=>'POST');
        $approval = WPVDMCP_Approvals::request('run_ability', $payload, 'False-positive persistence test');
        $approval_id = $approval['approval_id'] ?? '';
        assert_true($approval_id !== '', 'approval id returned');
        assert_true(WPVDMCP_Approvals::approve($approval_id, 1) === true, 'approval accepted');

        $GLOBALS['set_transient_override'] = function ($key, $value, $ttl) use ($approval_id) {
            if ($key === 'wpvdmcp_approval_' . $approval_id && is_array($value) && ($value['status'] ?? '') === 'consumed') {
                return true; // Report success without changing durable state.
            }
            return null;
        };
        $result = WPVDMCP_Approvals::consume($approval_id, 'run_ability', $payload);
        unset($GLOBALS['set_transient_override']);

        assert_true(is_wp_error($result), 'false-positive consumed write must fail closed');
        assert_same('approval_persist_failed', $result->get_error_code(), 'false-positive consumed write error code');
        $status = WPVDMCP_Approvals::status($approval_id);
        assert_same('approved', $status['status'] ?? null, 'false-positive write must leave durable receipt approved');

        $claim_key = 'wpvdmcp_approval_claim_' . hash('sha256', $approval_id);
        assert_true(null !== get_option($claim_key, null), 'uncertain consumed write must retain its claim');
        $replay = WPVDMCP_Approvals::consume($approval_id, 'run_ability', $payload);
        assert_true(is_wp_error($replay), 'retained claim must block replay after uncertain persistence');
        assert_same('approval_in_progress', $replay->get_error_code(), 'replay must stay locked after uncertain persistence');
    } finally {
        unset($GLOBALS['set_transient_override']);
        $GLOBALS['wp_options'] = $previous_options;
    }
});
