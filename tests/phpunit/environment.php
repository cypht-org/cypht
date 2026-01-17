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
}
