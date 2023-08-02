<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Message_List_Functions extends TestCase {

    public function setUp(): void {
        date_default_timezone_set('UTC');
        require 'bootstrap.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_list_settings() {
        $parent = build_parent_mock();
        $handler_mod = new Hm_Handler_Test($parent, 'home');
        $this->assertEquals(array('', array(), '-1 week', 20),  get_message_list_settings('', $handler_mod));
        $this->assertEquals(array('unread', array('Unread'), '-1 week', 20), get_message_list_settings('unread', $handler_mod));
        $this->assertEquals(array('email', array('All Email'), '-1 week', 20), get_message_list_settings('email', $handler_mod));
        $this->assertEquals(array('flagged', array('Flagged'), '-1 week', 20), get_message_list_settings('flagged', $handler_mod));
        $this->assertEquals(array('combined_inbox', array('Everything'), '-1 week', 20), get_message_list_settings('combined_inbox', $handler_mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_list_meta() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('', message_list_meta(array(), $mod));
        $this->assertEquals('<div class="list_meta">last 7 days<b>-</b><span class="src_count"></span> sources@20 each<b>-</b><span class="total"></span> total</div>', message_list_meta(array('list_meta' => 'foo', 'message_list_since' => '-1 week'), $mod));
        $this->assertEquals('<div class="list_meta">last 7 days<b>-</b><span class="src_count"></span> sources@5 each<b>-</b><span class="total"></span> total</div>', message_list_meta(array('list_meta' => 'foo', 'per_source_limit' => 5), $mod));
        $this->assertEquals('<div class="list_meta">last 7 days<b>-</b><span class="src_count"></span> sources@5 each<b>-</b><span class="total"></span> total</div>', message_list_meta(array('list_meta' => 'foo', 'per_source_limit' => 5, 'message_list_since' => '-1 week'), $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_human_readable_interval() {
        $date = date('D M d, Y G:i:s', strtotime('yesterday'));
        $this->assertEquals(1, preg_match("/\d+ day, \d+ (minute|minutes|hour|hours|day|days|week|weeks|month|months)/", human_readable_interval($date)));
        $date = date("D M d, Y G:i:s");
        $this->assertEquals('Just now', human_readable_interval($date));
        $date = date("D M d, 3000 G:i:s");
        $this->assertEquals('From the future!', human_readable_interval($date));
        $this->assertEquals('Unknown', human_readable_interval(''));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_row() {
        function callback($v) { return ''; }
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals(array('<tr class="foo class"></tr>', 'foo'), message_list_row(array(array('callback', 'foo')), 'foo', 'email', $mod, 'class'));
        $this->assertEquals(array('<tr class="foo class"><td class="news_cell checkbox_cell"></td></tr>', 'foo'), message_list_row(array(), 'foo', 'news', $mod, 'class'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_safe_output_callback() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<td class="foo" data-title="">bar</td>', safe_output_callback(array('foo', 'bar'), 'email', $mod));
        $this->assertEquals('<div class="foo" data-title="bar"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5%201l-3%206h1l3-6h-1zm-4%201l-1%202%201%202h1l-1-2%201-2h-1zm5%200l1%202-1%202h1l1-2-1-2h-1z%22%20%2F%3E%0A%3C%2Fsvg%3E" />bar</div>', safe_output_callback(array('foo', 'bar', 'code'), 'news', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_checkbox_callback() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<td class="checkbox_cell"><input id="foo" type="checkbox" value="foo" /><label class="checkbox_label" for="foo"></label></td>', checkbox_callback(array('foo'), 'email', $mod));
        $this->assertEquals('<input type="checkbox" id="foo" value="foo" /><label class="checkbox_label" for="foo"></label></td><td class="news_cell">', checkbox_callback(array('foo'), 'news', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_subject_callback() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<td class="subject"><div class=""><a title="foo" href="bar">foo</a></div></td>', subject_callback(array('foo', 'bar', array()), 'email', $mod));
        $this->assertEquals('<div class="subject"><div class="" title="foo"><img alt="list item" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M5%201l-3%206h1l3-6h-1zm-4%201l-1%202%201%202h1l-1-2%201-2h-1zm5%200l1%202-1%202h1l1-2-1-2h-1z%22%20%2F%3E%0A%3C%2Fsvg%3E" /> <a href="bar">foo</a></div></div>', subject_callback(array('foo', 'bar', array(), 'code'), 'news', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_date_callback() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<td class="msg_date" title="Thu, 01 Jan 1970 00:00:01 +0000">foo<input type="hidden" class="msg_timestamp" value="1" /></td>', date_callback(array('foo', 1), 'email', $mod));
        $this->assertEquals('<div class="msg_date">foo<input type="hidden" class="msg_timestamp" value="1" /></div>', date_callback(array('foo', 1), 'news', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_icon_callback() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<td class="icon" title="Flagged, Answered, Attachment"> F A +</td>', icon_callback(array(array('flagged', 'answered', 'attachment')), 'email', $mod));
        $this->assertEquals('<div class="icon" title="Flagged, Answered, Attachment"> F A +</div>', icon_callback(array(array('flagged', 'answered', 'attachment')), 'news', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_controls() {
        $mod = new Hm_Output_Test(array('msg_controls_extra' => 'foo', 'foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<a class="toggle_link" href="#"><img alt="x" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6.406%201l-.719.688-2.781%202.781-.781-.781-.719-.688-1.406%201.406.688.719%201.5%201.5.719.688.719-.688%203.5-3.5.688-.719-1.406-1.406z%22%20%2F%3E%0A%3C%2Fsvg%3E" width="8" height="8" /></a><div class="msg_controls"><a class="msg_read core_msg_control" href="#" data-action="read">Read</a><a class="msg_unread core_msg_control" href="#" data-action="unread">Unread</a><a class="msg_flag core_msg_control" href="#" data-action="flag">Flag</a><a class="msg_unflag core_msg_control" href="#" data-action="unflag">Unflag</a><a class="msg_delete core_msg_control" href="#" data-action="delete">Delete</a><a class="msg_archive core_msg_control" href="#" data-action="archive">Archive</a>foo</div>', message_controls($mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_since_dropdown() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<select name="foo" id="foo" class="message_list_since"><option value="today">Today</option><option selected="selected" value="-1 week">Last 7 days</option><option value="-2 weeks">Last 2 weeks</option><option value="-4 weeks">Last 4 weeks</option><option value="-6 weeks">Last 6 weeks</option><option value="-6 months">Last 6 months</option><option value="-1 year">Last year</option><option value="-5 years">Last 5 years</option></select>', message_since_dropdown('-1 week', 'foo', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_list_sources() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<div class="list_sources"><div class="src_title">Sources</div></div>', list_sources(array(array('group' => 'background', 'type' => 'imap', 'folder' => 'foo')), $mod));
        $this->assertEquals('<div class="list_sources"><div class="src_title">Sources</div><div class="list_src">imap blah foo</div></div>', list_sources(array(array('name' => 'blah', 'type' => 'imap', 'folder' => bin2hex('foo'))), $mod));
        $this->assertEquals('<div class="list_sources"><div class="src_title">Sources</div><div class="list_src">imap blah INBOX</div></div>', list_sources(array(array('name' => 'blah', 'type' => 'imap')), $mod));
        $this->assertEquals('<div class="list_sources"><div class="src_title">Sources</div><div class="list_src">pop3 blah </div></div>', list_sources(array(array('name' => 'blah', 'type' => 'pop3')), $mod));
        $this->assertEquals('<div class="list_sources"><div class="src_title">Sources</div></div>', list_sources(array(array('nodisplay' => true, 'name' => 'blah', 'type' => 'pop3')), $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_list_controls() {
        $this->assertEquals('<div class="list_controls no_mobile">foobazbar</div>
    <div class="list_controls on_mobile">
        <img alt="" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2030%2030%22%20width%3D%2230px%22%20height%3D%2230px%22%3E%20%20%20%20%3Cpath%20d%3D%22M%207%204%20C%206.744125%204%206.4879687%204.0974687%206.2929688%204.2929688%20L%204.2929688%206.2929688%20C%203.9019687%206.6839688%203.9019687%207.3170313%204.2929688%207.7070312%20L%2011.585938%2015%20L%204.2929688%2022.292969%20C%203.9019687%2022.683969%203.9019687%2023.317031%204.2929688%2023.707031%20L%206.2929688%2025.707031%20C%206.6839688%2026.098031%207.3170313%2026.098031%207.7070312%2025.707031%20L%2015%2018.414062%20L%2022.292969%2025.707031%20C%2022.682969%2026.098031%2023.317031%2026.098031%2023.707031%2025.707031%20L%2025.707031%2023.707031%20C%2026.098031%2023.316031%2026.098031%2022.682969%2025.707031%2022.292969%20L%2018.414062%2015%20L%2025.707031%207.7070312%20C%2026.098031%207.3170312%2026.098031%206.6829688%2025.707031%206.2929688%20L%2023.707031%204.2929688%20C%2023.316031%203.9019687%2022.682969%203.9019687%2022.292969%204.2929688%20L%2015%2011.585938%20L%207.7070312%204.2929688%20C%207.5115312%204.0974687%207.255875%204%207%204%20z%22%2F%3E%3C%2Fsvg%3E" width="20" height="20" onclick="listControlsMenu()"/>
        <div id="list_controls_menu" classs="list_controls_menu">foobazbar</div>
    </div>', list_controls('foo', 'bar', 'baz'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_validate_search_terms() {
        $this->assertEquals('foo', validate_search_terms('foo'));
        $this->assertEquals('',  validate_search_terms('<br />'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_validate_search_field() {
        $this->assertEquals('', validate_search_fld('foo'));
        $this->assertEquals('BODY', validate_search_fld('BODY'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search_field_selection() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals('<select id="search_fld" name="search_fld"><option selected="selected" value="TEXT">Entire message</option><option value="BODY">Message body</option><option value="SUBJECT">Subject</option><option value="FROM">From</option><option value="TO">To</option><option value="CC">Cc</option></select>', search_field_selection('TEXT', $mod));
        $this->assertEquals('<select id="search_fld" name="search_fld"><option value="TEXT">Entire message</option><option value="BODY">Message body</option><option value="SUBJECT">Subject</option><option value="FROM">From</option><option value="TO">To</option><option value="CC">Cc</option></select>', search_field_selection('foo', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_build_page_links() {
        $this->assertEquals('<a href="?page=message_list&amp;list_path=%2F&amp;list_page=4"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&larr;" /></a>  <a href="?page=message_list&amp;list_path=%2F&amp;list_page=1">1</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=2">2</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=3">3</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=4">4</a> <a class="current_page" href="?page=message_list&amp;list_path=%2F&amp;list_page=5">5</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=6">6</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=7">7</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=8">8</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=9">9</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=10">10</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=11">11</a> ... <a href="?page=message_list&amp;list_path=%2F&amp;list_page=100">100</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=6"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&rarr;" /></a>', build_page_links(10, 5, 1000, '/'));
        $this->assertEquals('<a href="?page=message_list&amp;list_path=%2F&amp;list_page=9"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&larr;" /></a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=1">1</a> ...  <a href="?page=message_list&amp;list_path=%2F&amp;list_page=4">4</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=5">5</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=6">6</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=7">7</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=8">8</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=9">9</a> <a class="current_page" href="?page=message_list&amp;list_path=%2F&amp;list_page=10">10</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=11">11</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=12">12</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=13">13</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=14">14</a> ... <a href="?page=message_list&amp;list_path=%2F&amp;list_page=100">100</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=11"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&rarr;" /></a>', build_page_links(10, 10, 1000, '/'));
        $this->assertEquals('', build_page_links(10, 1, 10, '/'));
        $this->assertEquals('<a class="disabled_link"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M6%200l-4%204%204%204v-8z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&larr;" /></a>  <a class="current_page" href="?page=message_list&amp;list_path=%2F&amp;list_page=1&amp;filter=1&amp;sort=1">1</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=2&amp;filter=1&amp;sort=1">2</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=3&amp;filter=1&amp;sort=1">3</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=4&amp;filter=1&amp;sort=1">4</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=5&amp;filter=1&amp;sort=1">5</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=6&amp;filter=1&amp;sort=1">6</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=7&amp;filter=1&amp;sort=1">7</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=8&amp;filter=1&amp;sort=1">8</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=9&amp;filter=1&amp;sort=1">9</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=10&amp;filter=1&amp;sort=1">10</a> <a href="?page=message_list&amp;list_path=%2F&amp;list_page=2&amp;filter=1&amp;sort=1"><img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M2%200v8l4-4-4-4z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="&rarr;" /></a>', build_page_links(10, 1, 100, '/', true, true));
    }
}
?>
