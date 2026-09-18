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
        $this->assertSame('my_custom_db', $this->parseEnvValue('DB_NAME'));
    }

    public function test_write_env_appends_unknown_key() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv(['CYPHT_NOT_IN_EXAMPLE' => 'value']);
        $this->assertSame('value', $this->parseEnvValue('CYPHT_NOT_IN_EXAMPLE'));
    }

    public function test_write_env_quotes_values_with_special_characters() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv(['DB_PASS' => 'pass "word" with spaces']);
        $this->assertSame('pass "word" with spaces', $this->parseEnvValue('DB_PASS'));
    }

    /**
     * Symfony Dotenv supports multi-line quoted values and ${VAR}
     * interpolation, like bash. A value ending in a backslash, or
     * containing a dollar sign, must still round-trip literally and must
     * not swallow the next line or resolve another variable.
     */
    public function test_write_env_escapes_values_that_are_dotenv_metacharacters() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv([
            'DB_NAME' => 'other_db',
            'DB_PASS' => 'trailing\\backslash\\',
        ]);
        $this->assertSame('trailing\\backslash\\', $this->parseEnvValue('DB_PASS'));
        $this->assertSame('other_db', $this->parseEnvValue('DB_NAME'));
        $this->assertSame('/var/lib/mysqld/mysqld.sock', $this->parseEnvValue('DB_SOCKET'));
    }

    public function test_write_env_does_not_interpolate_dollar_variables() {
        $installer = new Hm_Installer($this->tmp_dir);
        $installer->writeEnv([
            'DB_NAME' => 'secret_db_name',
            'DB_PASS' => '${DB_NAME}',
        ]);
        $this->assertSame('${DB_NAME}', $this->parseEnvValue('DB_PASS'));
    }

    private function parseEnvValue($key) {
        $dotenv = new \Symfony\Component\Dotenv\Dotenv();
        $vars = $dotenv->parse(file_get_contents($this->tmp_dir.'.env'));
        return $vars[$key] ?? null;
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
