<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Core_Message_Functions extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        require APP_PATH.'modules/core/modules.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_msg_html() {
        $test = '<script></script><body>foo</body>';
        $this->assertEquals('foo', format_msg_html($test));
        $test = '<a href="http://blah.com">';
        $this->assertEquals('<a href="http://blah.com"></a>', format_msg_html($test));
        $test ='foo<body>bar</body>';
        $this->assertEquals('foobar', format_msg_html($test));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_convert_html_to_text() {
        $test = '<script></script><body>foo</body>';
        $this->assertEquals('foo', convert_html_to_text($test));
        $test = '<a href="http://blah.com">';
        $this->assertEquals('', convert_html_to_text($test));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_msg_image() {
        $this->assertEquals("<img class=\"msg_img\" alt=\"\" src=\"data:image/png;base64,Zm9v\r\n\" />", format_msg_image('foo', 'png'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_msg_text() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals("foo<br />\nbar<br />", format_msg_text("foo\nbar", $mod));
        $this->assertEquals('<a href="http://foo.com">http://foo.com</a><br />', format_msg_text('http://foo.com', $mod));
        $this->assertEquals('<a href="http://foo.com">http://foo.com</a>]<br />', format_msg_text('http://foo.com]', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_reply_text() {
        $this->assertEquals("> line one\n> line two\n> > line three", format_reply_text("line one\nline two\n> line three"));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reply_to_address() {
        $headers = array(
            'To' => '"me" <some@body.com>',
            'From' => 'foo@bar.com',
            'Cc' => 'foo@bar.com, baz@bar.com, not@me.com'
        );
        $this->assertEquals(array('', ''), (reply_to_address(array('reply-to' => 'foo'), 'reply')));
        $this->assertEquals(array('foo@bar.com', ''), reply_to_address($headers, 'reply'));
        $this->assertEquals('', reply_to_address($headers, 'forward'));
        $this->assertEquals(array('foo@bar.com', 'baz@bar.com, not@me.com, me some@body.com'), reply_to_address($headers, 'reply_all'));
        unset($headers['Cc']);
        $this->assertEquals(array('foo@bar.com', 'me some@body.com'), reply_to_address($headers, 'reply_all'));
        $this->assertEquals(array('not@me.com', ''), reply_to_address(array('From' => 'not@me.com'), 'reply_all'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reply_to_subject() {
        $this->assertEquals('', reply_to_subject(array(), 'reply'));
        $this->assertEquals('Re: foo', reply_to_subject(array('Subject' => 'foo'), 'reply'));
        $this->assertEquals('Re: foo', reply_to_subject(array('Subject' => 'Re: foo'), 'reply'));
        $this->assertEquals('Fwd: Re: foo', reply_to_subject(array('Subject' => 'Re: foo'), 'forward'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reply_lead_in() {
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals("On foo you said\n\n", reply_lead_in(array('Date' => 'foo', 'From' => 'me', 'Subject' => 'this'), 'reply', 'you', $mod));
        $this->assertEquals("\n\n----- begin forwarded message -----\n\nFrom: me\nDate: foo\nSubject: this\n\n", reply_lead_in(array('Date' => 'foo', 'From' => 'me', 'Subject' => 'this'), 'forward', 'you', $mod));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_reply_format_body() {
        $this->assertEquals("lead in> this is a message", reply_format_body(array(), 'this is a message', 'lead in', 'reply', array('type' => 'text', 'subtype' => 'plain'), false));
        $this->assertEquals("lead in> this is a message", reply_format_body(array(), 'this is a message', 'lead in', 'reply', array('type' => 'text', 'subtype' => 'plain'), true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_reply_as_html() {
        $this->assertEquals('this is a message', format_reply_as_html('this is a message', 'textplain', 'forward', ''));
        $this->assertEquals('> this is a message', format_reply_as_html('this is a message', 'textplain', 'reply', ''));
        $this->assertEquals('<hr /><blockquote>this is a message</blockquote>', format_reply_as_html('this is a message', 'texthtml', 'reply', ''));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_reply_as_text() {
        $this->assertEquals('this is a message', format_reply_as_text('this is a message', 'textplain', 'forward', ''));
        $this->assertEquals('> this is a message', format_reply_as_text('this is a message', 'textplain', 'reply', ''));
        $this->assertEquals('> this is a message', format_reply_as_text('this is a message', 'texthtml', 'reply', ''));
        $this->assertEquals('this is a message', format_reply_as_text('this is a message', 'texthtml', 'forward', ''));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_reply_to_id() {
        $this->assertEquals('foo', reply_to_id(array('message-id' => 'foo'), 'reply'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_reply_fields() {
        $headers = array(
            'To' => '"me" <some@body.com>',
            'From' => 'foo@bar.com',
            'Cc' => 'baz@bar.com, not@me.com'
        );
        $mod = new Hm_Output_Test(array('foo' => 'bar', 'bar' => 'foo'), array('bar'));
        $this->assertEquals(array('foo@bar.com', '', '', '> this is a message', ''), format_reply_fields('this is a message', $headers, array('type' => 'text', 'subtype' => 'plain'), false, $mod, 'reply', array()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_decode_fld() {
        $this->assertEquals('pöstal', decode_fld('=?iso-8859-1?q?p=F6stal?='));
        $this->assertEquals('foo', decode_fld('=?iso-8859-1?B?'.base64_encode('foo').'?='));
        $this->assertEquals('foo', decode_fld('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_address_fld() {
        $res = array(
            array (
                'email' => 'blah@tests.com',
                'comment' => '(comment here)',
                'label' => 'stuff" foo',
            ),
            array(
                'email' => 'foo@blah.com',
                'comment' => '',
                'label' => 'bad address',
            ),
            array(
                'email' => 'brack@ets.org',
                'comment' => '',
                'label' => 'good address',
            ),
            array(
                'email' => 'actual@foo.com',
                'comment' => '',
                'label' => ''
            )
        );
        $this->assertEquals($res, process_address_fld('"stuff" foo blah@tests.com (comment here), bad address <"foo@blah.com">, good address <brack@ets.org>, \'not@addy.com\' actual@foo.com'));
    }

}
?>
