<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Functions extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_interface_langs() {
        $this->assertEquals(15, count(interface_langs()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_display_value() {
        $this->assertEquals('', display_value('test', array()));
        $this->assertEquals('foo', display_value('test', array(), false, 'foo'));
        $this->assertEquals(' bar', display_value('from', array('from' => '<blah> bar'), 'from'));
        $this->assertEquals('Just now', display_value('date', array('date' => date("D M d, Y G:i:s"))));
        $date = date("D M d, Y G:i:s");
        $time = time();
        $this->assertEquals($time, display_value('time', array('time' => date("D M d, Y G:i:s"))));
        $this->assertEquals('blah', display_value('foo', array('foo' => 'blah'), 'foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_translate_time_str() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('2 seconds', translate_time_str('2 seconds', $mod));
        $this->assertEquals('blah, blah', translate_time_str('blah, blah', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_data_sources() {
        $data = array(array(
            'callback' => 'imap_combined_unread_content',
            'folder' => '494e424f58',
            'type' => 'imap',
            'name' => 'test',
            'id' => 0
        ));
        $res = 'var hm_data_sources = function() { return [{callback:imap_combined_unread_content,folder:"494e424f58",type:"imap",name:"test",id:"0"}]; };';
        $res2 = 'var hm_data_sources_foo = function() { return [{callback:imap_combined_unread_content,folder:"494e424f58",type:"imap",name:"test",id:"0",group:"foo"}]; };var hm_data_sources = function() { return []; };';
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals($res, format_data_sources($data, $mod));
        $data[0]['group'] = 'foo';
        $this->assertEquals($res2, format_data_sources($data, $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_email_is_active() {
        $this->assertTrue(email_is_active(array('imap')));
        $this->assertFalse(email_is_active(array()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_email_address() {
        $this->assertFalse(is_email_address('foo', false));
        $this->assertTrue(is_email_address('foo', true));
        $this->assertFalse(is_email_address('', true));
        $this->assertTrue(is_email_address('jason@blah.com', false));
        $this->assertFalse(is_email_address('jason@blah', true));
        $this->assertFalse(is_email_address('jason'.chr(128).'blah', true));
        $this->assertFalse(is_email_address('jason@blah@blah.com', false));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_oauth2_data() {
        $mock_config = new Hm_Mock_Config();
        $this->assertEquals(array(), (get_oauth2_data($mock_config)));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_site_setting() {
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        process_site_setting('unread_since', $handler_mod);
        $this->assertEquals(array(), $handler_mod->get('new_user_settings'));
        $handler_mod->request->post['unread_since'] = 1;
        $handler_mod->request->post['save_settings'] = 1;
        process_site_setting('unread_since', $handler_mod, 'callback', false, true);
        $this->assertEquals(array('unread_since_setting' => false), $handler_mod->get('new_user_settings'));
        function callback($val) { return $val; };
        process_site_setting('unread_since', $handler_mod, 'callback', false, true);
        $this->assertEquals(array('unread_since_setting' => 1), $handler_mod->get('new_user_settings'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_since_argument() {
        $this->assertEquals(date('j-M-Y'), process_since_argument('foo'));
        $this->assertTrue(is_string(process_since_argument('-1 week')));
        $this->assertEquals('-1 week', process_since_argument('-1 week', true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_since_setting_callback() {
        $this->assertEquals('-1 week', since_setting_callback('-1 week'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_max_source_setting_callback() {
        $this->assertEquals(5, max_source_setting_callback(5));
        $this->assertEquals(DEFAULT_PER_SOURCE, max_source_setting_callback(50000));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_user_settings() {
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        $handler_mod->session->set('username', 'bar');
        save_user_settings($handler_mod, array('password' => 'foo'), false);
        $msgs = Hm_Msgs::get();
        $this->assertEquals('Settings saved', $msgs[0]);

        save_user_settings($handler_mod, array('password' => 'foo'), true);
        $msgs = Hm_Msgs::get();
        $this->assertEquals('Session destroyed on logout', $msgs[2]);

        $handler_mod->session->auth_state = false;
        save_user_settings($handler_mod, array('password' => 'foo'), false);
        $msgs = Hm_Msgs::get();
        $this->assertEquals('ERRIncorrect password, could not save settings to the server', $msgs[3]);

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_base_ajax_page() {
        setup_base_ajax_page('foo');
        $res = Hm_Handler_Modules::dump();
        $len = count($res['foo']);
        $this->assertEquals(6, $len);
        $this->assertEquals(0, count(Hm_Output_Modules::dump()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_base_page() {
        setup_base_page('foo');
        $res = Hm_Handler_Modules::dump();
        $len =  count($res['foo']);
        $res2 = Hm_Output_Modules::dump();
        $len2 = count($res2['foo']);
        $this->assertEquals(12, $len);
        $this->assertEquals(20, $len2);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_page_opts() {
        $this->assertEquals(6, count(start_page_opts()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_tls_stream_type() {
        $this->assertTrue(is_int(get_tls_stream_type()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_merge_folder_list_details() {
        $this->assertEquals(array(), merge_folder_list_details(false));
        $this->assertEquals(array(), merge_folder_list_details(array()));
        $this->assertEquals(array('foo' => 'barbar'), merge_folder_list_details(array(array('foo', 'bar'), array('foo', 'bar'))));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_ini() {
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['foo'] = array('bar');
        $this->assertEquals(array('bar'), get_ini($mock_config, 'foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_in_server_list() {
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test2', 'server' => 'test2', 'port' => 0, 'tls' => 1), 0);
        $this->assertFalse(in_server_list('Hm_Server_Wrapper', 0, 'foo'));
        $this->assertFalse(in_server_list('Hm_Server_Wrapper', 1, 'foo'));
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test2', 'server' => 'test2', 'port' => 0, 'tls' => 1), 1);
        $this->assertTrue(in_server_list('Hm_Server_Wrapper', 1, 'testuser'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_profiles_by_server_id() {
        $this->assertEquals(array(), profiles_by_smtp_id(array('smtp_id' => 0), 0));
    }
}
class Hm_Test_Core_Functions_Debug extends TestCase {

    public function setUp(): void {
        define('DEBUG_MODE', true);
        require 'bootstrap.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_ini_debug() {
        $mock_config = new Hm_Mock_Config();
        $mock_config->data['app_data_dir'] = './data';
        $mock_config->data['foo.ini'] = 'bar';
        $this->assertEquals(array('foo' => 'bar'), get_ini($mock_config, 'foo.ini'));
        $this->assertEquals(array(), get_ini($mock_config, 'no.ini'));
    }
}
?>
