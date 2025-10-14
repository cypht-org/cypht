<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Session_Functions extends TestCase {

    public $config;
    public function setUp(): void {
        $this->config = new Hm_Mock_Config();
        require APP_PATH.'modules/site/lib.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_session() {
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_PHP_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'DB');
        $this->config->set('auth_type', 'DB');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_DB_Session', get_class(($setup->setup_session())));

        $this->config->mods[] = 'dynamic_login';
        $this->config->set('auth_type', 'dynamic');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_DB_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'asdf');
        $this->config->set('auth_type', 'DB');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_PHP_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'custom');
        $this->config->set('auth_type', 'custom');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Custom_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'REDIS');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_Redis_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'MEM');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_Memcached_Session', get_class(($setup->setup_session())));

        Hm_Functions::$exists = false;
        $this->config->set('session_type', 'PHP');
        $this->config->set('auth_type', 'asdf');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_PHP_Session', get_class(($setup->setup_session())));

    }
    public function tearDown(): void {
        unset($this->config);
    }
}
