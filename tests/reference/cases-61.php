<?php
/** Production-like approval persistence and credential lifetime regressions. */
parity_test('approval creation never trusts an acknowledged but missing persisted receipt', function () {
    $before = $GLOBALS['wp_options'];
    $had_override = array_key_exists('set_transient_override', $GLOBALS);
    $prior_override = $had_override ? $GLOBALS['set_transient_override'] : null;
    try {
        $GLOBALS['set_transient_override'] = function ($key, $record) {
            return 0 === strpos((string) $key, 'wpvdmcp_approval_') && is_array($record) && ($record['status'] ?? '') === 'pending' ? true : null;
        };
        $result = WPVDMCP_Approvals::request('run_ability', array('name'=>'demo/durable-request','method'=>'POST'), 'Verify persisted request');
        assert_true(is_wp_error($result), 'unpersisted request cannot return an approval URL or usable ID');
        assert_same('approval_persist_failed', $result->get_error_code(), 'unpersisted request must have explicit persistence error');
    } finally {
        $GLOBALS['wp_options'] = $before;
        if ($had_override) $GLOBALS['set_transient_override'] = $prior_override; else unset($GLOBALS['set_transient_override']);
    }
});

parity_test('approval decisions verify actual stored states before reporting success', function () {
    $before = $GLOBALS['wp_options'];
    $had_override = array_key_exists('set_transient_override', $GLOBALS);
    $prior_override = $had_override ? $GLOBALS['set_transient_override'] : null;
    try {
        $payload = array('name'=>'demo/durable-decision','method'=>'POST');
        $requested = WPVDMCP_Approvals::request('run_ability', $payload, 'Verify persisted decision');
        $id = $requested['approval_id'] ?? '';
        assert_true($id !== '', 'valid approval request exists');
        $GLOBALS['set_transient_override'] = function ($key, $record) use ($id) {
            return $key === 'wpvdmcp_approval_' . $id && is_array($record) && in_array($record['status'] ?? '', array('approved','rejected'), true) ? true : null;
        };
        $approve = WPVDMCP_Approvals::approve($id, 1);
        assert_true(is_wp_error($approve), 'unpersisted approved decision cannot report success');
        assert_same('approval_persist_failed', $approve->get_error_code(), 'approved decision persistence failure');
        assert_same('pending', WPVDMCP_Approvals::status($id)['status'] ?? null, 'persisted receipt stays pending after failed approval');
        $locked = WPVDMCP_Approvals::reject($id, 1);
        assert_same('approval_in_progress', $locked->get_error_code(), 'uncertain approved-state persistence keeps the claim and blocks competing rejection');
        unset($GLOBALS['set_transient_override']);
        $other = WPVDMCP_Approvals::request('run_ability', $payload, 'Separate rejection persistence check');
        $rejection_id = $other['approval_id'] ?? '';
        assert_true($rejection_id !== '', 'fresh rejection receipt exists');
        $GLOBALS['set_transient_override'] = function ($key, $record) use ($rejection_id) {
            return $key === 'wpvdmcp_approval_' . $rejection_id && is_array($record) && ($record['status'] ?? '') === 'rejected' ? true : null;
        };
        $reject = WPVDMCP_Approvals::reject($rejection_id, 1);
        assert_true(is_wp_error($reject), 'unpersisted rejection cannot report success');
        assert_same('approval_persist_failed', $reject->get_error_code(), 'rejected decision persistence failure');
        assert_same('pending', WPVDMCP_Approvals::status($rejection_id)['status'] ?? null, 'persisted receipt stays pending after failed rejection');
        assert_same('approval_in_progress', WPVDMCP_Approvals::approve($rejection_id, 1)->get_error_code(), 'uncertain rejection retains claim against concurrent approval');
    } finally {
        $GLOBALS['wp_options'] = $before;
        if ($had_override) $GLOBALS['set_transient_override'] = $prior_override; else unset($GLOBALS['set_transient_override']);
    }
});

parity_test('token rotation invalidates old approvals even with the same administrator', function () {
    $before = $GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = hash('sha256', 'old-fake-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id'] = 1;
        $payload = array('name'=>'demo/old-credential','method'=>'DELETE','input'=>array('id'=>53));
        $requested = WPVDMCP_Approvals::request('run_ability', $payload, 'Old token approval');
        $id = $requested['approval_id'] ?? '';
        assert_true($id !== '', 'approval from old credential created');
        assert_true(WPVDMCP_Approvals::approve($id, 1) === true, 'administrator approved under old credential');
        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = hash('sha256', 'new-fake-token');
        $replay = WPVDMCP_Approvals::consume($id, 'run_ability', $payload);
        assert_true(is_wp_error($replay), 'new bearer cannot redeem a one-time approval granted under a revoked token');
        assert_same('approval_credential_changed', $replay->get_error_code(), 'rotation invalidates old credential approval explicitly');
        assert_same('approval_credential_changed', WPVDMCP_Approvals::status($id)->get_error_code(), 'rotated credential cannot present old approval as usable');
        $new_approval = WPVDMCP_Approvals::request('run_ability', $payload, 'New token approval');
        assert_true(!is_wp_error($new_approval), 'new credential can request its own fresh approval');
        assert_true(WPVDMCP_Approvals::approve($new_approval['approval_id'], 1) === true, 'new approval can be approved');
        assert_true(WPVDMCP_Approvals::consume($new_approval['approval_id'], 'run_ability', $payload) === true, 'new approval works with current bearer');
    } finally {
        $GLOBALS['wp_options'] = $before;
    }
});

parity_test('pre-binding legacy approval receipts fail closed after an upgrade', function () {
    $before = $GLOBALS['wp_options'];
    try {
        $payload = array('name'=>'demo/legacy-unbound','method'=>'DELETE','input'=>array('id'=>52));
        $created = WPVDMCP_Approvals::request('run_ability', $payload, 'Legacy approval upgrade');
        $id = $created['approval_id'] ?? '';
        assert_true($id !== '', 'test receipt created');
        unset($GLOBALS['wp_options']['transient:wpvdmcp_approval_' . $id]['value']['token_hash_fingerprint']);
        $result = WPVDMCP_Approvals::consume($id, 'run_ability', $payload);
        assert_true(is_wp_error($result), 'credential-unbound legacy approval must not be executable after upgrade');
        assert_same('approval_corrupt', $result->get_error_code(), 'missing fingerprint invalidates stale approval');
    } finally {
        $GLOBALS['wp_options'] = $before;
    }
});
