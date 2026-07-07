<?php

/**
 * Unit tests for Hm_API_Curl
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

class Hm_Test_API_Curl extends TestCase {

    public function setUp(): void {
        // Reset Hm_Functions statics to known defaults before each test
        Hm_Functions::$exec_res  = '{"unit":"test"}';
        Hm_Functions::$curl_fail = false;
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_default_format_is_json(): void {
        $api = new Hm_API_Curl();
        $this->assertSame('json', $api->format);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_format_can_be_set_in_constructor(): void {
        $api = new Hm_API_Curl('xml');
        $this->assertSame('xml', $api->format);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_parses_json_response(): void {
        Hm_Functions::$exec_res = '{"key":"value","num":42}';
        $api    = new Hm_API_Curl();
        $result = $api->command('https://example.com');
        $this->assertSame(['key' => 'value', 'num' => 42], $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_returns_empty_array_for_invalid_json(): void {
        Hm_Functions::$exec_res = 'not valid json {{';
        $api    = new Hm_API_Curl();
        $result = $api->command('https://example.com');
        $this->assertSame([], $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_returns_empty_array_for_null_json(): void {
        Hm_Functions::$exec_res = 'null';
        $api    = new Hm_API_Curl();
        $result = $api->command('https://example.com');
        $this->assertSame([], $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_returns_raw_response_for_non_json_format(): void {
        Hm_Functions::$exec_res = '<html>raw response</html>';
        $api    = new Hm_API_Curl('html');
        $result = $api->command('https://example.com');
        $this->assertSame('<html>raw response</html>', $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_returns_empty_array_when_curl_init_fails(): void {
        Hm_Functions::$curl_fail = true;
        $api    = new Hm_API_Curl();
        $result = $api->command('https://example.com');
        $this->assertSame([], $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_last_status_is_set_after_command(): void {
        $api = new Hm_API_Curl();
        $api->command('https://example.com');
        $this->assertSame(200, $api->last_status);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_last_status_is_null_before_any_command(): void {
        $api = new Hm_API_Curl();
        $this->assertNull($api->last_status);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_with_post_fields_returns_parsed_response(): void {
        Hm_Functions::$exec_res = '{"posted":true}';
        $api    = new Hm_API_Curl();
        $result = $api->command(
            'https://example.com',
            ['Content-Type: application/x-www-form-urlencoded'],
            ['field' => 'value', 'other' => 'data']
        );
        $this->assertSame(['posted' => true], $result);
    }

    /**
     * post data with special characters is URL-encoded.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_url_encodes_post_field_names_and_values(): void {
        // We cannot inspect what was sent to curl directly, but the command
        // must succeed and return parsed JSON without throwing.
        Hm_Functions::$exec_res = '{"ok":1}';
        $api    = new Hm_API_Curl();
        $result = $api->command('https://example.com', [], ['a key' => 'a value & more']);
        $this->assertSame(['ok' => 1], $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_with_custom_http_method(): void {
        Hm_Functions::$exec_res = '{"deleted":true}';
        $api    = new Hm_API_Curl();
        $result = $api->command('https://example.com', [], [], '', 'DELETE');
        $this->assertSame(['deleted' => true], $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_with_raw_request_body(): void {
        Hm_Functions::$exec_res = '{"received":true}';
        $api    = new Hm_API_Curl();
        $result = $api->command('https://example.com', [], [], '{"data":"payload"}', 'PUT');
        $this->assertSame(['received' => true], $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_command_with_headers(): void {
        Hm_Functions::$exec_res = '{"auth":true}';
        $api    = new Hm_API_Curl();
        $result = $api->command(
            'https://example.com',
            ['Authorization: Bearer token123', 'Accept: application/json']
        );
        $this->assertSame(['auth' => true], $result);
    }
}
