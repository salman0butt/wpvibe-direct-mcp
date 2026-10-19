<?php

function wpvdmcp_render_token_test_admin() {
    $starting_level = ob_get_level();
    ob_start();
    try {
        WPVDMCP_Admin::instance()->render();
        return ob_get_clean();
    } finally {
        while (ob_get_level() > $starting_level) ob_end_clean();
    }
}

parity_test('failed one-time token deletion never exposes the secret', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $get_before = $_GET;
    $had_override = array_key_exists('delete_option_override', $GLOBALS);
    $override_before = $had_override ? $GLOBALS['delete_option_override'] : null;
    try {
        $GLOBALS['wp_current_user'] = 1;
        $_GET = array();
        $token = 'valid-but-undeletable-token';
        update_option('wpvdmcp_token_hash', hash('sha256', $token));
        update_option('wpvdmcp_user_id', 1);
        set_transient('wpvdmcp_new_token_1', $token, 5 * MINUTE_IN_SECONDS);
        $GLOBALS['delete_option_override'] = function($key) {
            return 'transient:wpvdmcp_new_token_1' === $key ? false : null;
        };

        $html = wpvdmcp_render_token_test_admin();
        assert_true(strpos($html, 'id="wpvd-token"') === false, 'failed deletion must not reveal a reusable secret');
        $retry_html = wpvdmcp_render_token_test_admin();
        assert_true(strpos($retry_html, 'id="wpvd-token"') === false, 'repeat admin render must also hide undeletable credentials');
        assert_same($token, get_transient('wpvdmcp_new_token_1'), 'failed deletion must preserve the original transient');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        $_GET = $get_before;
        if ($had_override) $GLOBALS['delete_option_override'] = $override_before;
        else unset($GLOBALS['delete_option_override']);
    }
});

parity_test('overlapping token display requests reveal a secret at most once', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $get_before = $_GET;
    $had_hook = array_key_exists('get_transient_after_read', $GLOBALS);
    $hook_before = $had_hook ? $GLOBALS['get_transient_after_read'] : null;
    try {
        $GLOBALS['wp_current_user'] = 1;
        $_GET = array();
        $token = 'interleaved-display-token';
        update_option('wpvdmcp_token_hash', hash('sha256', $token));
        update_option('wpvdmcp_user_id', 1);
        set_transient('wpvdmcp_new_token_1', $token, 5 * MINUTE_IN_SECONDS);
        $nested_render = '';
        $interleaved = false;
        $GLOBALS['get_transient_after_read'] = function($key) use (&$interleaved, &$nested_render) {
            if ('wpvdmcp_new_token_1' === $key && ! $interleaved) {
                $interleaved = true;
                $nested_render = wpvdmcp_render_token_test_admin();
            }
        };

        $outer_render = wpvdmcp_render_token_test_admin();
        assert_same(true, $interleaved, 'test must interleave a second render while the first is reading its token');
        $reveals = (false !== strpos($outer_render, 'id="wpvd-token"') ? 1 : 0)
            + (false !== strpos($nested_render, 'id="wpvd-token"') ? 1 : 0);
        assert_same(1, $reveals, 'overlapping admin requests must not reveal the same one-time token twice');
        assert_same(false, get_transient('wpvdmcp_new_token_1'), 'the one-time token must be consumed');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        $_GET = $get_before;
        if ($had_hook) $GLOBALS['get_transient_after_read'] = $hook_before;
        else unset($GLOBALS['get_transient_after_read']);
    }
});

parity_test('revoked token cannot be redisplayed from stale one-time transient', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $get_before = $_GET;
    try {
        $GLOBALS['wp_current_user'] = 1;
        $_GET = array();
        $token = 'revoked-but-stale-display-token';
        update_option('wpvdmcp_token_hash', '');
        update_option('wpvdmcp_user_id', 0);
        set_transient('wpvdmcp_new_token_1', $token, 5 * MINUTE_IN_SECONDS);

        $html = wpvdmcp_render_token_test_admin();
        assert_true(strpos($html, 'id="wpvd-token"') === false, 'revoked credentials must never appear in stale display state');
        assert_same(false, get_transient('wpvdmcp_new_token_1'), 'stale display state must be consumed');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        $_GET = $get_before;
    }
});

parity_test('busy database token mutation lock prevents competing token display', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $get_before = $_GET;
    $had_wpdb = array_key_exists('wpdb', $GLOBALS);
    $wpdb_before = $had_wpdb ? $GLOBALS['wpdb'] : null;
    try {
        $GLOBALS['wp_current_user'] = 1;
        $_GET = array();
        $token = 'busy-token-mutation-lock';
        update_option('wpvdmcp_token_hash', hash('sha256', $token));
        update_option('wpvdmcp_user_id', 1);
        set_transient('wpvdmcp_new_token_1', $token, 5 * MINUTE_IN_SECONDS);
        $GLOBALS['wp_options']['wpvdmcp_token_mutation_claim'] = 'live-other-session-claim';
        $GLOBALS['wpdb'] = new WPVDMCP_Test_Advisory_DB(false);

        $html = wpvdmcp_render_token_test_admin();
        assert_true(strpos($html, 'id="wpvd-token"') === false, 'active database mutation lock must prevent competing display');
        assert_same($token, get_transient('wpvdmcp_new_token_1'), 'lock contention must preserve the waiting token');
        assert_same('live-other-session-claim', $GLOBALS['wp_options']['wpvdmcp_token_mutation_claim'], 'lock contention must not steal an active claim');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        $_GET = $get_before;
        if ($had_wpdb) $GLOBALS['wpdb'] = $wpdb_before;
        else unset($GLOBALS['wpdb']);
    }
});

parity_test('old display transient cannot reveal a token after credential rotation', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $get_before = $_GET;
    try {
        $GLOBALS['wp_current_user'] = 1;
        $_GET = array();
        $old_token = 'old-display-after-rotation';
        update_option('wpvdmcp_token_hash', hash('sha256', 'new-replacement-credential'));
        update_option('wpvdmcp_user_id', 1);
        set_transient('wpvdmcp_new_token_1', $old_token, 5 * MINUTE_IN_SECONDS);

        $html = wpvdmcp_render_token_test_admin();
        assert_true(strpos($html, 'id="wpvd-token"') === false, 'rotated credentials must not expose their stale display transient');
        assert_same(false, get_transient('wpvdmcp_new_token_1'), 'old display transient must be consumed after rotation');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        $_GET = $get_before;
    }
});
