<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the brute_force module set
 * @package tests
 * @subpackage brute_force
 */
class Hm_Test_Brute_Force_Handler_Modules extends TestCase {

    private $config;
    private $tracker;

    public function setUp(): void {
        require __DIR__ . '/../../helpers.php';
        require_once APP_PATH . 'modules/brute_force/modules.php';

        $this->config = new Hm_Mock_Config();
        setup_db($this->config);
        $this->clearAttempts();
        $this->tracker = new Hm_Brute_Force_Tracker($this->config);
    }

    public function tearDown(): void {
        $this->clearAttempts();
    }

    private function clearAttempts(): void {
        if (!$this->config) {
            return;
        }
        $dbh = Hm_DB::connect($this->config);
        if ($dbh) {
            Hm_DB::execute($dbh, 'delete from hm_login_attempts', []);
        }
    }

    private function handlerTest(string $module): Handler_Test {
        $test = new Handler_Test($module, 'brute_force');
        $test->config = $this->config->dump();
        return $test;
    }

    // -------------------------------------------------------------------------
    // Hm_Brute_Force_Tracker unit tests
    // -------------------------------------------------------------------------

    /** @runInSeparateProcess */
    public function test_make_key_returns_consistent_hash() {
        $key1 = $this->tracker->make_key('ip', '127.0.0.1');
        $key2 = $this->tracker->make_key('ip', '127.0.0.1');
        $key3 = $this->tracker->make_key('ip', '10.0.0.1');

        $this->assertSame($key1, $key2);
        $this->assertNotSame($key1, $key3);
        // Must not expose the raw value
        $this->assertStringNotContainsString('127.0.0.1', $key1);
    }

    /** @runInSeparateProcess */
    public function test_make_key_namespaces_differ() {
        $ip_key   = $this->tracker->make_key('ip',   '127.0.0.1');
        $user_key = $this->tracker->make_key('user', '127.0.0.1');
        $this->assertNotSame($ip_key, $user_key);
    }

    /** @runInSeparateProcess */
    public function test_load_returns_empty_array_when_no_rows() {
        $data = $this->tracker->load();
        $this->assertSame([], $data);
    }

    /** @runInSeparateProcess */
    public function test_save_and_load_roundtrip() {
        $original = ['ip:abc' => ['count' => 3, 'locked_until' => 0, 'last_attempt' => time()]];
        $this->tracker->save($original);
        $loaded = $this->tracker->load();
        $this->assertSame($original, $loaded);
    }

    /** @runInSeparateProcess */
    public function test_is_locked_returns_false_for_unknown_key() {
        $this->assertFalse($this->tracker->is_locked('ip:unknown', 5));
    }

    /** @runInSeparateProcess */
    public function test_is_locked_false_when_below_threshold() {
        $data = ['ip:abc' => ['count' => 3, 'locked_until' => time() + 900, 'last_attempt' => time()]];
        $this->tracker->save($data);
        $this->assertFalse($this->tracker->is_locked('ip:abc', 5));
    }

    /** @runInSeparateProcess */
    public function test_is_locked_true_when_threshold_reached_and_not_expired() {
        $data = ['ip:abc' => ['count' => 5, 'locked_until' => time() + 900, 'last_attempt' => time()]];
        $this->tracker->save($data);
        $this->assertTrue($this->tracker->is_locked('ip:abc', 5));
    }

    /** @runInSeparateProcess */
    public function test_is_locked_false_when_lockout_expired() {
        $data = ['ip:abc' => ['count' => 5, 'locked_until' => time() - 1, 'last_attempt' => time() - 1000]];
        $this->tracker->save($data);
        $this->assertFalse($this->tracker->is_locked('ip:abc', 5));
    }

    /** @runInSeparateProcess */
    public function test_record_failure_increments_count() {
        $key  = 'ip:abc';
        $entry = $this->tracker->record_failure($key, 5, 900);
        $data = $this->tracker->load();
        $this->assertSame(1, $entry['count']);
        $this->assertSame(1, $data[$key]['count']);
    }

