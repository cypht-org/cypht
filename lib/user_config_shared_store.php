<?php

/**
 * Atomic storage for encrypted user configuration shared states.
 */
abstract class Hm_User_Config_Shared_Store {
    /**
     * Read a user's current shared-state record.
     * @param string $username account name
     * @return array|null A revision and encrypted payload, or null if absent.
     * @throws Exception if the username is invalid or the stored record cannot be read or validated
     */
    abstract public function read($username);

    /**
     * Insert when $revision is null, otherwise replace that exact revision.
     * @param string $username account name
     * @param int|null $revision expected current revision, or null for insert
     * @param string $payload encrypted record payload
     * @return bool False when another writer changed the record.
     * @throws Exception if arguments are invalid, storage fails, or the record cannot be encoded
     */
    abstract public function compare_and_swap($username, $revision, $payload);

    /**
     * Run an operation exclusively against an existing user's shared state.
     * @param string $username account name
     * @param callable $operation operation to run while synchronized
     * @return mixed the operation's result
     * @throws Throwable if synchronization fails or the operation throws
     */
    abstract public function synchronized($username, callable $operation);

    /**
     * Hash an account name for use as a storage key.
     * @param string $username account name
     * @return string hexadecimal SHA-256 key
     * @throws InvalidArgumentException if the username is empty or not a string
     */
    protected function user_key($username) {
        if (!is_string($username) || $username === '') {
            throw new InvalidArgumentException('A username is required');
        }
        return hash('sha256', $username);
    }

    /**
     * Validate an encrypted payload before storing it.
     * @param string $payload encrypted record payload
     * @return void
     * @throws InvalidArgumentException if the payload is empty or not a string
     */
    protected function check_payload($payload) {
        if (!is_string($payload) || $payload === '') {
            throw new InvalidArgumentException('A nonempty encrypted payload is required');
        }
    }

    /**
     * Validate a compare-and-swap revision.
     * @param int|null $revision expected current revision
     * @return void
     * @throws InvalidArgumentException if the revision is not null or a positive integer
     */
    protected function check_revision($revision) {
        if ($revision !== null && (!is_int($revision) || $revision < 1)) {
            throw new InvalidArgumentException('The revision must be a positive integer or null');
        }
    }

    /**
     * Decode the Base64 payload returned by a storage backend.
     * @param string $payload encoded payload
     * @return string decoded payload
     * @throws RuntimeException if the payload is not valid nonempty Base64
     */
    protected function decode_payload($payload) {
        $decoded = is_string($payload) ? base64_decode($payload, true) : false;
        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('Invalid shared-state payload');
        }
        return $decoded;
    }
}

class Hm_User_Config_Shared_File_Store extends Hm_User_Config_Shared_Store {
    private $directory;
    private $held_locks = [];

    /**
     * Create a filesystem-backed shared-state store.
     * @param string $directory writable user-settings directory
     * @return void
     * @throws RuntimeException if the directory is missing or not writable
     */
    public function __construct($directory) {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('The user settings directory is not writable');
        }
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path for an account's shared-state record.
     * @param string $username account name
     * @return string record path
     */
    private function path($username) {
        return $this->directory . DIRECTORY_SEPARATOR . '.shared-state-' . $this->user_key($username);
    }

