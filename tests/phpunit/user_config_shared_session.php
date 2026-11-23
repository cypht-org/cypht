<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_User_Config_Shared_Session extends TestCase {
    private $directory;
    private $site;

    /** @return void */
    protected function setUp(): void {
        $this->directory = sys_get_temp_dir() . '/cypht-shared-state-session-' . bin2hex(random_bytes(12));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Unable to create test directory');
        }
        $this->site = new Hm_Mock_Config();
        $this->site->set('user_settings_dir', $this->directory);
        $this->site->set('user_config_type', 'file');
    }

    /** @return void */
    protected function tearDown(): void {
        Hm_Dispatch::set_shared_session(null);
        foreach (glob($this->directory . '/.shared-state-*') as $path) {
            unlink($path);
        }
        foreach (glob($this->directory . '/*.txt') as $path) {
            unlink($path);
        }
        foreach (glob($this->directory . '/*.db*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    /**
     * Create a session and user configuration attached to a shared state.
     * @param string $password account password
     * @param array|null $credentials existing shared-state session credentials
     * @return array session and user configuration objects
     */
    private function session_and_config($password, $credentials = null, $session_key = null) {
        $session = new Hm_PHP_Session($this->site, 'Hm_Auth_DB');
        $session->session_key = $session_key ?? bin2hex(random_bytes(16));
        $session->set('username', 'alice');

        $config = new Hm_User_Config_File($this->site);
        $config->load('alice', $password);

        if ($credentials !== null) {
            $session->set('shared_state_credentials', $credentials);
            $session->open_shared_state($config);
        } else {
            $session->open_shared_state($config, $password);
        }
        return [$session, $config];
    }

    /** @return void */
    public function test_changes_are_shared_across_active_sessions_until_explicit_save() {
        list($first, $first_config) = $this->session_and_config('password');
        list($second, $second_config) = $this->session_and_config('password');

        $first_config->set('theme_setting', 'dark');
        $first->record_unsaved('Theme changed');
        $first->flush_shared_state();

        $permanent = new Hm_User_Config_File($this->site);
        $permanent->load('alice', 'password');
        $this->assertNotSame('dark', $permanent->get('theme_setting'));

        list($next, $next_config) = $this->session_and_config(
            'password',
            $second->get('shared_state_credentials'),
            $second->session_key
        );

        $this->assertSame('dark', $next_config->get('theme_setting'));
        $this->assertSame(['Theme changed'], $next->get('changed_settings'));
        $this->assertTrue($next->save_shared_state('password'));

        $permanent->load('alice', 'password');
        $this->assertSame('dark', $permanent->get('theme_setting'));
        $this->assertSame([], $next->get('changed_settings'));
    }

    /** @return void */
    public function test_session_user_data_writes_are_shared() {
        list($first, $first_config) = $this->session_and_config('password');
        list($second, $second_config) = $this->session_and_config('password');

        $first->set('custom_imap_sources', ['folder' => 'add'], true);
        $first->record_unsaved('Added folder');
        $first->flush_shared_state();

        list($next, $next_config) = $this->session_and_config(
            'password',
            $second->get('shared_state_credentials'),
            $second->session_key
        );

        $this->assertSame(['folder' => 'add'], $next_config->get('custom_imap_sources'));
        $this->assertSame(['Added folder'], $next->get('changed_settings'));
    }

    /** @return void */
    public function test_single_server_automatically_saves_merged_changes() {
        $this->site->set('single_server_mode', true);
        $this->site->set('auth_type', 'IMAP');

        list($first, $first_config) = $this->session_and_config('password');
        list($second, $second_config) = $this->session_and_config('password');

        $first_config->set('first_setting', 'first');
        $second_config->set('second_setting', 'second');
        $this->assertFalse($second_config->get('first_setting'));
        $second_config->set('third_setting', 'third');

        $permanent = new Hm_User_Config_File($this->site);
        $permanent->load('alice', 'password');
        $this->assertSame('first', $permanent->get('first_setting'));
        $this->assertSame('second', $permanent->get('second_setting'));
        $this->assertSame('third', $permanent->get('third_setting'));
    }

    /** @return void */
    public function test_old_session_is_rejected_after_replacement() {
        list($old, $config) = $this->session_and_config('old');
        $credentials = $old->get('shared_state_credentials');

        $this->session_and_config('new');

        $this->expectException(Hm_Shared_State_Reauthentication_Required::class);
        $this->session_and_config('old', $credentials);
    }

    /** @return void */
    public function test_database_shared_state_and_permanent_save() {
        $path = $this->directory . '/settings.db';
        $db = new PDO('sqlite:' . $path);
        $db->exec('CREATE TABLE hm_user_settings (username TEXT PRIMARY KEY, settings BLOB)');
        $db->exec('CREATE TABLE hm_user_shared_state (user_key TEXT PRIMARY KEY, revision INTEGER NOT NULL, payload BLOB NOT NULL)');

        $this->site->set('user_config_type', 'DB');
        $this->site->set('db_driver', 'sqlite');
        $this->site->set('db_name', $path);
        $this->site->set('db_host', 'localhost');
        $this->site->set('db_user', 'test');
        $this->site->set('db_pass', 'test');

        $first = new Hm_PHP_Session($this->site, 'Hm_Auth_DB');
        $first->session_key = bin2hex(random_bytes(16));
        $first->set('username', 'alice');

        $first_config = new Hm_User_Config_DB($this->site);
        $first_config->load('alice', 'password');
        $first->open_shared_state($first_config, 'password');
        $this->assertTrue($first_config->save('alice', 'password'));

        $first_config->set('example', 'unsaved');
        $first->record_unsaved('Changed');
        $first->flush_shared_state();

        $second = new Hm_PHP_Session($this->site, 'Hm_Auth_DB');
        $second->session_key = bin2hex(random_bytes(16));
        $second->set('username', 'alice');

        $second_config = new Hm_User_Config_DB($this->site);
        $second_config->load('alice', 'password');
        $this->assertFalse($second_config->decrypt_failed);
        $second->open_shared_state($second_config, 'password');

        $this->assertSame('unsaved', $second_config->get('example'));
        $this->assertTrue($second->save_shared_state('password'));

        $permanent = new Hm_User_Config_DB($this->site);
        $permanent->load('alice', 'password');
        $this->assertSame('unsaved', $permanent->get('example'));
        $this->assertSame([], $second->get('changed_settings'));
    }

    /** @return void */
    public function test_core_loader_reads_the_latest_shared_settings() {
        $session = new Hm_PHP_Session($this->site, 'Hm_Auth_DB');
        $session->session_key = bin2hex(random_bytes(16));
        $session->active = true;
        $session->loaded = true;
        $session->set('username', 'alice');

        $request = new Hm_Mock_Request('HTTP');
        $request->post = ['username' => 'alice', 'password' => 'password'];

        $parent = (object) [
            'session' => $session,
            'request' => $request,
            'cache' => null,
            'site_config' => $this->site,
            'user_config' => new Hm_User_Config_File($this->site),
        ];
        $handler = new Hm_Handler_load_user_data($parent, 'home');
        $handler->process();

        $this->assertTrue($handler->module_output()['shared_state_enabled']);
        $parent->user_config->set('theme_setting', 'dark');
        $session->record_unsaved('Theme changed');
        $session->flush_shared_state();

        $other = new Hm_PHP_Session($this->site, 'Hm_Auth_DB');
        $other->session_key = bin2hex(random_bytes(16));
        $other->active = true;
        $other->loaded = false;
        $other->set('username', 'alice');

        $other_config = new Hm_User_Config_File($this->site);
        $other_config->load('alice', 'password');
        $other->open_shared_state($other_config, 'password');

        $request->post = [];
        $parent->session = $other;
        $parent->user_config = new Hm_User_Config_File($this->site);
        $handler = new Hm_Handler_load_user_data($parent, 'home');
        $handler->process();

        $this->assertSame('dark', $parent->user_config->get('theme_setting'));
        $this->assertSame(['Theme changed'], $handler->module_output()['changed_settings']);
    }

    /** @return void */
    public function test_shared_draft_remains_until_the_last_active_session_releases_it() {
        list($first, $first_config) = $this->session_and_config('password');
        list($second, $second_config) = $this->session_and_config('password');

        $first_config->set('theme_setting', 'dark');
        $first->record_unsaved('Theme changed');
        $first->flush_shared_state();

        $first->release_shared_state();
        $store = Hm_User_Config_Shared_Session::create_store($this->site);
        $this->assertNotNull($store->read('alice'));

        list($third, $third_config) = $this->session_and_config('password');
        $this->assertSame('dark', $third_config->get('theme_setting'));

        $second->release_shared_state();
        $this->assertNotNull($store->read('alice'));

        $third->release_shared_state();
        $this->assertNull($store->read('alice'));

        list($next, $next_config) = $this->session_and_config('password');
        $this->assertNotSame('dark', $next_config->get('theme_setting'));
        $this->assertSame([], $next->get('changed_settings'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     * @return void
     */
    public function test_logout_of_last_session_discards_unsaved_changes() {
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');
        session_start();
        list($session, $config) = $this->session_and_config('password');
        $session->active = true;

        $config->set('theme_setting', 'dark');
        $session->record_unsaved('Theme changed');
        $session->destroy(new Hm_Mock_Request('HTTP'));

        $store = Hm_User_Config_Shared_Session::create_store($this->site);
        $this->assertNull($store->read('alice'));

        list($next, $next_config) = $this->session_and_config('password');
        $this->assertNotSame('dark', $next_config->get('theme_setting'));
        $this->assertSame([], $next->get('changed_settings'));
    }

    /** @return void */
    public function test_failed_permanent_decryption_does_not_replace_shared_state() {
        $permanent = new Hm_User_Config_File($this->site);
        $permanent->reload(['saved' => 'value'], 'alice');
        $permanent->save('alice', 'old');

        list($old, $config) = $this->session_and_config('old');
        $config->set('saved', 'unsaved');
        $old->flush_shared_state();

        $store = Hm_User_Config_Shared_Session::create_store($this->site);
        $before = $store->read('alice');

        $session = new Hm_PHP_Session($this->site, 'Hm_Auth_DB');
        $session->active = true;
        $session->loaded = true;
        $session->set('username', 'alice');
        $session->session_key = bin2hex(random_bytes(16));

        $request = new Hm_Mock_Request('HTTP');
        $request->post = ['username' => 'alice', 'password' => 'new'];

        $parent = (object) [
            'session' => $session,
            'request' => $request,
            'cache' => null,
            'site_config' => $this->site,
            'user_config' => new Hm_User_Config_File($this->site),
        ];
        $handler = new Hm_Handler_load_user_data($parent, 'home');
        $handler->process();

        $this->assertTrue($parent->user_config->decrypt_failed);
        $this->assertTrue($session->get('skip_shared_state_load'));
        $this->assertSame($before, $store->read('alice'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     * @return void
     */
    public function test_changes_after_closing_the_session_early_are_flushed() {
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');
        session_start();
        list($session, $config) = $this->session_and_config('password');
        $session->active = true;
        $session->enc_key = 'session-key';

        $session->close_early();

        $config->set('theme_setting', 'dark');
        $session->record_unsaved('Theme changed');
        $session->end();

        list($next, $next_config) = $this->session_and_config('password');
        $this->assertSame('dark', $next_config->get('theme_setting'));
        $this->assertSame(['Theme changed'], $next->get('changed_settings'));
    }

    /** @return void */
    public function test_direct_redirect_flushes_shared_changes() {
        list($session, $config) = $this->session_and_config('password');

        $config->set('theme_setting', 'dark');
        $session->record_unsaved('Theme changed');
        Hm_Dispatch::page_redirect('?home');

        list($next, $next_config) = $this->session_and_config('password');
        $this->assertSame('dark', $next_config->get('theme_setting'));
        $this->assertSame(['Theme changed'], $next->get('changed_settings'));
    }
}
