<?php

parity_test('token generation rejects coerced owner readback before revealing credentials', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $had_update = array_key_exists('update_option_override', $GLOBALS);
    $update_before = $had_update ? $GLOBALS['update_option_override'] : null;
    $had_throw = array_key_exists('throw_on_redirect', $GLOBALS);
    $throw_before = $had_throw ? $GLOBALS['throw_on_redirect'] : null;
    $had_redirect = array_key_exists('last_redirect', $GLOBALS);
    $redirect_before = $had_redirect ? $GLOBALS['last_redirect'] : null;
    try {
        $old_hash = hash('sha256', 'previous-valid-token');
        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = $old_hash;
        $GLOBALS['wp_options']['wpvdmcp_user_id'] = 2;
        $GLOBALS['wp_current_user'] = 1;
        $GLOBALS['throw_on_redirect'] = true;
        $poisoned = false;
        $GLOBALS['update_option_override'] = function($key, $value) use (&$poisoned) {
            if ($key === 'wpvdmcp_user_id' && $value === 1 && ! $poisoned) {
                $poisoned = true;
                $GLOBALS['wp_options'][$key] = '1abc';
                return true;
            }
            return null;
        };

        try {
            WPVDMCP_Admin::instance()->generate_token();
        } catch (RuntimeException $e) {
            assert_same('redirect', $e->getMessage(), 'generation must redirect after checking persistence');
        }

        assert_same(true, $poisoned, 'test must replace saved owner with an invalid numeric-prefix string');
        assert_true(strpos($GLOBALS['last_redirect'] ?? '', 'token_error=1') !== false, 'coerced owner must report token generation failure');
        assert_same($old_hash, $GLOBALS['wp_options']['wpvdmcp_token_hash'] ?? null, 'failed generation must restore old hash');
        assert_same(2, $GLOBALS['wp_options']['wpvdmcp_user_id'] ?? null, 'failed generation must restore old owner');
        assert_same(false, get_transient('wpvdmcp_new_token_1'), 'invalid saved owner must not expose a new token');

        $GLOBALS['update_option_override'] = function($key, $value) {
            if ($key === 'wpvdmcp_user_id' && $value === 1) {
                $GLOBALS['wp_options'][$key] = '1';
                return true;
            }
            return null;
        };
        try {
            WPVDMCP_Admin::instance()->generate_token();
        } catch (RuntimeException $e) {
            assert_same('redirect', $e->getMessage(), 'canonical string owner generation redirects');
        }
        assert_true(strpos($GLOBALS['last_redirect'] ?? '', 'token_generated=1') !== false, 'WordPress canonical string user ID remains valid');
        assert_same('1', $GLOBALS['wp_options']['wpvdmcp_user_id'] ?? null, 'canonical string owner remains stored');
        assert_true(is_string(get_transient('wpvdmcp_new_token_1')), 'canonical string user ID still allows one-time display');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        if ($had_update) $GLOBALS['update_option_override'] = $update_before;
        else unset($GLOBALS['update_option_override']);
        if ($had_throw) $GLOBALS['throw_on_redirect'] = $throw_before;
        else unset($GLOBALS['throw_on_redirect']);
        if ($had_redirect) $GLOBALS['last_redirect'] = $redirect_before;
        else unset($GLOBALS['last_redirect']);
    }
});

parity_test('token revocation does not report success for malformed fallback owner', function() {
    $options_before = $GLOBALS['wp_options'];
    $user_before = $GLOBALS['wp_current_user'];
    $had_update = array_key_exists('update_option_override', $GLOBALS);
    $update_before = $had_update ? $GLOBALS['update_option_override'] : null;
    $had_delete = array_key_exists('delete_option_override', $GLOBALS);
    $delete_before = $had_delete ? $GLOBALS['delete_option_override'] : null;
    $had_throw = array_key_exists('throw_on_redirect', $GLOBALS);
    $throw_before = $had_throw ? $GLOBALS['throw_on_redirect'] : null;
    $had_redirect = array_key_exists('last_redirect', $GLOBALS);
    $redirect_before = $had_redirect ? $GLOBALS['last_redirect'] : null;
    try {
        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = hash('sha256', 'previous-revoked-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id'] = 1;
        $GLOBALS['wp_current_user'] = 1;
        $GLOBALS['throw_on_redirect'] = true;
        $GLOBALS['delete_option_override'] = function($key) {
            return in_array($key, array('wpvdmcp_token_hash', 'wpvdmcp_user_id'), true) ? false : null;
        };
        $GLOBALS['update_option_override'] = function($key, $value) {
            if ($key === 'wpvdmcp_user_id' && $value === 0) {
                $GLOBALS['wp_options'][$key] = '0junk';
                return true;
            }
            return null;
        };

        try {
            WPVDMCP_Admin::instance()->revoke_token();
        } catch (RuntimeException $e) {
            assert_same('redirect', $e->getMessage(), 'revocation must redirect after checking fallback state');
        }

        assert_true(strpos($GLOBALS['last_redirect'] ?? '', 'token_revoke_error=1') !== false, 'malformed fallback owner must report revocation persistence failure');
        assert_same('', $GLOBALS['wp_options']['wpvdmcp_token_hash'] ?? null, 'failed fallback verification still deactivates token hash');
        assert_same('0junk', $GLOBALS['wp_options']['wpvdmcp_user_id'] ?? null, 'test must model a malformed saved owner');

        $GLOBALS['wp_options']['wpvdmcp_token_hash'] = hash('sha256', 'canonical-string-revoke-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id'] = 1;
        $GLOBALS['update_option_override'] = function($key, $value) {
            if ($key === 'wpvdmcp_user_id' && $value === 0) {
                $GLOBALS['wp_options'][$key] = '0';
                return true;
            }
            return null;
        };
        try {
            WPVDMCP_Admin::instance()->revoke_token();
        } catch (RuntimeException $e) {
            assert_same('redirect', $e->getMessage(), 'canonical string fallback revocation redirects');
        }
        assert_true(strpos($GLOBALS['last_redirect'] ?? '', 'token_revoked=1') !== false, 'WordPress canonical string zero fallback remains valid');
        assert_same('0', $GLOBALS['wp_options']['wpvdmcp_user_id'] ?? null, 'canonical string fallback owner remains stored');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
        $GLOBALS['wp_current_user'] = $user_before;
        if ($had_update) $GLOBALS['update_option_override'] = $update_before;
        else unset($GLOBALS['update_option_override']);
        if ($had_delete) $GLOBALS['delete_option_override'] = $delete_before;
        else unset($GLOBALS['delete_option_override']);
        if ($had_throw) $GLOBALS['throw_on_redirect'] = $throw_before;
        else unset($GLOBALS['throw_on_redirect']);
        if ($had_redirect) $GLOBALS['last_redirect'] = $redirect_before;
        else unset($GLOBALS['last_redirect']);
    }
});
