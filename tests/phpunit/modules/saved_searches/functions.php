<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for saved_searches module functions
 * Covers both simple and advanced search functionality
 */
class Hm_Test_Saved_Searches_Functions extends TestCase {

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_search_from_post() {
        $mock_request = new stdClass();
        $mock_request->post = array(
            'search_terms' => 'test search',
            'search_since' => 'SINCE 1-Jan-2023',
            'search_fld' => 'TEXT',
            'search_name' => 'My Test Search'
        );

        $result = get_search_from_post($mock_request);

        $this->assertEquals('test search', $result[0]);
        $this->assertEquals('SINCE 1-Jan-2023', $result[1]);
        $this->assertEquals('TEXT', $result[2]);
        $this->assertEquals('My Test Search', $result[3]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_search_from_post_with_defaults() {
        $mock_request = new stdClass();
        $mock_request->post = array();

        $result = get_search_from_post($mock_request);

        $this->assertEquals('', $result[0]);
        $this->assertEquals(DEFAULT_SEARCH_SINCE, $result[1]);
        $this->assertEquals(DEFAULT_SEARCH_FLD, $result[2]);
        $this->assertEquals('', $result[3]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_search_from_url() {
        $mock_request = new stdClass();
        $mock_request->get = array(
            'search_terms' => 'url search',
            'search_since' => 'SINCE 1-Jun-2023',
            'search_fld' => 'SUBJECT',
            'search_name' => 'URL Search'
        );

        $result = get_search_from_url($mock_request);

        $this->assertEquals('url search', $result[0]);
        $this->assertEquals('SINCE 1-Jun-2023', $result[1]);
        $this->assertEquals('SUBJECT', $result[2]);
        $this->assertEquals('URL Search', $result[3]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_advanced_search_from_post_with_valid_json() {
        $search_data = array(
            'terms' => array(
                array('term' => 'hello', 'condition' => 'and'),
                array('term' => 'world', 'condition' => 'or')
            ),
            'targets' => array(
                array('target' => 'SUBJECT', 'orig' => 'TEXT', 'condition' => 'and')
            ),
            'sources' => array(
                array('source' => 'INBOX', 'label' => 'Inbox')
            ),
            'times' => array(
                array('from' => '2023-01-01', 'to' => '2023-12-31')
            ),
            'other' => array(
                'charset' => 'UTF-8',
                'limit' => 100,
                'flags' => array('SEEN')
            )
        );

        $mock_request = new stdClass();
        $mock_request->post = array(
            'adv_search_data' => json_encode($search_data)
        );

        $result = get_advanced_search_from_post($mock_request);

        $this->assertEquals($search_data, $result);
        $this->assertIsArray($result['terms']);
        $this->assertEquals('hello', $result['terms'][0]['term']);
        $this->assertEquals('or', $result['terms'][1]['condition']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_advanced_search_from_post_with_invalid_json() {
        $mock_request = new stdClass();
        $mock_request->post = array(
            'adv_search_data' => 'invalid json'
        );

        $result = get_advanced_search_from_post($mock_request);

        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_advanced_search_from_post_missing_data() {
        $mock_request = new stdClass();
        $mock_request->post = array();

        $result = get_advanced_search_from_post($mock_request);

        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_search_label_field() {
        $mock_output = new class {
            public function trans($str) { return $str; }
            public function html_safe($str) { return htmlspecialchars($str, ENT_QUOTES); }
        };

        $result = update_search_label_field('Test Search', $mock_output);

        $this->assertStringContainsString('update_search_label_field', $result);
        $this->assertStringContainsString('Test Search', $result);
        $this->assertStringContainsString('search_terms_label', $result);
    }
}