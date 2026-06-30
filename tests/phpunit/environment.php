<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Environment
 */
class Hm_Test_Environment extends TestCase {
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $environment = Hm_Environment::getInstance();
        $environment->load();
        $cypht_dotenv = $environment->get('CYPHT_DOTENV');
        $this->assertStringEndsWith(".env", $cypht_dotenv);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_default_value() {
        $environment = Hm_Environment::getInstance();
        $environment->load();
        $undifined_env_data = $environment::get('APP_VERSION', "DEFAUL_VALUE");
        $this->assertEquals('DEFAUL_VALUE', $undifined_env_data);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_environment_variables() {
        $environment = Hm_Environment::getInstance();
        $reflection = new ReflectionClass($environment);
        $method = $reflection->getMethod('get_environment_variables');
        $method->setAccessible(true);
        $env_vars = $method->invoke($environment);
        $expected = array_merge($_ENV, $_SERVER);
        $this->assertEquals($expected, $env_vars);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_env_function_returns_value_from_environment() {
        putenv('CYPHT_TEST_VAR=hello_world');
        $this->assertEquals('hello_world', env('CYPHT_TEST_VAR'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_env_function_returns_default_when_variable_not_set() {
        $this->assertEquals('my_default', env('CYPHT_UNDEFINED_XYZ_12345', 'my_default'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_env_function_returns_null_default_when_not_set() {
        $this->assertNull(env('CYPHT_UNDEFINED_XYZ_99999'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_config_array_converts_true_string_to_boolean() {
        $tmp = tempnam(sys_get_temp_dir(), 'cypht_test_') . '.php';
        file_put_contents($tmp, "<?php return array('key1' => 'true', 'key2' => 'false', 'key3' => 'value');");
        $result = process_config_array($tmp);
        unlink($tmp);
        $this->assertTrue($result['key1']);
        $this->assertFalse($result['key2']);
        $this->assertEquals('value', $result['key3']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_config_array_returns_empty_array_for_non_array_return() {
        $tmp = tempnam(sys_get_temp_dir(), 'cypht_test_') . '.php';
        file_put_contents($tmp, "<?php return 'not_an_array';");
        $result = process_config_array($tmp);
        unlink($tmp);
        $this->assertEquals(array(), $result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_config_array_preserves_nested_arrays() {
        $tmp = tempnam(sys_get_temp_dir(), 'cypht_test_') . '.php';
        file_put_contents($tmp, "<?php return array('nested' => array('a' => 1, 'b' => 2));");
        $result = process_config_array($tmp);
        unlink($tmp);
        $this->assertEquals(array('a' => 1, 'b' => 2), $result['nested']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_merge_config_files_merges_all_php_files_in_directory() {
        $dir = sys_get_temp_dir() . '/cypht_test_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/a.php', "<?php return array('key_a' => 'value_a');");
        file_put_contents($dir . '/b.php', "<?php return array('key_b' => 'true');");
        $result = merge_config_files($dir);
        array_map('unlink', glob($dir . '/*.php'));
        rmdir($dir);
        $this->assertEquals('value_a', $result['key_a']);
        $this->assertTrue($result['key_b']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_define_default_constants_defines_all_expected_constants() {
        $mock_config = new Hm_Mock_Config();
        $environment = Hm_Environment::getInstance();
        $environment->define_default_constants($mock_config);
        $this->assertTrue(defined('DEFAULT_SEARCH_SINCE'));
        $this->assertTrue(defined('DEFAULT_UNREAD_PER_SOURCE'));
        $this->assertTrue(defined('DEFAULT_LIST_STYLE'));
        $this->assertTrue(defined('DEFAULT_ENABLE_SIEVE_FILTER'));
        $this->assertTrue(defined('DEFAULT_THEME'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_define_default_constants_sets_numeric_defaults() {
        $mock_config = new Hm_Mock_Config();
        $environment = Hm_Environment::getInstance();
        $environment->define_default_constants($mock_config);
        $this->assertIsInt(DEFAULT_UNREAD_PER_SOURCE);
        $this->assertIsInt(DEFAULT_FLAGGED_PER_SOURCE);
        $this->assertIsInt(DEFAULT_IMAP_PER_PAGE);
    }
}
