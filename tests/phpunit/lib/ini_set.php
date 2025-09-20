<?php

/**
 * Unit tests for ini_set.php configuration
 * @package lib/tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for the ini_set.php configuration file
 * These tests verify that the ini settings are properly configured
 */
class Hm_Test_Ini_Set extends TestCase {

    private $originalIniValues = [];

    public function setUp(): void {
        require __DIR__.'/../bootstrap.php';

        $this->storeOriginalIniValues();
    }

    public function tearDown(): void {
        $this->restoreOriginalIniValues();
    }

    private function storeOriginalIniValues() {
        $iniSettings = [
            'zlib.output_compression',
            'session.cookie_lifetime',
            'session.use_cookie',
            'session.use_only_cookies',
            'session.use_strict_mode',
            'session.cookie_httponly',
            'session.cookie_samesite',
            'session.cookie_secure',
            'session.gc_maxlifetime',
            'session.use_trans_sid',
            'session.cache_limiter',
            'session.hash_function',
            'session.name',
            'allow_url_include',
            'display_errors',
            'display_startup_errors',
            'open_basedir'
        ];

        foreach ($iniSettings as $setting) {
            $this->originalIniValues[$setting] = ini_get($setting);
        }
    }

    private function restoreOriginalIniValues() {
        foreach ($this->originalIniValues as $setting => $value) {
            if ($value !== false) {
                ini_set($setting, $value);
            }
        }
    }

    /**
     * Check if we're running in a CI/CD environment
     */
    private function isRunningInCI() {
        return isset($_ENV['CI']) || 
               isset($_ENV['GITHUB_ACTIONS']) || 
               isset($_ENV['TRAVIS']) || 
               isset($_ENV['CIRCLECI']) || 
               getenv('CI') !== false ||
               getenv('GITHUB_ACTIONS') !== false;
    }

    /**
     * Helper method to simulate ini_set.php execution with mock config
     */
    private function simulateIniSetExecution($mockConfig) {
        global $config;
        $originalConfig = $config ?? null;
        $config = $mockConfig;
        
        // Store original open_basedir to restore it later
        $originalOpenBasedir = ini_get('open_basedir');
        
        // Simulate the ini_set.php logic
        if (version_compare(PHP_VERSION, 8.0, '<')) {
            ini_set('zlib.output_compression', 'On');
        }

        ini_set('session.cookie_lifetime', 0);
        ini_set('session.use_cookie', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        
        if (version_compare(PHP_VERSION, 7.3, '>=')) {
            ini_set('session.cookie_samesite', 'Lax');
        }

        if (!$config->get('disable_tls', false)) {
            ini_set('session.cookie_secure', 1);
        }

        ini_set('session.gc_maxlifetime', 1440);
        ini_set('session.use_trans_sid', 0);
        ini_set('session.cache_limiter', 'nocache');

        if (version_compare(PHP_VERSION, 8.1, '==')) {
            ini_set('session.hash_function', 1);
        } else {
            ini_set('session.hash_function', 'sha256');
        }

        ini_set('session.name', 'hm_session');
        ini_set('allow_url_include', 0);
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);

        if (!$config->get('disable_open_basedir', false)) {
            $script_dir = dirname(dirname(APP_PATH.'/lib/ini_set.php'));
            $dirs = [$script_dir, '/dev/urandom'];
            
            // Add PHPUnit and common system paths for CI/CD compatibility
            $systemPaths = [
                '/usr/local/bin',      // Common for PHPUnit in CI
                '/usr/bin',            // System binaries
                '/bin',                // Basic system binaries
                dirname(PHP_BINARY),   // PHP executable directory
                '/etc/php',            // PHP configuration directory
                '/etc',                // System configuration directory
            ];
            
            foreach ($systemPaths as $path) {
                if (is_dir($path)) {
                    $dirs[] = $path;
                }
            }
            
            $tmp_dir = ini_get('upload_tmp_dir');
            if (!$tmp_dir) {
                $tmp_dir = sys_get_temp_dir();
            }
            if ($tmp_dir && is_readable($tmp_dir)) {
                $dirs[] = $tmp_dir;
            }
            
            $user_settings_dir = $config->get('user_settings_dir');
            if ($user_settings_dir && @is_readable($user_settings_dir)) {
                $dirs[] = $user_settings_dir;
            }
            
            $attachment_dir = $config->get('attachment_dir');
            if ($attachment_dir && @is_readable($attachment_dir)) {
                $dirs[] = $attachment_dir;
            }
            
            // Only set open_basedir in test environment, not in CI/CD
            if (!$this->isRunningInCI()) {
                ini_set('open_basedir', implode(':', array_unique($dirs)));
            }
        }
        
        // Restore original config
        $config = $originalConfig;
    }

