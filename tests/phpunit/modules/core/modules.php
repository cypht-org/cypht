<?php

class Hm_Test_Core_Handler_Modules extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        require 'helpers.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_folder_icon_setting() {
        $test = new Handler_Test('check_folder_icon_setting', 'core');
        $res = $test->run();
        $this->assertFalse($res->handler_response['hide_folder_icons']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcesM#s
     */
    public function test_process_pw_update() {
        $test = new Handler_Test('process_pw_update', 'core');
        $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());

        $test->post = array('server_pw_id' => 0, 'password' => 'foo');
        $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());

        $test->input = array('missing_pw_servers' => array(0 => array('id' => 0, 'type' => 'POP3')));
        $res = $test->run();
        $this->assertEquals(array('ERRUnable to authenticate to the POP3 server'), Hm_Msgs::get());
        $this->assertFalse($res->handler_response['connect_status']);
        Hm_Msgs::flush();

        Hm_POP3_List::change_state('authed');
        $res = $test->run();
        $this->assertEquals(array('Password Updated'), Hm_Msgs::get());
        $this->assertTrue($res->handler_response['connect_status']);
        Hm_Msgs::flush();

        $test->input = array('missing_pw_servers' => array(0 => array('id' => 0, 'type' => 'SMTP')));
        $res = $test->run();
        $this->assertEquals(array('ERRUnable to authenticate to the SMTP server'), Hm_Msgs::get());
        $this->assertFalse($res->handler_response['connect_status']);
        Hm_Msgs::flush();

        Hm_SMTP_List::change_state('authed');
        $res = $test->run();
        $this->assertEquals(array('Password Updated'), Hm_Msgs::get());
        $this->assertTrue($res->handler_response['connect_status']);
        Hm_Msgs::flush();

        $test->input = array('missing_pw_servers' => array(0 => array('id' => 0, 'type' => 'IMAP')));
        $res = $test->run();
        $this->assertEquals(array('ERRUnable to authenticate to the IMAP server'), Hm_Msgs::get());
        $this->assertFalse($res->handler_response['connect_status']);
        Hm_Msgs::flush();

        Hm_IMAP_List::change_state('authed');
        $res = $test->run();
        $this->assertEquals(array('Password Updated'), Hm_Msgs::get());
        $this->assertTrue($res->handler_response['connect_status']);
        Hm_Msgs::flush();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_missing_passwords() {
        $test = new Handler_Test('check_missing_passwords', 'core');
        $res = $test->run();
        $this->assertFalse(array_key_exists('missing_pw_servers', $res->handler_response));
        $test->modules = array('imap', 'pop3', 'smtp');
        $test->user_config = array('no_password_save_setting' => true);
        Hm_IMAP_List::add(array('nopass' => 1, 'user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        Hm_POP3_List::add(array('nopass' => 1, 'user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        Hm_SMTP_List::add(array('nopass' => 1, 'user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        $res = $test->run();
        $this->assertEquals(9, count($res->handler_response['missing_pw_servers']));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_close_session_early() {
        $test = new Handler_Test('close_session_early', 'core');
        $res = $test->run();
        $this->assertFalse($res->session->is_active());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_http_headers() {
        $test = new Handler_Test('http_headers', 'core');
        $test->tls = true;
        $test->rtype = 'AJAX';
        $test->input = array('language' => 'English');
        $res = $test->run();
		$out = array(
			'Content-Language' => 'En',
            'Strict-Transport-Security' => 'max-age=31536000',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; script-src 'self' 'unsafe-inline'; connect-src 'self'; font-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline';",
            'Content-Type' => 'application/json',
		);
        foreach ($out as $key => $val) {
            $this->assertEquals($out[$key], $res->handler_response['http_headers'][$key]);
        }
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_list_style_setting_passed() {
        $test = new Handler_Test('process_list_style_setting', 'core');
        $test->post = array('save_settings' => true, 'list_style' => 'news_style');
        $res = $test->run();
        $this->assertEquals('news_style', $res->handler_response['new_user_settings']['list_style_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_list_style_setting_failed() {
        $test = new Handler_Test('process_list_style_setting', 'core');
        $test->post = array('save_settings' => true, 'list_style' => 'blah');
        $res = $test->run();
        $this->assertEquals('email_style', $res->handler_response['new_user_settings']['list_style_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_start_page_setting_passed() {
        $test = new Handler_Test('process_start_page_setting', 'core');
        $test->post = array('save_settings' => true, 'start_page' => 'page=message_list&list_path=unread');
        $res = $test->run();
        $this->assertEquals('page=message_list&list_path=unread', $res->handler_response['new_user_settings']['start_page_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_start_page_setting_failed() {
        $test = new Handler_Test('process_start_page_setting', 'core');
        $test->post = array('save_settings' => true, 'start_page' => 'blah');
        $res = $test->run();
        $this->assertEquals('', $res->handler_response['new_user_settings']['start_page_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_hide_folder_icons_setting_failed() {
        $test = new Handler_Test('process_hide_folder_icons', 'core');
        $test->post = array('save_settings' => true, 'no_folder_icons' => false);
        $res = $test->run();
        $this->assertFalse($res->handler_response['new_user_settings']['no_folder_icons_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_show_list_icons() {
        $test = new Handler_Test('process_show_list_icons', 'core');
        $test->post = array('save_settings' => true, 'show_list_icons' => false);
        $res = $test->run();
        $this->assertFalse($res->handler_response['new_user_settings']['show_list_icons_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_unread_source_max_setting() {
        $test = new Handler_Test('process_unread_source_max_setting', 'core');
        $test->post = array('save_settings' => true, 'unread_per_source' => 10);
        $res = $test->run();
        $this->assertEquals(10, $res->handler_response['new_user_settings']['unread_per_source_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_all_email_source_max_setting() {
        $test = new Handler_Test('process_all_email_source_max_setting', 'core');
        $test->post = array('save_settings' => true, 'all_email_per_source' => 10);
        $res = $test->run();
        $this->assertEquals(10, $res->handler_response['new_user_settings']['all_email_per_source_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_no_password_setting() {
        $test = new Handler_Test('process_no_password_setting', 'core');
        $test->post = array('save_settings' => true, 'no_password_save' => true);
        $res = $test->run();
        $this->assertTrue($res->handler_response['new_user_settings']['no_password_save_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_delete_prompt_setting() {
        $test = new Handler_Test('process_delete_prompt_setting', 'core');
        $test->post = array('save_settings' => true, 'disable_delete_prompt' => true);
        $res = $test->run();
        $this->assertTrue($res->handler_response['new_user_settings']['disable_delete_prompt_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_all_source_max_setting() {
        $test = new Handler_Test('process_all_source_max_setting', 'core');
        $test->post = array('save_settings' => true, 'all_per_source' => 10);
        $res = $test->run();
        $this->assertEquals(10, $res->handler_response['new_user_settings']['all_per_source_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_flagged_source_max_setting() {
        $test = new Handler_Test('process_flagged_source_max_setting', 'core');
        $test->post = array('save_settings' => true, 'flagged_per_source' => 10);
        $res = $test->run();
        $this->assertEquals(10, $res->handler_response['new_user_settings']['flagged_per_source_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_flagged_since_setting() {
        $test = new Handler_Test('process_flagged_since_setting', 'core');
        $test->post = array('save_settings' => true, 'flagged_since' => 'foo');
        $res = $test->run();
        $this->assertEquals('today', $res->handler_response['new_user_settings']['flagged_since_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_all_since_setting() {
        $test = new Handler_Test('process_all_since_setting', 'core');
        $test->post = array('save_settings' => true, 'all_since' => 'foo');
        $res = $test->run();
        $this->assertEquals('today', $res->handler_response['new_user_settings']['all_since_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_all_email_since_setting() {
        $test = new Handler_Test('process_all_email_since_setting', 'core');
        $test->post = array('save_settings' => true, 'all_email_since' => 'foo');
        $res = $test->run();
        $this->assertEquals('today', $res->handler_response['new_user_settings']['all_email_since_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_unread_since_setting() {
        $test = new Handler_Test('process_unread_since_setting', 'core');
        $test->post = array('save_settings' => true, 'unread_since' => 'foo');
        $res = $test->run();
        $this->assertEquals('today', $res->handler_response['new_user_settings']['unread_since_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_language_setting_passed() {
        $test = new Handler_Test('process_language_setting', 'core');
        $test->post = array('save_settings' => true, 'language' => 'en');
        $res = $test->run();
        $this->assertEquals('en', $res->handler_response['new_user_settings']['language_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_language_setting_failed() {
        $test = new Handler_Test('process_language_setting', 'core');
        $test->post = array('save_settings' => true, 'language' => 'foo');
        $res = $test->run();
        $this->assertEquals('en', $res->handler_response['new_user_settings']['language_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_timezone_setting_passed() {
        $test = new Handler_Test('process_timezone_setting', 'core');
        $test->post = array('save_settings' => true, 'timezone' => 'America/Chicago');
        $res = $test->run();
        $this->assertEquals('America/Chicago', $res->handler_response['new_user_settings']['timezone_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_timezone_setting_failed() {
        $test = new Handler_Test('process_timezone_setting', 'core');
        $test->post = array('save_settings' => true, 'timezone' => 'foo');
        $res = $test->run();
        $this->assertFalse($res->handler_response['new_user_settings']['timezone_setting']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_save_form() {
        $test = new Handler_Test('process_save_form', 'core');
        $test->session = array('username' => 'foo');
        $test->run();
        $test->post = array('save_settings' => true, 'password' => 'foo');
        $this->assertEquals(array(), Hm_Msgs::get());
        $test->run();
        $test->post = array('save_settings_permanently' => 1, 'save_settings' => true, 'password' => 'foo');
        $test->run();
        $this->assertEquals(array('Settings saved'), Hm_Msgs::get());
        Hm_Msgs::flush();
        $test->post = array('save_settings_permanently_then_logout' => 1, 'save_settings' => true, 'password' => 'foo');
        $test->run();
        $this->assertEquals(array('Saved user data on logout', 'Session destroyed on logout'), Hm_Msgs::get());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_user_settings() {
        $test = new Handler_Test('save_user_settings', 'core');
        $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());
        $test->post = array('save_settings' => true);
        $test->input = array('new_user_settings' => array('foo' => 'bar'));
        $test->run();
        $this->assertEquals(array('Settings updated'), Hm_Msgs::get());
	}
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_title() {
        $test = new Handler_Test('title', 'core');
        $res = $test->run();
        $this->assertEquals('', $res->handler_response['title']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_language() {
        $test = new Handler_Test('language', 'core');
        $res = $test->run();
        $this->assertEquals('en', $res->handler_response['language']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_date() {
        $test = new Handler_Test('date', 'core');
        $res = $test->run();
        $this->assertTrue(array_key_exists('date', $res->handler_response));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_stay_logged_in() {
        $test = new Handler_Test('stay_logged_in', 'core');
        $res = $test->run();
        $this->assertFalse(array_key_exists('allow_long_session', $res->handler_response));
        $test->post = array('stay_logged_in' => true);
        $test->config = array('allow_long_session' => true);
        $res = $test->run();
        $this->assertTrue($res->session->lifetime > 0);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login() {
        $test = new Handler_Test('login', 'core');
        $test->input = array('create_username' => true);
        $res = $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());
        $test->input = array();
        $res = $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());
        $test->post = array('username' => 'foo', 'password' => 'bar');
        $test->run();
        $this->assertEquals(array('ERRInvalid username or password'), Hm_Msgs::get());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_page_data() {
        $test = new Handler_Test('default_page_data', 'core');
        $test->config = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array(), $res->handler_response['data_sources']);
        $this->assertEquals('', $res->handler_response['encrypt_ajax_requests']);
        $this->assertEquals('', $res->handler_response['encrypt_local_storage']);
        $this->assertTrue($res->handler_response['single_server_mode']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_user_data() {
        $test = new Handler_Test('load_user_data', 'core');
        $test->user_config = array('start_page_setting' => 'page=message_list&list_path=unread', 'saved_pages' => 'foo');
        $res = $test->run();
        $test->session = array('user_data' => array('foo' => 'bar'));
        $res = $test->run();
        $this->assertEquals(array('start_page_setting' => 'page=message_list&list_path=unread', 'saved_pages' => 'foo'), $test->user_config);
        $test->post = array('username' => 'foo', 'password' => 'bar');
        $res = $test->run();
        $this->assertFalse($res->handler_response['disable_delete_prompt']);
        $this->assertFalse($res->handler_response['is_mobile']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_user_data() {
        $test = new Handler_Test('save_user_data', 'core');
        $res = $test->run();
        $this->assertEquals(array('user_settings_dir' => './data', 'default_language' => 'es'), $res->session->get('user_data'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_logout() {
        $test = new Handler_Test('logout', 'core');
        $test->post = array('logout' => true);
        $test->prep();
        $test->ses_obj->loaded = false;
        $test->run_only();
        $this->assertEquals(array('Session destroyed on logout'), Hm_Msgs::get());
        Hm_Msgs::flush();

        $test->post = array('password' => 'foo', 'save_and_logout' => true);
        $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());
        Hm_Msgs::flush();
        
        $test->config = array('user_settings_dir' => './data');
        $test->session = array('username' => 'foo');
        $test->prep();
        $test->ses_obj->auth_state = false;
        $test->run_only();
        $this->assertEquals(array('ERRIncorrect password, could not save settings to the server'), Hm_Msgs::get());
        Hm_Msgs::flush();
        $test->prep();
        $test->ses_obj->auth_state = true;
        $test->run_only();
        $this->assertEquals(array('Saved user data on logout', 'Session destroyed on logout'), Hm_Msgs::get());
        Hm_Msgs::flush();

        $test->post = array('save_and_logout' => true);
        $test->run();
        $this->assertEquals(array('ERRYour password is required to save your settings to the server'), Hm_Msgs::get());
        Hm_Msgs::flush();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_type() {
        $test = new Handler_Test('message_list_type', 'core');
        $test->get = array('uid' => 1, 'list_parent' => 'unread', 'list_page' => 1, 'list_path' => 'unread');
        $test->input = array('is_mobile' => true);
        $res = $test->run();
		$this->assertEquals(1, $res->handler_response['uid']);
		$this->assertEquals(1, $res->handler_response['news_list_style']);
		$this->assertEquals(1, $res->handler_response['list_page']);
		$this->assertEquals(1, $res->handler_response['is_mobile']);
		$this->assertEquals(1, $res->handler_response['list_meta']);
        $test->get = array('list_parent' => 'unread', 'list_path' => 'unread');
        $res = $test->run();
		$this->assertEquals('', $res->handler_response['uid']);
		$this->assertEquals(1, $res->handler_response['news_list_style']);
		$this->assertEquals(1, $res->handler_response['list_page']);
		$this->assertEquals(1, $res->handler_response['is_mobile']);
		$this->assertEquals(1, $res->handler_response['list_meta']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reload_folder_cookie() {
        $test = new Handler_Test('reload_folder_cookie', 'core');
        $test->input = array('reload_folders' => true);
        $res = $test->run();
        $this->assertTrue($res->session->cookie_set);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reset_search() {
        $test = new Handler_Test('reset_search', 'core');
        $res = $test->run();
        $this->assertEquals('', $res->session->get('search_terms'));
        $this->assertEquals(DEFAULT_SINCE, $res->session->get('search_since'));
        $this->assertEquals(DEFAULT_SEARCH_FLD, $res->session->get('search_fld'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_search_terms() {
        $test = new Handler_Test('process_search_terms', 'core');
        $test->get = array('search_terms' => 'foo', 'search_since' => '-1 week', 'search_fld' => 'BODY');
        $res = $test->run();
		$this->assertEquals('foo', $res->handler_response['search_terms']);
		$this->assertEquals('-1 week', $res->handler_response['search_since']);
		$this->assertEquals('BODY', $res->handler_response['search_fld']);
    }
}
/**
 * TODO: add assertions to all tests
 */

class Hm_Test_Core_Output_Modules extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        require 'helpers.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_from_folder_list() {
        $test = new Output_Test('search_from_folder_list', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals('<li class="menu_search"><form method="get"><a class="unread_link" href="?page=search"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200c-1.927%200-3.5%201.573-3.5%203.5s1.573%203.5%203.5%203.5c.592%200%201.166-.145%201.656-.406a1%201%200%200%200%20.125.125l1%201a1.016%201.016%200%201%200%201.438-1.438l-1-1a1%201%200%200%200-.156-.125c.266-.493.438-1.059.438-1.656%200-1.927-1.573-3.5-3.5-3.5zm0%201c1.387%200%202.5%201.113%202.5%202.5%200%20.661-.241%201.273-.656%201.719l-.031.031a1%201%200%200%200-.125.125c-.442.397-1.043.625-1.688.625-1.387%200-2.5-1.113-2.5-2.5s1.113-2.5%202.5-2.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" alt="Search" width="16" height="16" /></a><input type="hidden" name="page" value="search" /><label class="screen_reader" for="search_terms">Search</label><input type="search" id="search_terms" class="search_terms" name="search_terms" placeholder="Search" /></form></li>', $res->output_data['formatted_folder_list']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_content_start() {
        $test = new Output_Test('search_content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="search_content"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a></div>Search'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_content_end() {
        $test = new Output_Test('search_content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_reminder() {
        $test = new Output_Test('save_reminder', 'core');
        $res = $test->run();
        $this->assertEquals(array(), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<div class="save_reminder"><a title="You have unsaved changes" href="?page=save"><img alt="Save" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3%200v3h-2l3%203%203-3h-2v-3h-2zm-3%207v1h8v-1h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_start() {
        $test = new Output_Test('search_form_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="search_form"><form method="get">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_content() {
        $test = new Output_Test('search_form_content', 'core');
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" name="page" value="search" /> <label class="screen_reader" for="search_terms">Search Terms</label><input required placeholder="Search Terms" id="search_terms" type="search" class="search_terms" name="search_terms" value="" /> <label class="screen_reader" for="search_fld">Search Field</label><select id="search_fld" name="search_fld"><option selected="selected" value="TEXT">Entire message</option><option value="BODY">Message body</option><option value="SUBJECT">Subject</option><option value="FROM">From</option></select> <label class="screen_reader" for="search_since">Search Since</label><select name="search_since" id="search_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select> <input type="submit" class="search_update" value="Update" /> <input type="button" class="search_reset" value="Reset" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_end() {
        $test = new Output_Test('search_form_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</form></div><div class="list_controls"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_results_table_end() {
        $test = new Output_Test('search_results_table_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</tbody></table>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_js_search_data() {
        $test = new Output_Test('js_search_data', 'core');
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript">var hm_search_terms = function() { return ""; };var hm_run_search = function() { return "0"; };</script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_end() {
        $test = new Output_Test('login_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</form>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_start() {
        $test = new Output_Test('login_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<form class="login_form" method="POST">'), $res->output_response);
        $test->handler_response = array('router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<form class="logout_form" method="POST">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login() {
        $test = new Output_Test('login', 'core');
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" id="unsaved_changes" value="0" /><input type="hidden" name="hm_page_key" value="" /><div class="confirm_logout"><div class="confirm_text">Unsaved changes will be lost! Re-enter your password to save and exit. &nbsp;<a href="?page=save">More info</a></div><label class="screen_reader" for="logout_password">Password</label><input id="logout_password" name="password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_and_logout" value="Save and Logout" /><input class="save_settings" id="logout_without_saving" type="submit" name="logout" value="Just Logout" /><input class="cancel_logout save_settings" type="button" value="Cancel" /></div>'), $res->output_response);
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => false);
        $res = $test->run();
        $this->assertEquals(array('<h1 class="title"></h1> <input type="hidden" name="hm_page_key" value="" /> <label class="screen_reader" for="username">Username</label><input autofocus required type="text" placeholder="Username" id="username" name="username" value=""> <label class="screen_reader" for="password">Password</label><input required type="password" id="password" placeholder="Password" name="password"><div class="long_session"><input type="checkbox" id="stay_logged_in" value="1" name="stay_logged_in" /> <label for="stay_logged_in">Stay logged in</label></div> <input type="submit" id="login" value="Login" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_server_content_start() {
        $test = new Output_Test('server_content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title">Servers<div class="list_controls"></div></div><div class="server_content">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_server_content_end() {
        $test = new Output_Test('server_content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_date() {
        $test = new Output_Test('date', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="date"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_msgs() {
        Hm_Msgs::add('ERRfoo');
        Hm_Msgs::add('foo');
        $test = new Output_Test('msgs', 'core');
        $test->handler_response = array('router_login_state' => false);
        $res = $test->run();
        $this->assertEquals(array('<div class="sys_messages logged_out"><span class="err">foo</span>,foo</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_start() {
        $test = new Output_Test('header_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<!DOCTYPE html><html dir="ltr" class="ltr_page" lang=en><head><meta name="theme-color" content="#888888" /><meta charset="utf-8" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_end() {
        $test = new Output_Test('header_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</head>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_start() {
        $test = new Output_Test('content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<body><noscript class="noscript">You need to have Javascript enabled to use , sorry about that!</noscript><script type="text/javascript">sessionStorage.clear();</script>'), $res->output_response);
        $test->handler_response = array('changed_settings' => array(0), 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<body><noscript class="noscript">You need to have Javascript enabled to use , sorry about that!</noscript><input type="hidden" id="hm_page_key" value="" /><a href="?page=save" title="Unsaved Changes"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AgSFQseE+bgxAAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAS0lEQVQ4y6WTSQoAMAgDk/z/z+21lK6ON5UZEIklNdXLIbAkhcBVgccmBP4VeDUMgV8FPi1D4JvAL7eFwDuBf/4aAs8CV0NB0sirA+jtAijusTaJAAAAAElFTkSuQmCC" alt="Unsaved changes" class="unsaved_reminder" /></a>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_content() {
        $test = new Output_Test('header_content', 'core');
        $res = $test->run();
        $this->assertEquals(array('<title></title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_login_state' => true, 'page_title' => 'foo');
        $res = $test->run();
        $this->assertEquals(array('<title>foo</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_login_state' => true, 'mailbox_list_title' => array('foo'));
        $res = $test->run();
        $this->assertEquals(array('<title>foo</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_page_name' => 'home', 'router_login_state' => true, 'list_path' => 'message_list');
        $res = $test->run();
        $this->assertEquals(array('<title>Message List</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_login_state' => true, 'router_page_name' => 'notfound');
        $res = $test->run();
        $this->assertEquals(array('<title>Nope</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        $test->handler_response = array('router_login_state' => true, 'router_page_name' => 'home');
        $res = $test->run();
        $this->assertEquals(array('<title>Home</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css() {
        $test = new Output_Test('header_css', 'core');
        $res = $test->run();
        $this->assertEquals(array('<link href="site.css?v=asdf" media="all" rel="stylesheet" type="text/css" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js() {
        $test = new Output_Test('page_js', 'core');
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="site.js?v=asdf"></script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_end() {
        $test = new Output_Test('content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</body></html>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_js_data() {
        $test = new Output_Test('js_data', 'core');
        $test->handler_response = array('disable_delete_prompt' => true);
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript">var globals = {};var hm_empty_folder = function() { return "So alone"; };var hm_mobile = function() { return 0; };var hm_debug = function() { return "0"; };var hm_page_name = function() { return ""; };var hm_list_path = function() { return ""; };var hm_list_parent = function() { return ""; };var hm_msg_uid = function() { return ""; };var hm_encrypt_ajax_requests = function() { return ""; };var hm_encrypt_local_storage = function() { return ""; };var hm_flag_image_src = function() { return "data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E"; };var hm_data_sources = function() { return []; };var hm_delete_prompt = function() { return true; };</script>'), $res->output_response);
        $test->handler_response = array();
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript">var globals = {};var hm_empty_folder = function() { return "So alone"; };var hm_mobile = function() { return 0; };var hm_debug = function() { return "0"; };var hm_page_name = function() { return ""; };var hm_list_path = function() { return ""; };var hm_list_parent = function() { return ""; };var hm_msg_uid = function() { return ""; };var hm_encrypt_ajax_requests = function() { return ""; };var hm_encrypt_local_storage = function() { return ""; };var hm_flag_image_src = function() { return "data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E"; };var hm_data_sources = function() { return []; };var hm_delete_prompt = function() { return confirm("Are you sure?"); };</script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_loading_icon() {
        $test = new Output_Test('loading_icon', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="loading_icon"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_settings_form() {
        $test = new Output_Test('start_settings_form', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="user_settings"><div class="content_title">Site Settings</div><form method="POST"><input type="hidden" name="hm_page_key" value="" /><table class="settings_table"><colgroup><col class="label_col"><col class="setting_col"></colgroup>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_page_setting() {
        $test = new Output_Test('start_page_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="start_page">First page after login</label></td><td><select id="start_page" name="start_page"><option value="none">None</option><option value="page=home">Home</option><option value="page=message_list&list_path=combined_inbox">Everything</option><option value="page=message_list&list_path=unread">Unread</option><option value="page=message_list&list_path=flagged">Flagged</option><option value="page=compose">Compose</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('start_page' => 'page=message_list&list_path=unread'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="start_page">First page after login</label></td><td><select id="start_page" name="start_page"><option value="none">None</option><option value="page=home">Home</option><option value="page=message_list&list_path=combined_inbox">Everything</option><option selected="selected" value="page=message_list&list_path=unread">Unread</option><option value="page=message_list&list_path=flagged">Flagged</option><option value="page=compose">Compose</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_list_style_setting() {
        $test = new Output_Test('list_style_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="list_style">Message list style</label></td><td><select id="list_style" name="list_style"><option value="email_style">Email</option><option value="news_style">News</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('list_style' => 'email_style'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="list_style">Message list style</label></td><td><select id="list_style" name="list_style"><option selected="selected" value="email_style">Email</option><option value="news_style">News</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_no_folder_icon_setting() {
        $test = new Output_Test('no_folder_icon_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="no_folder_icons">Hide folder list icons</label></td><td><input type="checkbox"  value="1" id="no_folder_icons" name="no_folder_icons" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('no_folder_icons' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="no_folder_icons">Hide folder list icons</label></td><td><input type="checkbox"  checked="checked" value="1" id="no_folder_icons" name="no_folder_icons" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_no_password_setting() {
        $test = new Output_Test('no_password_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="no_password_save">Don\'t save account passwords between logins</label></td><td><input type="checkbox"  value="1" id="no_password_save" name="no_password_save" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('no_password_save' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="no_password_save">Don\'t save account passwords between logins</label></td><td><input type="checkbox"  checked="checked" value="1" id="no_password_save" name="no_password_save" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_prompt_setting() {
        $test = new Output_Test('delete_prompt_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="disable_delete_prompt">Disable prompts when deleting</label></td><td><input type="checkbox"  value="1" id="disable_delete_prompt" name="disable_delete_prompt" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('disable_delete_prompt' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="disable_delete_prompt">Disable prompts when deleting</label></td><td><input type="checkbox"  checked="checked" value="1" id="disable_delete_prompt" name="disable_delete_prompt" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_flagged_settings() {
        $test = new Output_Test('start_flagged_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".flagged_setting" colspan="2" class="settings_subtitle"><img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="16" />Flagged</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_everything_settings() {
        $test = new Output_Test('start_everything_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".all_setting" colspan="2" class="settings_subtitle"><img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v1h8v-1h-8zm0%202v5.906c0%20.06.034.094.094.094h7.813c.06%200%20.094-.034.094-.094v-5.906h-2.969v1.031h-2.031v-1.031h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="16" />Everything</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_unread_settings() {
        $test = new Output_Test('start_unread_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".unread_setting" colspan="2" class="settings_subtitle"><img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1l4%202%204-2v-1h-8zm0%202v4h8v-4l-4%202-4-2z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="16" />Unread</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_all_email_settings() {
        $test = new Output_Test('start_all_email_settings', 'core');
        $test->handler_response = array('router_module_list' => array());
        $res = $test->run();
        $this->assertEquals(array('router_module_list' => array()), $res->output_response);
        $test->handler_response = array('router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".email_setting" colspan="2" class="settings_subtitle"><img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1l4%202%204-2v-1h-8zm0%202v4h8v-4l-4%202-4-2z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="16" />All Email</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_general_settings() {
        $test = new Output_Test('start_general_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".general_setting" colspan="2" class="settings_subtitle"><img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="16" />General</td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unread_source_max_setting() {
        $test = new Output_Test('unread_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_per_source">Max messages per source</label></td><td><input type="text" size="2" id="unread_per_source" name="unread_per_source" value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('unread_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_per_source">Max messages per source</label></td><td><input type="text" size="2" id="unread_per_source" name="unread_per_source" value="10" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unread_since_setting() {
        $test = new Output_Test('unread_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_since">Show messages received since</label></td><td><select name="unread_since" id="unread_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('unread_since' => '-1 week'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_since">Show messages received since</label></td><td><select name="unread_since" id="unread_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flagged_source_max_setting() {
        $test = new Output_Test('flagged_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_per_source">Max messages per source</label></td><td><input type="text" size="2" id="flagged_per_source" name="flagged_per_source" value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('flagged_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_per_source">Max messages per source</label></td><td><input type="text" size="2" id="flagged_per_source" name="flagged_per_source" value="10" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flagged_since_setting() {
        $test = new Output_Test('flagged_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_since">Show messages received since</label></td><td><select name="flagged_since" id="flagged_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('flagged_since' => '-1 week'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_since">Show messages received since</label></td><td><select name="flagged_since" id="flagged_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_email_source_max_setting() {
        $test = new Output_Test('all_email_source_max_setting', 'core');
        $test->handler_response = array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array());
        $res = $test->run();
        $this->assertEquals(array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array()), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="email_setting"><td><label for="all_email_per_source">Max messages per source</label></td><td><input type="text" size="2" id="all_email_per_source" name="all_email_per_source" value="10" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_source_max_setting() {
        $test = new Output_Test('all_source_max_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_per_source">Max messages per source</label></td><td><input type="text" size="2" id="all_per_source" name="all_per_source" value="20" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_per_source' => 10));
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_per_source">Max messages per source</label></td><td><input type="text" size="2" id="all_per_source" name="all_per_source" value="10" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_email_since_setting() {
        $test = new Output_Test('all_email_since_setting', 'core');
        $test->handler_response = array('user_settings' => array('all_email_since' => '-1 week'), 'router_module_list' => array());
        $res = $test->run();
        $this->assertEquals(array('user_settings' => array('all_email_since' => '-1 week'), 'router_module_list' => array()), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_email_since' => '-1 week'), 'router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="email_setting"><td><label for="all_email_since">Show messages received since</label></td><td><select name="all_email_since" id="all_email_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_since_setting() {
        $test = new Output_Test('all_since_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_since">Show messages received since</label></td><td><select name="all_since" id="all_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('all_since' => '-1 week'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_since">Show messages received since</label></td><td><select name="all_since" id="all_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_language_setting() {
        $test = new Output_Test('language_setting', 'core');
        $test->handler_response = array('language'=> 'en');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="language">Language</label></td><td><select id="language" name="language"><option value="pt-br">Brazilian Portuguese</option><option value="nl">Dutch</option><option selected="selected" value="en">English</option><option value="fr">French</option><option value="de">German</option><option value="hu">Hungarian</option><option value="it">Italian</option><option value="ja">Japanese</option><option value="ro">Romanian</option><option value="ru">Russian</option><option value="es">Spanish</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_timezone_setting() {
        $test = new Output_Test('timezone_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="timezone">Timezone</label></td><td><select id="timezone" name="timezone"><option value="Africa/Abidjan">Africa/Abidjan</option><option value="Africa/Accra">Africa/Accra</option><option value="Africa/Addis_Ababa">Africa/Addis_Ababa</option><option value="Africa/Algiers">Africa/Algiers</option><option value="Africa/Asmara">Africa/Asmara</option><option value="Africa/Bamako">Africa/Bamako</option><option value="Africa/Bangui">Africa/Bangui</option><option value="Africa/Banjul">Africa/Banjul</option><option value="Africa/Bissau">Africa/Bissau</option><option value="Africa/Blantyre">Africa/Blantyre</option><option value="Africa/Brazzaville">Africa/Brazzaville</option><option value="Africa/Bujumbura">Africa/Bujumbura</option><option value="Africa/Cairo">Africa/Cairo</option><option value="Africa/Casablanca">Africa/Casablanca</option><option value="Africa/Ceuta">Africa/Ceuta</option><option value="Africa/Conakry">Africa/Conakry</option><option value="Africa/Dakar">Africa/Dakar</option><option value="Africa/Dar_es_Salaam">Africa/Dar_es_Salaam</option><option value="Africa/Djibouti">Africa/Djibouti</option><option value="Africa/Douala">Africa/Douala</option><option value="Africa/El_Aaiun">Africa/El_Aaiun</option><option value="Africa/Freetown">Africa/Freetown</option><option value="Africa/Gaborone">Africa/Gaborone</option><option value="Africa/Harare">Africa/Harare</option><option value="Africa/Johannesburg">Africa/Johannesburg</option><option value="Africa/Juba">Africa/Juba</option><option value="Africa/Kampala">Africa/Kampala</option><option value="Africa/Khartoum">Africa/Khartoum</option><option value="Africa/Kigali">Africa/Kigali</option><option value="Africa/Kinshasa">Africa/Kinshasa</option><option value="Africa/Lagos">Africa/Lagos</option><option value="Africa/Libreville">Africa/Libreville</option><option value="Africa/Lome">Africa/Lome</option><option value="Africa/Luanda">Africa/Luanda</option><option value="Africa/Lubumbashi">Africa/Lubumbashi</option><option value="Africa/Lusaka">Africa/Lusaka</option><option value="Africa/Malabo">Africa/Malabo</option><option value="Africa/Maputo">Africa/Maputo</option><option value="Africa/Maseru">Africa/Maseru</option><option value="Africa/Mbabane">Africa/Mbabane</option><option value="Africa/Mogadishu">Africa/Mogadishu</option><option value="Africa/Monrovia">Africa/Monrovia</option><option value="Africa/Nairobi">Africa/Nairobi</option><option value="Africa/Ndjamena">Africa/Ndjamena</option><option value="Africa/Niamey">Africa/Niamey</option><option value="Africa/Nouakchott">Africa/Nouakchott</option><option value="Africa/Ouagadougou">Africa/Ouagadougou</option><option value="Africa/Porto-Novo">Africa/Porto-Novo</option><option value="Africa/Sao_Tome">Africa/Sao_Tome</option><option value="Africa/Tripoli">Africa/Tripoli</option><option value="Africa/Tunis">Africa/Tunis</option><option value="Africa/Windhoek">Africa/Windhoek</option><option value="America/Adak">America/Adak</option><option value="America/Anchorage">America/Anchorage</option><option value="America/Anguilla">America/Anguilla</option><option value="America/Antigua">America/Antigua</option><option value="America/Araguaina">America/Araguaina</option><option value="America/Argentina/Buenos_Aires">America/Argentina/Buenos_Aires</option><option value="America/Argentina/Catamarca">America/Argentina/Catamarca</option><option value="America/Argentina/Cordoba">America/Argentina/Cordoba</option><option value="America/Argentina/Jujuy">America/Argentina/Jujuy</option><option value="America/Argentina/La_Rioja">America/Argentina/La_Rioja</option><option value="America/Argentina/Mendoza">America/Argentina/Mendoza</option><option value="America/Argentina/Rio_Gallegos">America/Argentina/Rio_Gallegos</option><option value="America/Argentina/Salta">America/Argentina/Salta</option><option value="America/Argentina/San_Juan">America/Argentina/San_Juan</option><option value="America/Argentina/San_Luis">America/Argentina/San_Luis</option><option value="America/Argentina/Tucuman">America/Argentina/Tucuman</option><option value="America/Argentina/Ushuaia">America/Argentina/Ushuaia</option><option value="America/Aruba">America/Aruba</option><option value="America/Asuncion">America/Asuncion</option><option value="America/Atikokan">America/Atikokan</option><option value="America/Bahia">America/Bahia</option><option value="America/Bahia_Banderas">America/Bahia_Banderas</option><option value="America/Barbados">America/Barbados</option><option value="America/Belem">America/Belem</option><option value="America/Belize">America/Belize</option><option value="America/Blanc-Sablon">America/Blanc-Sablon</option><option value="America/Boa_Vista">America/Boa_Vista</option><option value="America/Bogota">America/Bogota</option><option value="America/Boise">America/Boise</option><option value="America/Cambridge_Bay">America/Cambridge_Bay</option><option value="America/Campo_Grande">America/Campo_Grande</option><option value="America/Cancun">America/Cancun</option><option value="America/Caracas">America/Caracas</option><option value="America/Cayenne">America/Cayenne</option><option value="America/Cayman">America/Cayman</option><option value="America/Chicago">America/Chicago</option><option value="America/Chihuahua">America/Chihuahua</option><option value="America/Costa_Rica">America/Costa_Rica</option><option value="America/Creston">America/Creston</option><option value="America/Cuiaba">America/Cuiaba</option><option value="America/Curacao">America/Curacao</option><option value="America/Danmarkshavn">America/Danmarkshavn</option><option value="America/Dawson">America/Dawson</option><option value="America/Dawson_Creek">America/Dawson_Creek</option><option value="America/Denver">America/Denver</option><option value="America/Detroit">America/Detroit</option><option value="America/Dominica">America/Dominica</option><option value="America/Edmonton">America/Edmonton</option><option value="America/Eirunepe">America/Eirunepe</option><option value="America/El_Salvador">America/El_Salvador</option><option value="America/Fort_Nelson">America/Fort_Nelson</option><option value="America/Fortaleza">America/Fortaleza</option><option value="America/Glace_Bay">America/Glace_Bay</option><option value="America/Godthab">America/Godthab</option><option value="America/Goose_Bay">America/Goose_Bay</option><option value="America/Grand_Turk">America/Grand_Turk</option><option value="America/Grenada">America/Grenada</option><option value="America/Guadeloupe">America/Guadeloupe</option><option value="America/Guatemala">America/Guatemala</option><option value="America/Guayaquil">America/Guayaquil</option><option value="America/Guyana">America/Guyana</option><option value="America/Halifax">America/Halifax</option><option value="America/Havana">America/Havana</option><option value="America/Hermosillo">America/Hermosillo</option><option value="America/Indiana/Indianapolis">America/Indiana/Indianapolis</option><option value="America/Indiana/Knox">America/Indiana/Knox</option><option value="America/Indiana/Marengo">America/Indiana/Marengo</option><option value="America/Indiana/Petersburg">America/Indiana/Petersburg</option><option value="America/Indiana/Tell_City">America/Indiana/Tell_City</option><option value="America/Indiana/Vevay">America/Indiana/Vevay</option><option value="America/Indiana/Vincennes">America/Indiana/Vincennes</option><option value="America/Indiana/Winamac">America/Indiana/Winamac</option><option value="America/Inuvik">America/Inuvik</option><option value="America/Iqaluit">America/Iqaluit</option><option value="America/Jamaica">America/Jamaica</option><option value="America/Juneau">America/Juneau</option><option value="America/Kentucky/Louisville">America/Kentucky/Louisville</option><option value="America/Kentucky/Monticello">America/Kentucky/Monticello</option><option value="America/Kralendijk">America/Kralendijk</option><option value="America/La_Paz">America/La_Paz</option><option value="America/Lima">America/Lima</option><option value="America/Los_Angeles">America/Los_Angeles</option><option value="America/Lower_Princes">America/Lower_Princes</option><option value="America/Maceio">America/Maceio</option><option value="America/Managua">America/Managua</option><option value="America/Manaus">America/Manaus</option><option value="America/Marigot">America/Marigot</option><option value="America/Martinique">America/Martinique</option><option value="America/Matamoros">America/Matamoros</option><option value="America/Mazatlan">America/Mazatlan</option><option value="America/Menominee">America/Menominee</option><option value="America/Merida">America/Merida</option><option value="America/Metlakatla">America/Metlakatla</option><option value="America/Mexico_City">America/Mexico_City</option><option value="America/Miquelon">America/Miquelon</option><option value="America/Moncton">America/Moncton</option><option value="America/Monterrey">America/Monterrey</option><option value="America/Montevideo">America/Montevideo</option><option value="America/Montserrat">America/Montserrat</option><option value="America/Nassau">America/Nassau</option><option value="America/New_York">America/New_York</option><option value="America/Nipigon">America/Nipigon</option><option value="America/Nome">America/Nome</option><option value="America/Noronha">America/Noronha</option><option value="America/North_Dakota/Beulah">America/North_Dakota/Beulah</option><option value="America/North_Dakota/Center">America/North_Dakota/Center</option><option value="America/North_Dakota/New_Salem">America/North_Dakota/New_Salem</option><option value="America/Ojinaga">America/Ojinaga</option><option value="America/Panama">America/Panama</option><option value="America/Pangnirtung">America/Pangnirtung</option><option value="America/Paramaribo">America/Paramaribo</option><option value="America/Phoenix">America/Phoenix</option><option value="America/Port-au-Prince">America/Port-au-Prince</option><option value="America/Port_of_Spain">America/Port_of_Spain</option><option value="America/Porto_Velho">America/Porto_Velho</option><option value="America/Puerto_Rico">America/Puerto_Rico</option><option value="America/Punta_Arenas">America/Punta_Arenas</option><option value="America/Rainy_River">America/Rainy_River</option><option value="America/Rankin_Inlet">America/Rankin_Inlet</option><option value="America/Recife">America/Recife</option><option value="America/Regina">America/Regina</option><option value="America/Resolute">America/Resolute</option><option value="America/Rio_Branco">America/Rio_Branco</option><option value="America/Santarem">America/Santarem</option><option value="America/Santiago">America/Santiago</option><option value="America/Santo_Domingo">America/Santo_Domingo</option><option value="America/Sao_Paulo">America/Sao_Paulo</option><option value="America/Scoresbysund">America/Scoresbysund</option><option value="America/Sitka">America/Sitka</option><option value="America/St_Barthelemy">America/St_Barthelemy</option><option value="America/St_Johns">America/St_Johns</option><option value="America/St_Kitts">America/St_Kitts</option><option value="America/St_Lucia">America/St_Lucia</option><option value="America/St_Thomas">America/St_Thomas</option><option value="America/St_Vincent">America/St_Vincent</option><option value="America/Swift_Current">America/Swift_Current</option><option value="America/Tegucigalpa">America/Tegucigalpa</option><option value="America/Thule">America/Thule</option><option value="America/Thunder_Bay">America/Thunder_Bay</option><option value="America/Tijuana">America/Tijuana</option><option value="America/Toronto">America/Toronto</option><option value="America/Tortola">America/Tortola</option><option value="America/Vancouver">America/Vancouver</option><option value="America/Whitehorse">America/Whitehorse</option><option value="America/Winnipeg">America/Winnipeg</option><option value="America/Yakutat">America/Yakutat</option><option value="America/Yellowknife">America/Yellowknife</option><option value="Antarctica/Casey">Antarctica/Casey</option><option value="Antarctica/Davis">Antarctica/Davis</option><option value="Antarctica/DumontDUrville">Antarctica/DumontDUrville</option><option value="Antarctica/Macquarie">Antarctica/Macquarie</option><option value="Antarctica/Mawson">Antarctica/Mawson</option><option value="Antarctica/McMurdo">Antarctica/McMurdo</option><option value="Antarctica/Palmer">Antarctica/Palmer</option><option value="Antarctica/Rothera">Antarctica/Rothera</option><option value="Antarctica/Syowa">Antarctica/Syowa</option><option value="Antarctica/Troll">Antarctica/Troll</option><option value="Antarctica/Vostok">Antarctica/Vostok</option><option value="Arctic/Longyearbyen">Arctic/Longyearbyen</option><option value="Asia/Aden">Asia/Aden</option><option value="Asia/Almaty">Asia/Almaty</option><option value="Asia/Amman">Asia/Amman</option><option value="Asia/Anadyr">Asia/Anadyr</option><option value="Asia/Aqtau">Asia/Aqtau</option><option value="Asia/Aqtobe">Asia/Aqtobe</option><option value="Asia/Ashgabat">Asia/Ashgabat</option><option value="Asia/Atyrau">Asia/Atyrau</option><option value="Asia/Baghdad">Asia/Baghdad</option><option value="Asia/Bahrain">Asia/Bahrain</option><option value="Asia/Baku">Asia/Baku</option><option value="Asia/Bangkok">Asia/Bangkok</option><option value="Asia/Barnaul">Asia/Barnaul</option><option value="Asia/Beirut">Asia/Beirut</option><option value="Asia/Bishkek">Asia/Bishkek</option><option value="Asia/Brunei">Asia/Brunei</option><option value="Asia/Chita">Asia/Chita</option><option value="Asia/Choibalsan">Asia/Choibalsan</option><option value="Asia/Colombo">Asia/Colombo</option><option value="Asia/Damascus">Asia/Damascus</option><option value="Asia/Dhaka">Asia/Dhaka</option><option value="Asia/Dili">Asia/Dili</option><option value="Asia/Dubai">Asia/Dubai</option><option value="Asia/Dushanbe">Asia/Dushanbe</option><option value="Asia/Famagusta">Asia/Famagusta</option><option value="Asia/Gaza">Asia/Gaza</option><option value="Asia/Hebron">Asia/Hebron</option><option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh</option><option value="Asia/Hong_Kong">Asia/Hong_Kong</option><option value="Asia/Hovd">Asia/Hovd</option><option value="Asia/Irkutsk">Asia/Irkutsk</option><option value="Asia/Jakarta">Asia/Jakarta</option><option value="Asia/Jayapura">Asia/Jayapura</option><option value="Asia/Jerusalem">Asia/Jerusalem</option><option value="Asia/Kabul">Asia/Kabul</option><option value="Asia/Kamchatka">Asia/Kamchatka</option><option value="Asia/Karachi">Asia/Karachi</option><option value="Asia/Kathmandu">Asia/Kathmandu</option><option value="Asia/Khandyga">Asia/Khandyga</option><option value="Asia/Kolkata">Asia/Kolkata</option><option value="Asia/Krasnoyarsk">Asia/Krasnoyarsk</option><option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur</option><option value="Asia/Kuching">Asia/Kuching</option><option value="Asia/Kuwait">Asia/Kuwait</option><option value="Asia/Macau">Asia/Macau</option><option value="Asia/Magadan">Asia/Magadan</option><option value="Asia/Makassar">Asia/Makassar</option><option value="Asia/Manila">Asia/Manila</option><option value="Asia/Muscat">Asia/Muscat</option><option value="Asia/Nicosia">Asia/Nicosia</option><option value="Asia/Novokuznetsk">Asia/Novokuznetsk</option><option value="Asia/Novosibirsk">Asia/Novosibirsk</option><option value="Asia/Omsk">Asia/Omsk</option><option value="Asia/Oral">Asia/Oral</option><option value="Asia/Phnom_Penh">Asia/Phnom_Penh</option><option value="Asia/Pontianak">Asia/Pontianak</option><option value="Asia/Pyongyang">Asia/Pyongyang</option><option value="Asia/Qatar">Asia/Qatar</option><option value="Asia/Qyzylorda">Asia/Qyzylorda</option><option value="Asia/Riyadh">Asia/Riyadh</option><option value="Asia/Sakhalin">Asia/Sakhalin</option><option value="Asia/Samarkand">Asia/Samarkand</option><option value="Asia/Seoul">Asia/Seoul</option><option value="Asia/Shanghai">Asia/Shanghai</option><option value="Asia/Singapore">Asia/Singapore</option><option value="Asia/Srednekolymsk">Asia/Srednekolymsk</option><option value="Asia/Taipei">Asia/Taipei</option><option value="Asia/Tashkent">Asia/Tashkent</option><option value="Asia/Tbilisi">Asia/Tbilisi</option><option value="Asia/Tehran">Asia/Tehran</option><option value="Asia/Thimphu">Asia/Thimphu</option><option value="Asia/Tokyo">Asia/Tokyo</option><option value="Asia/Tomsk">Asia/Tomsk</option><option value="Asia/Ulaanbaatar">Asia/Ulaanbaatar</option><option value="Asia/Urumqi">Asia/Urumqi</option><option value="Asia/Ust-Nera">Asia/Ust-Nera</option><option value="Asia/Vientiane">Asia/Vientiane</option><option value="Asia/Vladivostok">Asia/Vladivostok</option><option value="Asia/Yakutsk">Asia/Yakutsk</option><option value="Asia/Yangon">Asia/Yangon</option><option value="Asia/Yekaterinburg">Asia/Yekaterinburg</option><option value="Asia/Yerevan">Asia/Yerevan</option><option value="Atlantic/Azores">Atlantic/Azores</option><option value="Atlantic/Bermuda">Atlantic/Bermuda</option><option value="Atlantic/Canary">Atlantic/Canary</option><option value="Atlantic/Cape_Verde">Atlantic/Cape_Verde</option><option value="Atlantic/Faroe">Atlantic/Faroe</option><option value="Atlantic/Madeira">Atlantic/Madeira</option><option value="Atlantic/Reykjavik">Atlantic/Reykjavik</option><option value="Atlantic/South_Georgia">Atlantic/South_Georgia</option><option value="Atlantic/St_Helena">Atlantic/St_Helena</option><option value="Atlantic/Stanley">Atlantic/Stanley</option><option value="Australia/Adelaide">Australia/Adelaide</option><option value="Australia/Brisbane">Australia/Brisbane</option><option value="Australia/Broken_Hill">Australia/Broken_Hill</option><option value="Australia/Currie">Australia/Currie</option><option value="Australia/Darwin">Australia/Darwin</option><option value="Australia/Eucla">Australia/Eucla</option><option value="Australia/Hobart">Australia/Hobart</option><option value="Australia/Lindeman">Australia/Lindeman</option><option value="Australia/Lord_Howe">Australia/Lord_Howe</option><option value="Australia/Melbourne">Australia/Melbourne</option><option value="Australia/Perth">Australia/Perth</option><option value="Australia/Sydney">Australia/Sydney</option><option value="Europe/Amsterdam">Europe/Amsterdam</option><option value="Europe/Andorra">Europe/Andorra</option><option value="Europe/Astrakhan">Europe/Astrakhan</option><option value="Europe/Athens">Europe/Athens</option><option value="Europe/Belgrade">Europe/Belgrade</option><option value="Europe/Berlin">Europe/Berlin</option><option value="Europe/Bratislava">Europe/Bratislava</option><option value="Europe/Brussels">Europe/Brussels</option><option value="Europe/Bucharest">Europe/Bucharest</option><option value="Europe/Budapest">Europe/Budapest</option><option value="Europe/Busingen">Europe/Busingen</option><option value="Europe/Chisinau">Europe/Chisinau</option><option value="Europe/Copenhagen">Europe/Copenhagen</option><option value="Europe/Dublin">Europe/Dublin</option><option value="Europe/Gibraltar">Europe/Gibraltar</option><option value="Europe/Guernsey">Europe/Guernsey</option><option value="Europe/Helsinki">Europe/Helsinki</option><option value="Europe/Isle_of_Man">Europe/Isle_of_Man</option><option value="Europe/Istanbul">Europe/Istanbul</option><option value="Europe/Jersey">Europe/Jersey</option><option value="Europe/Kaliningrad">Europe/Kaliningrad</option><option value="Europe/Kiev">Europe/Kiev</option><option value="Europe/Kirov">Europe/Kirov</option><option value="Europe/Lisbon">Europe/Lisbon</option><option value="Europe/Ljubljana">Europe/Ljubljana</option><option value="Europe/London">Europe/London</option><option value="Europe/Luxembourg">Europe/Luxembourg</option><option value="Europe/Madrid">Europe/Madrid</option><option value="Europe/Malta">Europe/Malta</option><option value="Europe/Mariehamn">Europe/Mariehamn</option><option value="Europe/Minsk">Europe/Minsk</option><option value="Europe/Monaco">Europe/Monaco</option><option value="Europe/Moscow">Europe/Moscow</option><option value="Europe/Oslo">Europe/Oslo</option><option value="Europe/Paris">Europe/Paris</option><option value="Europe/Podgorica">Europe/Podgorica</option><option value="Europe/Prague">Europe/Prague</option><option value="Europe/Riga">Europe/Riga</option><option value="Europe/Rome">Europe/Rome</option><option value="Europe/Samara">Europe/Samara</option><option value="Europe/San_Marino">Europe/San_Marino</option><option value="Europe/Sarajevo">Europe/Sarajevo</option><option value="Europe/Saratov">Europe/Saratov</option><option value="Europe/Simferopol">Europe/Simferopol</option><option value="Europe/Skopje">Europe/Skopje</option><option value="Europe/Sofia">Europe/Sofia</option><option value="Europe/Stockholm">Europe/Stockholm</option><option value="Europe/Tallinn">Europe/Tallinn</option><option value="Europe/Tirane">Europe/Tirane</option><option value="Europe/Ulyanovsk">Europe/Ulyanovsk</option><option value="Europe/Uzhgorod">Europe/Uzhgorod</option><option value="Europe/Vaduz">Europe/Vaduz</option><option value="Europe/Vatican">Europe/Vatican</option><option value="Europe/Vienna">Europe/Vienna</option><option value="Europe/Vilnius">Europe/Vilnius</option><option value="Europe/Volgograd">Europe/Volgograd</option><option value="Europe/Warsaw">Europe/Warsaw</option><option value="Europe/Zagreb">Europe/Zagreb</option><option value="Europe/Zaporozhye">Europe/Zaporozhye</option><option value="Europe/Zurich">Europe/Zurich</option><option value="Indian/Antananarivo">Indian/Antananarivo</option><option value="Indian/Chagos">Indian/Chagos</option><option value="Indian/Christmas">Indian/Christmas</option><option value="Indian/Cocos">Indian/Cocos</option><option value="Indian/Comoro">Indian/Comoro</option><option value="Indian/Kerguelen">Indian/Kerguelen</option><option value="Indian/Mahe">Indian/Mahe</option><option value="Indian/Maldives">Indian/Maldives</option><option value="Indian/Mauritius">Indian/Mauritius</option><option value="Indian/Mayotte">Indian/Mayotte</option><option value="Indian/Reunion">Indian/Reunion</option><option value="Pacific/Apia">Pacific/Apia</option><option value="Pacific/Auckland">Pacific/Auckland</option><option value="Pacific/Bougainville">Pacific/Bougainville</option><option value="Pacific/Chatham">Pacific/Chatham</option><option value="Pacific/Chuuk">Pacific/Chuuk</option><option value="Pacific/Easter">Pacific/Easter</option><option value="Pacific/Efate">Pacific/Efate</option><option value="Pacific/Enderbury">Pacific/Enderbury</option><option value="Pacific/Fakaofo">Pacific/Fakaofo</option><option value="Pacific/Fiji">Pacific/Fiji</option><option value="Pacific/Funafuti">Pacific/Funafuti</option><option value="Pacific/Galapagos">Pacific/Galapagos</option><option value="Pacific/Gambier">Pacific/Gambier</option><option value="Pacific/Guadalcanal">Pacific/Guadalcanal</option><option value="Pacific/Guam">Pacific/Guam</option><option value="Pacific/Honolulu">Pacific/Honolulu</option><option value="Pacific/Kiritimati">Pacific/Kiritimati</option><option value="Pacific/Kosrae">Pacific/Kosrae</option><option value="Pacific/Kwajalein">Pacific/Kwajalein</option><option value="Pacific/Majuro">Pacific/Majuro</option><option value="Pacific/Marquesas">Pacific/Marquesas</option><option value="Pacific/Midway">Pacific/Midway</option><option value="Pacific/Nauru">Pacific/Nauru</option><option value="Pacific/Niue">Pacific/Niue</option><option value="Pacific/Norfolk">Pacific/Norfolk</option><option value="Pacific/Noumea">Pacific/Noumea</option><option value="Pacific/Pago_Pago">Pacific/Pago_Pago</option><option value="Pacific/Palau">Pacific/Palau</option><option value="Pacific/Pitcairn">Pacific/Pitcairn</option><option value="Pacific/Pohnpei">Pacific/Pohnpei</option><option value="Pacific/Port_Moresby">Pacific/Port_Moresby</option><option value="Pacific/Rarotonga">Pacific/Rarotonga</option><option value="Pacific/Saipan">Pacific/Saipan</option><option value="Pacific/Tahiti">Pacific/Tahiti</option><option value="Pacific/Tarawa">Pacific/Tarawa</option><option value="Pacific/Tongatapu">Pacific/Tongatapu</option><option value="Pacific/Wake">Pacific/Wake</option><option value="Pacific/Wallis">Pacific/Wallis</option><option value="UTC">UTC</option></select></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('timezone' => 'America/Chicago'));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="timezone">Timezone</label></td><td><select id="timezone" name="timezone"><option value="Africa/Abidjan">Africa/Abidjan</option><option value="Africa/Accra">Africa/Accra</option><option value="Africa/Addis_Ababa">Africa/Addis_Ababa</option><option value="Africa/Algiers">Africa/Algiers</option><option value="Africa/Asmara">Africa/Asmara</option><option value="Africa/Bamako">Africa/Bamako</option><option value="Africa/Bangui">Africa/Bangui</option><option value="Africa/Banjul">Africa/Banjul</option><option value="Africa/Bissau">Africa/Bissau</option><option value="Africa/Blantyre">Africa/Blantyre</option><option value="Africa/Brazzaville">Africa/Brazzaville</option><option value="Africa/Bujumbura">Africa/Bujumbura</option><option value="Africa/Cairo">Africa/Cairo</option><option value="Africa/Casablanca">Africa/Casablanca</option><option value="Africa/Ceuta">Africa/Ceuta</option><option value="Africa/Conakry">Africa/Conakry</option><option value="Africa/Dakar">Africa/Dakar</option><option value="Africa/Dar_es_Salaam">Africa/Dar_es_Salaam</option><option value="Africa/Djibouti">Africa/Djibouti</option><option value="Africa/Douala">Africa/Douala</option><option value="Africa/El_Aaiun">Africa/El_Aaiun</option><option value="Africa/Freetown">Africa/Freetown</option><option value="Africa/Gaborone">Africa/Gaborone</option><option value="Africa/Harare">Africa/Harare</option><option value="Africa/Johannesburg">Africa/Johannesburg</option><option value="Africa/Juba">Africa/Juba</option><option value="Africa/Kampala">Africa/Kampala</option><option value="Africa/Khartoum">Africa/Khartoum</option><option value="Africa/Kigali">Africa/Kigali</option><option value="Africa/Kinshasa">Africa/Kinshasa</option><option value="Africa/Lagos">Africa/Lagos</option><option value="Africa/Libreville">Africa/Libreville</option><option value="Africa/Lome">Africa/Lome</option><option value="Africa/Luanda">Africa/Luanda</option><option value="Africa/Lubumbashi">Africa/Lubumbashi</option><option value="Africa/Lusaka">Africa/Lusaka</option><option value="Africa/Malabo">Africa/Malabo</option><option value="Africa/Maputo">Africa/Maputo</option><option value="Africa/Maseru">Africa/Maseru</option><option value="Africa/Mbabane">Africa/Mbabane</option><option value="Africa/Mogadishu">Africa/Mogadishu</option><option value="Africa/Monrovia">Africa/Monrovia</option><option value="Africa/Nairobi">Africa/Nairobi</option><option value="Africa/Ndjamena">Africa/Ndjamena</option><option value="Africa/Niamey">Africa/Niamey</option><option value="Africa/Nouakchott">Africa/Nouakchott</option><option value="Africa/Ouagadougou">Africa/Ouagadougou</option><option value="Africa/Porto-Novo">Africa/Porto-Novo</option><option value="Africa/Sao_Tome">Africa/Sao_Tome</option><option value="Africa/Tripoli">Africa/Tripoli</option><option value="Africa/Tunis">Africa/Tunis</option><option value="Africa/Windhoek">Africa/Windhoek</option><option value="America/Adak">America/Adak</option><option value="America/Anchorage">America/Anchorage</option><option value="America/Anguilla">America/Anguilla</option><option value="America/Antigua">America/Antigua</option><option value="America/Araguaina">America/Araguaina</option><option value="America/Argentina/Buenos_Aires">America/Argentina/Buenos_Aires</option><option value="America/Argentina/Catamarca">America/Argentina/Catamarca</option><option value="America/Argentina/Cordoba">America/Argentina/Cordoba</option><option value="America/Argentina/Jujuy">America/Argentina/Jujuy</option><option value="America/Argentina/La_Rioja">America/Argentina/La_Rioja</option><option value="America/Argentina/Mendoza">America/Argentina/Mendoza</option><option value="America/Argentina/Rio_Gallegos">America/Argentina/Rio_Gallegos</option><option value="America/Argentina/Salta">America/Argentina/Salta</option><option value="America/Argentina/San_Juan">America/Argentina/San_Juan</option><option value="America/Argentina/San_Luis">America/Argentina/San_Luis</option><option value="America/Argentina/Tucuman">America/Argentina/Tucuman</option><option value="America/Argentina/Ushuaia">America/Argentina/Ushuaia</option><option value="America/Aruba">America/Aruba</option><option value="America/Asuncion">America/Asuncion</option><option value="America/Atikokan">America/Atikokan</option><option value="America/Bahia">America/Bahia</option><option value="America/Bahia_Banderas">America/Bahia_Banderas</option><option value="America/Barbados">America/Barbados</option><option value="America/Belem">America/Belem</option><option value="America/Belize">America/Belize</option><option value="America/Blanc-Sablon">America/Blanc-Sablon</option><option value="America/Boa_Vista">America/Boa_Vista</option><option value="America/Bogota">America/Bogota</option><option value="America/Boise">America/Boise</option><option value="America/Cambridge_Bay">America/Cambridge_Bay</option><option value="America/Campo_Grande">America/Campo_Grande</option><option value="America/Cancun">America/Cancun</option><option value="America/Caracas">America/Caracas</option><option value="America/Cayenne">America/Cayenne</option><option value="America/Cayman">America/Cayman</option><option selected="selected" value="America/Chicago">America/Chicago</option><option value="America/Chihuahua">America/Chihuahua</option><option value="America/Costa_Rica">America/Costa_Rica</option><option value="America/Creston">America/Creston</option><option value="America/Cuiaba">America/Cuiaba</option><option value="America/Curacao">America/Curacao</option><option value="America/Danmarkshavn">America/Danmarkshavn</option><option value="America/Dawson">America/Dawson</option><option value="America/Dawson_Creek">America/Dawson_Creek</option><option value="America/Denver">America/Denver</option><option value="America/Detroit">America/Detroit</option><option value="America/Dominica">America/Dominica</option><option value="America/Edmonton">America/Edmonton</option><option value="America/Eirunepe">America/Eirunepe</option><option value="America/El_Salvador">America/El_Salvador</option><option value="America/Fort_Nelson">America/Fort_Nelson</option><option value="America/Fortaleza">America/Fortaleza</option><option value="America/Glace_Bay">America/Glace_Bay</option><option value="America/Godthab">America/Godthab</option><option value="America/Goose_Bay">America/Goose_Bay</option><option value="America/Grand_Turk">America/Grand_Turk</option><option value="America/Grenada">America/Grenada</option><option value="America/Guadeloupe">America/Guadeloupe</option><option value="America/Guatemala">America/Guatemala</option><option value="America/Guayaquil">America/Guayaquil</option><option value="America/Guyana">America/Guyana</option><option value="America/Halifax">America/Halifax</option><option value="America/Havana">America/Havana</option><option value="America/Hermosillo">America/Hermosillo</option><option value="America/Indiana/Indianapolis">America/Indiana/Indianapolis</option><option value="America/Indiana/Knox">America/Indiana/Knox</option><option value="America/Indiana/Marengo">America/Indiana/Marengo</option><option value="America/Indiana/Petersburg">America/Indiana/Petersburg</option><option value="America/Indiana/Tell_City">America/Indiana/Tell_City</option><option value="America/Indiana/Vevay">America/Indiana/Vevay</option><option value="America/Indiana/Vincennes">America/Indiana/Vincennes</option><option value="America/Indiana/Winamac">America/Indiana/Winamac</option><option value="America/Inuvik">America/Inuvik</option><option value="America/Iqaluit">America/Iqaluit</option><option value="America/Jamaica">America/Jamaica</option><option value="America/Juneau">America/Juneau</option><option value="America/Kentucky/Louisville">America/Kentucky/Louisville</option><option value="America/Kentucky/Monticello">America/Kentucky/Monticello</option><option value="America/Kralendijk">America/Kralendijk</option><option value="America/La_Paz">America/La_Paz</option><option value="America/Lima">America/Lima</option><option value="America/Los_Angeles">America/Los_Angeles</option><option value="America/Lower_Princes">America/Lower_Princes</option><option value="America/Maceio">America/Maceio</option><option value="America/Managua">America/Managua</option><option value="America/Manaus">America/Manaus</option><option value="America/Marigot">America/Marigot</option><option value="America/Martinique">America/Martinique</option><option value="America/Matamoros">America/Matamoros</option><option value="America/Mazatlan">America/Mazatlan</option><option value="America/Menominee">America/Menominee</option><option value="America/Merida">America/Merida</option><option value="America/Metlakatla">America/Metlakatla</option><option value="America/Mexico_City">America/Mexico_City</option><option value="America/Miquelon">America/Miquelon</option><option value="America/Moncton">America/Moncton</option><option value="America/Monterrey">America/Monterrey</option><option value="America/Montevideo">America/Montevideo</option><option value="America/Montserrat">America/Montserrat</option><option value="America/Nassau">America/Nassau</option><option value="America/New_York">America/New_York</option><option value="America/Nipigon">America/Nipigon</option><option value="America/Nome">America/Nome</option><option value="America/Noronha">America/Noronha</option><option value="America/North_Dakota/Beulah">America/North_Dakota/Beulah</option><option value="America/North_Dakota/Center">America/North_Dakota/Center</option><option value="America/North_Dakota/New_Salem">America/North_Dakota/New_Salem</option><option value="America/Ojinaga">America/Ojinaga</option><option value="America/Panama">America/Panama</option><option value="America/Pangnirtung">America/Pangnirtung</option><option value="America/Paramaribo">America/Paramaribo</option><option value="America/Phoenix">America/Phoenix</option><option value="America/Port-au-Prince">America/Port-au-Prince</option><option value="America/Port_of_Spain">America/Port_of_Spain</option><option value="America/Porto_Velho">America/Porto_Velho</option><option value="America/Puerto_Rico">America/Puerto_Rico</option><option value="America/Punta_Arenas">America/Punta_Arenas</option><option value="America/Rainy_River">America/Rainy_River</option><option value="America/Rankin_Inlet">America/Rankin_Inlet</option><option value="America/Recife">America/Recife</option><option value="America/Regina">America/Regina</option><option value="America/Resolute">America/Resolute</option><option value="America/Rio_Branco">America/Rio_Branco</option><option value="America/Santarem">America/Santarem</option><option value="America/Santiago">America/Santiago</option><option value="America/Santo_Domingo">America/Santo_Domingo</option><option value="America/Sao_Paulo">America/Sao_Paulo</option><option value="America/Scoresbysund">America/Scoresbysund</option><option value="America/Sitka">America/Sitka</option><option value="America/St_Barthelemy">America/St_Barthelemy</option><option value="America/St_Johns">America/St_Johns</option><option value="America/St_Kitts">America/St_Kitts</option><option value="America/St_Lucia">America/St_Lucia</option><option value="America/St_Thomas">America/St_Thomas</option><option value="America/St_Vincent">America/St_Vincent</option><option value="America/Swift_Current">America/Swift_Current</option><option value="America/Tegucigalpa">America/Tegucigalpa</option><option value="America/Thule">America/Thule</option><option value="America/Thunder_Bay">America/Thunder_Bay</option><option value="America/Tijuana">America/Tijuana</option><option value="America/Toronto">America/Toronto</option><option value="America/Tortola">America/Tortola</option><option value="America/Vancouver">America/Vancouver</option><option value="America/Whitehorse">America/Whitehorse</option><option value="America/Winnipeg">America/Winnipeg</option><option value="America/Yakutat">America/Yakutat</option><option value="America/Yellowknife">America/Yellowknife</option><option value="Antarctica/Casey">Antarctica/Casey</option><option value="Antarctica/Davis">Antarctica/Davis</option><option value="Antarctica/DumontDUrville">Antarctica/DumontDUrville</option><option value="Antarctica/Macquarie">Antarctica/Macquarie</option><option value="Antarctica/Mawson">Antarctica/Mawson</option><option value="Antarctica/McMurdo">Antarctica/McMurdo</option><option value="Antarctica/Palmer">Antarctica/Palmer</option><option value="Antarctica/Rothera">Antarctica/Rothera</option><option value="Antarctica/Syowa">Antarctica/Syowa</option><option value="Antarctica/Troll">Antarctica/Troll</option><option value="Antarctica/Vostok">Antarctica/Vostok</option><option value="Arctic/Longyearbyen">Arctic/Longyearbyen</option><option value="Asia/Aden">Asia/Aden</option><option value="Asia/Almaty">Asia/Almaty</option><option value="Asia/Amman">Asia/Amman</option><option value="Asia/Anadyr">Asia/Anadyr</option><option value="Asia/Aqtau">Asia/Aqtau</option><option value="Asia/Aqtobe">Asia/Aqtobe</option><option value="Asia/Ashgabat">Asia/Ashgabat</option><option value="Asia/Atyrau">Asia/Atyrau</option><option value="Asia/Baghdad">Asia/Baghdad</option><option value="Asia/Bahrain">Asia/Bahrain</option><option value="Asia/Baku">Asia/Baku</option><option value="Asia/Bangkok">Asia/Bangkok</option><option value="Asia/Barnaul">Asia/Barnaul</option><option value="Asia/Beirut">Asia/Beirut</option><option value="Asia/Bishkek">Asia/Bishkek</option><option value="Asia/Brunei">Asia/Brunei</option><option value="Asia/Chita">Asia/Chita</option><option value="Asia/Choibalsan">Asia/Choibalsan</option><option value="Asia/Colombo">Asia/Colombo</option><option value="Asia/Damascus">Asia/Damascus</option><option value="Asia/Dhaka">Asia/Dhaka</option><option value="Asia/Dili">Asia/Dili</option><option value="Asia/Dubai">Asia/Dubai</option><option value="Asia/Dushanbe">Asia/Dushanbe</option><option value="Asia/Famagusta">Asia/Famagusta</option><option value="Asia/Gaza">Asia/Gaza</option><option value="Asia/Hebron">Asia/Hebron</option><option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh</option><option value="Asia/Hong_Kong">Asia/Hong_Kong</option><option value="Asia/Hovd">Asia/Hovd</option><option value="Asia/Irkutsk">Asia/Irkutsk</option><option value="Asia/Jakarta">Asia/Jakarta</option><option value="Asia/Jayapura">Asia/Jayapura</option><option value="Asia/Jerusalem">Asia/Jerusalem</option><option value="Asia/Kabul">Asia/Kabul</option><option value="Asia/Kamchatka">Asia/Kamchatka</option><option value="Asia/Karachi">Asia/Karachi</option><option value="Asia/Kathmandu">Asia/Kathmandu</option><option value="Asia/Khandyga">Asia/Khandyga</option><option value="Asia/Kolkata">Asia/Kolkata</option><option value="Asia/Krasnoyarsk">Asia/Krasnoyarsk</option><option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur</option><option value="Asia/Kuching">Asia/Kuching</option><option value="Asia/Kuwait">Asia/Kuwait</option><option value="Asia/Macau">Asia/Macau</option><option value="Asia/Magadan">Asia/Magadan</option><option value="Asia/Makassar">Asia/Makassar</option><option value="Asia/Manila">Asia/Manila</option><option value="Asia/Muscat">Asia/Muscat</option><option value="Asia/Nicosia">Asia/Nicosia</option><option value="Asia/Novokuznetsk">Asia/Novokuznetsk</option><option value="Asia/Novosibirsk">Asia/Novosibirsk</option><option value="Asia/Omsk">Asia/Omsk</option><option value="Asia/Oral">Asia/Oral</option><option value="Asia/Phnom_Penh">Asia/Phnom_Penh</option><option value="Asia/Pontianak">Asia/Pontianak</option><option value="Asia/Pyongyang">Asia/Pyongyang</option><option value="Asia/Qatar">Asia/Qatar</option><option value="Asia/Qyzylorda">Asia/Qyzylorda</option><option value="Asia/Riyadh">Asia/Riyadh</option><option value="Asia/Sakhalin">Asia/Sakhalin</option><option value="Asia/Samarkand">Asia/Samarkand</option><option value="Asia/Seoul">Asia/Seoul</option><option value="Asia/Shanghai">Asia/Shanghai</option><option value="Asia/Singapore">Asia/Singapore</option><option value="Asia/Srednekolymsk">Asia/Srednekolymsk</option><option value="Asia/Taipei">Asia/Taipei</option><option value="Asia/Tashkent">Asia/Tashkent</option><option value="Asia/Tbilisi">Asia/Tbilisi</option><option value="Asia/Tehran">Asia/Tehran</option><option value="Asia/Thimphu">Asia/Thimphu</option><option value="Asia/Tokyo">Asia/Tokyo</option><option value="Asia/Tomsk">Asia/Tomsk</option><option value="Asia/Ulaanbaatar">Asia/Ulaanbaatar</option><option value="Asia/Urumqi">Asia/Urumqi</option><option value="Asia/Ust-Nera">Asia/Ust-Nera</option><option value="Asia/Vientiane">Asia/Vientiane</option><option value="Asia/Vladivostok">Asia/Vladivostok</option><option value="Asia/Yakutsk">Asia/Yakutsk</option><option value="Asia/Yangon">Asia/Yangon</option><option value="Asia/Yekaterinburg">Asia/Yekaterinburg</option><option value="Asia/Yerevan">Asia/Yerevan</option><option value="Atlantic/Azores">Atlantic/Azores</option><option value="Atlantic/Bermuda">Atlantic/Bermuda</option><option value="Atlantic/Canary">Atlantic/Canary</option><option value="Atlantic/Cape_Verde">Atlantic/Cape_Verde</option><option value="Atlantic/Faroe">Atlantic/Faroe</option><option value="Atlantic/Madeira">Atlantic/Madeira</option><option value="Atlantic/Reykjavik">Atlantic/Reykjavik</option><option value="Atlantic/South_Georgia">Atlantic/South_Georgia</option><option value="Atlantic/St_Helena">Atlantic/St_Helena</option><option value="Atlantic/Stanley">Atlantic/Stanley</option><option value="Australia/Adelaide">Australia/Adelaide</option><option value="Australia/Brisbane">Australia/Brisbane</option><option value="Australia/Broken_Hill">Australia/Broken_Hill</option><option value="Australia/Currie">Australia/Currie</option><option value="Australia/Darwin">Australia/Darwin</option><option value="Australia/Eucla">Australia/Eucla</option><option value="Australia/Hobart">Australia/Hobart</option><option value="Australia/Lindeman">Australia/Lindeman</option><option value="Australia/Lord_Howe">Australia/Lord_Howe</option><option value="Australia/Melbourne">Australia/Melbourne</option><option value="Australia/Perth">Australia/Perth</option><option value="Australia/Sydney">Australia/Sydney</option><option value="Europe/Amsterdam">Europe/Amsterdam</option><option value="Europe/Andorra">Europe/Andorra</option><option value="Europe/Astrakhan">Europe/Astrakhan</option><option value="Europe/Athens">Europe/Athens</option><option value="Europe/Belgrade">Europe/Belgrade</option><option value="Europe/Berlin">Europe/Berlin</option><option value="Europe/Bratislava">Europe/Bratislava</option><option value="Europe/Brussels">Europe/Brussels</option><option value="Europe/Bucharest">Europe/Bucharest</option><option value="Europe/Budapest">Europe/Budapest</option><option value="Europe/Busingen">Europe/Busingen</option><option value="Europe/Chisinau">Europe/Chisinau</option><option value="Europe/Copenhagen">Europe/Copenhagen</option><option value="Europe/Dublin">Europe/Dublin</option><option value="Europe/Gibraltar">Europe/Gibraltar</option><option value="Europe/Guernsey">Europe/Guernsey</option><option value="Europe/Helsinki">Europe/Helsinki</option><option value="Europe/Isle_of_Man">Europe/Isle_of_Man</option><option value="Europe/Istanbul">Europe/Istanbul</option><option value="Europe/Jersey">Europe/Jersey</option><option value="Europe/Kaliningrad">Europe/Kaliningrad</option><option value="Europe/Kiev">Europe/Kiev</option><option value="Europe/Kirov">Europe/Kirov</option><option value="Europe/Lisbon">Europe/Lisbon</option><option value="Europe/Ljubljana">Europe/Ljubljana</option><option value="Europe/London">Europe/London</option><option value="Europe/Luxembourg">Europe/Luxembourg</option><option value="Europe/Madrid">Europe/Madrid</option><option value="Europe/Malta">Europe/Malta</option><option value="Europe/Mariehamn">Europe/Mariehamn</option><option value="Europe/Minsk">Europe/Minsk</option><option value="Europe/Monaco">Europe/Monaco</option><option value="Europe/Moscow">Europe/Moscow</option><option value="Europe/Oslo">Europe/Oslo</option><option value="Europe/Paris">Europe/Paris</option><option value="Europe/Podgorica">Europe/Podgorica</option><option value="Europe/Prague">Europe/Prague</option><option value="Europe/Riga">Europe/Riga</option><option value="Europe/Rome">Europe/Rome</option><option value="Europe/Samara">Europe/Samara</option><option value="Europe/San_Marino">Europe/San_Marino</option><option value="Europe/Sarajevo">Europe/Sarajevo</option><option value="Europe/Saratov">Europe/Saratov</option><option value="Europe/Simferopol">Europe/Simferopol</option><option value="Europe/Skopje">Europe/Skopje</option><option value="Europe/Sofia">Europe/Sofia</option><option value="Europe/Stockholm">Europe/Stockholm</option><option value="Europe/Tallinn">Europe/Tallinn</option><option value="Europe/Tirane">Europe/Tirane</option><option value="Europe/Ulyanovsk">Europe/Ulyanovsk</option><option value="Europe/Uzhgorod">Europe/Uzhgorod</option><option value="Europe/Vaduz">Europe/Vaduz</option><option value="Europe/Vatican">Europe/Vatican</option><option value="Europe/Vienna">Europe/Vienna</option><option value="Europe/Vilnius">Europe/Vilnius</option><option value="Europe/Volgograd">Europe/Volgograd</option><option value="Europe/Warsaw">Europe/Warsaw</option><option value="Europe/Zagreb">Europe/Zagreb</option><option value="Europe/Zaporozhye">Europe/Zaporozhye</option><option value="Europe/Zurich">Europe/Zurich</option><option value="Indian/Antananarivo">Indian/Antananarivo</option><option value="Indian/Chagos">Indian/Chagos</option><option value="Indian/Christmas">Indian/Christmas</option><option value="Indian/Cocos">Indian/Cocos</option><option value="Indian/Comoro">Indian/Comoro</option><option value="Indian/Kerguelen">Indian/Kerguelen</option><option value="Indian/Mahe">Indian/Mahe</option><option value="Indian/Maldives">Indian/Maldives</option><option value="Indian/Mauritius">Indian/Mauritius</option><option value="Indian/Mayotte">Indian/Mayotte</option><option value="Indian/Reunion">Indian/Reunion</option><option value="Pacific/Apia">Pacific/Apia</option><option value="Pacific/Auckland">Pacific/Auckland</option><option value="Pacific/Bougainville">Pacific/Bougainville</option><option value="Pacific/Chatham">Pacific/Chatham</option><option value="Pacific/Chuuk">Pacific/Chuuk</option><option value="Pacific/Easter">Pacific/Easter</option><option value="Pacific/Efate">Pacific/Efate</option><option value="Pacific/Enderbury">Pacific/Enderbury</option><option value="Pacific/Fakaofo">Pacific/Fakaofo</option><option value="Pacific/Fiji">Pacific/Fiji</option><option value="Pacific/Funafuti">Pacific/Funafuti</option><option value="Pacific/Galapagos">Pacific/Galapagos</option><option value="Pacific/Gambier">Pacific/Gambier</option><option value="Pacific/Guadalcanal">Pacific/Guadalcanal</option><option value="Pacific/Guam">Pacific/Guam</option><option value="Pacific/Honolulu">Pacific/Honolulu</option><option value="Pacific/Kiritimati">Pacific/Kiritimati</option><option value="Pacific/Kosrae">Pacific/Kosrae</option><option value="Pacific/Kwajalein">Pacific/Kwajalein</option><option value="Pacific/Majuro">Pacific/Majuro</option><option value="Pacific/Marquesas">Pacific/Marquesas</option><option value="Pacific/Midway">Pacific/Midway</option><option value="Pacific/Nauru">Pacific/Nauru</option><option value="Pacific/Niue">Pacific/Niue</option><option value="Pacific/Norfolk">Pacific/Norfolk</option><option value="Pacific/Noumea">Pacific/Noumea</option><option value="Pacific/Pago_Pago">Pacific/Pago_Pago</option><option value="Pacific/Palau">Pacific/Palau</option><option value="Pacific/Pitcairn">Pacific/Pitcairn</option><option value="Pacific/Pohnpei">Pacific/Pohnpei</option><option value="Pacific/Port_Moresby">Pacific/Port_Moresby</option><option value="Pacific/Rarotonga">Pacific/Rarotonga</option><option value="Pacific/Saipan">Pacific/Saipan</option><option value="Pacific/Tahiti">Pacific/Tahiti</option><option value="Pacific/Tarawa">Pacific/Tarawa</option><option value="Pacific/Tongatapu">Pacific/Tongatapu</option><option value="Pacific/Wake">Pacific/Wake</option><option value="Pacific/Wallis">Pacific/Wallis</option><option value="UTC">UTC</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_msg_list_icons_setting() {
        $test = new Output_Test('msg_list_icons_setting', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="show_list_icons">Show icons in message lists</label></td><td><input type="checkbox"  id="show_list_icons" name="show_list_icons" value="1" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('show_list_icons' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="show_list_icons">Show icons in message lists</label></td><td><input type="checkbox"  checked="checked" id="show_list_icons" name="show_list_icons" value="1" /></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_end_settings_form() {
        $test = new Output_Test('end_settings_form', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td class="submit_cell" colspan="2"><input class="save_settings" type="submit" name="save_settings" value="Save" /></td></tr></table></form></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_start() {
        $test = new Output_Test('folder_list_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<a class="folder_toggle" href="#"><img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1h8v-1h-8zm0%202.969v1h8v-1h-8zm0%203v1h8v-1h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="20" /></a><nav class="folder_cell"><div class="folder_list">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_content_start() {
        $test = new Output_Test('folder_list_content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array(), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => ''), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start() {
        $test = new Output_Test('main_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name main_menu" data-source=".main">Main<img alt="" class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></div><div class="main"><ul class="folders">'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="src_name main_menu" data-source=".main">Main<img alt="" class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></div><div class="main"><ul class="folders">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_content() {
        $test = new Output_Test('main_menu_content', 'core');
        $test->handler_response = array('folder_sources' => array(array('email_folders', 'baz')));
        $res = $test->run();
        $this->assertEquals(array('<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v1h8v-1h-8zm0%202v5.906c0%20.06.034.094.094.094h7.813c.06%200%20.094-.034.094-.094v-5.906h-2.969v1.031h-2.031v-1.031h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Everything</a><span class="combined_inbox_count"></span></li><li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1l4%202%204-2v-1h-8zm0%202v4h8v-4l-4%202-4-2z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Unread</a><span class="total_unread_count"></span></li><li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Flagged</a> <span class="flagged_count"></span></li>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('folder_sources' => array(array('email_folders', 'baz')), 'formatted_folder_list' => '<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v1h8v-1h-8zm0%202v5.906c0%20.06.034.094.094.094h7.813c.06%200%20.094-.034.094-.094v-5.906h-2.969v1.031h-2.031v-1.031h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Everything</a><span class="combined_inbox_count"></span></li><li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1l4%202%204-2v-1h-8zm0%202v4h8v-4l-4%202-4-2z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Unread</a><span class="total_unread_count"></span></li><li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Flagged</a> <span class="flagged_count"></span></li>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_logout_menu_item() {
        $test = new Output_Test('logout_menu_item', 'core');
        $res = $test->run();
        $this->assertEquals(array('<li class="menu_logout"><a class="unread_link logout_link" href="#"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3%200v4h1v-4h-1zm-1.281%201.438l-.375.313c-.803.64-1.344%201.634-1.344%202.75%200%201.929%201.571%203.5%203.5%203.5s3.5-1.571%203.5-3.5c0-1.116-.529-2.11-1.344-2.75l-.375-.313-.625.781.375.313c.585.46.969%201.165.969%201.969%200%201.391-1.109%202.5-2.5%202.5s-2.5-1.109-2.5-2.5c0-.804.361-1.509.938-1.969l.406-.313-.625-.781z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Logout</a></li>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_logout"><a class="unread_link logout_link" href="#"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3%200v4h1v-4h-1zm-1.281%201.438l-.375.313c-.803.64-1.344%201.634-1.344%202.75%200%201.929%201.571%203.5%203.5%203.5s3.5-1.571%203.5-3.5c0-1.116-.529-2.11-1.344-2.75l-.375-.313-.625.781.375.313c.585.46.969%201.165.969%201.969%200%201.391-1.109%202.5-2.5%202.5s-2.5-1.109-2.5-2.5c0-.804.361-1.509.938-1.969l.406-.313-.625-.781z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Logout</a></li>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_end() {
        $test = new Output_Test('main_menu_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</ul></div>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '</ul></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_email_menu_content() {
        $test = new Output_Test('email_menu_content', 'core');
        $test->handler_response = array('folder_sources' => array(array('email_folders', 'baz')));
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name" data-source=".email_folders">Email<img class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="8" height="8" /></div><div style="display: none;" class="email_folders"><ul class="folders"><li class="menu_email"><a class="unread_link" href="?page=message_list&amp;list_path=email"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.21%200-4%201.79-4%204s1.79%204%204%204%204-1.79%204-4-1.79-4-4-4zm0%201c.333%200%20.637.086.938.188-.214.197-.45.383-.406.563.04.18.688.13.688.5%200%20.27-.425.346-.125.656.35.35-.636.978-.656%201.438-.03.83.841.969%201.531.969.424%200%20.503.195.469.438-.546.758-1.438%201.25-2.438%201.25-.378%200-.729-.09-1.063-.219.224-.442-.313-1.344-.781-1.625-.226-.226-.689-.114-.969-.219-.092-.271-.178-.545-.188-.844.031-.05.081-.094.156-.094.19%200%20.454.374.594.344.18-.04-.742-1.313-.313-1.563.2-.12.609.394.469-.156-.12-.51.366-.276.656-.406.26-.11.455-.414.125-.594l-.219-.188c.45-.27.972-.438%201.531-.438zm2.313%201.094c.184.222.323.481.438.75l-.188.219c-.29.27-.327-.212-.438-.313-.13-.11-.638.025-.688-.125-.077-.181.499-.418.875-.531z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> All</a> <span class="unread_mail_count"></span></li>baz</ul></div>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('folder_sources' => array(array('email_folders', 'baz')), 'formatted_folder_list' => '<div class="src_name" data-source=".email_folders">Email<img class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="8" height="8" /></div><div style="display: none;" class="email_folders"><ul class="folders"><li class="menu_email"><a class="unread_link" href="?page=message_list&amp;list_path=email"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.21%200-4%201.79-4%204s1.79%204%204%204%204-1.79%204-4-1.79-4-4-4zm0%201c.333%200%20.637.086.938.188-.214.197-.45.383-.406.563.04.18.688.13.688.5%200%20.27-.425.346-.125.656.35.35-.636.978-.656%201.438-.03.83.841.969%201.531.969.424%200%20.503.195.469.438-.546.758-1.438%201.25-2.438%201.25-.378%200-.729-.09-1.063-.219.224-.442-.313-1.344-.781-1.625-.226-.226-.689-.114-.969-.219-.092-.271-.178-.545-.188-.844.031-.05.081-.094.156-.094.19%200%20.454.374.594.344.18-.04-.742-1.313-.313-1.563.2-.12.609.394.469-.156-.12-.51.366-.276.656-.406.26-.11.455-.414.125-.594l-.219-.188c.45-.27.972-.438%201.531-.438zm2.313%201.094c.184.222.323.481.438.75l-.188.219c-.29.27-.327-.212-.438-.313-.13-.11-.638.025-.688-.125-.077-.181.499-.418.875-.531z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> All</a> <span class="unread_mail_count"></span></li>baz</ul></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_menu_start() {
        $test = new Output_Test('settings_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name" data-source=".settings">Settings<img class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="8" height="8" /></div><ul style="display: none;" class="settings folders"><li class="menu_home"><a class="unread_link" href="?page=home"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200l-4%203h1v4h2v-2h2v2h2v-4.031l1%20.031-4-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Home</a></li>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="src_name" data-source=".settings">Settings<img class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="8" height="8" /></div><ul style="display: none;" class="settings folders"><li class="menu_home"><a class="unread_link" href="?page=home"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200l-4%203h1v4h2v-2h2v2h2v-4.031l1%20.031-4-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Home</a></li>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_form() {
        $test = new Output_Test('save_form', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="save_settings_page"><div class="content_title">Save Settings</div><div class="save_details">Settings are not saved permanently on the server unless you explicitly allow it. If you don\'t save your settings, any changes made since you last logged in will be deleted when your session expires or you logout. You must re-enter your password for security purposes to save your settings permanently.<div class="save_subtitle">Unsaved Changes</div><ul class="unsaved_settings"><li>No changes need to be saved</li></ul></div><div class="save_perm_form"><form method="post"><input type="hidden" name="hm_page_key" value="" /><label class="screen_reader" for="password">Password</label><input required id="password" name="password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_settings_permanently" value="Save" /><input class="save_settings" type="submit" name="save_settings_permanently_then_logout" value="Save and Logout" /></form><form method="post"><input type="hidden" name="hm_page_key" value="" /><input class="save_settings" type="submit" name="logout" value="Just Logout" /></form></div></div>'), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo'));
        $res = $test->run();
        $this->assertEquals(array('<div class="save_settings_page"><div class="content_title">Save Settings</div><div class="save_details">Settings are not saved permanently on the server unless you explicitly allow it. If you don\'t save your settings, any changes made since you last logged in will be deleted when your session expires or you logout. You must re-enter your password for security purposes to save your settings permanently.<div class="save_subtitle">Unsaved Changes</div><ul class="unsaved_settings"><li>foo (1X)</li></ul></div><div class="save_perm_form"><form method="post"><input type="hidden" name="hm_page_key" value="" /><label class="screen_reader" for="password">Password</label><input required id="password" name="password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_settings_permanently" value="Save" /><input class="save_settings" type="submit" name="save_settings_permanently_then_logout" value="Save and Logout" /></form><form method="post"><input type="hidden" name="hm_page_key" value="" /><input class="save_settings" type="submit" name="logout" value="Just Logout" /></form></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_servers_link() {
        $test = new Output_Test('settings_servers_link', 'core');
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_servers"><a class="unread_link" href="?page=servers"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M.344%200a.5.5%200%200%200-.344.5v5a.5.5%200%200%200%20.5.5h2.5v1h-1c-.55%200-1%20.45-1%201h6c0-.55-.45-1-1-1h-1v-1h2.5a.5.5%200%200%200%20.5-.5v-5a.5.5%200%200%200-.5-.5h-7a.5.5%200%200%200-.094%200%20.5.5%200%200%200-.063%200zm.656%201h6v4h-6v-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Servers</a></li>'), $res->output_response);
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('single_server_mode' => 1), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_site_link() {
        $test = new Output_Test('settings_site_link', 'core');
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_settings"><a class="unread_link" href="?page=settings"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Site</a></li>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_save_link() {
        $test = new Output_Test('settings_save_link', 'core');
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_save"><a class="unread_link" href="?page=save"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3%200v3h-2l3%203%203-3h-2v-3h-2zm-3%207v1h8v-1h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Save</a></li>'), $res->output_response);
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('single_server_mode' => 1), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_menu_end() {
        $test = new Output_Test('settings_menu_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</ul>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '</ul>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_content_end() {
        $test = new Output_Test('folder_list_content_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('<a href="#" class="update_message_list">[reload]</a><a href="#" class="hide_folders"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="Collapse" width="16" height="16" /></a>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<a href="#" class="update_message_list">[reload]</a><a href="#" class="hide_folders"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="Collapse" width="16" height="16" /></a>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_end() {
        $test = new Output_Test('folder_list_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div></nav>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_section_start() {
        $test = new Output_Test('content_section_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<main class="content_cell">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_section_end() {
        $test = new Output_Test('content_section_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</main>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_start() {
        $test = new Output_Test('message_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'sent');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=sent">Sent</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'combined_inbox');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=combined_inbox">Everything</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'email');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=email">All Email</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'unread');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=message_list&amp;list_path=unread">Unread</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'search', 'list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('Search', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" class="msg_uid" value="5" /><div class="content_title"><a href="?page=search&amp;list_path=search">Search</a><img class="path_delim" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&gt;" /><a href="?page=message_list&amp;list_path=">bar</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_parent' => 'search', 'list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('search', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" class="msg_uid" value="5" /><div class="content_title"><a href="?page=search&amp;list_path=search">Search</a><img class="path_delim" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&gt;" /><a href="?page=message_list&amp;list_path=">search<img class="path_delim" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&gt;" />bar</a></div><div class="msg_text">'), $res->output_response);
        $test->handler_response = array('list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('foo', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" class="msg_uid" value="5" /><div class="content_title"><a href="?page=message_list&amp;list_path=&list_page=1&filter=foo&sort=bar">foo<img class="path_delim" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&gt;" />bar</a></div><div class="msg_text">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_end() {
        $test = new Output_Test('message_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_notfound_content() {
        $test = new Output_Test('notfound_content', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title">Page Not Found!</div><div class="empty_list"><br />Nothingness</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_start() {
        $test = new Output_Test('message_list_start', 'core');
        $test->handler_response = array('message_list_fields' => array('foo', 'bar'));
        $res = $test->run();
        $this->assertEquals(array('<table class="message_table"><colgroup><col class="f"><col class="b"></colgroup><thead><tr><th class="o">o</th><th class="a">r</th></tr></thead><tbody class="message_table_body">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_home_heading() {
        $test = new Output_Test('home_heading', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title">Home</div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_home_password_dialogs() {
        $test = new Output_Test('home_password_dialogs', 'core');
        $res = $test->run();
        $this->assertEquals(array(), $res->output_response);
        $test->handler_response = array('missing_pw_servers' => array(array('server' => 'host', 'user' => 'test', 'type' => 'foo', 'id' => 1, 'name' => 'bar')));
        $res = $test->run();
        $this->assertEquals(array('<div class="home_password_dialogs"><div class="nux_title">Passwords</div>You have elected to not store passwords between logins. Enter your passwords below to gain access to these services during this session.<br /><br /><div class="div_foo_1" >foo bar test host <input placeholder="Password" type="password" class="pw_input" id="update_pw_foo_1" /> <input type="button" class="pw_update" data-id="foo_1" value="Update" /></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_heading() {
        $test = new Output_Test('message_list_heading', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list _list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a></div><div class="mailbox_list_title"></div><div class="list_controls"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a title="Configure" href="?page=settings#_setting"><img alt="Configure" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('custom_list_controls' => 'foo');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list _list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a></div><div class="mailbox_list_title"></div><div class="list_controls"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a>foo</div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('list_path' => 'pop3');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list pop3_list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a></div><div class="mailbox_list_title"></div><div class="list_controls"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a title="Configure" href="?page=settings#pop3_setting"><img alt="Configure" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('no_list_controls' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list _list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a></div><div class="mailbox_list_title"></div><div class="list_controls"></div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('list_path' => 'combined_inbox');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list combined_inbox_list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a></div><div class="mailbox_list_title"></div><div class="list_controls"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a title="Configure" href="?page=settings#all_setting"><img alt="Configure" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_end() {
        $test = new Output_Test('message_list_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</tbody></table><div class="page_links"></div></div>'), $res->output_response);
    }
}
class Hm_Test_Core_Output_Modules_Debug extends PHPUnit_Framework_TestCase {
    public function setUp() {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
        require 'helpers.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css_debug() {
        $test = new Output_Test('header_css', 'core');
        $test->handler_response = array('router_module_list' => array('core'));
        $res = $test->run();
        $this->assertEquals(array('router_module_list' => array('core')), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js_debug() {
        $test = new Output_Test('page_js', 'core');
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('foo', 'core'));
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="third_party/zepto.min.js"></script><script type="text/javascript" src="third_party/forge.min.js"></script><script type="text/javascript" src="modules/core/site.js"></script>'), $res->output_response);
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="third_party/zepto.min.js"></script><script type="text/javascript" src="third_party/forge.min.js"></script><script type="text/javascript" src="modules/core/site.js"></script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start_debug() {
        $test = new Output_Test('main_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name main_menu" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">[Debug]</span><img alt="" class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></div><div class="main"><ul class="folders">'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="src_name main_menu" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">[Debug]</span><img alt="" class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></div><div class="main"><ul class="folders">'), $res->output_response);
    }
}
?>