    /** @runInSeparateProcess */
    public function test_record_failure_sets_lockout_at_threshold() {
        $key  = 'ip:abc';
        $entry = [];
        for ($i = 0; $i < 5; $i++) {
            $entry = $this->tracker->record_failure($key, 5, 900);
        }
        $data = $this->tracker->load();
        $this->assertGreaterThan(time(), $entry['locked_until']);
        $this->assertGreaterThan(time(), $data[$key]['locked_until']);
    }

    /** @runInSeparateProcess */
    public function test_record_failure_resets_after_expired_lockout() {
        $data = [
            'ip:abc' => ['count' => 5, 'locked_until' => time() - 1, 'last_attempt' => time() - 1000],
        ];
        $this->tracker->save($data);
        $entry = $this->tracker->record_failure('ip:abc', 5, 900);
        $data = $this->tracker->load();
        $this->assertSame(1, $entry['count']);
        $this->assertSame(1, $data['ip:abc']['count']);
    }

    /** @runInSeparateProcess */
    public function test_clear_removes_key() {
        $data = ['ip:abc' => ['count' => 3, 'locked_until' => 0, 'last_attempt' => time()]];
        $this->tracker->save($data);
        $this->tracker->clear('ip:abc');
        $data = $this->tracker->load();
        $this->assertArrayNotHasKey('ip:abc', $data);
    }

    /** @runInSeparateProcess */
    public function test_clean_expired_removes_old_entries() {
        $data = [
            'ip:old' => ['count' => 5, 'locked_until' => time() - 1000, 'last_attempt' => time() - 2000],
            'ip:new' => ['count' => 3, 'locked_until' => time() + 500,  'last_attempt' => time()],
        ];
        $this->tracker->save($data);
        $this->tracker->clean_expired(900);
        $data = $this->tracker->load();
        $this->assertArrayNotHasKey('ip:old', $data);
        $this->assertArrayHasKey('ip:new', $data);
    }

    /** @runInSeparateProcess */
    public function test_lockout_seconds_remaining_zero_when_not_locked() {
        $data = ['ip:abc' => ['count' => 2, 'locked_until' => 0, 'last_attempt' => time()]];
        $this->tracker->save($data);
        $this->assertSame(0, $this->tracker->lockout_seconds_remaining('ip:abc', 5));
    }

    /** @runInSeparateProcess */
    public function test_lockout_seconds_remaining_positive_when_locked() {
        $data = ['ip:abc' => ['count' => 5, 'locked_until' => time() + 300, 'last_attempt' => time()]];
        $this->tracker->save($data);
        $remaining = $this->tracker->lockout_seconds_remaining('ip:abc', 5);
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(300, $remaining);
    }

