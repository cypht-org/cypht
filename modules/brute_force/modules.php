<?php

/**
 * Brute force login protection modules
 * @package modules
 * @subpackage brute_force
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Database-backed tracker for failed login attempts.
 * Stores attempt counts keyed by IP address and username (hashed).
 * @subpackage brute_force
 */
class Hm_Brute_Force_Tracker {

    /**
     * Database connection
     * @var PDO|false
     */
    private $dbh;

    /**
     * Create a tracker for the configured site database
     * @param object $config site config
     * @return void
     */
    public function __construct($config) {
        $this->dbh = Hm_DB::connect($config);
    }

    /**
     * Load attempt data from the database
     * @return array
     */
    public function load() {
        if (!$this->dbh) {
            return [];
        }
        $rows = Hm_DB::execute($this->dbh, 'select attempt_key, attempt_count, locked_until, last_attempt from hm_login_attempts', [], false, true);
        if (!is_array($rows)) {
            return [];
        }
        $data = [];
        foreach ($rows as $row) {
            $data[$row['attempt_key']] = [
                'count' => (int) $row['attempt_count'],
                'locked_until' => (int) $row['locked_until'],
                'last_attempt' => (int) $row['last_attempt'],
            ];
        }
        return $data;
    }

    /**
     * Persist attempt data to the database
     * @param array $data
     * @return void
     */
    public function save($data) {
        if (!$this->dbh) {
            return;
        }
        Hm_DB::execute($this->dbh, 'delete from hm_login_attempts', []);
        foreach ($data as $key => $entry) {
            Hm_DB::execute(
                $this->dbh,
                'insert into hm_login_attempts (attempt_key, attempt_count, locked_until, last_attempt) values (?, ?, ?, ?)',
                [$key, (int) $entry['count'], (int) $entry['locked_until'], (int) $entry['last_attempt']]
            );
        }
    }

    /**
     * Build an empty attempt entry
     * @return array
     */
    private function empty_entry() {
        return ['count' => 0, 'locked_until' => 0, 'last_attempt' => 0];
    }

    /**
     * Load one attempt entry from the database
     * @param string $key
     * @return array
     */
    private function load_entry($key) {
        $row = Hm_DB::execute(
            $this->dbh,
            'select attempt_count, locked_until, last_attempt from hm_login_attempts where attempt_key=?',
            [$key]
        );
        if (!is_array($row)) {
            return $this->empty_entry();
        }
        return [
            'count' => (int) $row['attempt_count'],
            'locked_until' => (int) $row['locked_until'],
            'last_attempt' => (int) $row['last_attempt'],
        ];
    }

    /**
     * Persist one attempt entry to the database
     * @param string $key
     * @param array $entry
     * @return bool
     */
    private function save_entry($key, $entry) {
        Hm_DB::execute($this->dbh, 'delete from hm_login_attempts where attempt_key=?', [$key]);
        return Hm_DB::execute(
            $this->dbh,
            'insert into hm_login_attempts (attempt_key, attempt_count, locked_until, last_attempt) values (?, ?, ?, ?)',
            [$key, (int) $entry['count'], (int) $entry['locked_until'], (int) $entry['last_attempt']]
        ) !== false;
    }

    /**
     * Delete old attempt entries from the database
     * @param int $lockout_duration seconds
     * @return int deleted rows
     */
    private function delete_expired($lockout_duration) {
        $now = time();
        $idle_cutoff = $now - (int) $lockout_duration;
        $deleted = Hm_DB::execute(
            $this->dbh,
            'delete from hm_login_attempts where locked_until < ? and last_attempt < ?',
            [$now, $idle_cutoff]
        );
        return $deleted === false ? 0 : (int) $deleted;
    }

