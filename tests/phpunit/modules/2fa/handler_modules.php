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
}