    /**
     * Run a callback while holding the account's exclusive file lock.
     * @param string $username account name
     * @param callable $operation operation receiving the record path
     * @return mixed the operation's result
     * @throws Throwable if the operation throws
     */
    private function with_lock($username, $operation) {
        $path = $this->path($username);
        if (isset($this->held_locks[$path])) {
            return $operation($path);
        }
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Unable to open the shared-state lock');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the shared state');
            }
            $this->held_locks[$path] = true;
            return $operation($path);
        } finally {
            unset($this->held_locks[$path]);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Run an operation exclusively against an account's shared state.
     * @param string $username account name
     * @param callable $operation operation to run while synchronized
     * @return mixed the operation's result
     * @throws Throwable if the operation throws
     */
    public function synchronized($username, callable $operation) {
        return $this->with_lock($username, function () use ($operation) {
            return $operation();
        });
    }

    /**
     * Read and validate a shared-state file.
     * @param string $path record path
     * @return array|null revision and encrypted payload, or null if absent
     * @throws RuntimeException if the file cannot be read or its record is invalid
     */
    private function read_path($path) {
        if (!file_exists($path)) {
            return null;
        }
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Unable to read the shared state');
        }
        $record = json_decode($data, true);
        if (!is_array($record) || !isset($record['revision'], $record['payload']) ||
            !is_int($record['revision']) || $record['revision'] < 1 ||
            !is_string($record['payload']) || $record['payload'] === '') {
            throw new RuntimeException('Invalid shared-state record');
        }
        return ['revision' => $record['revision'], 'payload' => $this->decode_payload($record['payload'])];
    }

    /**
     * Read a user's current shared-state record.
     * @param string $username account name
     * @return array|null revision and encrypted payload, or null if absent
     * @throws Exception if the username is invalid or locking, reading, or validation fails
     */
    public function read($username) {
        return $this->with_lock($username, function ($path) {
            return $this->read_path($path);
        });
    }

    /**
     * Insert or replace a shared-state record if its revision matches.
     * @param string $username account name
     * @param int|null $revision expected current revision, or null for insert
     * @param string $payload encrypted record payload
     * @return bool whether the record was written
     * @throws Throwable if validation, locking, encoding, or file operations fail
     */
    public function compare_and_swap($username, $revision, $payload) {
        $this->check_payload($payload);
        $this->check_revision($revision);
        return $this->with_lock($username, function ($path) use ($revision, $payload) {
            $current = $this->read_path($path);
            $current_revision = $current ? $current['revision'] : null;
            if ($current_revision !== $revision) {
                return false;
            }

            $record = json_encode(['revision' => ($revision ?? 0) + 1, 'payload' => base64_encode($payload)], JSON_THROW_ON_ERROR);
            $temporary = $path . '.' . bin2hex(random_bytes(16)) . '.tmp';
            $handle = fopen($temporary, 'x');
            if ($handle === false) {
                throw new RuntimeException('Unable to create the shared-state temporary file');
            }

            try {
                if (!chmod($temporary, 0600) || fwrite($handle, $record) !== strlen($record) || !fflush($handle)) {
                    throw new RuntimeException('Unable to write the shared state');
                }

                if (!fsync($handle)) {
                    throw new RuntimeException('Unable to sync the shared state');
                }

                fclose($handle);
                $handle = null;
                
                if (!rename($temporary, $path)) {
                    throw new RuntimeException('Unable to replace the shared state');
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                if (file_exists($temporary) && !unlink($temporary)) {
                    Hm_Debug::add('Unable to remove a shared-state temporary file', 'warning');
                }
            }
            return true;
        });
    }
}

class Hm_User_Config_Shared_DB_Store extends Hm_User_Config_Shared_Store {
    private $dbh;

    /**
     * Create a database-backed shared-state store.
     * @param PDO $dbh database connection
     * @return void
     */
    public function __construct(PDO $dbh) {
        $this->dbh = $dbh;
    }

    /**
     * Run an operation under a database transaction and user-row lock.
     * @param string $username account name
     * @param callable $operation operation to run while synchronized
     * @return mixed the operation's result
     * @throws Throwable if the operation throws
     */
    public function synchronized($username, callable $operation) {
        if ($this->dbh->inTransaction()) {
            throw new RuntimeException('Cannot synchronize a shared state inside an existing transaction');
        }
        $this->dbh->beginTransaction();
        try {
            // Acquire a write lock without changing the revision.
            $stmt = $this->dbh->prepare('update hm_user_shared_state set revision = revision where user_key = ?');
            $stmt->execute([$this->user_key($username)]);
            $result = $operation();
            $this->dbh->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Read a user's current shared-state record.
     * @param string $username account name
     * @return array|null revision and encrypted payload, or null if absent
     * @throws Exception if the username or stored record is invalid, or a database operation fails
     */
    public function read($username) {
        $stmt = $this->dbh->prepare('select revision, payload from hm_user_shared_state where user_key = ?');
        $stmt->execute([$this->user_key($username)]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record === false) {
            return null;
        }

        // Some PDO drivers return binary columns as streams instead of strings.
        if (is_resource($record['payload'])) {
            $record['payload'] = stream_get_contents($record['payload']);
        }

        if (!is_string($record['payload']) || $record['payload'] === '' || (int) $record['revision'] < 1) {
            throw new RuntimeException('Invalid shared-state record');
        }
        
        return ['revision' => (int) $record['revision'], 'payload' => $this->decode_payload($record['payload'])];
    }

    /**
     * Insert or replace a shared-state record if its revision matches.
     * @param string $username account name
     * @param int|null $revision expected current revision, or null for insert
     * @param string $payload encrypted record payload
     * @return bool whether the record was written
     * @throws Exception if arguments are invalid, a database operation fails, or the driver is unsupported
     */
    public function compare_and_swap($username, $revision, $payload) {
        $this->check_payload($payload);
        $this->check_revision($revision);
        $key = $this->user_key($username);
        $payload = base64_encode($payload);
        if ($revision !== null) {
            $stmt = $this->dbh->prepare('update hm_user_shared_state set revision = revision + 1, payload = ? where user_key = ? and revision = ?');
            $stmt->execute([$payload, $key, $revision]);
            return $stmt->rowCount() === 1;
        }
        $driver = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $sql = 'insert into hm_user_shared_state (user_key, revision, payload) values (?, 1, ?) on duplicate key update user_key = user_key';
        } elseif ($driver === 'pgsql' || $driver === 'sqlite') {
            $sql = 'insert into hm_user_shared_state (user_key, revision, payload) values (?, 1, ?) on conflict (user_key) do nothing';
        } else {
            throw new RuntimeException('Unsupported shared-state database driver');
        }
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([$key, $payload]);
        return $stmt->rowCount() === 1;
    }
}
