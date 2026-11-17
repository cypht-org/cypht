<?php

use PHPUnit\Framework\TestCase;

/**
 * Records a write that must not run before the second factor is confirmed.
 */
class Hm_Handler_password_change_probe extends Hm_Handler_Module {
    public function process() {
        $this->out('password_changed', true);
    }
}

/**
 * Halts the rest of the handler list.
 */
class Hm_Handler_halt_for_second_factor extends Hm_Handler_Module {
    public function process() {
        $this->halt();
    }
}

/**
 * Destroys the session. Used to prove logout still sees an open session.
 */
class Hm_Handler_destroy_session_probe extends Hm_Handler_Module {
    public function process() {
        $this->session->destroy($this->request);
    }
}

/**
 * Session double that records close and destroy order.
 */
class Hm_Halt_Order_Session extends Hm_Mock_Session {
    public $events = array();
    public $active = true;

    public function is_active() {
        return $this->active;
    }

    public function close_early() {
        $this->events[] = 'close';
        $this->active = false;
    }

    public function destroy($request = null) {
        $this->events[] = 'destroy';
        $this->active = false;
    }
}

/**
 * Tests for the 2FA session gate
 * @package tests
 * @subpackage 2fa
 */
class Hm_Test_2fa_Handler_Modules extends TestCase {

    public $module_exec;

