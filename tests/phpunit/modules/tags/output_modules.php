<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for modules/tags/output_modules.php
 */
class Hm_Test_Tags_Output_Modules extends TestCase {

    public function setUp(): void {
        require __DIR__.'/../../helpers.php';
        require_once APP_PATH.'modules/tags/hm-tags.php';
        require_once APP_PATH.'modules/tags/output_modules.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tags_menu_with_no_tags_only_shows_add_label_link() {
        $test = new Output_Test('tags', 'tags');
        $res = $test->run();
        $sources = $res->output_response['folder_sources'];
        $this->assertCount(1, $sources);
        $this->assertEquals('tags_folders', $sources[0][0]);
        $this->assertStringContainsString('tag_add_new_btn', $sources[0][1]);
        $this->assertStringNotContainsString('tag_row', $sources[0][1]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tags_menu_renders_nested_tags_with_color_dot() {
        $test = new Output_Test('tags', 'tags');
        $test->handler_response = array('tags' => array(
            'parent1' => array('id' => 'parent1', 'name' => 'Work', 'parent' => null, 'color' => '#1a73e8'),
            'child1' => array('id' => 'child1', 'name' => 'Invoices', 'parent' => 'parent1', 'color' => '#e8710a'),
        ));
        $res = $test->run();
        $html = $res->output_response['folder_sources'][0][1];

        $this->assertStringContainsString('data-tag-id="parent1"', $html);
        $this->assertStringContainsString('has_children', $html);
        $this->assertStringContainsString('color: #1a73e8;', $html);
        $this->assertStringContainsString('data-tag-parent="parent1"', $html);
        $this->assertStringContainsString('color: #e8710a;', $html);
        $this->assertStringContainsString('tags_json_data', $html);
        $this->assertStringContainsString('tags_palette_data', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tags_menu_sanitizes_invalid_stored_color() {
        $test = new Output_Test('tags', 'tags');
        $test->handler_response = array('tags' => array(
            'x1' => array('id' => 'x1', 'name' => 'Bad', 'parent' => null, 'color' => 'javascript:alert(1)'),
        ));
        $res = $test->run();
        $html = $res->output_response['folder_sources'][0][1];

        $this->assertStringContainsString('color: '.Hm_Tags::defaultColor().';', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tag_bar_appends_icon_to_string_headers() {
        $test = new Output_Test('tag_bar', 'tags');
        $test->handler_response = array('msg_headers' => '<div>headers</div>');
        $res = $test->run();
        $this->assertStringContainsString('tag_icon', $res->output_response['msg_headers']);
        $this->assertStringStartsWith('<div>headers</div>', $res->output_response['msg_headers']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tag_bar_leaves_non_string_headers_untouched() {
        $test = new Output_Test('tag_bar', 'tags');
        $test->handler_response = array('msg_headers' => array('not', 'a', 'string'));
        $res = $test->run();
        $this->assertEquals(array('not', 'a', 'string'), $res->output_response['msg_headers']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_filter_tag_data_defaults_to_empty_list() {
        $test = new Output_Test('filter_tag_data', 'tags');
        $res = $test->run();
        $this->assertEquals(array(), $res->output_response['formatted_message_list']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tag_per_source_setting_shows_default_without_reset_icon() {
        $test = new Output_Test('tag_per_source_setting', 'tags');
        $res = $test->run();
        $this->assertStringContainsString('value="'.DEFAULT_TAGS_PER_SOURCE.'"', $res->output_response[0]);
        $this->assertStringNotContainsString('reset_default_value_input', $res->output_response[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tag_per_source_setting_shows_reset_icon_when_customized() {
        $test = new Output_Test('tag_per_source_setting', 'tags');
        $test->handler_response = array('user_settings' => array('tag_per_source' => 5));
        $res = $test->run();
        $this->assertStringContainsString('value="5"', $res->output_response[0]);
        $this->assertStringContainsString('reset_default_value_input', $res->output_response[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tag_since_setting_defaults_to_configured_default() {
        $test = new Output_Test('tag_since_setting', 'tags');
        $res = $test->run();
        $this->assertStringContainsString('tag_since', $res->output_response[0]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_tag_settings_renders_section_heading_without_side_effects() {
        $test = new Output_Test('start_tag_settings', 'tags');
        $res = $test->run();
        $this->assertStringContainsString('Tags', $res->output_response[0]);
        $this->assertStringContainsString('settings_subtitle', $res->output_response[0]);
    }
}
