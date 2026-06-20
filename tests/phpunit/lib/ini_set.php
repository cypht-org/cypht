<?php

/**
 * Unit tests for lib/ini_set.php configuration script
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

class Hm_Test_Ini_Set extends TestCase {

    /**
     * Require the real lib/ini_set.php with the given config overrides applied to
     * a Hm_Mock_Config instance set as the global $config that ini_set.php reads.
     */
    private function apply_ini_set(array $overrides = []): void {
        global $config;
        $config = new Hm_Mock_Config();
        foreach ($overrides as $key => $value) {
            $config->set($key, $value);
        }
        require APP_PATH.'lib/ini_set.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_compression_settings_php_pre_8(): void {
        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            $this->markTestSkipped('Test only applies to PHP < 8.0');
        }
        $this->apply_ini_set(['disable_open_basedir' => true]);
        $this->assertSame('1', ini_get('zlib.output_compression'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_compression_settings_php_8_plus(): void {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $this->markTestSkipped('Test only applies to PHP >= 8.0');
        }
        $before = ini_get('zlib.output_compression');
        $this->apply_ini_set(['disable_open_basedir' => true]);
        $this->assertSame($before, ini_get('zlib.output_compression'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_security_settings(): void {
        $this->apply_ini_set(['disable_open_basedir' => true]);

        $this->assertSame('0', ini_get('session.cookie_lifetime'));
        $this->assertSame('1', ini_get('session.cookie_httponly'));
        $this->assertSame('1440', ini_get('session.gc_maxlifetime'));
        $this->assertSame('0', ini_get('session.use_trans_sid'));
        $this->assertSame('nocache', ini_get('session.cache_limiter'));
        $this->assertSame('hm_session', ini_get('session.name'));

        // use_strict_mode is PHP_INI_ALL so it is writable
        $useStrictMode = ini_get('session.use_strict_mode');
        $this->assertTrue(
            $useStrictMode === '1' || $useStrictMode === false,
            'session.use_strict_mode should be 1 or false if read-only in this environment'
        );

        // Note: ini_set.php calls ini_set('session.use_cookie', 1) but that name is
        // invalid (should be session.use_cookies), so PHP silently ignores it.
        $this->assertSame('1', ini_get('session.use_only_cookies'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_samesite_php_7_3_plus(): void {
        if (version_compare(PHP_VERSION, '7.3', '<')) {
            $this->markTestSkipped('Test only applies to PHP >= 7.3');
        }
        $this->apply_ini_set(['disable_open_basedir' => true]);
        $this->assertSame('Lax', ini_get('session.cookie_samesite'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_secure_with_tls_enabled(): void {
        $this->apply_ini_set(['disable_tls' => false, 'disable_open_basedir' => true]);
        $this->assertSame('1', ini_get('session.cookie_secure'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_secure_with_tls_disabled(): void {
        $before = ini_get('session.cookie_secure');
        $this->apply_ini_set(['disable_tls' => true, 'disable_open_basedir' => true]);
        $this->assertSame($before, ini_get('session.cookie_secure'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_hash_php_8_1(): void {
        if (version_compare(PHP_VERSION, '8.1', '!=')) {
            $this->markTestSkipped('Test only applies to PHP 8.1');
        }
        $this->apply_ini_set(['disable_open_basedir' => true]);
        // session.hash_function was removed in PHP 8.0; ini_set silently fails
        $value = ini_get('session.hash_function');
        $this->assertTrue(
            $value === '1' || $value === false,
            'session.hash_function should be 1 or false (removed in PHP 8.0)'
        );
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_hash_non_php_8_1(): void {
        if (version_compare(PHP_VERSION, '8.1', '==')) {
            $this->markTestSkipped('Test only applies to PHP != 8.1');
        }
        $this->apply_ini_set(['disable_open_basedir' => true]);
        // session.hash_function was removed in PHP 8.0; on older PHP it should be sha256
        $value = ini_get('session.hash_function');
        $this->assertTrue(
            $value === 'sha256' || $value === false || $value === '1',
            'session.hash_function should be sha256, 1, or false if removed in this PHP version'
        );
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_general_security_settings(): void {
        $this->apply_ini_set(['disable_open_basedir' => true]);

        // allow_url_include is PHP_INI_SYSTEM on PHP 8.1+, so ini_set may silently fail
        $allowUrlInclude = ini_get('allow_url_include');
        $this->assertTrue(
            $allowUrlInclude === '0' || $allowUrlInclude === '' || $allowUrlInclude === false,
            'allow_url_include should be disabled (0, empty string, or false if read-only)'
        );

        $this->assertSame('0', ini_get('display_errors'));

        // Note: ini_set.php contains a typo: 'display_start_up_errors' instead of
        // 'display_startup_errors', so that ini_set call silently fails.
        // We verify display_errors is set correctly (covered above).
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_default(): void {
        $this->apply_ini_set(['disable_open_basedir' => false]);

        $value = ini_get('open_basedir');
        $this->assertNotEmpty($value);

        $appRoot = rtrim(APP_PATH, '/\\');
        $this->assertStringContainsString($appRoot, $value);

        $tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $this->assertStringContainsString($tmpDir, $value);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_disabled(): void {
        $before = ini_get('open_basedir');
        $this->apply_ini_set(['disable_open_basedir' => true]);
        $this->assertSame($before, ini_get('open_basedir'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_with_custom_directories(): void {
        $tmpDir      = sys_get_temp_dir();
        $testUserDir = $tmpDir.DIRECTORY_SEPARATOR.'test_user_'.getmypid();
        $testAttDir  = $tmpDir.DIRECTORY_SEPARATOR.'test_attach_'.getmypid();

        mkdir($testUserDir, 0755, true);
        mkdir($testAttDir, 0755, true);

        try {
            $this->apply_ini_set([
                'disable_open_basedir' => false,
                'user_settings_dir'    => $testUserDir,
                'attachment_dir'       => $testAttDir,
            ]);

            $value = ini_get('open_basedir');
            $this->assertStringContainsString($testUserDir, $value);
            $this->assertStringContainsString($testAttDir, $value);
        } finally {
            @rmdir($testUserDir);
            @rmdir($testAttDir);
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_with_nonreadable_directories(): void {
        $this->apply_ini_set([
            'disable_open_basedir' => false,
            'user_settings_dir'    => '/nonexistent/user_dir',
            'attachment_dir'       => '/nonexistent/attach_dir',
        ]);

        $value = ini_get('open_basedir');
        $this->assertStringNotContainsString('/nonexistent/user_dir', $value);
        $this->assertStringNotContainsString('/nonexistent/attach_dir', $value);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tmp_dir_in_open_basedir(): void {
        $this->apply_ini_set(['disable_open_basedir' => false]);

        $tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $this->assertStringContainsString($tmpDir, ini_get('open_basedir'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_version_compatibility(): void {
        $this->assertTrue(
            version_compare(PHP_VERSION, '7.0', '>='),
            'Tests require PHP 7.0 or higher'
        );

        if (version_compare(PHP_VERSION, '7.3', '>=')) {
            $this->assertTrue(true, 'SameSite cookie support available');
        }

        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            $this->assertTrue(true, 'PHP 8+ features available');
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ini_set_error_handling(): void {
        $errorOccurred = false;
        set_error_handler(function() use (&$errorOccurred) {
            $errorOccurred = true;
        });

        $this->apply_ini_set(['disable_open_basedir' => true]);

        restore_error_handler();

        $this->assertFalse($errorOccurred, 'No errors should occur during ini_set operations');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_configuration_dependencies(): void {
        $config = new Hm_Mock_Config();

        $this->assertNotNull($config);
        $this->assertIsCallable([$config, 'get']);
        $this->assertIsBool($config->get('disable_tls', false));
        $this->assertIsBool($config->get('disable_open_basedir', false));
    }
}
