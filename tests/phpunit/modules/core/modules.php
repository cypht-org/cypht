<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Handler_Modules extends TestCase {

    public function setUp(): void {
        require __DIR__.'/../../bootstrap.php';
        require __DIR__.'/../../helpers.php';
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
        $test->modules = array('imap', 'smtp');
        $test->user_config = array('no_password_save_setting' => true);
        Hm_IMAP_List::add(array('nopass' => 1, 'user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        Hm_SMTP_List::add(array('nopass' => 1, 'user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        $res = $test->run();
        $this->assertEquals(4, count($res->handler_response['missing_pw_servers']));
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
    public function test_http_headers_allow_images() {
        $test = new Handler_Test('http_headers', 'core');
        $test->tls = true;
        $test->rtype = 'AJAX';
        $test->input = array('language' => 'English');
        $test->config['allow_external_image_sources'] = true;
        $res = $test->run();
		$out = array(
            'Content-Security-Policy' => "default-src 'none'; script-src 'self' 'unsafe-inline'; connect-src 'self'; font-src 'self'; img-src * data:; style-src 'self' 'unsafe-inline';",
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
    public function test_process_mailto_handler() {
        $test = new Handler_Test('process_mailto_handler_setting', 'core');
        $test->post = array('save_settings' => true, 'mailto_handler' => true);
        $res = $test->run();
        $this->assertTrue($res->handler_response['new_user_settings']['mailto_handler_setting']);
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
        #$this->assertEquals(array('ERRInvalid username or password'), Hm_Msgs::get());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_page_data() {
        $test = new Handler_Test('default_page_data', 'core');
        $test->config = array('auth_type' => 'IMAP', 'single_server_mode' => true);
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
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_user_data() {
        $test = new Handler_Test('save_user_data', 'core');
        $res = $test->run();
        $this->assertEquals(array('user_settings_dir' => APP_PATH.'tests/phpunit/data', 'default_language' => 'es', 'default_setting_inline_message' => true), $res->session->get('user_data'));
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

class Hm_Test_Core_Output_Modules extends TestCase {

    public function setUp(): void {
        require __DIR__.'/../../bootstrap.php';
        require __DIR__.'/../../helpers.php';
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
        $this->assertEquals('<li class="menu_search"><form method="get"><a class="unread_link" href="?page=search"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200c-1.927%200-3.5%201.573-3.5%203.5s1.573%203.5%203.5%203.5c.592%200%201.166-.145%201.656-.406a1%201%200%200%200%20.125.125l1%201a1.016%201.016%200%201%200%201.438-1.438l-1-1a1%201%200%200%200-.156-.125c.266-.493.438-1.059.438-1.656%200-1.927-1.573-3.5-3.5-3.5zm0%201c1.387%200%202.5%201.113%202.5%202.5%200%20.661-.241%201.273-.656%201.719l-.031.031a1%201%200%200%200-.125.125c-.442.397-1.043.625-1.688.625-1.387%200-2.5-1.113-2.5-2.5s1.113-2.5%202.5-2.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" alt="Search" width="16" height="16" /></a><input type="hidden" name="page" value="search" /><label class="screen_reader" for="search_terms">Search</label><input type="search" class="search_terms" name="search_terms" placeholder="Search" /></form></li>', $res->output_data['formatted_folder_list']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_content_start() {
        $test = new Output_Test('search_content_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="search_content"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control" href="#" data-action="archive">Archive</a></div>Search'), $res->output_response);
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
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('single_server_mode' => true), $res->output_response);
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
        $this->assertEquals(array('<input type="hidden" name="page" value="search" /> <label class="screen_reader" for="search_terms">Search Terms</label><input required placeholder="Search Terms" id="search_terms" type="search" class="search_terms" name="search_terms" value="" /> <label class="screen_reader" for="search_fld">Search Field</label><select id="search_fld" name="search_fld"><option selected="selected" value="TEXT">Entire message</option><option value="BODY">Message body</option><option value="SUBJECT">Subject</option><option value="FROM">From</option><option value="TO">To</option><option value="CC">Cc</option></select> <label class="screen_reader" for="search_since">Search Since</label><select name="search_since" id="search_since" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select><select name="sort" class="combined_sort"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select> | <input type="submit" class="search_update" value="Update" /> <input type="button" class="search_reset" value="Reset" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_end() {
        $test = new Output_Test('search_form_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</form></div><div class="list_controls no_mobile"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div>
    <div class="list_controls on_mobile">
        <img alt="" src="data:image/svg+xml;base64,PCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KDTwhLS0gVXBsb2FkZWQgdG86IFNWRyBSZXBvLCB3d3cuc3ZncmVwby5jb20sIFRyYW5zZm9ybWVkIGJ5OiBTVkcgUmVwbyBNaXhlciBUb29scyAtLT4KPHN2ZyBmaWxsPSIjN0Y3RjdGIiBoZWlnaHQ9IjI1NnB4IiB3aWR0aD0iMjU2cHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmlld0JveD0iMCAwIDMyLjA1NSAzMi4wNTUiIHhtbDpzcGFjZT0icHJlc2VydmUiPgoNPGcgaWQ9IlNWR1JlcG9fYmdDYXJyaWVyIiBzdHJva2Utd2lkdGg9IjAiLz4KDTxnIGlkPSJTVkdSZXBvX3RyYWNlckNhcnJpZXIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlPSIjQ0NDQ0NDIiBzdHJva2Utd2lkdGg9IjEuNjY2ODYwMDAwMDAwMDAwMiI+IDxnPiA8cGF0aCBkPSJNMy45NjgsMTIuMDYxQzEuNzc1LDEyLjA2MSwwLDEzLjgzNSwwLDE2LjAyN2MwLDIuMTkyLDEuNzczLDMuOTY3LDMuOTY4LDMuOTY3YzIuMTg5LDAsMy45NjYtMS43NzIsMy45NjYtMy45NjcgQzcuOTM0LDEzLjgzNSw2LjE1NywxMi4wNjEsMy45NjgsMTIuMDYxeiBNMTYuMjMzLDEyLjA2MWMtMi4xODgsMC0zLjk2OCwxLjc3My0zLjk2OCwzLjk2NWMwLDIuMTkyLDEuNzc4LDMuOTY3LDMuOTY4LDMuOTY3IHMzLjk3LTEuNzcyLDMuOTctMy45NjdDMjAuMjAxLDEzLjgzNSwxOC40MjMsMTIuMDYxLDE2LjIzMywxMi4wNjF6IE0yOC4wOSwxMi4wNjFjLTIuMTkyLDAtMy45NjksMS43NzQtMy45NjksMy45NjcgYzAsMi4xOSwxLjc3NCwzLjk2NSwzLjk2OSwzLjk2NWMyLjE4OCwwLDMuOTY1LTEuNzcyLDMuOTY1LTMuOTY1UzMwLjI3OCwxMi4wNjEsMjguMDksMTIuMDYxeiIvPiA8L2c+IDwvZz4KDTxnIGlkPSJTVkdSZXBvX2ljb25DYXJyaWVyIj4gPGc+IDxwYXRoIGQ9Ik0zLjk2OCwxMi4wNjFDMS43NzUsMTIuMDYxLDAsMTMuODM1LDAsMTYuMDI3YzAsMi4xOTIsMS43NzMsMy45NjcsMy45NjgsMy45NjdjMi4xODksMCwzLjk2Ni0xLjc3MiwzLjk2Ni0zLjk2NyBDNy45MzQsMTMuODM1LDYuMTU3LDEyLjA2MSwzLjk2OCwxMi4wNjF6IE0xNi4yMzMsMTIuMDYxYy0yLjE4OCwwLTMuOTY4LDEuNzczLTMuOTY4LDMuOTY1YzAsMi4xOTIsMS43NzgsMy45NjcsMy45NjgsMy45NjcgczMuOTctMS43NzIsMy45Ny0zLjk2N0MyMC4yMDEsMTMuODM1LDE4LjQyMywxMi4wNjEsMTYuMjMzLDEyLjA2MXogTTI4LjA5LDEyLjA2MWMtMi4xOTIsMC0zLjk2OSwxLjc3NC0zLjk2OSwzLjk2NyBjMCwyLjE5LDEuNzc0LDMuOTY1LDMuOTY5LDMuOTY1YzIuMTg4LDAsMy45NjUtMS43NzIsMy45NjUtMy45NjVTMzAuMjc4LDEyLjA2MSwyOC4wOSwxMi4wNjF6Ii8+IDwvZz4gPC9nPgoNPC9zdmc+" width="20" height="20" onclick="listControlsMenu()"/>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div>
    </div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
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
    public function test_fancy_login_end() {
        $GLOBALS['fancy_login_allowed'] = 'y';
        $test = new Output_Test('login_end', 'core');
        $res = $test->run();
        $this->assertEquals(array('</form></div>'), $res->output_response);
        unset($GLOBALS['fancy_login_allowed']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_start() {
        $test = new Output_Test('login_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<style type="text/css">.mobile .login_form{margin-top:60px;display:block;float:none;width:100%;background-color:#fff;font-size:130%;height:auto;}.logged_out{display:block !important;}.sys_messages{position:fixed;right:20px;top:15px;min-height:30px;display:none;background-color:#fff;color:teal;margin-top:0px;padding:15px;padding-bottom:5px;white-space:nowrap;border:solid 1px #999;border-radius:5px;filter:drop-shadow(4px 4px 4px #ccc);z-index:101;}.g-recaptcha{margin-left:-12px;}.mobile .g-recaptcha{clear:left;margin-left:20px;}.title{font-weight:normal;padding:0px;margin:0px;margin-left:20px;margin-bottom:20px;letter-spacing:-1px;color:#999;}html,body{max-width:100%;min-height:100%;background-color:#fff;}body{background:linear-gradient(180deg,#faf6f5,#faf6f5,#faf6f5,#faf6f5,#fff);font-size:1em;height:100%;color:#333;font-family:Arial;padding:0px;margin:0px;min-width:700px;font-size:100%;}input,option,select{font-size:100%;padding:3px;}textarea,select,input{border:outset 1px #ddd;background-color:#fff;color:#333;border-radius:6px;}.screen_reader{position:absolute;top:auto;width:1px;height:1px;overflow:hidden;}.login_form{float:left;font-size:90%;padding-top:60px;height:300px;border-radius:0px 0px 20px 0px;margin:0px;background-color:#f5f5f5;width:300px;padding-left:20px;}.login_form input{clear:both;float:left;padding:4px;margin-left:20px;margin-top:10px;margin-bottom:10px;}#username,#password{width:200px;}.err{color:red !important;}.long_session{float:left;}.long_session input{padding:0px;float:none;}.mobile .long_session{float:left;clear:both;}</style><form class="login_form" method="POST">'), $res->output_response);
        $test->handler_response = array('router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<form class="logout_form" method="POST">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_fancy_login_start() {
        $GLOBALS['fancy_login_allowed'] = 'y';
        $test = new Output_Test('login_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<style type="text/css">body,html{max-width:100vw !important; max-height:100vh !important; overflow:hidden !important;}.form-container{background-color:#f1f1f1;background: linear-gradient( rgba(4, 26, 0, 0.85), rgba(4, 26, 0, 0.85)), url(modules/core/assets/images/cloud.png);background-attachment: fixed;background-position: center;background-repeat: no-repeat;background-size: cover;display:grid; place-items:center; height:100vh; width:100vw;} .logged_out{display:block !important;}.sys_messages{position:fixed;right:20px;top:15px;min-height:30px;display:none;background-color:#fff;color:teal;margin-top:0px;padding:15px;padding-bottom:5px;white-space:nowrap;border:solid 1px #999;border-radius:5px;filter:drop-shadow(4px 4px 4px #ccc);z-index:101;}.g-recaptcha{margin:0px 10px 10px 10px;}.mobile .g-recaptcha{margin:0px 10px 5px 10px;}.title{font-weight:normal;padding:0px;margin:0px;margin-left:20px;margin-bottom:20px;letter-spacing:-1px;color:#999;}html,body{min-width:100px !important;background-color:#fff;}body{background:linear-gradient(180deg,#faf6f5,#faf6f5,#faf6f5,#faf6f5,#fff);font-size:1em;color:#333;font-family:Arial;padding:0px;margin:0px;min-width:700px;font-size:100%;}input,option,select{font-size:100%;padding:3px;}textarea,select,input{border:solid 1px #ddd;background-color:#fff;color:#333;border-radius:3px;}.screen_reader{position:absolute;top:auto;width:1px;height:1px;overflow:hidden;}.login_form{display:flex; justify-content:space-evenly; align-items:center; flex-direction:column;font-size:90%;padding-top:60px;height:360px;border-radius:20px 20px 20px 20px;margin:0px;background-color:rgba(0,0,0,.6);min-width:300px;}.login_form input{clear:both;float:left;padding:4px;margin-top:10px;margin-bottom:10px;}#username,#password{width:200px; height:25px;} .err{color:red !important;}.long_session{float:left;}.long_session input{padding:0px;float:none;font-size:18px;}.mobile .long_session{float:left;clear:both;} @media screen and (min-width:400px){.login_form{min-width:400px;}}.user-icon_signin{display:block; background-color:white; border-radius:100%; padding:10px; height:40px; margin-top:-120px; box-shadow: #6eb549 .4px 2.4px 6.2px; }.label_signin{width:210px; margin:0px 0px -18px 0px;color:#fff;opacity:0.7;} @media (max-height : 500px){ .user-icon_signin{display:none;}}
                </style><div class="form-container"><form class="login_form" method="POST">'), $res->output_response);
        $test->handler_response = array('router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="form-container"><form class="logout_form" method="POST">'), $res->output_response);
        unset($GLOBALS['fancy_login_allowed']);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login() {
        $test = new Output_Test('login', 'core');
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" id="unsaved_changes" value="0" /><input type="hidden" name="hm_page_key" value="" /><div class="confirm_logout"><div class="confirm_text">Unsaved changes will be lost! Re-enter your password to save and exit. &nbsp;<a href="?page=save">More info</a></div><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="logout_password">Password</label><input id="logout_password" autocomplete="current-password" name="password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_and_logout" value="Save and Logout" /><input class="save_settings" id="logout_without_saving" type="submit" name="logout" value="Just Logout" /><input class="cancel_logout save_settings" type="button" value="Cancel" /></div>'), $res->output_response);
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => false);
        $res = $test->run();
        $this->assertEquals(array('<h1 class="title"></h1>       <input type="hidden" name="hm_page_key" value="" /> <label class="screen_reader" for="username">Username</label><input autofocus required type="text" placeholder="Username" id="username" name="username" value=""> <label class="screen_reader" for="password">Password</label><input required type="password" id="password" placeholder="Password" name="password"><div class="long_session"><input type="checkbox" id="stay_logged_in" value="1" name="stay_logged_in" /> <label for="stay_logged_in">Stay logged in</label></div> <input style="cursor:pointer;" type="submit" id="login" value="Login" />'), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo'), 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" id="unsaved_changes" value="1" /><input type="hidden" name="hm_page_key" value="" /><div class="confirm_logout"><div class="confirm_text">Unsaved changes will be lost! Re-enter your password to save and exit. &nbsp;<a href="?page=save">More info</a></div><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="logout_password">Password</label><input id="logout_password" autocomplete="current-password" name="password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_and_logout" value="Save and Logout" /><input class="save_settings" id="logout_without_saving" type="submit" name="logout" value="Just Logout" /><input class="cancel_logout save_settings" type="button" value="Cancel" /></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_fancy_login() {
        $GLOBALS['fancy_login_allowed'] = 'y';
        $test = new Output_Test('login', 'core');
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" id="unsaved_changes" value="0" /><input type="hidden" name="hm_page_key" value="" /><div class="confirm_logout"><div class="confirm_text">Unsaved changes will be lost! Re-enter your password to save and exit. &nbsp;<a href="?page=save">More info</a></div><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="logout_password">Password</label><input id="logout_password" autocomplete="current-password" name="password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_and_logout" value="Save and Logout" /><input class="save_settings" id="logout_without_saving" type="submit" name="logout" value="Just Logout" /><input class="cancel_logout save_settings" type="button" value="Cancel" /></div>'), $res->output_response);
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => false);
        $res = $test->run();
        $this->assertEquals(array('<svg class="user-icon_signin" viewBox="0 0 20 20"><path d="M12.075,10.812c1.358-0.853,2.242-2.507,2.242-4.037c0-2.181-1.795-4.618-4.198-4.618S5.921,4.594,5.921,6.775c0,1.53,0.884,3.185,2.242,4.037c-3.222,0.865-5.6,3.807-5.6,7.298c0,0.23,0.189,0.42,0.42,0.42h14.273c0.23,0,0.42-0.189,0.42-0.42C17.676,14.619,15.297,11.677,12.075,10.812 M6.761,6.775c0-2.162,1.773-3.778,3.358-3.778s3.359,1.616,3.359,3.778c0,2.162-1.774,3.778-3.359,3.778S6.761,8.937,6.761,6.775 M3.415,17.69c0.218-3.51,3.142-6.297,6.704-6.297c3.562,0,6.486,2.787,6.705,6.297H3.415z"></path></svg><img src="modules/core/assets/images/logo.svg" style="height:90px;"><!--h1 class="title"></h1--> <input type="hidden" name="hm_page_key" value="" /> <label class="label_signin" for="username">Username</label><input autofocus required type="text" placeholder="Username" id="username" name="username" value=""> <label class="label_signin" for="password">Password</label><input required type="password" id="password" placeholder="Password" name="password"><div class="long_session"><input type="checkbox" id="stay_logged_in" value="1" name="stay_logged_in" /> <label for="stay_logged_in">Stay logged in</label></div> <input style="cursor:pointer; display:block; width:210px; background-color:#6eb549; color:white; height:40px;" type="submit" id="login" value="Login" />'), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo'), 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<input type="hidden" id="unsaved_changes" value="1" /><input type="hidden" name="hm_page_key" value="" /><div class="confirm_logout"><div class="confirm_text">Unsaved changes will be lost! Re-enter your password to save and exit. &nbsp;<a href="?page=save">More info</a></div><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="logout_password">Password</label><input id="logout_password" autocomplete="current-password" name="password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_and_logout" value="Save and Logout" /><input class="save_settings" id="logout_without_saving" type="submit" name="logout" value="Just Logout" /><input class="cancel_logout save_settings" type="button" value="Cancel" /></div>'), $res->output_response);
        unset($GLOBALS['fancy_login_allowed']);
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
        $this->assertEquals(array('<!DOCTYPE html><html dir="ltr" class="ltr_page" lang=en><head><meta name="apple-mobile-web-app-capable" content="yes" /><meta name="mobile-web-app-capable" content="yes" /><meta name="apple-mobile-web-app-status-bar-style" content="black" /><meta name="theme-color" content="#888888" /><meta charset="utf-8" />'), $res->output_response);
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
        $this->assertEquals(array('<body class=""><noscript class="noscript">You need to have Javascript enabled to use , sorry about that!</noscript><script type="text/javascript">sessionStorage.clear();</script>'), $res->output_response);
        $test->handler_response = array('changed_settings' => array(0), 'router_login_state' => true);
        $res = $test->run();
        $this->assertEquals(array('<body class=""><noscript class="noscript">You need to have Javascript enabled to use , sorry about that!</noscript><input type="hidden" id="hm_page_key" value="" /><a class="unsaved_icon" href="?page=save" title="Unsaved Changes"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AgSFQseE+bgxAAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAAS0lEQVQ4y6WTSQoAMAgDk/z/z+21lK6ON5UZEIklNdXLIbAkhcBVgccmBP4VeDUMgV8FPi1D4JvAL7eFwDuBf/4aAs8CV0NB0sirA+jtAijusTaJAAAAAElFTkSuQmCC" alt="Unsaved changes" class="unsaved_reminder" /></a>'), $res->output_response);
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
        //$this->assertEquals(array('<title>Message List</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        //$test->handler_response = array('router_login_state' => true, 'router_page_name' => 'notfound');
        //$res = $test->run();
        //$this->assertEquals(array('<title>Nope</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
        //$test->handler_response = array('router_login_state' => true, 'router_page_name' => 'home');
        //$res = $test->run();
        //$this->assertEquals(array('<title>Home</title><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0"><link rel="icon" class="tab_icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFVJREFUOI3NkkEKACEMA2d92fpzfVn3oHhYqAF7qIFeSpImUMjGA1jEoEQTFKAC/UDbp3bhBRqj0m7a5C78F56Rx5MEdUBHFMlkV09ogN3xB7kG+fgA0tc160Jy09wAAAAASUVORK5CYII=" ><base href="" />'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css() {
        $test = new Output_Test('header_css', 'core');
        $res = $test->run();
        $this->assertEquals(array('<link href="site.css?v=asdf" media="all" rel="stylesheet" type="text/css" /><style type="text/css">@font-face {font-family:"Behdad";src:url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css_integrity() {
        define('CSS_HASH', 'foo');
        $test = new Output_Test('header_css', 'core');
        $test->handler_response = array('router_module_list', array('core'));
        $res = $test->run();
        $this->assertEquals(array('<link href="site.css?v=asdf" integrity="foo" media="all" rel="stylesheet" type="text/css" /><style type="text/css">@font-face {font-family:"Behdad";src:url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js_integrity() {
        define('JS_HASH', 'foo');
        $test = new Output_Test('page_js', 'core');
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" integrity="foo" src="site.js?v=asdf" async></script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js() {
        $test = new Output_Test('page_js', 'core');
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="site.js?v=asdf" async></script>'), $res->output_response);
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
        $this->assertEquals(array('<script type="text/javascript">var globals = {};var hm_empty_folder = function() { return "So alone"; };var hm_mobile = function() { return 0; };var hm_debug = function() { return "0"; };var hm_mailto = function() { return 0; };var hm_page_name = function() { return ""; };var hm_language_direction = function() { return "ltr"; };var hm_list_path = function() { return ""; };var hm_list_parent = function() { return ""; };var hm_msg_uid = function() { return Hm_Utils.get_from_global("msg_uid", ""); };var hm_encrypt_ajax_requests = function() { return ""; };var hm_encrypt_local_storage = function() { return ""; };var hm_web_root_path = function() { return ""; };var hm_flag_image_src = function() { return "data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E"; };var hm_check_dirty_flag = function() { return 0; };var hm_data_sources = function() { return []; };var hm_delete_prompt = function() { return true; };</script>'), $res->output_response);
        $test->handler_response = array();
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript">var globals = {};var hm_empty_folder = function() { return "So alone"; };var hm_mobile = function() { return 0; };var hm_debug = function() { return "0"; };var hm_mailto = function() { return 0; };var hm_page_name = function() { return ""; };var hm_language_direction = function() { return "ltr"; };var hm_list_path = function() { return ""; };var hm_list_parent = function() { return ""; };var hm_msg_uid = function() { return Hm_Utils.get_from_global("msg_uid", ""); };var hm_encrypt_ajax_requests = function() { return ""; };var hm_encrypt_local_storage = function() { return ""; };var hm_web_root_path = function() { return ""; };var hm_flag_image_src = function() { return "data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E"; };var hm_check_dirty_flag = function() { return 0; };var hm_data_sources = function() { return []; };var hm_delete_prompt = function() { return confirm("Are you sure?"); };</script>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="general_setting"><td><label for="start_page">First page after login</label></td><td><select id="start_page" name="start_page"><option value="none">None</option><option value="page=home">Home</option><option value="page=message_list&list_path=combined_inbox">Everything</option><option selected="selected" value="page=message_list&list_path=unread">Unread</option><option value="page=message_list&list_path=flagged">Flagged</option><option value="page=compose">Compose</option></select><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_select"  src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
    public function test_mailto_handler_setting() {
        $test = new Output_Test('mailto_handler_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="mailto_handler">Allow handling of mailto links</label></td><td><input type="checkbox"  value="1" id="mailto_handler" name="mailto_handler" /></td></tr>'), $res->output_response);
        $test->handler_response = array('user_settings' => array('mailto_handler' => true));
        $res = $test->run();
        $this->assertEquals(array('<tr class="general_setting"><td><label for="mailto_handler">Allow handling of mailto links</label></td><td><input type="checkbox"  checked="checked" value="1" id="mailto_handler" name="mailto_handler" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_checkbox"  src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="general_setting"><td><label for="no_folder_icons">Hide folder list icons</label></td><td><input type="checkbox"  checked="checked" value="1" id="no_folder_icons" name="no_folder_icons" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_checkbox"  src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="general_setting"><td><label for="no_password_save">Don\'t save account passwords between logins</label></td><td><input type="checkbox"  checked="checked" value="1" id="no_password_save" name="no_password_save" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_checkbox"  src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="general_setting"><td><label for="disable_delete_prompt">Disable prompts when deleting</label></td><td><input type="checkbox"  checked="checked" value="1" id="disable_delete_prompt" name="disable_delete_prompt" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_checkbox"  src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('single_server_mode' => true), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_junk_settings() {
        $test = new Output_Test('start_junk_settings', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td data-target=".junk_setting" colspan="2" class="settings_subtitle"><img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20width%3D%2223%22%20height%3D%2223%22%20viewBox%3D%220%200%2023%2023%22%3E%0A%20%20%3Cpath%20d%3D%22M1%204L1%205.5820312L11%2012L21%205.5820312L21%204L1%204%20z%20M%201%207.7519531L1%2010.552734L1%2020L12.421875%2020L14%2020L14.757812%2020L15.757812%2019L13.636719%2016.878906L17.878906%2012.636719L20%2014.757812L21%2013.757812L21%2013L21%209.1953125L21%207.7519531L11%2014L1%207.7519531%20z%20M%2017.878906%2015.464844L16.464844%2016.878906L18.585938%2019L16.464844%2021.121094L17.878906%2022.535156L20%2020.414062L22.121094%2022.535156L23.535156%2021.121094L21.414062%2019L23.535156%2016.878906L22.121094%2015.464844L20%2017.585938L17.878906%2015.464844%20z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="16" />Junk</td></tr>'), $res->output_response);
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
        $test->handler_response = array('router_module_list' => array('imap'), 'single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('router_module_list' => array('imap'), 'single_server_mode' => true), $res->output_response);
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
        $this->assertEquals(array('<tr class="unread_setting"><td><label for="unread_per_source">Max messages per source</label></td><td><input type="text" size="2" id="unread_per_source" name="unread_per_source" value="10" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_input" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="flagged_setting"><td><label for="flagged_per_source">Max messages per source</label></td><td><input type="text" size="2" id="flagged_per_source" name="flagged_per_source" value="10" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_input" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="email_setting"><td><label for="all_email_per_source">Max messages per source</label></td><td><input type="text" size="2" id="all_email_per_source" name="all_email_per_source" value="10" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_input" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="all_setting"><td><label for="all_per_source">Max messages per source</label></td><td><input type="text" size="2" id="all_per_source" name="all_per_source" value="10" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_input" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
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
        $this->assertEquals(array('<tr class="general_setting"><td><label for="language">Language</label></td><td><select id="language" name="language"><option value="az">Azerbaijani</option><option value="pt-BR">Brazilian Portuguese</option><option value="zh-Hans">Chinese Simplified</option><option value="nl">Dutch</option><option selected="selected" value="en">English</option><option value="et">Estonian</option><option value="fa">Farsi</option><option value="fr">French</option><option value="de">German</option><option value="hu">Hungarian</option><option value="id">Indonesian</option><option value="it">Italian</option><option value="ja">Japanese</option><option value="ro">Romanian</option><option value="ru">Russian</option><option value="es">Spanish</option></select></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_timezone_setting() {
        $test = new Output_Test('timezone_setting', 'core');
        $res = $test->run();
        $this->assertTrue(strlen($res->output_response[0]) > 0);
        $test->handler_response = array('user_settings' => array('timezone' => 'America/Chicago'));
        $res = $test->run();
        $this->assertTrue(strlen($res->output_response[0]) > 0);
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
        $this->assertEquals(array('<tr class="general_setting"><td><label for="show_list_icons">Show icons in message lists</label></td><td><input type="checkbox"  checked="checked" id="show_list_icons" name="show_list_icons" value="1" /><span class="tooltip_restore" restore_aria_label="Restore default value"><img alt="Refresh" class="refresh_list reset_default_value_checkbox"  src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" /></span></td></tr>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_end_settings_form() {
        $test = new Output_Test('end_settings_form', 'core');
        $res = $test->run();
        $this->assertEquals(array('<tr><td class="submit_cell" colspan="2"><input class="save_settings" type="submit" name="save_settings" value="Save" /></td></tr></table></form><form method="POST"><input type="hidden" name="hm_page_key" value="" /><input class="reset_factory_button" type="submit" name="reset_factory" value="Restore Defaults" /></form></div>'), $res->output_response);
        $test->handler_response = array('single_server_mode' => true);
        $res = $test->run();
        $this->assertEquals(array('<tr><td class="submit_cell" colspan="2"><input class="save_settings" type="submit" name="save_settings" value="Save" /></td></tr></table></form><form method="POST"><input type="hidden" name="hm_page_key" value="" /><input class="reset_factory_button" type="submit" name="reset_factory" value="Restore Defaults" /></form></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_start() {
        $test = new Output_Test('folder_list_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<a class="folder_toggle" href="#">Show folders<img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1h8v-1h-8zm0%202.969v1h8v-1h-8zm0%203v1h8v-1h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="16" height="20" /></a><nav class="folder_cell"><div class="folder_list">'), $res->output_response);
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
        $this->assertEquals(array('<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v1h8v-1h-8zm0%202v5.906c0%20.06.034.094.094.094h7.813c.06%200%20.094-.034.094-.094v-5.906h-2.969v1.031h-2.031v-1.031h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Everything</a><span class="combined_inbox_count"></span></li><li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1l4%202%204-2v-1h-8zm0%202v4h8v-4l-4%202-4-2z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Unread</a><span class="total_unread_count"></span></li><li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Flagged</a> <span class="flagged_count"></span></li><li class="menu_junk"><a class="unread_link" href="?page=message_list&amp;list_path=junk"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20width%3D%2223%22%20height%3D%2223%22%20viewBox%3D%220%200%2023%2023%22%3E%0A%20%20%3Cpath%20d%3D%22M1%204L1%205.5820312L11%2012L21%205.5820312L21%204L1%204%20z%20M%201%207.7519531L1%2010.552734L1%2020L12.421875%2020L14%2020L14.757812%2020L15.757812%2019L13.636719%2016.878906L17.878906%2012.636719L20%2014.757812L21%2013.757812L21%2013L21%209.1953125L21%207.7519531L11%2014L1%207.7519531%20z%20M%2017.878906%2015.464844L16.464844%2016.878906L18.585938%2019L16.464844%2021.121094L17.878906%2022.535156L20%2020.414062L22.121094%2022.535156L23.535156%2021.121094L21.414062%2019L23.535156%2016.878906L22.121094%2015.464844L20%2017.585938L17.878906%2015.464844%20z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Junk</a></li><li class="menu_trash"><a class="unread_link" href="?page=message_list&amp;list_path=trash"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20viewBox%3D%220%200%2028%2028%22%3E%0A%20%20%3Cpath%20d%3D%22M6%208v16c0%201.1.9%202%202%202h14c1.1%200%202-.9%202-2V8H6zM24%204h-6c0-.6-.4-1-1-1h-4c-.6%200-1%20.4-1%201H6C5.4%204%205%204.4%205%205s.4%201%201%201h18c.6%200%201-.4%201-1S24.6%204%2024%204z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Trash</a></li><li class="menu_drafts"><a class="unread_link" href="?page=message_list&amp;list_path=drafts"><img class="account_icon" src="data:image/svg+xml,%3C%3Fxml%20version%3D%221.0%22%20encoding%3D%22iso-8859-1%22%3F%3E%0A%3C%21--%20Uploaded%20to%3A%20SVG%20Repo%2C%20www.svgrepo.com%2C%20Generator%3A%20SVG%20Repo%20Mixer%20Tools%20--%3E%0A%3C%21DOCTYPE%20svg%20PUBLIC%20%22-%2F%2FW3C%2F%2FDTD%20SVG%201.1%2F%2FEN%22%20%22http%3A%2F%2Fwww.w3.org%2FGraphics%2FSVG%2F1.1%2FDTD%2Fsvg11.dtd%22%3E%0A%3Csvg%20fill%3D%22%23000000%22%20version%3D%221.1%22%20id%3D%22Capa_1%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20%0A%09%20width%3D%22800px%22%20height%3D%22800px%22%20viewBox%3D%220%200%2031.854%2031.854%22%0A%09%20xml%3Aspace%3D%22preserve%22%3E%0A%3Cg%3E%0A%09%3Cpath%20d%3D%22M15.94%2C11.735c0%2C0.588-0.476%2C1.064-1.065%2C1.064H5.298c-0.588%2C0-1.065-0.476-1.065-1.064c0-0.587%2C0.477-1.063%2C1.065-1.063%0A%09%09h9.577C15.465%2C10.672%2C15.94%2C11.148%2C15.94%2C11.735z%20M21.52%2C11.087l5.342%2C4.791L15.535%2C28.505l-5.34-4.791L21.52%2C11.087z%0A%09%09%20M20.008%2C16.188l-0.033-0.029c-0.127-0.114-0.293-0.172-0.461-0.163c-0.168%2C0.009-0.328%2C0.085-0.439%2C0.211l-6.079%2C6.776%0A%09%09c-0.253%2C0.281-0.229%2C0.713%2C0.052%2C0.965c0.126%2C0.115%2C0.291%2C0.174%2C0.461%2C0.164c0.168-0.01%2C0.327-0.086%2C0.44-0.213l6.108-6.81%0A%09%09C20.293%2C16.828%2C20.27%2C16.423%2C20.008%2C16.188z%20M21.27%2C13.751l-0.301%2C0.336c-0.111%2C0.126-0.17%2C0.292-0.162%2C0.461%0A%09%09c0.01%2C0.169%2C0.086%2C0.328%2C0.211%2C0.441l0.035%2C0.031c0.127%2C0.113%2C0.293%2C0.171%2C0.461%2C0.162c0.17-0.009%2C0.328-0.085%2C0.44-0.211%0A%09%09l0.728-0.811l-0.51-0.457C21.91%2C13.467%2C21.506%2C13.489%2C21.27%2C13.751z%20M22.035%2C10.51l5.341%2C4.791l1.312-1.462l-5.34-4.791%0A%09%09L22.035%2C10.51z%20M31.047%2C11.21l-1.877%2C2.093l-5.341-4.79l1.88-2.093c0.247-0.28%2C0.6-0.448%2C0.975-0.468%0A%09%09c0.375-0.021%2C0.742%2C0.109%2C1.021%2C0.359l3.234%2C2.903C31.52%2C9.735%2C31.569%2C10.629%2C31.047%2C11.21z%20M29.996%2C10.264l-3.233-2.902%0A%09%09l-0.937%2C1.042l3.233%2C2.903L29.996%2C10.264z%20M9.557%2C24.428l5.339%2C4.791l-6.31%2C2.586c-0.243%2C0.098-0.521%2C0.051-0.716-0.125%0A%09%09c-0.196-0.177-0.273-0.447-0.201-0.699L9.557%2C24.428z%20M10.045%2C26.13l-0.722%2C2.507l1.411%2C1.268l2.412-0.986L10.045%2C26.13z%0A%09%09%20M8.215%2C24.285l-0.623%2C2.162H2.859c-1.332%2C0-2.413-1.08-2.413-2.412V6.493c0-1.332%2C1.08-2.413%2C2.413-2.413h2.94V3.906%0A%09%09c0-0.492%2C0.399-0.89%2C0.89-0.89h0.74C7.414%2C2.909%2C7.406%2C2.781%2C7.406%2C2.661C7.406%2C1.194%2C8.599%2C0%2C10.067%2C0%0A%09%09c1.469%2C0%2C2.661%2C1.194%2C2.661%2C2.661c0%2C0.12-0.01%2C0.248-0.023%2C0.354h0.749c0.492%2C0%2C0.89%2C0.399%2C0.89%2C0.89v0.174h2.87%0A%09%09c1.332%2C0%2C2.412%2C1.081%2C2.412%2C2.413v4.63l-2.128%2C2.372V7.506c0-0.699-0.565-1.265-1.264-1.265h-1.891v1.201%0A%09%09c0%2C0.491-0.399%2C0.89-0.89%2C0.89H6.69c-0.492%2C0-0.89-0.399-0.89-0.89V6.241H3.874c-0.699%2C0-1.265%2C0.567-1.265%2C1.265V23.02%0A%09%09c0%2C0.701%2C0.567%2C1.266%2C1.265%2C1.266H8.215z%20M9.003%2C2.661c0%2C0.124%2C0.023%2C0.248%2C0.061%2C0.355h2.005c0.04-0.107%2C0.062-0.23%2C0.062-0.355%0A%09%09c0-0.587-0.477-1.065-1.064-1.065C9.479%2C1.596%2C9.003%2C2.074%2C9.003%2C2.661z%20M14.949%2C16.341l0.991-1.105%0A%09%09c-0.014-0.576-0.484-1.054-1.064-1.054H5.298c-0.588%2C0-1.065%2C0.493-1.065%2C1.082c0%2C0.587%2C0.477%2C1.082%2C1.065%2C1.082h9.577%0A%09%09C14.9%2C16.344%2C14.925%2C16.342%2C14.949%2C16.341z%20M4.233%2C18.791c0%2C0.588%2C0.477%2C1.062%2C1.065%2C1.062H11.8l1.907-2.127H5.298%0A%09%09C4.71%2C17.727%2C4.233%2C18.203%2C4.233%2C18.791z%22%2F%3E%0A%3C%2Fg%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Drafts</a></li>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('folder_sources' => array(array('email_folders', 'baz')), 'formatted_folder_list' => '<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v1h8v-1h-8zm0%202v5.906c0%20.06.034.094.094.094h7.813c.06%200%20.094-.034.094-.094v-5.906h-2.969v1.031h-2.031v-1.031h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Everything</a><span class="combined_inbox_count"></span></li><li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%201v1l4%202%204-2v-1h-8zm0%202v4h8v-4l-4%202-4-2z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Unread</a><span class="total_unread_count"></span></li><li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v8h1v-8h-1zm2%200v4h2v1h4l-2-1.969%202-2.031h-3v-1h-3z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Flagged</a> <span class="flagged_count"></span></li><li class="menu_junk"><a class="unread_link" href="?page=message_list&amp;list_path=junk"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20width%3D%2223%22%20height%3D%2223%22%20viewBox%3D%220%200%2023%2023%22%3E%0A%20%20%3Cpath%20d%3D%22M1%204L1%205.5820312L11%2012L21%205.5820312L21%204L1%204%20z%20M%201%207.7519531L1%2010.552734L1%2020L12.421875%2020L14%2020L14.757812%2020L15.757812%2019L13.636719%2016.878906L17.878906%2012.636719L20%2014.757812L21%2013.757812L21%2013L21%209.1953125L21%207.7519531L11%2014L1%207.7519531%20z%20M%2017.878906%2015.464844L16.464844%2016.878906L18.585938%2019L16.464844%2021.121094L17.878906%2022.535156L20%2020.414062L22.121094%2022.535156L23.535156%2021.121094L21.414062%2019L23.535156%2016.878906L22.121094%2015.464844L20%2017.585938L17.878906%2015.464844%20z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Junk</a></li><li class="menu_trash"><a class="unread_link" href="?page=message_list&amp;list_path=trash"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20viewBox%3D%220%200%2028%2028%22%3E%0A%20%20%3Cpath%20d%3D%22M6%208v16c0%201.1.9%202%202%202h14c1.1%200%202-.9%202-2V8H6zM24%204h-6c0-.6-.4-1-1-1h-4c-.6%200-1%20.4-1%201H6C5.4%204%205%204.4%205%205s.4%201%201%201h18c.6%200%201-.4%201-1S24.6%204%2024%204z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Trash</a></li><li class="menu_drafts"><a class="unread_link" href="?page=message_list&amp;list_path=drafts"><img class="account_icon" src="data:image/svg+xml,%3C%3Fxml%20version%3D%221.0%22%20encoding%3D%22iso-8859-1%22%3F%3E%0A%3C%21--%20Uploaded%20to%3A%20SVG%20Repo%2C%20www.svgrepo.com%2C%20Generator%3A%20SVG%20Repo%20Mixer%20Tools%20--%3E%0A%3C%21DOCTYPE%20svg%20PUBLIC%20%22-%2F%2FW3C%2F%2FDTD%20SVG%201.1%2F%2FEN%22%20%22http%3A%2F%2Fwww.w3.org%2FGraphics%2FSVG%2F1.1%2FDTD%2Fsvg11.dtd%22%3E%0A%3Csvg%20fill%3D%22%23000000%22%20version%3D%221.1%22%20id%3D%22Capa_1%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20%0A%09%20width%3D%22800px%22%20height%3D%22800px%22%20viewBox%3D%220%200%2031.854%2031.854%22%0A%09%20xml%3Aspace%3D%22preserve%22%3E%0A%3Cg%3E%0A%09%3Cpath%20d%3D%22M15.94%2C11.735c0%2C0.588-0.476%2C1.064-1.065%2C1.064H5.298c-0.588%2C0-1.065-0.476-1.065-1.064c0-0.587%2C0.477-1.063%2C1.065-1.063%0A%09%09h9.577C15.465%2C10.672%2C15.94%2C11.148%2C15.94%2C11.735z%20M21.52%2C11.087l5.342%2C4.791L15.535%2C28.505l-5.34-4.791L21.52%2C11.087z%0A%09%09%20M20.008%2C16.188l-0.033-0.029c-0.127-0.114-0.293-0.172-0.461-0.163c-0.168%2C0.009-0.328%2C0.085-0.439%2C0.211l-6.079%2C6.776%0A%09%09c-0.253%2C0.281-0.229%2C0.713%2C0.052%2C0.965c0.126%2C0.115%2C0.291%2C0.174%2C0.461%2C0.164c0.168-0.01%2C0.327-0.086%2C0.44-0.213l6.108-6.81%0A%09%09C20.293%2C16.828%2C20.27%2C16.423%2C20.008%2C16.188z%20M21.27%2C13.751l-0.301%2C0.336c-0.111%2C0.126-0.17%2C0.292-0.162%2C0.461%0A%09%09c0.01%2C0.169%2C0.086%2C0.328%2C0.211%2C0.441l0.035%2C0.031c0.127%2C0.113%2C0.293%2C0.171%2C0.461%2C0.162c0.17-0.009%2C0.328-0.085%2C0.44-0.211%0A%09%09l0.728-0.811l-0.51-0.457C21.91%2C13.467%2C21.506%2C13.489%2C21.27%2C13.751z%20M22.035%2C10.51l5.341%2C4.791l1.312-1.462l-5.34-4.791%0A%09%09L22.035%2C10.51z%20M31.047%2C11.21l-1.877%2C2.093l-5.341-4.79l1.88-2.093c0.247-0.28%2C0.6-0.448%2C0.975-0.468%0A%09%09c0.375-0.021%2C0.742%2C0.109%2C1.021%2C0.359l3.234%2C2.903C31.52%2C9.735%2C31.569%2C10.629%2C31.047%2C11.21z%20M29.996%2C10.264l-3.233-2.902%0A%09%09l-0.937%2C1.042l3.233%2C2.903L29.996%2C10.264z%20M9.557%2C24.428l5.339%2C4.791l-6.31%2C2.586c-0.243%2C0.098-0.521%2C0.051-0.716-0.125%0A%09%09c-0.196-0.177-0.273-0.447-0.201-0.699L9.557%2C24.428z%20M10.045%2C26.13l-0.722%2C2.507l1.411%2C1.268l2.412-0.986L10.045%2C26.13z%0A%09%09%20M8.215%2C24.285l-0.623%2C2.162H2.859c-1.332%2C0-2.413-1.08-2.413-2.412V6.493c0-1.332%2C1.08-2.413%2C2.413-2.413h2.94V3.906%0A%09%09c0-0.492%2C0.399-0.89%2C0.89-0.89h0.74C7.414%2C2.909%2C7.406%2C2.781%2C7.406%2C2.661C7.406%2C1.194%2C8.599%2C0%2C10.067%2C0%0A%09%09c1.469%2C0%2C2.661%2C1.194%2C2.661%2C2.661c0%2C0.12-0.01%2C0.248-0.023%2C0.354h0.749c0.492%2C0%2C0.89%2C0.399%2C0.89%2C0.89v0.174h2.87%0A%09%09c1.332%2C0%2C2.412%2C1.081%2C2.412%2C2.413v4.63l-2.128%2C2.372V7.506c0-0.699-0.565-1.265-1.264-1.265h-1.891v1.201%0A%09%09c0%2C0.491-0.399%2C0.89-0.89%2C0.89H6.69c-0.492%2C0-0.89-0.399-0.89-0.89V6.241H3.874c-0.699%2C0-1.265%2C0.567-1.265%2C1.265V23.02%0A%09%09c0%2C0.701%2C0.567%2C1.266%2C1.265%2C1.266H8.215z%20M9.003%2C2.661c0%2C0.124%2C0.023%2C0.248%2C0.061%2C0.355h2.005c0.04-0.107%2C0.062-0.23%2C0.062-0.355%0A%09%09c0-0.587-0.477-1.065-1.064-1.065C9.479%2C1.596%2C9.003%2C2.074%2C9.003%2C2.661z%20M14.949%2C16.341l0.991-1.105%0A%09%09c-0.014-0.576-0.484-1.054-1.064-1.054H5.298c-0.588%2C0-1.065%2C0.493-1.065%2C1.082c0%2C0.587%2C0.477%2C1.082%2C1.065%2C1.082h9.577%0A%09%09C14.9%2C16.344%2C14.925%2C16.342%2C14.949%2C16.341z%20M4.233%2C18.791c0%2C0.588%2C0.477%2C1.062%2C1.065%2C1.062H11.8l1.907-2.127H5.298%0A%09%09C4.71%2C17.727%2C4.233%2C18.203%2C4.233%2C18.791z%22%2F%3E%0A%3C%2Fg%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Drafts</a></li>'), $res->output_response);
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
        $test->handler_response = array('single_server_mode' => true, 'folder_sources' => array(array('email_folders', 'baz')));
        $res = $test->run();
        $this->assertEquals(array('<div class="email_folders"><ul class="folders">baz</ul></div>'), $res->output_response);
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
        $this->assertEquals(array('<div class="save_settings_page"><div class="content_title">Save Settings</div><div class="save_details">Settings are not saved permanently on the server unless you explicitly allow it. If you don\'t save your settings, any changes made since you last logged in will be deleted when your session expires or you logout. You must re-enter your password for security purposes to save your settings permanently.<div class="save_subtitle">Unsaved Changes</div><ul class="unsaved_settings"><li>No changes need to be saved</li></ul></div><div class="save_perm_form"><form method="post"><input type="hidden" name="hm_page_key" value="" /><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="password">Password</label><input required id="password" name="password" autocomplete="current-password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_settings_permanently" value="Save" /><input class="save_settings" type="submit" name="save_settings_permanently_then_logout" value="Save and Logout" /></form><form method="post"><input type="hidden" name="hm_page_key" value="" /><input class="save_settings" type="submit" name="logout" value="Just Logout" /></form></div></div>'), $res->output_response);
        $test->handler_response = array('changed_settings' => array('foo'));
        $res = $test->run();
        $this->assertEquals(array('<div class="save_settings_page"><div class="content_title">Save Settings</div><div class="save_details">Settings are not saved permanently on the server unless you explicitly allow it. If you don\'t save your settings, any changes made since you last logged in will be deleted when your session expires or you logout. You must re-enter your password for security purposes to save your settings permanently.<div class="save_subtitle">Unsaved Changes</div><ul class="unsaved_settings"><li>foo (1X)</li></ul></div><div class="save_perm_form"><form method="post"><input type="hidden" name="hm_page_key" value="" /><input type="text" value="cypht_user" autocomplete="username" style="display: none;"/><label class="screen_reader" for="password">Password</label><input required id="password" name="password" autocomplete="current-password" class="save_settings_password" type="password" placeholder="Password" /><input class="save_settings" type="submit" name="save_settings_permanently" value="Save" /><input class="save_settings" type="submit" name="save_settings_permanently_then_logout" value="Save and Logout" /></form><form method="post"><input type="hidden" name="hm_page_key" value="" /><input class="save_settings" type="submit" name="logout" value="Just Logout" /></form></div></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_servers_link() {
        $test = new Output_Test('settings_servers_link', 'core');
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<li class="menu_servers"><a class="unread_link" href="?page=servers"><img class="account_icon" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M.344%200a.5.5%200%200%200-.344.5v5a.5.5%200%200%200%20.5.5h2.5v1h-1c-.55%200-1%20.45-1%201h6c0-.55-.45-1-1-1h-1v-1h2.5a.5.5%200%200%200%20.5-.5v-5a.5.5%200%200%200-.5-.5h-7a.5.5%200%200%200-.094%200%20.5.5%200%200%200-.063%200zm.656%201h6v4h-6v-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="" width="16" height="16" /> Servers</a></li>'), $res->output_response);
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
        $this->assertEquals(array('<a href="#" class="update_message_list">[reload]</a><a href="#" class="hide_folders">Hide folders<img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="Collapse" width="16" height="16" /></a>'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<a href="#" class="update_message_list">[reload]</a><a href="#" class="hide_folders">Hide folders<img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="Collapse" width="16" height="16" /></a>'), $res->output_response);
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
        $this->assertEquals(array('<main class="content_cell"><div class="offline">Offline</div>'), $res->output_response);
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
        $test->handler_response = array('list_parent' => 'advanced_search');
        $res = $test->run();
        $this->assertEquals(array('<div class="content_title"><a href="?page=advanced_search&amp;list_path=advanced_search">Advanced Search</a></div><div class="msg_text">'), $res->output_response);
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
        $test->handler_response = array('message_list_fields' => array(array(false, true, false)));
        $res = $test->run();
        $this->assertEquals(array('<table class="message_table"><thead><tr><th></th></tr></thead><tbody class="message_table_body">'), $res->output_response);
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

        $this->assertEquals(array('<div class="message_list _list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" class="combined_sort"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select><div class="list_controls no_mobile"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a title="Configure" href="?page=settings#_setting"><img alt="Configure" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div>
    <div class="list_controls on_mobile">
        <img alt="" src="data:image/svg+xml;base64,PCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KDTwhLS0gVXBsb2FkZWQgdG86IFNWRyBSZXBvLCB3d3cuc3ZncmVwby5jb20sIFRyYW5zZm9ybWVkIGJ5OiBTVkcgUmVwbyBNaXhlciBUb29scyAtLT4KPHN2ZyBmaWxsPSIjN0Y3RjdGIiBoZWlnaHQ9IjI1NnB4IiB3aWR0aD0iMjU2cHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmlld0JveD0iMCAwIDMyLjA1NSAzMi4wNTUiIHhtbDpzcGFjZT0icHJlc2VydmUiPgoNPGcgaWQ9IlNWR1JlcG9fYmdDYXJyaWVyIiBzdHJva2Utd2lkdGg9IjAiLz4KDTxnIGlkPSJTVkdSZXBvX3RyYWNlckNhcnJpZXIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlPSIjQ0NDQ0NDIiBzdHJva2Utd2lkdGg9IjEuNjY2ODYwMDAwMDAwMDAwMiI+IDxnPiA8cGF0aCBkPSJNMy45NjgsMTIuMDYxQzEuNzc1LDEyLjA2MSwwLDEzLjgzNSwwLDE2LjAyN2MwLDIuMTkyLDEuNzczLDMuOTY3LDMuOTY4LDMuOTY3YzIuMTg5LDAsMy45NjYtMS43NzIsMy45NjYtMy45NjcgQzcuOTM0LDEzLjgzNSw2LjE1NywxMi4wNjEsMy45NjgsMTIuMDYxeiBNMTYuMjMzLDEyLjA2MWMtMi4xODgsMC0zLjk2OCwxLjc3My0zLjk2OCwzLjk2NWMwLDIuMTkyLDEuNzc4LDMuOTY3LDMuOTY4LDMuOTY3IHMzLjk3LTEuNzcyLDMuOTctMy45NjdDMjAuMjAxLDEzLjgzNSwxOC40MjMsMTIuMDYxLDE2LjIzMywxMi4wNjF6IE0yOC4wOSwxMi4wNjFjLTIuMTkyLDAtMy45NjksMS43NzQtMy45NjksMy45NjcgYzAsMi4xOSwxLjc3NCwzLjk2NSwzLjk2OSwzLjk2NWMyLjE4OCwwLDMuOTY1LTEuNzcyLDMuOTY1LTMuOTY1UzMwLjI3OCwxMi4wNjEsMjguMDksMTIuMDYxeiIvPiA8L2c+IDwvZz4KDTxnIGlkPSJTVkdSZXBvX2ljb25DYXJyaWVyIj4gPGc+IDxwYXRoIGQ9Ik0zLjk2OCwxMi4wNjFDMS43NzUsMTIuMDYxLDAsMTMuODM1LDAsMTYuMDI3YzAsMi4xOTIsMS43NzMsMy45NjcsMy45NjgsMy45NjdjMi4xODksMCwzLjk2Ni0xLjc3MiwzLjk2Ni0zLjk2NyBDNy45MzQsMTMuODM1LDYuMTU3LDEyLjA2MSwzLjk2OCwxMi4wNjF6IE0xNi4yMzMsMTIuMDYxYy0yLjE4OCwwLTMuOTY4LDEuNzczLTMuOTY4LDMuOTY1YzAsMi4xOTIsMS43NzgsMy45NjcsMy45NjgsMy45NjcgczMuOTctMS43NzIsMy45Ny0zLjk2N0MyMC4yMDEsMTMuODM1LDE4LjQyMywxMi4wNjEsMTYuMjMzLDEyLjA2MXogTTI4LjA5LDEyLjA2MWMtMi4xOTIsMC0zLjk2OSwxLjc3NC0zLjk2OSwzLjk2NyBjMCwyLjE5LDEuNzc0LDMuOTY1LDMuOTY5LDMuOTY1YzIuMTg4LDAsMy45NjUtMS43NzIsMy45NjUtMy45NjVTMzAuMjc4LDEyLjA2MSwyOC4wOSwxMi4wNjF6Ii8+IDwvZz4gPC9nPgoNPC9zdmc+" width="20" height="20" onclick="listControlsMenu()"/>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a title="Configure" href="?page=settings#_setting"><img alt="Configure" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div>
    </div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('custom_list_controls' => 'foo');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list _list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" class="combined_sort"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select><div class="list_controls no_mobile"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a>foo</div>
    <div class="list_controls on_mobile">
        <img alt="" src="data:image/svg+xml;base64,PCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KDTwhLS0gVXBsb2FkZWQgdG86IFNWRyBSZXBvLCB3d3cuc3ZncmVwby5jb20sIFRyYW5zZm9ybWVkIGJ5OiBTVkcgUmVwbyBNaXhlciBUb29scyAtLT4KPHN2ZyBmaWxsPSIjN0Y3RjdGIiBoZWlnaHQ9IjI1NnB4IiB3aWR0aD0iMjU2cHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmlld0JveD0iMCAwIDMyLjA1NSAzMi4wNTUiIHhtbDpzcGFjZT0icHJlc2VydmUiPgoNPGcgaWQ9IlNWR1JlcG9fYmdDYXJyaWVyIiBzdHJva2Utd2lkdGg9IjAiLz4KDTxnIGlkPSJTVkdSZXBvX3RyYWNlckNhcnJpZXIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlPSIjQ0NDQ0NDIiBzdHJva2Utd2lkdGg9IjEuNjY2ODYwMDAwMDAwMDAwMiI+IDxnPiA8cGF0aCBkPSJNMy45NjgsMTIuMDYxQzEuNzc1LDEyLjA2MSwwLDEzLjgzNSwwLDE2LjAyN2MwLDIuMTkyLDEuNzczLDMuOTY3LDMuOTY4LDMuOTY3YzIuMTg5LDAsMy45NjYtMS43NzIsMy45NjYtMy45NjcgQzcuOTM0LDEzLjgzNSw2LjE1NywxMi4wNjEsMy45NjgsMTIuMDYxeiBNMTYuMjMzLDEyLjA2MWMtMi4xODgsMC0zLjk2OCwxLjc3My0zLjk2OCwzLjk2NWMwLDIuMTkyLDEuNzc4LDMuOTY3LDMuOTY4LDMuOTY3IHMzLjk3LTEuNzcyLDMuOTctMy45NjdDMjAuMjAxLDEzLjgzNSwxOC40MjMsMTIuMDYxLDE2LjIzMywxMi4wNjF6IE0yOC4wOSwxMi4wNjFjLTIuMTkyLDAtMy45NjksMS43NzQtMy45NjksMy45NjcgYzAsMi4xOSwxLjc3NCwzLjk2NSwzLjk2OSwzLjk2NWMyLjE4OCwwLDMuOTY1LTEuNzcyLDMuOTY1LTMuOTY1UzMwLjI3OCwxMi4wNjEsMjguMDksMTIuMDYxeiIvPiA8L2c+IDwvZz4KDTxnIGlkPSJTVkdSZXBvX2ljb25DYXJyaWVyIj4gPGc+IDxwYXRoIGQ9Ik0zLjk2OCwxMi4wNjFDMS43NzUsMTIuMDYxLDAsMTMuODM1LDAsMTYuMDI3YzAsMi4xOTIsMS43NzMsMy45NjcsMy45NjgsMy45NjdjMi4xODksMCwzLjk2Ni0xLjc3MiwzLjk2Ni0zLjk2NyBDNy45MzQsMTMuODM1LDYuMTU3LDEyLjA2MSwzLjk2OCwxMi4wNjF6IE0xNi4yMzMsMTIuMDYxYy0yLjE4OCwwLTMuOTY4LDEuNzczLTMuOTY4LDMuOTY1YzAsMi4xOTIsMS43NzgsMy45NjcsMy45NjgsMy45NjcgczMuOTctMS43NzIsMy45Ny0zLjk2N0MyMC4yMDEsMTMuODM1LDE4LjQyMywxMi4wNjEsMTYuMjMzLDEyLjA2MXogTTI4LjA5LDEyLjA2MWMtMi4xOTIsMC0zLjk2OSwxLjc3NC0zLjk2OSwzLjk2NyBjMCwyLjE5LDEuNzc0LDMuOTY1LDMuOTY5LDMuOTY1YzIuMTg4LDAsMy45NjUtMS43NzIsMy45NjUtMy45NjVTMzAuMjc4LDEyLjA2MSwyOC4wOSwxMi4wNjF6Ii8+IDwvZz4gPC9nPgoNPC9zdmc+" width="20" height="20" onclick="listControlsMenu()"/>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a>foo</div>
    </div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('no_list_controls' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list _list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" class="combined_sort"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select><div class="list_controls no_mobile"></div>
    <div class="list_controls on_mobile">
        <img alt="" src="data:image/svg+xml;base64,PCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KDTwhLS0gVXBsb2FkZWQgdG86IFNWRyBSZXBvLCB3d3cuc3ZncmVwby5jb20sIFRyYW5zZm9ybWVkIGJ5OiBTVkcgUmVwbyBNaXhlciBUb29scyAtLT4KPHN2ZyBmaWxsPSIjN0Y3RjdGIiBoZWlnaHQ9IjI1NnB4IiB3aWR0aD0iMjU2cHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmlld0JveD0iMCAwIDMyLjA1NSAzMi4wNTUiIHhtbDpzcGFjZT0icHJlc2VydmUiPgoNPGcgaWQ9IlNWR1JlcG9fYmdDYXJyaWVyIiBzdHJva2Utd2lkdGg9IjAiLz4KDTxnIGlkPSJTVkdSZXBvX3RyYWNlckNhcnJpZXIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlPSIjQ0NDQ0NDIiBzdHJva2Utd2lkdGg9IjEuNjY2ODYwMDAwMDAwMDAwMiI+IDxnPiA8cGF0aCBkPSJNMy45NjgsMTIuMDYxQzEuNzc1LDEyLjA2MSwwLDEzLjgzNSwwLDE2LjAyN2MwLDIuMTkyLDEuNzczLDMuOTY3LDMuOTY4LDMuOTY3YzIuMTg5LDAsMy45NjYtMS43NzIsMy45NjYtMy45NjcgQzcuOTM0LDEzLjgzNSw2LjE1NywxMi4wNjEsMy45NjgsMTIuMDYxeiBNMTYuMjMzLDEyLjA2MWMtMi4xODgsMC0zLjk2OCwxLjc3My0zLjk2OCwzLjk2NWMwLDIuMTkyLDEuNzc4LDMuOTY3LDMuOTY4LDMuOTY3IHMzLjk3LTEuNzcyLDMuOTctMy45NjdDMjAuMjAxLDEzLjgzNSwxOC40MjMsMTIuMDYxLDE2LjIzMywxMi4wNjF6IE0yOC4wOSwxMi4wNjFjLTIuMTkyLDAtMy45NjksMS43NzQtMy45NjksMy45NjcgYzAsMi4xOSwxLjc3NCwzLjk2NSwzLjk2OSwzLjk2NWMyLjE4OCwwLDMuOTY1LTEuNzcyLDMuOTY1LTMuOTY1UzMwLjI3OCwxMi4wNjEsMjguMDksMTIuMDYxeiIvPiA8L2c+IDwvZz4KDTxnIGlkPSJTVkdSZXBvX2ljb25DYXJyaWVyIj4gPGc+IDxwYXRoIGQ9Ik0zLjk2OCwxMi4wNjFDMS43NzUsMTIuMDYxLDAsMTMuODM1LDAsMTYuMDI3YzAsMi4xOTIsMS43NzMsMy45NjcsMy45NjgsMy45NjdjMi4xODksMCwzLjk2Ni0xLjc3MiwzLjk2Ni0zLjk2NyBDNy45MzQsMTMuODM1LDYuMTU3LDEyLjA2MSwzLjk2OCwxMi4wNjF6IE0xNi4yMzMsMTIuMDYxYy0yLjE4OCwwLTMuOTY4LDEuNzczLTMuOTY4LDMuOTY1YzAsMi4xOTIsMS43NzgsMy45NjcsMy45NjgsMy45NjcgczMuOTctMS43NzIsMy45Ny0zLjk2N0MyMC4yMDEsMTMuODM1LDE4LjQyMywxMi4wNjEsMTYuMjMzLDEyLjA2MXogTTI4LjA5LDEyLjA2MWMtMi4xOTIsMC0zLjk2OSwxLjc3NC0zLjk2OSwzLjk2NyBjMCwyLjE5LDEuNzc0LDMuOTY1LDMuOTY5LDMuOTY1YzIuMTg4LDAsMy45NjUtMS43NzIsMy45NjUtMy45NjVTMzAuMjc4LDEyLjA2MSwyOC4wOSwxMi4wNjF6Ii8+IDwvZz4gPC9nPgoNPC9zdmc+" width="20" height="20" onclick="listControlsMenu()"/>
        <div id="list_controls_menu" classs="list_controls_menu"></div>
    </div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
        $test->handler_response = array('list_path' => 'combined_inbox');
        $res = $test->run();
        $this->assertEquals(array('<div class="message_list combined_inbox_list"><div class="content_title"><a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control" href="#" data-action="archive">Archive</a></div><div class="mailbox_list_title"></div><select name="sort" class="combined_sort"><option value="4">Arrival Date &darr;</option><option value="-4">Arrival Date &uarr;</option><option value="2">From &darr;</option><option value="-2">From &uarr;</option><option value="3">Subject &darr;</option><option value="-3">Subject &uarr;</option></select><div class="list_controls no_mobile"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a title="Configure" href="?page=settings#all_setting"><img alt="Configure" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div>
    <div class="list_controls on_mobile">
        <img alt="" src="data:image/svg+xml;base64,PCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KDTwhLS0gVXBsb2FkZWQgdG86IFNWRyBSZXBvLCB3d3cuc3ZncmVwby5jb20sIFRyYW5zZm9ybWVkIGJ5OiBTVkcgUmVwbyBNaXhlciBUb29scyAtLT4KPHN2ZyBmaWxsPSIjN0Y3RjdGIiBoZWlnaHQ9IjI1NnB4IiB3aWR0aD0iMjU2cHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmlld0JveD0iMCAwIDMyLjA1NSAzMi4wNTUiIHhtbDpzcGFjZT0icHJlc2VydmUiPgoNPGcgaWQ9IlNWR1JlcG9fYmdDYXJyaWVyIiBzdHJva2Utd2lkdGg9IjAiLz4KDTxnIGlkPSJTVkdSZXBvX3RyYWNlckNhcnJpZXIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlPSIjQ0NDQ0NDIiBzdHJva2Utd2lkdGg9IjEuNjY2ODYwMDAwMDAwMDAwMiI+IDxnPiA8cGF0aCBkPSJNMy45NjgsMTIuMDYxQzEuNzc1LDEyLjA2MSwwLDEzLjgzNSwwLDE2LjAyN2MwLDIuMTkyLDEuNzczLDMuOTY3LDMuOTY4LDMuOTY3YzIuMTg5LDAsMy45NjYtMS43NzIsMy45NjYtMy45NjcgQzcuOTM0LDEzLjgzNSw2LjE1NywxMi4wNjEsMy45NjgsMTIuMDYxeiBNMTYuMjMzLDEyLjA2MWMtMi4xODgsMC0zLjk2OCwxLjc3My0zLjk2OCwzLjk2NWMwLDIuMTkyLDEuNzc4LDMuOTY3LDMuOTY4LDMuOTY3IHMzLjk3LTEuNzcyLDMuOTctMy45NjdDMjAuMjAxLDEzLjgzNSwxOC40MjMsMTIuMDYxLDE2LjIzMywxMi4wNjF6IE0yOC4wOSwxMi4wNjFjLTIuMTkyLDAtMy45NjksMS43NzQtMy45NjksMy45NjcgYzAsMi4xOSwxLjc3NCwzLjk2NSwzLjk2OSwzLjk2NWMyLjE4OCwwLDMuOTY1LTEuNzcyLDMuOTY1LTMuOTY1UzMwLjI3OCwxMi4wNjEsMjguMDksMTIuMDYxeiIvPiA8L2c+IDwvZz4KDTxnIGlkPSJTVkdSZXBvX2ljb25DYXJyaWVyIj4gPGc+IDxwYXRoIGQ9Ik0zLjk2OCwxMi4wNjFDMS43NzUsMTIuMDYxLDAsMTMuODM1LDAsMTYuMDI3YzAsMi4xOTIsMS43NzMsMy45NjcsMy45NjgsMy45NjdjMi4xODksMCwzLjk2Ni0xLjc3MiwzLjk2Ni0zLjk2NyBDNy45MzQsMTMuODM1LDYuMTU3LDEyLjA2MSwzLjk2OCwxMi4wNjF6IE0xNi4yMzMsMTIuMDYxYy0yLjE4OCwwLTMuOTY4LDEuNzczLTMuOTY4LDMuOTY1YzAsMi4xOTIsMS43NzgsMy45NjcsMy45NjgsMy45NjcgczMuOTctMS43NzIsMy45Ny0zLjk2N0MyMC4yMDEsMTMuODM1LDE4LjQyMywxMi4wNjEsMTYuMjMzLDEyLjA2MXogTTI4LjA5LDEyLjA2MWMtMi4xOTIsMC0zLjk2OSwxLjc3NC0zLjk2OSwzLjk2NyBjMCwyLjE5LDEuNzc0LDMuOTY1LDMuOTY5LDMuOTY1YzIuMTg4LDAsMy45NjUtMS43NzIsMy45NjUtMy45NjVTMzAuMjc4LDEyLjA2MSwyOC4wOSwxMi4wNjF6Ii8+IDwvZz4gPC9nPgoNPC9zdmc+" width="20" height="20" onclick="listControlsMenu()"/>
        <div id="list_controls_menu" classs="list_controls_menu"><a class="refresh_link" title="Refresh" href="#"><img alt="Refresh" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.201%200-4%201.799-4%204s1.799%204%204%204c1.104%200%202.092-.456%202.813-1.188l-.688-.688c-.54.548-1.289.875-2.125.875-1.659%200-3-1.341-3-3s1.341-3%203-3c.834%200%201.545.354%202.094.906l-1.094%201.094h3v-3l-1.188%201.188c-.731-.72-1.719-1.188-2.813-1.188z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a href="#" title="Sources" class="source_link"><img alt="Sources" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M0%200v2h8v-1h-5v-1h-3zm0%203v4.5c0%20.28.22.5.5.5h7c.28%200%20.5-.22.5-.5v-4.5h-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a><a title="Configure" href="?page=settings#all_setting"><img alt="Configure" class="refresh_list" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M3.5%200l-.5%201.188-.281.125-1.188-.5-.719.719.5%201.188-.125.281-1.188.5v1l1.188.5.125.313-.5%201.156.719.719%201.188-.5.281.125.5%201.188h1l.5-1.188.281-.125%201.188.5.719-.719-.5-1.188.125-.281%201.188-.5v-1l-1.188-.5-.125-.281.469-1.188-.688-.719-1.188.5-.281-.125-.5-1.188h-1zm.5%202.5c.83%200%201.5.67%201.5%201.5s-.67%201.5-1.5%201.5-1.5-.67-1.5-1.5.67-1.5%201.5-1.5z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E" width="20" height="20" /></a></div>
    </div><div class="list_sources"><div class="src_title">Sources</div></div></div>'), $res->output_response);
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
class Hm_Test_Core_Output_Modules_Debug extends TestCase {
    public function setUp(): void {
        define('DEBUG_MODE', true);
        require __DIR__.'/../../bootstrap.php';
        require __DIR__.'/../../helpers.php';
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
        $this->assertEquals(array('<link href="modules/core/site.css" media="all" rel="stylesheet" type="text/css" /><style type="text/css">@font-face {font-family:"Behdad";src:url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff2") format("woff2"),url("modules/core/assets/fonts/Behdad/Behdad-Regular.woff") format("woff");</style>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js_debug() {
        $test = new Output_Test('page_js', 'core');
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('foo', 'core'));
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="third_party/cash.min.js"></script><script type="text/javascript" src="third_party/resumable.min.js"></script><script type="text/javascript" src="third_party/tingle.min.js"></script><script type="text/javascript" src="third_party/ays-beforeunload-shim.js"></script><script type="text/javascript" src="third_party/jquery.are-you-sure.js"></script><script type="text/javascript" src="third_party/sortable.min.js"></script><script type="text/javascript" src="third_party/forge.min.js"></script><script type="text/javascript" src="modules/core/site.js"></script>'), $res->output_response);
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('imap'));
        $res = $test->run();
        $this->assertEquals(array('<script type="text/javascript" src="third_party/cash.min.js"></script><script type="text/javascript" src="third_party/resumable.min.js"></script><script type="text/javascript" src="third_party/tingle.min.js"></script><script type="text/javascript" src="third_party/ays-beforeunload-shim.js"></script><script type="text/javascript" src="third_party/jquery.are-you-sure.js"></script><script type="text/javascript" src="third_party/sortable.min.js"></script><script type="text/javascript" src="third_party/forge.min.js"></script><script type="text/javascript" src="modules/core/site.js"></script><script type="text/javascript" src="modules/imap/site.js"></script>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start_debug() {
        $test = new Output_Test('main_menu_start', 'core');
        $res = $test->run();
        $this->assertEquals(array('<div class="src_name main_menu" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">Debug</span><img alt="" class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></div><div class="main"><ul class="folders">'), $res->output_response);
        $test->rtype = 'AJAX';
        $res = $test->run();
        $this->assertEquals(array('formatted_folder_list' => '<div class="src_name main_menu" data-source=".main">Main <span title="Running in debug mode. See https://cypht.org/install.html Section 6 for more detail." class="debug_title">Debug</span><img alt="" class="menu_caret" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M1.5%201l-1.5%201.5%204%204%204-4-1.5-1.5-2.5%202.5-2.5-2.5z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></div><div class="main"><ul class="folders">'), $res->output_response);
    }
}
?>