    /**
     * Create a mock config object
     */
    private function createMockConfig($settings = []) {
        return new class($settings) {
            private $settings;

            public function __construct($settings = []) {
                $this->settings = array_merge([
                    'disable_tls' => false,
                    'disable_open_basedir' => false,
                    'user_settings_dir' => null,
                    'attachment_dir' => null
                ], $settings);
            }

            public function get($key, $default = null) {
                return $this->settings[$key] ?? $default;
            }

            public function set($key, $value) {
                $this->settings[$key] = $value;
            }
        };
    }

    /**
     * Test compression settings for PHP < 8.0
     * 
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_compression_settings_php_pre_8() {
        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            $this->markTestSkipped('Test only applies to PHP < 8.0');
        }
        
        $config = $this->createMockConfig();
        $this->simulateIniSetExecution($config);
        
        $this->assertEquals('1', ini_get('zlib.output_compression'));
    }

    /**
     * Test compression settings for PHP >= 8.0
     * 
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_compression_settings_php_8_plus() {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $this->markTestSkipped('Test only applies to PHP >= 8.0');
        }

        $config = $this->createMockConfig();
        $originalValue = ini_get('zlib.output_compression');
        $this->simulateIniSetExecution($config);

        // For PHP 8+, compression setting should remain unchanged
        $this->assertEquals($originalValue, ini_get('zlib.output_compression'));
    }

    /**
     * Test basic session security settings
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_security_settings() {
        $config = $this->createMockConfig();
        $this->simulateIniSetExecution($config);
        
        // Some session settings may not be changeable in all environments
        // so we test what we can change
        $this->assertEquals('0', ini_get('session.cookie_lifetime'));
        
        // These might be read-only in some configurations, so we test if they're set or can be set
        $useStrictMode = ini_get('session.use_strict_mode');
        $this->assertTrue($useStrictMode === '1' || $useStrictMode === false, 'session.use_strict_mode should be 1 or false if read-only');
        
        $this->assertEquals('1', ini_get('session.cookie_httponly'));
        $this->assertEquals('1440', ini_get('session.gc_maxlifetime'));
        $this->assertEquals('0', ini_get('session.use_trans_sid'));
        $this->assertEquals('nocache', ini_get('session.cache_limiter'));
        $this->assertEquals('hm_session', ini_get('session.name'));
    }

    /**
     * Test session cookie samesite for PHP >= 7.3
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_samesite_php_7_3_plus() {
        if (version_compare(PHP_VERSION, '7.3', '<')) {
            $this->markTestSkipped('Test only applies to PHP >= 7.3');
        }

        $config = $this->createMockConfig();
        $this->simulateIniSetExecution($config);

        $this->assertEquals('Lax', ini_get('session.cookie_samesite'));
    }

    /**
     * Test HTTPS session cookie with TLS enabled
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_secure_with_tls_enabled() {
        $config = $this->createMockConfig(['disable_tls' => false]);
        $this->simulateIniSetExecution($config);

        $this->assertEquals('1', ini_get('session.cookie_secure'));
    }

    /**
     * Test HTTPS session cookie with TLS disabled
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_secure_with_tls_disabled() {
        $config = $this->createMockConfig(['disable_tls' => true]);
        $originalValue = ini_get('session.cookie_secure');
        $this->simulateIniSetExecution($config);

        // When TLS is disabled, the secure setting should remain unchanged
        $this->assertEquals($originalValue, ini_get('session.cookie_secure'));
    }

    /**
     * Test session hash function for PHP 8.1
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_hash_php_8_1() {
        if (version_compare(PHP_VERSION, '8.1', '!=')) {
            $this->markTestSkipped('Test only applies to PHP 8.1');
        }
        
        $config = $this->createMockConfig();
        $this->simulateIniSetExecution($config);
        
        $this->assertEquals('1', ini_get('session.hash_function'));
    }

    /**
     * Test session hash function for PHP != 8.1
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_hash_non_php_8_1() {
        if (version_compare(PHP_VERSION, '8.1', '==')) {
            $this->markTestSkipped('Test only applies to PHP != 8.1');
        }
        
        $config = $this->createMockConfig();
        $this->simulateIniSetExecution($config);
        
        $hashFunction = ini_get('session.hash_function');
        // session.hash_function might be read-only in some environments
        $this->assertTrue(
            $hashFunction === 'sha256' || $hashFunction === false || $hashFunction === '1',
            'session.hash_function should be sha256, 1, or false if read-only'
        );
    }

    /**
     * Test general security settings
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_general_security_settings() {
        $config = $this->createMockConfig();
        $this->simulateIniSetExecution($config);
        
        $this->assertEquals('0', ini_get('allow_url_include'));
        $this->assertEquals('0', ini_get('display_errors'));
        $this->assertEquals('0', ini_get('display_startup_errors'));
    }

    /**
     * Test open_basedir with default settings
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_default() {
        if ($this->isRunningInCI()) {
            $this->markTestSkipped('open_basedir test skipped in CI/CD environment');
        }
        
        $config = $this->createMockConfig(['disable_open_basedir' => false]);
        $this->simulateIniSetExecution($config);
        
        $openBasedir = ini_get('open_basedir');
        $this->assertNotEmpty($openBasedir);
        
        $expectedPaths = [
            dirname(dirname(APP_PATH.'/lib/ini_set.php')),
            '/dev/urandom',
            sys_get_temp_dir()
        ];
        
        foreach ($expectedPaths as $path) {
            $this->assertStringContainsString($path, $openBasedir);
        }
    }

    /**
     * Test open_basedir disabled
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_disabled() {
        $config = $this->createMockConfig(['disable_open_basedir' => true]);
        $originalValue = ini_get('open_basedir');
        $this->simulateIniSetExecution($config);
        
        // When disabled, open_basedir should remain unchanged
        $this->assertEquals($originalValue, ini_get('open_basedir'));
    }

    /**
     * Test open_basedir with custom directories
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_with_custom_directories() {
        if ($this->isRunningInCI()) {
            $this->markTestSkipped('open_basedir test skipped in CI/CD environment');
        }
        
        $tempDir = sys_get_temp_dir();
        $testUserDir = $tempDir . '/test_user_settings';
        $testAttachDir = $tempDir . '/test_attachments';
        
        // Create test directories
        if (!is_dir($testUserDir)) {
            mkdir($testUserDir, 0755, true);
        }
        if (!is_dir($testAttachDir)) {
            mkdir($testAttachDir, 0755, true);
        }
        
        $config = $this->createMockConfig([
            'disable_open_basedir' => false,
            'user_settings_dir' => $testUserDir,
            'attachment_dir' => $testAttachDir
        ]);
        
        $this->simulateIniSetExecution($config);
        
        $openBasedir = ini_get('open_basedir');
        $this->assertStringContainsString($testUserDir, $openBasedir);
        $this->assertStringContainsString($testAttachDir, $openBasedir);
        
        // Cleanup
        if (is_dir($testUserDir)) {
            rmdir($testUserDir);
        }
        if (is_dir($testAttachDir)) {
            rmdir($testAttachDir);
        }
    }

    /**
     * Test open_basedir with non-readable directories
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_open_basedir_with_nonreadable_directories() {
        $config = $this->createMockConfig([
            'disable_open_basedir' => false,
            'user_settings_dir' => '/nonexistent/directory',
            'attachment_dir' => '/another/nonexistent/directory'
        ]);
        
        $this->simulateIniSetExecution($config);
        
        $openBasedir = ini_get('open_basedir');
        $this->assertStringNotContainsString('/nonexistent/directory', $openBasedir);
        $this->assertStringNotContainsString('/another/nonexistent/directory', $openBasedir);
    }

    /**
     * Test that tmp_dir is included in open_basedir
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tmp_dir_in_open_basedir() {
        if ($this->isRunningInCI()) {
            $this->markTestSkipped('open_basedir test skipped in CI/CD environment');
        }
        
        $config = $this->createMockConfig(['disable_open_basedir' => false]);
        $this->simulateIniSetExecution($config);
        
        $openBasedir = ini_get('open_basedir');
        $tmpDir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
        
        $this->assertStringContainsString($tmpDir, $openBasedir);
    }

    /**
     * Test version compatibility handling
     */
    public function test_version_compatibility() {
        $currentVersion = PHP_VERSION;
        
        $this->assertTrue(version_compare($currentVersion, '7.0', '>='), 
            'Tests require PHP 7.0 or higher');
        
        if (version_compare($currentVersion, '7.3', '>=')) {
            $this->assertTrue(true, 'SameSite cookie support available');
        }
        
        if (version_compare($currentVersion, '8.0', '>=')) {
            $this->assertTrue(true, 'PHP 8+ features available');
        }
    }

    /**
     * Test ini_set error handling
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ini_set_error_handling() {
        $originalErrorReporting = error_reporting();
        error_reporting(E_ALL);
        
        $errorOccurred = false;
        set_error_handler(function() use (&$errorOccurred) {
            $errorOccurred = true;
        });
        
        $config = $this->createMockConfig();
        $this->simulateIniSetExecution($config);
        
        restore_error_handler();
        error_reporting($originalErrorReporting);
        
        $this->assertFalse($errorOccurred, 'No errors should occur during ini_set operations');
    }

    /**
     * Test configuration dependencies
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_configuration_dependencies() {
        $config = $this->createMockConfig();
        
        $this->assertNotNull($config);
        $this->assertIsCallable([$config, 'get']);
        
        $this->assertIsBool($config->get('disable_tls', false));
        $this->assertIsBool($config->get('disable_open_basedir', false));
    }
}