    /**
     * Increment one failed attempt entry
     * @param array $entry
     * @param int $max_attempts
     * @param int $lockout_duration seconds
     * @param int $now current timestamp
     * @return array updated entry
     */
    private function increment_entry($entry, $max_attempts, $lockout_duration, $now) {
        if ($entry['locked_until'] > 0 && $entry['locked_until'] < $now) {
            $entry = $this->empty_entry();
        }
        $entry['count']++;
        $entry['last_attempt'] = $now;
        if ($entry['count'] >= $max_attempts) {
            $entry['locked_until'] = $now + $lockout_duration;
        }
        return $entry;
    }

    /**
     * Check whether an attempt entry is currently locked
     * @param array $entry
     * @param int $max_attempts
     * @param int $now current timestamp
     * @return bool
     */
    private function entry_is_locked($entry, $max_attempts, $now) {
        return $entry['count'] >= $max_attempts && $now < $entry['locked_until'];
    }

    /**
     * Build a storage key from an arbitrary string, hashed for privacy
     * @param string $prefix key namespace (e.g. 'ip' or 'user')
     * @param string $value raw value
     * @return string
     */
    public function make_key($prefix, $value) {
        return $prefix . ':' . hash('sha256', $value);
    }

    /**
     * Remove entries whose lockout has expired and have been idle long enough
     * @param int $lockout_duration seconds
     * @return int deleted rows
     */
    public function clean_expired($lockout_duration) {
        if (!$this->dbh) {
            return 0;
        }
        return $this->delete_expired($lockout_duration);
    }

    /**
     * Check whether a given key is currently locked out
     * @param string $key
     * @param int $max_attempts
     * @return bool
     */
    public function is_locked($key, $max_attempts) {
        return $this->lockout_seconds_remaining($key, $max_attempts) > 0;
    }

    /**
     * Get seconds remaining on a lockout (0 if not locked)
     * @param string $key
     * @param int $max_attempts
     * @return int
     */
    public function lockout_seconds_remaining($key, $max_attempts) {
        if (!$this->dbh) {
            return 0;
        }
        $now = time();
        $entry = $this->load_entry($key);
        if (!$this->entry_is_locked($entry, $max_attempts, $now)) {
            return 0;
        }
        return max(0, $entry['locked_until'] - $now);
    }

    /**
     * Record failed attempts for multiple keys and persist the updates
     * @param array $keys
     * @param int $max_attempts
     * @param int $lockout_duration seconds
     * @return array updated entries keyed by attempt key
     */
    public function record_failures($keys, $max_attempts, $lockout_duration) {
        if (!$this->dbh) {
            return [];
        }

        $this->delete_expired($lockout_duration);

        $now = time();
        $updated = [];
        foreach (array_values(array_unique($keys)) as $key) {
            $entry = $this->load_entry($key);
            $entry = $this->increment_entry($entry, $max_attempts, $lockout_duration, $now);
            $this->save_entry($key, $entry);
            $updated[$key] = $entry;
        }
        return $updated;
    }

    /**
     * Record a failed attempt for a key and persist the update
     * @param string $key
     * @param int $max_attempts
     * @param int $lockout_duration seconds
     * @return array updated entry
     */
    public function record_failure($key, $max_attempts, $lockout_duration) {
        $updated = $this->record_failures([$key], $max_attempts, $lockout_duration);
        if (isset($updated[$key])) {
            return $updated[$key];
        }
        return $this->empty_entry();
    }