    // -------------------------------------------------------------------------
    // Handler module integration tests
    // -------------------------------------------------------------------------

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_check_allows_clean_attempt() {
        $test       = $this->handlerTest('brute_force_check');
        $test->post = ['username' => 'alice', 'password' => 'secret'];
        $res        = $test->run();
        // Post should NOT have been cleared
        $this->assertNotEmpty($test->req_obj->post);
        $this->assertEmpty(Hm_Msgs::get());
        Hm_Msgs::flush();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_check_skips_non_login_requests() {
        $test = $this->handlerTest('brute_force_check');
        $res  = $test->run(); // no username/password in post
        $this->assertEmpty(Hm_Msgs::get());
        Hm_Msgs::flush();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_check_blocks_locked_ip() {
        // Pre-seed a locked entry for the default test IP (empty string in mocks)
        $ip_key = $this->tracker->make_key('ip', '');
        $data   = [
            $ip_key => ['count' => 5, 'locked_until' => time() + 900, 'last_attempt' => time()],
        ];
        $this->tracker->save($data);

        $test       = $this->handlerTest('brute_force_check');
        $test->post = ['username' => 'alice', 'password' => 'secret'];
        $res        = $test->run();

        // Post must be cleared
        $this->assertEmpty($res->request->post ?? $test->req_obj->post);
        $msgs = Hm_Msgs::get();
        $this->assertNotEmpty($msgs);
        $this->assertStringContainsString('Too many failed login attempts', $msgs[0]);
        Hm_Msgs::flush();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_track_records_failure() {
        $ip_key  = $this->tracker->make_key('ip', '');
        $user_key = $this->tracker->make_key('user', 'alice');

        $test        = $this->handlerTest('brute_force_track');
        // Simulate that brute_force_check ran and set these output values
        $test->input = ['bf_ip' => '', 'bf_username' => 'alice'];
        // Session is NOT active (failed login) — set AFTER prep() so it isn't overwritten
        $test->prep();
        $test->ses_obj->loaded = false;
        $test->run_only();

        $data = $this->tracker->load();
        $this->assertArrayHasKey($ip_key, $data);
        $this->assertSame(1, $data[$ip_key]['count']);
        $this->assertArrayHasKey($user_key, $data);
        $this->assertSame(1, $data[$user_key]['count']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_track_records_failure_from_post_fallback() {
        $ip_key  = $this->tracker->make_key('ip', '');
        $user_key = $this->tracker->make_key('user', 'alice');

        $test        = $this->handlerTest('brute_force_track');
        $test->post  = ['username' => 'alice', 'password' => 'wrong'];
        $test->prep();
        $test->ses_obj->loaded = false;
        $test->ses_obj->auth_state = false;
        $test->run_only();

        $data = $this->tracker->load();
        $this->assertArrayHasKey($ip_key, $data);
        $this->assertSame(1, $data[$ip_key]['count']);
        $this->assertArrayHasKey($user_key, $data);
        $this->assertSame(1, $data[$user_key]['count']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_track_records_failed_credentials_when_session_is_active() {
        $ip_key  = $this->tracker->make_key('ip', '');
        $user_key = $this->tracker->make_key('user', 'alice');

        $test        = $this->handlerTest('brute_force_track');
        $test->post  = ['username' => 'alice', 'password' => 'wrong'];
        $test->input = ['bf_ip' => '', 'bf_username' => 'alice'];
        $test->prep();
        $test->ses_obj->loaded = true;
        $test->ses_obj->auth_state = false;
        $test->run_only();

        $data = $this->tracker->load();
        $this->assertArrayHasKey($ip_key, $data);
        $this->assertSame(1, $data[$ip_key]['count']);
        $this->assertArrayHasKey($user_key, $data);
        $this->assertSame(1, $data[$user_key]['count']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_track_clears_on_success() {
        $ip_key   = $this->tracker->make_key('ip', '');
        $user_key = $this->tracker->make_key('user', 'alice');

        // Pre-seed some failures
        $data = [
            $ip_key   => ['count' => 3, 'locked_until' => 0, 'last_attempt' => time()],
            $user_key => ['count' => 3, 'locked_until' => 0, 'last_attempt' => time()],
        ];
        $this->tracker->save($data);

        $test        = $this->handlerTest('brute_force_track');
        $test->input = ['bf_ip' => '', 'bf_username' => 'alice'];
        // Session IS active (successful login) — default Hm_Mock_Session has loaded=true
        $test->prep();
        $test->run_only();

        $data = $this->tracker->load();
        $this->assertArrayNotHasKey($ip_key, $data);
        $this->assertArrayNotHasKey($user_key, $data);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_track_skips_when_already_blocked() {
        $test        = $this->handlerTest('brute_force_track');
        $test->input = ['bf_ip' => '', 'bf_username' => 'alice', 'bf_blocked' => true];
        $test->ses_obj = new Hm_Mock_Session();
        $test->ses_obj->loaded = false;
        $test->run();

        // Nothing should be written
        $data = $this->tracker->load();
        $this->assertSame([], $data);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_brute_force_track_skips_non_login_requests() {
        $test = $this->handlerTest('brute_force_track');
        // No bf_ip / bf_username in input (no login attempt)
        $test->run();

        $data = $this->tracker->load();
        $this->assertSame([], $data);
    }
}
