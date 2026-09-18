<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Web_Installer extends TestCase {

    private $tmp_dir;
    private $token_file;

    public function setUp(): void {
        require_once APP_PATH.'lib/installer.php';
        require_once APP_PATH.'lib/web_installer.php';
        $this->tmp_dir = sys_get_temp_dir().'/cypht_web_installer_test_'.uniqid().'/';
        mkdir($this->tmp_dir);
        copy(APP_PATH.'.env.example', $this->tmp_dir.'.env.example');
        $this->token_file = $this->tmp_dir.'install.token';
    }

    public function tearDown(): void {
        foreach (scandir($this->tmp_dir) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                unlink($this->tmp_dir.$entry);
            }
        }
        rmdir($this->tmp_dir);
    }

    private function webInstaller() {
        return new Hm_Web_Installer(new Hm_Installer($this->tmp_dir), $this->token_file);
    }

    public function test_token_does_not_exist_before_generation() {
        $web_installer = $this->webInstaller();
        $this->assertFalse($web_installer->tokenExists());
    }

    public function test_generate_token_creates_file_and_returns_it() {
        $web_installer = $this->webInstaller();
        $token = $web_installer->generateToken();
        $this->assertTrue($web_installer->tokenExists());
        $this->assertSame($token, trim(file_get_contents($this->token_file)));
    }

    public function test_token_matches_accepts_the_generated_value() {
        $web_installer = $this->webInstaller();
        $token = $web_installer->generateToken();
        $this->assertTrue($web_installer->tokenMatches($token));
    }

    public function test_token_matches_rejects_wrong_value() {
        $web_installer = $this->webInstaller();
        $web_installer->generateToken();
        $this->assertFalse($web_installer->tokenMatches('wrong'));
    }

    public function test_token_matches_rejects_empty_value() {
        $web_installer = $this->webInstaller();
        $web_installer->generateToken();
        $this->assertFalse($web_installer->tokenMatches(''));
    }

    public function test_token_matches_is_false_before_generation() {
        $web_installer = $this->webInstaller();
        $this->assertFalse($web_installer->tokenMatches('anything'));
    }

    public function test_consume_token_removes_the_file() {
        $web_installer = $this->webInstaller();
        $token = $web_installer->generateToken();
        $web_installer->consumeToken();
        $this->assertFalse($web_installer->tokenExists());
        $this->assertFalse($web_installer->tokenMatches($token));
    }

    public function test_secret_matches_accepts_equal_non_empty_strings() {
        $this->assertTrue(Hm_Web_Installer::secretMatches('abc', 'abc'));
    }

    public function test_secret_matches_rejects_different_strings() {
        $this->assertFalse(Hm_Web_Installer::secretMatches('abc', 'def'));
    }

    public function test_secret_matches_rejects_empty_expected_or_given() {
        $this->assertFalse(Hm_Web_Installer::secretMatches('', 'abc'));
        $this->assertFalse(Hm_Web_Installer::secretMatches('abc', ''));
        $this->assertFalse(Hm_Web_Installer::secretMatches('', ''));
    }

    public function test_is_https_true_for_https_server_var() {
        $this->assertTrue(Hm_Web_Installer::isHttps(['HTTPS' => 'on']));
    }

    public function test_is_https_true_for_forwarded_proto() {
        $this->assertTrue(Hm_Web_Installer::isHttps(['HTTP_X_FORWARDED_PROTO' => 'https']));
    }

    public function test_is_https_false_when_absent() {
        $this->assertFalse(Hm_Web_Installer::isHttps([]));
    }

    public function test_is_https_false_when_https_off() {
        $this->assertFalse(Hm_Web_Installer::isHttps(['HTTPS' => 'off']));
    }

    public function test_collect_form_values_uses_defaults_for_missing_fields() {
        $values = Hm_Web_Installer::collectFormValues([]);
        $this->assertSame('mysql', $values['DB_DRIVER']);
        $this->assertSame('DB', $values['AUTH_TYPE']);
        $this->assertSame('file', $values['USER_CONFIG_TYPE']);
    }

    public function test_collect_form_values_trims_and_keeps_submitted_fields() {
        $values = Hm_Web_Installer::collectFormValues(['DB_NAME' => '  my_db  ']);
        $this->assertSame('my_db', $values['DB_NAME']);
    }

    public function test_collect_form_values_ignores_unknown_post_fields() {
        $values = Hm_Web_Installer::collectFormValues(['DB_NAME' => 'db', 'unexpected' => 'x']);
        $this->assertArrayNotHasKey('unexpected', $values);
    }

    public function test_is_under_path_true_for_a_direct_subdirectory() {
        $this->assertTrue(Hm_Web_Installer::isUnderPath($this->tmp_dir.'data', $this->tmp_dir));
    }

    public function test_is_under_path_true_for_the_base_itself() {
        $this->assertTrue(Hm_Web_Installer::isUnderPath(rtrim($this->tmp_dir, '/'), $this->tmp_dir));
    }

    public function test_is_under_path_false_for_a_sibling_directory() {
        $sibling = rtrim($this->tmp_dir, '/').'-sibling/data';
        $this->assertFalse(Hm_Web_Installer::isUnderPath($sibling, $this->tmp_dir));
    }

    public function test_is_under_path_false_for_a_path_that_merely_shares_a_prefix() {
        // e.g. base "/var/www/cypht" must not match "/var/www/cypht-old/data"
        $lookalike = rtrim($this->tmp_dir, '/').'-old/data';
        $this->assertFalse(Hm_Web_Installer::isUnderPath($lookalike, $this->tmp_dir));
    }

    public function test_validate_accepts_a_driver_from_the_allowed_list() {
        $values = Hm_Web_Installer::collectFormValues(['DB_DRIVER' => 'pgsql']);
        $values['USER_SETTINGS_DIR'] = '/var/lib/hm3/users';
        $values['ATTACHMENT_DIR'] = '/var/lib/hm3/attachments';
        $this->assertSame([], Hm_Web_Installer::validate($values, $this->tmp_dir));
    }

    public function test_validate_rejects_a_driver_outside_the_allowed_list() {
        $values = Hm_Web_Installer::collectFormValues(['DB_DRIVER' => 'mongodb']);
        $values['USER_SETTINGS_DIR'] = '/var/lib/hm3/users';
        $values['ATTACHMENT_DIR'] = '/var/lib/hm3/attachments';
        $errors = Hm_Web_Installer::validate($values, $this->tmp_dir);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('driver', $errors[0]);
    }

    public function test_validate_rejects_a_storage_directory_inside_the_web_root() {
        $values = Hm_Web_Installer::collectFormValues([]);
        $values['USER_SETTINGS_DIR'] = $this->tmp_dir.'data/users';
        $values['ATTACHMENT_DIR'] = '/var/lib/hm3/attachments';
        $errors = Hm_Web_Installer::validate($values, $this->tmp_dir);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('User settings directory', $errors[0]);
    }

    public function test_validate_rejects_an_empty_storage_directory() {
        $values = Hm_Web_Installer::collectFormValues([]);
        $values['ATTACHMENT_DIR'] = '';
        $errors = Hm_Web_Installer::validate($values, $this->tmp_dir);
        $this->assertNotEmpty($errors);
    }
}