    /**
     * Clear all attempt data for keys (called on successful login)
     * @param array $keys
     * @return bool
     */
    public function clear_many($keys) {
        if (!$this->dbh) {
            return false;
        }
        $success = true;
        foreach (array_values(array_unique($keys)) as $key) {
            if (Hm_DB::execute($this->dbh, 'delete from hm_login_attempts where attempt_key=?', [$key]) === false) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Clear all attempt data for a key (called on successful login)
     * @param string $key
     * @return bool
     */
    public function clear($key) {
        return $this->clear_many([$key]);
    }
}

/**
 * Check whether the current login attempt should be blocked due to too many
 * prior failures. Must run BEFORE the core login handler.
 * @subpackage brute_force/handler
 */
class Hm_Handler_brute_force_check extends Hm_Handler_Module {
    public function process() {
        // Only act on actual login POST submissions
        if (!array_key_exists('username', $this->request->post) ||
            !array_key_exists('password', $this->request->post)) {
            return;
        }

        $ip       = isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '';
        $username = trim((string) $this->request->post['username']);

        // Persist identifiers so brute_force_track can use them even after post is cleared
        $this->out('bf_ip', $ip, false);
        $this->out('bf_username', $username, false);

        $max_attempts    = (int) $this->config->get('brute_force_max_attempts', 5);
        $lockout_duration = (int) $this->config->get('brute_force_lockout_duration', 900);

        $tracker = new Hm_Brute_Force_Tracker($this->config);
        $ip_key   = $tracker->make_key('ip', $ip);
        $user_key = $tracker->make_key('user', $username);

        $ip_locked   = $tracker->is_locked($ip_key, $max_attempts);
        $user_locked = $tracker->is_locked($user_key, $max_attempts);

        if ($ip_locked || $user_locked) {
            $remaining = max(
                $tracker->lockout_seconds_remaining($ip_key, $max_attempts),
                $tracker->lockout_seconds_remaining($user_key, $max_attempts)
            );
            $minutes = (int) ceil($remaining / 60);
            // Wipe the POST so the login handler cannot authenticate
            $this->request->post = [];
            $this->out('bf_blocked', true, false);
            Hm_Msgs::add(
                sprintf('ERR: Too many failed login attempts. Please try again in %d minute(s).', max(1, $minutes)),
                'danger'
            );
            Hm_Debug::add(sprintf('Brute force: blocked login for IP %s / user %s', $ip, $username));
        }
    }
}

/**
 * Track the outcome of a login attempt and update the failure counters.
 * Must run AFTER the core login handler.
 * @subpackage brute_force/handler
 */
class Hm_Handler_brute_force_track extends Hm_Handler_Module {
    public function process() {
        $ip       = $this->get('bf_ip', null);
        $username = $this->get('bf_username', null);
        $password = null;

        if ($ip === null && array_key_exists('username', $this->request->post)) {
            $ip = isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '';
        }
        if ($username === null && array_key_exists('username', $this->request->post)) {
            $username = trim((string) $this->request->post['username']);
        }
        if (array_key_exists('password', $this->request->post)) {
            $password = (string) $this->request->post['password'];
        }

        // No login attempt happened (or it was already blocked before credentials were captured)
        if ($ip === null || $username === null) {
            return;
        }

        // Already blocked – do not record another failure
        if ($this->get('bf_blocked', false)) {
            return;
        }

        $max_attempts     = (int) $this->config->get('brute_force_max_attempts', 5);
        $lockout_duration = (int) $this->config->get('brute_force_lockout_duration', 900);

        $tracker = new Hm_Brute_Force_Tracker($this->config);
        $ip_key   = $tracker->make_key('ip', $ip);
        $user_key = $tracker->make_key('user', $username);

        $tracker->clean_expired($lockout_duration);

        $valid_credentials = $this->session->is_active();
        if ($password !== null && method_exists($this->session, 'auth')) {
            $valid_credentials = $this->session->auth($username, $password);
        }

        if ($valid_credentials) {
            // Successful login – reset counters for this IP and username
            $tracker->clear_many([$ip_key, $user_key]);
            Hm_Debug::add(sprintf('Brute force: cleared counters for IP %s / user %s on successful login', $ip, $username));
        } else {
            // Failed login – increment counters
            $updated = $tracker->record_failures(
                [$ip_key, $user_key],
                $max_attempts,
                $lockout_duration
            );
            $ip_count = isset($updated[$ip_key]['count']) ? $updated[$ip_key]['count'] : 0;
            $remaining = $max_attempts - $ip_count;
            if ($remaining > 0) {
                Hm_Debug::add(sprintf(
                    'Brute force: failed attempt recorded for IP %s (%d/%d)',
                    $ip, $ip_count, $max_attempts
                ));
            }
        }
    }
}
