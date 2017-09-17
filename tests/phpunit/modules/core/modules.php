<?php

/**
 * TODO: add assertions to all tests
 */

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
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_flagged_source_max_setting() {
        $test = new Handler_Test('process_flagged_source_max_setting', 'core');
        $test->post = array('save_settings' => true, 'flagged_per_source' => 10);
        $test->run();
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
        $test->run();
        $test->post = array('save_settings' => true, 'password' => 'foo');
        $test->run();
        $test->post = array('save_settings_permanently' => 1, 'save_settings' => true, 'password' => 'foo');
        $test->run();
        $test->post = array('save_settings_permanently_then_logout' => 1, 'save_settings' => true, 'password' => 'foo');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_user_settings() {
        $test = new Handler_Test('save_user_settings', 'core');
        $test->run();
        $test->post = array('save_settings' => true);
        $test->input = array('new_user_settings' => array('foo' => 'bar'));
        $test->run();
	}
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_title() {
        $test = new Handler_Test('title', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_language() {
        $test = new Handler_Test('language', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_date() {
        $test = new Handler_Test('date', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_stay_logged_in() {
        $test = new Handler_Test('stay_logged_in', 'core');
        $test->run();
        $test->post = array('stay_logged_in' => true);
        $test->config = array('allow_long_session' => true);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login() {
        $test = new Handler_Test('login', 'core');
        $test->input = array('create_username' => true);
        $test->run();
        $test->input = array();
        $test->run();
        $test->post = array('username' => 'foo', 'password' => 'bar');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_page_data() {
        $test = new Handler_Test('default_page_data', 'core');
        $test->config = array('single_server_mode' => true);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_user_data() {
        $test = new Handler_Test('load_user_data', 'core');
        $test->user_config = array('start_page_setting' => 'page=message_list&list_path=unread', 'saved_pages' => 'foo');
        $test->run();
        $test->session = array('user_data' => array('foo' => 'bar'));
        $test->run();
        $test->post = array('username' => 'foo', 'password' => 'bar');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_user_data() {
        $test = new Handler_Test('save_user_data', 'core');
        $test->run();
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

        $test->post = array('password' => 'foo', 'save_and_logout' => true);
        $test->run();
        
        $test->config = array('user_settings_dir' => './data');
        $test->session = array('username' => 'foo');
        $test->prep();
        $test->ses_obj->auth_state = false;
        $test->run_only();
        $test->prep();
        $test->ses_obj->auth_state = true;
        $test->run_only();

        $test->post = array('save_and_logout' => true);
        $test->run();
        
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_type() {
        $test = new Handler_Test('message_list_type', 'core');
        $test->get = array('uid' => 1, 'list_parent' => 'unread', 'list_page' => 1, 'list_path' => 'unread');
        $test->input = array('is_mobile' => true);
        $test->run();
        $test->get = array('list_parent' => 'unread', 'list_path' => 'unread');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reload_folder_cookie() {
        $test = new Handler_Test('reload_folder_cookie', 'core');
        $test->input = array('reload_folders' => true);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reset_search() {
        $test = new Handler_Test('reset_search', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_search_terms() {
        $test = new Handler_Test('process_search_terms', 'core');
        $test->get = array('search_terms' => 'foo', 'search_since' => '-1 week', 'search_fld' => 'BODY');
        $test->run();
    }
}
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
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_content_start() {
        $test = new Output_Test('search_content_start', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_content_end() {
        $test = new Output_Test('search_content_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_reminder() {
        $test = new Output_Test('save_reminder', 'core');
        $test->run();
        $test->handler_response = array('changed_settings' => array('foo', 'bar'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_start() {
        $test = new Output_Test('search_form_start', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_form_content() {
        $test = new Output_Test('search_form_content', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_earch_form_end() {
        $test = new Output_Test('search_form_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_earch_results_table_end() {
        $test = new Output_Test('search_results_table_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_js_search_data() {
        $test = new Output_Test('js_search_data', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_end() {
        $test = new Output_Test('login_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login_start() {
        $test = new Output_Test('login_start', 'core');
        $test->run();
        $test->handler_response = array('router_login_state' => true);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_login() {
        $test = new Output_Test('login', 'core');
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => true);
        $test->run();
        $test->handler_response = array('allow_long_session' => true, 'router_login_state' => false);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_server_content_start() {
        $test = new Output_Test('server_content_start', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_server_content_end() {
        $test = new Output_Test('server_content_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_date() {
        $test = new Output_Test('date', 'core');
        $test->run();
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
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_start() {
        $test = new Output_Test('header_start', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_end() {
        $test = new Output_Test('header_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_start() {
        $test = new Output_Test('content_start', 'core');
        $test->run();
        $test->handler_response = array('changed_settings' => array(0), 'router_login_state' => true);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_content() {
        $test = new Output_Test('header_content', 'core');
        $test->run();
        $test->handler_response = array('router_login_state' => true, 'page_title' => 'foo');
        $test->run();
        $test->handler_response = array('router_login_state' => true, 'mailbox_list_title' => array('foo'));
        $test->run();
        $test->handler_response = array('router_page_name' => 'home', 'router_login_state' => true, 'list_path' => 'message_list');
        $test->run();
        $test->handler_response = array('router_login_state' => true, 'router_page_name' => 'notfound');
        $test->run();
        $test->handler_response = array('router_login_state' => true, 'router_page_name' => 'home');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_header_css() {
        $test = new Output_Test('header_css', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js() {
        $test = new Output_Test('page_js', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_end() {
        $test = new Output_Test('content_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_js_data() {
        $test = new Output_Test('js_data', 'core');
        $test->handler_response = array('disable_delete_prompt' => true);
        $test->run();
        $test->handler_response = array();
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_loading_icon() {
        $test = new Output_Test('loading_icon', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_settings_form() {
        $test = new Output_Test('start_settings_form', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_page_setting() {
        $test = new Output_Test('start_page_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('start_page' => 'page=message_list&list_path=unread'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_list_style_setting() {
        $test = new Output_Test('list_style_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('list_style' => 'email_style'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_no_folder_icon_setting() {
        $test = new Output_Test('no_folder_icon_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $test->run();
        $test->handler_response = array('user_settings' => array('no_folder_icons' => true));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_no_password_setting() {
        $test = new Output_Test('no_password_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $test->run();
        $test->handler_response = array('user_settings' => array('no_password_save' => true));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_prompt_setting() {
        $test = new Output_Test('delete_prompt_setting', 'core');
        $test->handler_response = array('user_settings' => array());
        $test->run();
        $test->handler_response = array('user_settings' => array('disable_delete_prompt' => true));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_flagged_settings() {
        $test = new Output_Test('start_flagged_settings', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_everything_settings() {
        $test = new Output_Test('start_everything_settings', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_unread_settings() {
        $test = new Output_Test('start_unread_settings', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_all_email_settings() {
        $test = new Output_Test('start_all_email_settings', 'core');
        $test->handler_response = array('router_module_list' => array());
        $test->run();
        $test->handler_response = array('router_module_list' => array('imap'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_general_settings() {
        $test = new Output_Test('start_general_settings', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unread_source_max_setting() {
        $test = new Output_Test('unread_source_max_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('unread_per_source' => 10));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unread_since_setting() {
        $test = new Output_Test('unread_since_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('unread_since' => '-1 week'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flagged_source_max_setting() {
        $test = new Output_Test('flagged_source_max_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('flagged_per_source' => 10));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flagged_since_setting() {
        $test = new Output_Test('flagged_since_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('flagged_since' => '-1 week'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_email_source_max_setting() {
        $test = new Output_Test('all_email_source_max_setting', 'core');
        $test->handler_response = array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array());
        $test->run();
        $test->handler_response = array('user_settings' => array('all_email_per_source' => 10), 'router_module_list' => array('imap'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_source_max_setting() {
        $test = new Output_Test('all_source_max_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('all_per_source' => 10));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_email_since_setting() {
        $test = new Output_Test('all_email_since_setting', 'core');
        $test->handler_response = array('user_settings' => array('all_email_since' => '-1 week'), 'router_module_list' => array());
        $test->run();
        $test->handler_response = array('user_settings' => array('all_email_since' => '-1 week'), 'router_module_list' => array('imap'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_all_since_setting() {
        $test = new Output_Test('all_since_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('all_since' => '-1 week'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_language_setting() {
        $test = new Output_Test('language_setting', 'core');
        $test->handler_response = array('language'=> 'en');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_timezone_setting() {
        $test = new Output_Test('timezone_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('timezone' => 'America/Chicago'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_msg_list_icons_setting() {
        $test = new Output_Test('msg_list_icons_setting', 'core');
        $test->run();
        $test->handler_response = array('user_settings' => array('show_list_icons' => true));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_end_settings_form() {
        $test = new Output_Test('end_settings_form', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_start() {
        $test = new Output_Test('folder_list_start', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_content_start() {
        $test = new Output_Test('folder_list_content_start', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start() {
        $test = new Output_Test('main_menu_start', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_content() {
        $test = new Output_Test('main_menu_content', 'core');
        $test->handler_response = array('folder_sources' => array(array('email_folders', 'baz')));
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_logout_menu_item() {
        $test = new Output_Test('logout_menu_item', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_end() {
        $test = new Output_Test('main_menu_end', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_email_menu_content() {
        $test = new Output_Test('email_menu_content', 'core');
        $test->handler_response = array('folder_sources' => array(array('email_folders', 'baz')));
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_menu_start() {
        $test = new Output_Test('settings_menu_start', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_form() {
        $test = new Output_Test('save_form', 'core');
        $test->run();
        $test->handler_response = array('changed_settings' => array('foo'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_servers_link() {
        $test = new Output_Test('settings_servers_link', 'core');
        $test->run();
        $test->handler_response = array('single_server_mode' => true);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_site_link() {
        $test = new Output_Test('settings_site_link', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_save_link() {
        $test = new Output_Test('settings_save_link', 'core');
        $test->run();
        $test->handler_response = array('single_server_mode' => true);
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_menu_end() {
        $test = new Output_Test('settings_menu_end', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_content_end() {
        $test = new Output_Test('folder_list_content_end', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_folder_list_end() {
        $test = new Output_Test('folder_list_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_section_start() {
        $test = new Output_Test('content_section_start', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_content_section_end() {
        $test = new Output_Test('content_section_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_start() {
        $test = new Output_Test('message_start', 'core');
        $test->run();
        $test->handler_response = array('list_parent' => 'sent');
        $test->run();
        $test->handler_response = array('list_parent' => 'combined_inbox');
        $test->run();
        $test->handler_response = array('list_parent' => 'email');
        $test->run();
        $test->handler_response = array('list_parent' => 'unread');
        $test->run();
        $test->handler_response = array('list_parent' => 'search', 'list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('Search', 'bar'));
        $test->run();
        $test->handler_response = array('list_parent' => 'search', 'list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('search', 'bar'));
        $test->run();
        $test->handler_response = array('list_page' => 1, 'list_filter' => 'foo', 'list_sort' => 'bar', 'uid' => 5, 'mailbox_list_title' => array('foo', 'bar'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_end() {
        $test = new Output_Test('message_end', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_notfound_content() {
        $test = new Output_Test('notfound_content', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_start() {
        $test = new Output_Test('message_list_start', 'core');
        $test->handler_response = array('message_list_fields' => array('foo', 'bar'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_home_heading() {
        $test = new Output_Test('home_heading', 'core');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_home_password_dialogs() {
        $test = new Output_Test('home_password_dialogs', 'core');
        $test->run();
        $test->handler_response = array('missing_pw_servers' => array(array('server' => 'host', 'user' => 'test', 'type' => 'foo', 'id' => 1, 'name' => 'bar')));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_heading() {
        $test = new Output_Test('message_list_heading', 'core');
        $test->run();
        $test->handler_response = array('custom_list_controls' => 'foo');
        $test->run();
        $test->handler_response = array('list_path' => 'pop3');
        $test->run();
        $test->handler_response = array('no_list_controls' => true);
        $test->run();
        $test->handler_response = array('list_path' => 'combined_inbox');
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_end() {
        $test = new Output_Test('message_list_end', 'core');
        $test->run();
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
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_page_js_debug() {
        $test = new Output_Test('page_js', 'core');
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('foo', 'core'));
        $test->run();
        $test->handler_response = array('encrypt_ajax_requests' => true, 'router_module_list' => array('imap'));
        $test->run();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_main_menu_start_debug() {
        $test = new Output_Test('main_menu_start', 'core');
        $test->run();
        $test->rtype = 'AJAX';
        $test->run();
    }
}
?>
