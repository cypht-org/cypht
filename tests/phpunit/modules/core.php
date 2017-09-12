<?php


class Hm_Test_Core_Functions extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_interface_langs() {
        $this->assertEquals(11, count(interface_langs()));
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
        /* TODO: add assertions */
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        $handler_mod->session->set('username', 'bar');
        save_user_settings($handler_mod, array('password' => 'foo'), false);
        save_user_settings($handler_mod, array('password' => 'foo'), true);
        $handler_mod->session->auth_state = false;
        save_user_settings($handler_mod, array('password' => 'foo'), false);

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_base_ajax_page() {
        /* TODO: add assertions */
        setup_base_ajax_page('foo');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_base_page() {
        /* TODO: add assertions */
        setup_base_page('foo');
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
    public function test_restore_servers() {
        /* TODO: add assertions */
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        restore_servers(array(array(array('server' => 'foo'))), $handler_mod);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_filter_servers() {
        /* TODO: add assertions and coverage*/
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        filter_servers($handler_mod);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_merge_folder_list_details() {
        /* TODO: add assertions and coverage*/
        merge_folder_list_details(array());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_ini() {
        /* TODO: add assertions and coverage*/
        $mock_config = new Hm_Mock_Config();
        get_ini($mock_config, 'foo');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_in_server_list() {
        /* TODO: add assertions and coverage*/
        in_server_list('Hm_Server_Wrapper', 0, 'foo');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_profiles_by_server_id() {
        /* TODO: add assertions and coverage*/
        profiles_by_smtp_id(array(), 0);
    }
}

?>