    public function setUp(): void {
        require_once APP_PATH.'modules/2fa/modules.php';
        $config = new Hm_Mock_Config();
        $config->set('2fa_secret', 'test-secret');
        $this->module_exec = new Hm_Module_Exec($config);
        $this->module_exec->user_config->set('2fa_enable_setting', 1);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_owning_modules_register_after_halt_handlers() {
        require APP_PATH.'modules/core/setup.php';
        require APP_PATH.'modules/idle_timer/setup.php';
        $this->assertTrue(Hm_Handler_Modules::runs_after_halt('save_user_data'));
        $this->assertTrue(Hm_Handler_Modules::runs_after_halt('logout'));
        $this->assertTrue(Hm_Handler_Modules::runs_after_halt('idle_time_check'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_after_halt_names_come_from_site_config() {
        $config = new Hm_Mock_Config();
        $config->set('handlers_after_halt', array('save_user_data', 'logout'));
        new Hm_Module_Exec($config);
        $this->assertTrue(Hm_Handler_Modules::runs_after_halt('save_user_data'));
        $this->assertTrue(Hm_Handler_Modules::runs_after_halt('logout'));
        $this->assertFalse(Hm_Handler_Modules::runs_after_halt('idle_time_check'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     * @dataProvider second_factor_gate_cases
     */
    public function test_password_only_session_is_gated($case) {
        $input = $case['input'];
        $expected = $case['expected'];
        $page = 'gate_'.$case['id'];

        if (!empty($input['logout'])) {
            $this->assert_logout_runs_before_close($page, $expected);
            return;
        }

        handler_runs_after_halt('save_user_data');
        Hm_Handler_Modules::add($page, '2fa_check', true, false, 'after', true, '2fa');
        Hm_Handler_Modules::add($page, 'password_change_probe', true, false, 'after', true, 'test');
        Hm_Handler_Modules::add($page, 'save_user_data', true, false, 'after', true, 'core');

        $session = new Hm_Mock_Session();
        if (!empty($input['two_factor_confirmed'])) {
            $session->set('2fa_confirmed', true);
        }
        $request = new Hm_Mock_Request($input['request_type']);
        $this->module_exec->run_handler_modules($request, $session, $page);

        $this->assertSame($expected['halted'], $this->module_exec->is_halted());
        if (!empty($expected['password_changed'])) {
            $this->assertTrue($this->module_exec->handler_response['password_changed']);
        }
        else {
            $this->assertArrayNotHasKey('password_changed', $this->module_exec->handler_response);
        }
        $this->assertSame($expected['handler_halt_in_response'], array_key_exists('handler_halt', $this->module_exec->handler_response));
        if (array_key_exists('user_data_saved', $expected) && $expected['user_data_saved']) {
            $user_data = $session->get('user_data', array());
            $this->assertIsArray($user_data);
            $this->assertArrayHasKey('2fa_enable_setting', $user_data);
        }
        if (array_key_exists('session_closed', $expected)) {
            $this->assertSame(!$expected['session_closed'], $session->is_active());
        }
        if ($input['request_type'] === 'AJAX') {
            $this->module_exec->run_output_modules($request, false, $page, $this->module_exec->handler_response);
            $this->assertSame(array('status' => $expected['ajax_status']), $this->module_exec->output_response);
            $this->assertSame($expected['folder_list_present'], array_key_exists('formatted_folder_list', $this->module_exec->output_response));
        }
    }

    public static function second_factor_gate_cases() {
        return PolicyCases::forPolicy('second-factor-gates-password-only-session');
    }

    /**
     * @param string $page page id
     * @param array $expected expected case values
     * @return void
     */
    private function assert_logout_runs_before_close($page, $expected) {
        handler_runs_after_halt('destroy_session_probe');
        Hm_Handler_Modules::add($page, 'halt_for_second_factor', false, false, 'after', true, 'test');
        Hm_Handler_Modules::add($page, 'password_change_probe', false, false, 'after', true, 'test');
        Hm_Handler_Modules::add($page, 'destroy_session_probe', false, false, 'after', true, 'test');

        $session = new Hm_Halt_Order_Session();
        $request = new Hm_Mock_Request('HTTP');
        $this->module_exec->run_handler_modules($request, $session, $page);

        $destroy_at = array_search('destroy', $session->events, true);
        $close_at = array_search('close', $session->events, true);
        $this->assertNotFalse($destroy_at);
        $this->assertSame($expected['destroy_before_close'], ($close_at === false || $destroy_at < $close_at));
        $this->assertArrayNotHasKey('password_changed', $this->module_exec->handler_response);
    }

    public static function second_factor_lockout_cases() {
        return PolicyCases::forPolicy('second-factor-attempt-lockout');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     * @dataProvider second_factor_lockout_cases
     */
    public function test_second_factor_attempt_lockout($case) {
        require __DIR__.'/../../helpers.php';
        $input = $case['input'];
        $expected = $case['expected'];
        $config = new Hm_Mock_Config();
        setup_db($config);
        $config->set('2fa_secret', 'test-secret');
        $config->set('2fa_max_attempts', 5);
        $config->set('2fa_lockout_duration', 900);
        $this->clear_2fa_attempts($config);
        $this->seed_2fa_attempts($config, $input['username'], $input['seed_count'], $input['seed_locked_for']);
        $submit_code = !isset($input['submit_code']) || $input['submit_code'];

        $test = new Handler_Test('2fa_check', '2fa');
        $test->config = $config->dump();
        $test->user_config = array(
            '2fa_enable_setting' => 1,
        );
        $test->post = array();
        if ($submit_code) {
            $code = $this->login_code($config, $input['username'], isset($input['code']) ? $input['code'] : '', !empty($input['valid']));
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            $test->post = array('2fa_code' => $code);
        }
        $test->session = array('username' => $input['username']);
        $test->prep();
        $session = new Hm_2fa_Lock_Session();
        $session->set('username', $input['username']);
        $test->ses_obj = $session;
        $exec = $test->run_only();

        $this->assertSame($expected['confirmed'], (bool) $session->get('2fa_confirmed', false));
        $this->assertSame($expected['session_destroyed'], $session->destroyed);
        if ($expected['session_destroyed']) {
            $this->assertArrayNotHasKey('no_redirect', $exec->handler_response);
        }
        $error = array_key_exists('2fa_error', $exec->handler_response) ? $exec->handler_response['2fa_error'] : '';
        if ($expected['error'] === '') {
            $this->assertSame('', $error);
        }
        else {
            $this->assertStringContainsString($expected['error'], $error);
        }
        $notice = '';
        foreach (Hm_Msgs::getRaw() as $msg) {
            if (!empty($expected['notice']) && strpos($msg['text'], $expected['notice']) !== false) {
                $notice = $msg['text'];
                $this->assertSame('danger', $msg['type']);
            }
        }
        if (!empty($expected['notice'])) {
            $this->assertStringContainsString($expected['notice'], $notice);
        }
        if (array_key_exists('code_form', $expected)) {
            $this->assertSame($expected['code_form'], !empty($exec->handler_response['2fa_required']));
        }

        $row = $this->attempt_row($config, $input['username']);
        $this->clear_2fa_attempts($config);
        Hm_Msgs::flush();
        if ($expected['attempt_count'] === null) {
            $this->assertNull($row);
            return;
        }
        $this->assertNotNull($row);
        $this->assertSame($expected['attempt_count'], (int) $row['attempt_count']);
        $this->assertStringNotContainsString($input['username'], $row['attempt_key']);
        $locked = (int) $row['attempt_count'] >= 5 && (int) $row['locked_until'] > time();
        $this->assertSame($expected['locked'], $locked);
    }

    /**
     * Build the 6-digit login code for this case.
     * A valid case uses the current authenticator code. A wrong case stays 6 digits and is not that code.
     * @param object $config site config
     * @param string $username account name
     * @param string $code code from the policy case
     * @param bool $valid true when the case submits the current code
     * @return string
     */
    private function login_code($config, $username, $code, $valid) {
        $current = $this->current_totp($config, $username);
        if ($valid) {
            return $current;
        }
        $code = str_pad(substr(preg_replace('/\D/', '', (string) $code), 0, 6), 6, '0', STR_PAD_LEFT);
        if ($code === $current) {
            $code = str_pad((string) ((intval($code) + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    /**
     * @param object $config site config
     * @param string $username account name
     * @return string current 6-digit authenticator code
     */
    private function current_totp($config, $username) {
        list($secret, $simple) = get_2fa_key($config);
        $user_secret = create_secret($secret, $username, $simple ? 15 : 64);
        $time = str_pad(pack('N', (int) floor(time() / 30)), 8, chr(0), STR_PAD_LEFT);
        $hash = hash_hmac('sha1', $time, $user_secret, true);
        $offset = ord(substr($hash, -1)) & 0xF;
        $slice = substr($hash, $offset, strlen($hash) - $offset);
        $unpacked = unpack('N', substr($slice, 0, 4));
        $inthash = $unpacked[1] & 0x7FFFFFFF;
        return str_pad((string) ($inthash % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param object $config site config
     * @return void
     */
    private function clear_2fa_attempts($config) {
        $dbh = Hm_DB::connect($config);
        if ($dbh) {
            Hm_DB::execute($dbh, "delete from hm_login_attempts where attempt_key like '2fa:%'", array());
        }
    }

    /**
     * @param object $config site config
     * @param string $username account name
     * @param int $count stored failures
     * @param int $locked_for seconds of remaining lock
     * @return void
     */
    private function seed_2fa_attempts($config, $username, $count, $locked_for) {
        if ($count < 1) {
            return;
        }
        $dbh = Hm_DB::connect($config);
        $locked_until = $locked_for > 0 ? time() + $locked_for : 0;
        Hm_DB::execute(
            $dbh,
            'insert into hm_login_attempts (attempt_key, attempt_count, locked_until, last_attempt) values (?, ?, ?, ?)',
            array(tfa_attempt_key($username), $count, $locked_until, time())
        );
    }

    /**
     * @param object $config site config
     * @param string $username account name
     * @return array|null
     */
    private function attempt_row($config, $username) {
        $dbh = Hm_DB::connect($config);
        $rows = Hm_DB::execute(
            $dbh,
            'select attempt_key, attempt_count, locked_until from hm_login_attempts where attempt_key=?',
            array(tfa_attempt_key($username)),
            false,
            true
        );
        if (!$rows) {
            return null;
        }
        return $rows[0];
    }
}

/**
 * Session double that records destroy without ending the session on close.
 */
class Hm_2fa_Lock_Session extends Hm_Mock_Session {
    public $destroyed = false;

    public function destroy($request = null) {
        $this->destroyed = true;
        $this->loaded = false;
    }
}
