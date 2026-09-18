<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Installer extends TestCase {

    private $tmp_dir;

    public function setUp(): void {
        require_once APP_PATH.'lib/installer.php';
        $this->tmp_dir = sys_get_temp_dir().'/cypht_installer_test_'.uniqid().'/';
        mkdir($this->tmp_dir);
        copy(APP_PATH.'.env.example', $this->tmp_dir.'.env.example');
    }

    public function tearDown(): void {
        $this->removeDirectory($this->tmp_dir);
    }

    private function removeDirectory($dir) {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.$entry;
            if (is_dir($path)) {
                $this->removeDirectory($path.'/');
            }
            else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_env_exists_is_false_before_install() {
        $installer = new Hm_Installer($this->tmp_dir);
        $this->assertFalse($installer->envExists());
    }

    public function test_env_exists_is_true_after_write_env() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv(['DB_NAME' => 'cypht_db']);
        $this->assertTrue($installer->envExists());
    }

    public function test_check_requirements_reports_current_environment() {
        $installer = new Hm_Installer($this->tmp_dir);
        $requirements = $installer->checkRequirements();
        $this->assertArrayHasKey('php_version', $requirements);
        $this->assertArrayHasKey('mbstring', $requirements);
        $this->assertArrayHasKey('openssl', $requirements);
        $this->assertArrayHasKey('dom', $requirements);
        $this->assertArrayHasKey('pdo', $requirements);
        $this->assertTrue($requirements['php_version']);
    }

    public function test_write_env_overrides_existing_key() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv(['DB_NAME' => 'my_custom_db']);
        $env = file_get_contents($this->tmp_dir.'.env');
        $this->assertMatchesRegularExpression('/^DB_NAME=my_custom_db$/m', $env);
    }

    public function test_write_env_appends_unknown_key() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv(['CYPHT_NOT_IN_EXAMPLE' => 'value']);
        $env = file_get_contents($this->tmp_dir.'.env');
        $this->assertMatchesRegularExpression('/^CYPHT_NOT_IN_EXAMPLE=value$/m', $env);
    }

    public function test_write_env_quotes_values_with_special_characters() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv(['DB_PASS' => 'pass "word" with spaces']);
        $env = file_get_contents($this->tmp_dir.'.env');
        $this->assertStringContainsString('DB_PASS="pass \"word\" with spaces"', $env);
    }

    public function test_create_directories_creates_nested_path() {
        $installer = new Hm_Installer($this->tmp_dir);
        $dir = $this->tmp_dir.'a/b/c';
        $installer->createDirectories([$dir]);
        $this->assertDirectoryExists($dir);
    }

    public function test_create_directories_is_idempotent() {
        $installer = new Hm_Installer($this->tmp_dir);
        $dir = $this->tmp_dir.'existing';
        mkdir($dir);
        $installer->createDirectories([$dir]);
        $this->assertDirectoryExists($dir);
    }

    public function test_create_directories_throws_when_path_is_a_file() {
        $installer = new Hm_Installer($this->tmp_dir);
        $file = $this->tmp_dir.'blocking_file';
        file_put_contents($file, 'x');
        $this->expectException(RuntimeException::class);
        $installer->createDirectories([$file]);
    }
}
