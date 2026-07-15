<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Handler_Modules extends TestCase {

    public function setUp(): void {
        require __DIR__.'/../../helpers.php';
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

        $test->post = array('server_pw_id' => 'a1', 'password' => 'foo');
        $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());

        Hm_SMTP_List::add(array('user' => 'testuser', 'nopass' => 1, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => 'a1'));

        $test->input = array('missing_pw_servers' => array('a1' => array('id' => 'a1', 'type' => 'SMTP')));
        $res = $test->run();
        $this->assertEquals(array('Unable to authenticate to the SMTP server'), Hm_Msgs::get());
        $this->assertFalse($res->handler_response['connect_status']);
        Hm_Msgs::flush();

        Hm_SMTP_List::change_state('authed');
        $res = $test->run();
        $this->assertEquals(array('Password Updated'), Hm_Msgs::get());
        $this->assertTrue($res->handler_response['connect_status']);
        Hm_Msgs::flush();

        Hm_IMAP_List::add(array('user' => 'testuser', 'nopass' => 1, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => 'a1'));

        $test->input = array('missing_pw_servers' => array('a1' => array('id' => 'a1', 'type' => 'IMAP')));
        $res = $test->run();
        $this->assertEquals(array('Unable to authenticate to the IMAP server'), Hm_Msgs::get());
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
            'Content-Security-Policy' => "default-src 'none'; script-src 'self' 'unsafe-inline'; connect-src 'self'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;",
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
            'Content-Security-Policy' => "default-src 'none'; script-src 'self' 'unsafe-inline'; connect-src 'self'; font-src 'self' https://fonts.gstatic.com; img-src * data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;",
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
        $this->assertEquals(array('Incorrect password, could not save settings to the server'), Hm_Msgs::get());
        Hm_Msgs::flush();
        $test->prep();
        $test->ses_obj->auth_state = true;
        $test->run_only();
        $this->assertEquals(array('Saved user data on logout, Session destroyed on logout'), Hm_Msgs::get());
        Hm_Msgs::flush();

        $test->post = array('save_and_logout' => true);
        $test->run();
        $this->assertEquals(array('Your password is required to save your settings to the server'), Hm_Msgs::get());
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
        $this->assertEquals(DEFAULT_SEARCH_SINCE, $res->session->get('search_since'));
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

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_sort_order_setting_outputs_arrival_by_default() {
        $test = new Handler_Test('default_sort_order_setting', 'core');
        $res = $test->run();
        $this->assertEquals('arrival', $res->handler_response['default_sort_order']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_default_sort_order_setting_saves_valid_value() {
        $test = new Handler_Test('process_default_sort_order_setting', 'core');
        $test->post = array('save_settings' => 1, 'default_sort_order' => 'date');
        $res = $test->run();
        $this->assertEquals('date', $res->handler_response['new_user_settings']['default_sort_order_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ajax_save_search_all_folders_saves_valid_value() {
        $test = new Handler_Test('ajax_save_search_all_folders', 'core');
        $test->post = array('search_all_folders' => '1');
        $res = $test->run();
        $this->assertTrue($res->handler_response['search_all_folders_saved']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ajax_save_search_all_folders_ignores_invalid_value() {
        $test = new Handler_Test('ajax_save_search_all_folders', 'core');
        $test->post = array('search_all_folders' => '5');
        $res = $test->run();
        $this->assertArrayNotHasKey('search_all_folders_saved', $res->handler_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_search_all_folders_setting_outputs_enabled_flag() {
        $test = new Handler_Test('load_search_all_folders_setting', 'core');
        $test->user_config = array('search_all_folders_setting' => 1);
        $res = $test->run();
        $this->assertTrue($res->handler_response['search_all_folders_enabled']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_snoozed_source_max_setting_saves_value() {
        $test = new Handler_Test('process_snoozed_source_max_setting', 'core');
        $test->post = array('save_settings' => 1, 'snoozed_per_source' => 15);
        $res = $test->run();
        $this->assertEquals(15, $res->handler_response['new_user_settings']['snoozed_per_source_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_snoozed_since_setting_saves_value() {
        $test = new Handler_Test('process_snoozed_since_setting', 'core');
        $test->post = array('save_settings' => 1, 'snoozed_since' => '-1 week');
        $res = $test->run();
        $this->assertEquals('-1 week', $res->handler_response['new_user_settings']['snoozed_since_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_enable_snooze_setting_saves_bool_value() {
        $test = new Handler_Test('process_enable_snooze_setting', 'core');
        $test->post = array('save_settings' => 1, 'enable_snooze' => 1);
        $res = $test->run();
        $this->assertArrayHasKey('enable_snooze_setting', $res->handler_response['new_user_settings']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_junk_source_max_setting_saves_value() {
        $test = new Handler_Test('process_junk_source_max_setting', 'core');
        $test->post = array('save_settings' => 1, 'junk_per_source' => 10);
        $res = $test->run();
        $this->assertEquals(10, $res->handler_response['new_user_settings']['junk_per_source_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_junk_since_setting_saves_value() {
        $test = new Handler_Test('process_junk_since_setting', 'core');
        $test->post = array('save_settings' => 1, 'junk_since' => '-1 week');
        $res = $test->run();
        $this->assertEquals('-1 week', $res->handler_response['new_user_settings']['junk_since_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_trash_source_max_setting_saves_value() {
        $test = new Handler_Test('process_trash_source_max_setting', 'core');
        $test->post = array('save_settings' => 1, 'trash_per_source' => 12);
        $res = $test->run();
        $this->assertEquals(12, $res->handler_response['new_user_settings']['trash_per_source_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_trash_since_setting_saves_value() {
        $test = new Handler_Test('process_trash_since_setting', 'core');
        $test->post = array('save_settings' => 1, 'trash_since' => '-1 week');
        $res = $test->run();
        $this->assertEquals('-1 week', $res->handler_response['new_user_settings']['trash_since_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_warn_for_unsaved_changes_saves_value() {
        $test = new Handler_Test('process_warn_for_unsaved_changes_setting', 'core');
        $test->post = array('save_settings' => 1, 'warn_for_unsaved_changes' => 1);
        $res = $test->run();
        $this->assertArrayHasKey('warn_for_unsaved_changes_setting', $res->handler_response['new_user_settings']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_delete_attachment_setting_saves_value() {
        $test = new Handler_Test('process_delete_attachment_setting', 'core');
        $test->post = array('save_settings' => 1, 'allow_delete_attachment' => 1);
        $res = $test->run();
        $this->assertArrayHasKey('allow_delete_attachment_setting', $res->handler_response['new_user_settings']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_hm_msgs_stores_messages_as_session_cookie() {
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        Hm_Msgs::flush();
        Hm_Msgs::add('Test message for cookie', 'warning');
        $handler_mod->save_hm_msgs();
        $this->assertTrue($handler_mod->session->cookie_set);
        $this->assertEmpty(Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_hm_msgs_does_nothing_when_no_messages() {
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        Hm_Msgs::flush();
        $handler_mod->save_hm_msgs();
        $this->assertFalse($handler_mod->session->cookie_set);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_outputs_redirect_url_when_configured() {
        $test = new Handler_Test('login', 'core');
        $test->config = array('redirect_after_login' => '?page=home');
        $test->post = array('username' => 'testuser', 'password' => 'testpass');
        $res = $test->run();
        $this->assertEquals('?page=home', $res->handler_response['redirect_url']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_logout_handler_outputs_cancel_url_when_prompt_is_set() {
        $test = new Handler_Test('logout', 'core');
        $back_query = base64_encode(serialize(array('page' => 'home')));
        $test->get = array('prompt' => '1', 'back_query' => $back_query);
        $res = $test->run();
        $this->assertNotEmpty($res->handler_response['cancel_logout_url']);
        $this->assertStringContainsString('page=home', $res->handler_response['cancel_logout_url']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_logout_handler_outputs_cancel_url_without_back_query() {
        $test = new Handler_Test('logout', 'core');
        $test->get = array('prompt' => '1');
        $res = $test->run();
        $this->assertArrayHasKey('cancel_logout_url', $res->handler_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_type_clamps_negative_list_page_to_one() {
        $test = new Handler_Test('message_list_type', 'core');
        $test->get = array('list_path' => 'unread', 'list_page' => -5);
        $res = $test->run();
        $this->assertEquals(1, $res->handler_response['list_page']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_type_uses_news_style_from_user_config() {
        $test = new Handler_Test('message_list_type', 'core');
        $test->get = array('list_path' => 'unread');
        $test->user_config = array('list_style_setting' => 'news_style');
        $res = $test->run();
        $this->assertEquals(1, $res->handler_response['news_list_style']);
        $this->assertTrue($res->handler_response['no_message_list_headers']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_drafts_source_max_setting_saves_value() {
        $test = new Handler_Test('process_drafts_source_max_setting', 'core');
        $test->post = array('save_settings' => 1, 'drafts_per_source' => 10);
        $res = $test->run();
        $this->assertEquals(10, $res->handler_response['new_user_settings']['drafts_per_source_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_drafts_since_setting_saves_value() {
        $test = new Handler_Test('process_drafts_since_setting', 'core');
        $test->post = array('save_settings' => 1, 'drafts_since' => '-1 week');
        $res = $test->run();
        $this->assertEquals('-1 week', $res->handler_response['new_user_settings']['drafts_since_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_privacy_settings_processes_all_privacy_settings() {
        $test = new Handler_Test('privacy_settings', 'core');
        $test->post = array('save_settings' => 1, 'images_whitelist' => 'sender@example.com');
        $res = $test->run();
        $this->assertArrayHasKey('new_user_settings', $res->handler_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_version_upgrade_checker_uses_cached_version_from_session_without_http() {
        $test = new Handler_Test('version_upgrade_checker', 'core');
        $test->session = array('latest_version' => '99.0.0');
        $res = $test->run();
        $this->assertEquals('99.0.0', $res->handler_response['latest_version']);
        $this->assertTrue($res->handler_response['need_upgrade']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_quick_servers_setup_returns_early_when_required_form_fields_missing() {
        $test = new Handler_Test('quick_servers_setup', 'core');
        $test->post = array();
        $test->run();
        $this->assertEquals(array(), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reset_factory_restores_defaults_and_adds_message() {
        $test = new Handler_Test('reset_factory', 'core');
        $test->post = array('reset_factory' => 1);
        $test->run();
        $this->assertEquals(array('Settings restored to default'), Hm_Msgs::get());
        Hm_Msgs::flush();
    }
}
