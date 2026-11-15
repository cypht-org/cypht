<?php

class Hm_Shared_State_Reauthentication_Required extends RuntimeException {}

/**
 * Encrypted, revisioned user state shared by authenticated sessions.
 */
class Hm_User_Config_Shared_State {
    private $store;
    private $username;
    private $key;
    private $generation;
    private $baseline;

    /**
     * Create a shared-state manager for one account.
     * @param Hm_User_Config_Shared_Store $store backing store
     * @param string $username account name
     * @return void
     */
    public function __construct(Hm_User_Config_Shared_Store $store, $username) {
        $this->store = $store;
        $this->username = $username;
    }

    /**
     * Derive a password-based key for wrapping the shared-state key.
     * @param string $password account password
     * @param string $salt random derivation salt
     * @return string binary derived key
     */
    private static function derive_key($password, $salt) {
        return hash_pbkdf2('sha256', $password, $salt, 600000, 32, true);
    }

    /**
     * Encrypt the shared-state key using a key derived from the password.
     * @param string $key binary shared-state key
     * @param string $password account password
     * @return array salt and encrypted key
     * @throws Exception if secure random salt generation fails
     */
    private static function wrap_key($key, $password) {
        $salt = random_bytes(16);
        return [
            'salt' => base64_encode($salt),
            'wrapped' => Hm_Crypt::ciphertext($key, self::derive_key($password, $salt)),
        ];
    }

    /**
     * Decrypt the shared-state key from a stored record.
     * @param array $record encrypted shared-state record
     * @param string $password account password
     * @return string|false binary shared-state key, or false if decryption fails
     */
    private static function unwrap_key($record, $password) {
        $salt = base64_decode($record['salt'], true);
        if (! $salt) {
            return false;
        }
        return Hm_Crypt::plaintext($record['wrapped'], self::derive_key($password, $salt));
    }

    /**
     * Decode and validate the outer encrypted shared-state record.
     * @param string $payload serialized record payload
     * @return array validated record
     * @throws RuntimeException if the payload is malformed or has an unsupported structure
     */
    private static function decode_record($payload) {
        $record = json_decode($payload, true);
        if (!is_array($record) || !is_string($record['generation'] ?? null) ||
            !is_string($record['salt'] ?? null) ||
            !is_string($record['wrapped'] ?? null) ||
            !is_string($record['data'] ?? null)) {
            throw new RuntimeException('Invalid encrypted shared state');
        }
        return $record;
    }

    /**
     * Serialize and encrypt shared-state data.
     * @param array $data shared-state settings and dirty descriptions
     * @param string $key binary shared-state key
     * @return string encrypted data
     * @throws RuntimeException if the data cannot be encoded
     */
    private static function encode_data($data, $key) {
        $encoded = Hm_Transform::stringify($data);
        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode shared-state data');
        }
        return Hm_Crypt::ciphertext($encoded, $key);
    }

    /**
     * Decrypt and validate shared-state data.
     * @param array $record encrypted shared-state record
     * @param string $key binary shared-state key
     * @return array settings and dirty descriptions
     * @throws RuntimeException if the data cannot be decrypted or validated
     */
    private static function decode_data($record, $key) {
        $data = Hm_Transform::unstringify(Hm_Crypt::plaintext($record['data'], $key));
        if (!is_array($data) || !is_array($data['config'] ?? null) ||
            !is_array($data['changed_settings'] ?? null)) {
            throw new RuntimeException('Unable to decrypt the shared state');
        }
        return $data;
    }

    /**
     * Serialize an encrypted shared-state record.
     * @param array $record shared-state record
     * @return string serialized record
     * @throws JsonException if the record cannot be encoded
     */
    private static function encode_record($record) {
        return json_encode($record, JSON_THROW_ON_ERROR);
    }

    /**
     * Open existing shared state, or replace inaccessible state with loaded permanent settings.
     * @param string $password account password
     * @param array $permanent successfully loaded permanent settings
     * @return array settings and dirty descriptions
     * @throws Exception if shared state cannot be read, initialized, or serialized
     */
    public function open($password, array $permanent) {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $stored = $this->store->read($this->username);
            $record = self::decode_record($stored['payload']);
            if ($record) {
                $key = self::unwrap_key($record, $password);
                if ($key) {
                    $data = self::decode_data($record, $key);
                    $this->key = $key;
                    $this->generation = $record['generation'];
                    $this->baseline = $data;
                    return $data;
                }
            }

            $key = random_bytes(32);
            $data = ['config' => $permanent, 'changed_settings' => []];
            $record = array_merge([
                'generation' => bin2hex(random_bytes(16)),
                'data' => self::encode_data($data, $key),
            ], self::wrap_key($key, $password));
            
            if ($this->store->compare_and_swap($this->username, $stored['revision'] ?? null, self::encode_record($record))) {
                $this->key = $key;
                $this->generation = $record['generation'];
                $this->baseline = $data;
                return $data;
            }
        }
        throw new RuntimeException('Shared-state initialization conflicted repeatedly');
    }

    /**
     * Return credentials needed to resume this shared-state generation.
     * @return array Base64 shared-state key and generation identifier
     * @throws LogicException if shared state has not been opened
     */
    public function credentials() {
        if (!is_string($this->key) || !is_string($this->generation)) {
            throw new LogicException('Shared state has not been opened');
        }
        return ['key' => base64_encode($this->key), 'generation' => $this->generation];
    }

    /**
     * Set the caller's local baseline after a save that preserves local values.
     * @param array $data local settings and dirty descriptions
     * @return void
     * @throws LogicException if shared state has not been opened
     */
    public function rebase_local(array $data) {
        if ($this->baseline === null) {
            throw new LogicException('Shared state has not been opened');
        }
        $this->baseline = $data;
    }

    /**
     * Resume a shared state using credentials stored in an authenticated session.
     * @param array $credentials shared-state key and generation credentials
     * @return array settings and dirty descriptions
     * @throws Exception if credentials or the stored state are invalid or belong to another generation
     */
    public function resume(array $credentials) {
        $key = base64_decode($credentials['key'] ?? '', true);
        $generation = $credentials['generation'] ?? null;
        if (!is_string($key) || strlen($key) !== 32 || !is_string($generation)) {
            throw new RuntimeException('Invalid shared-state session credentials');
        }
        $stored = $this->store->read($this->username);
        if ($stored === null) {
            throw new RuntimeException('The shared state is missing');
        }
        $record = self::decode_record($stored['payload']);
        if (!hash_equals($record['generation'], $generation)) {
            throw new Hm_Shared_State_Reauthentication_Required('The shared state was replaced; sign in again');
        }
        $this->key = $key;
        $this->generation = $generation;
        $this->baseline = self::decode_data($record, $key);
        return $this->baseline;
    }

    /**
     * Merge local edits against a baseline and the latest shared settings.
     * @param mixed $base prior local value
     * @param mixed $local current local value
     * @param mixed $latest latest shared value
     * @return mixed merged value
     */
    private static function merge($base, $local, $latest) {
        if ($local === $base) {
            return $latest;
        }
        if (is_array($base) && is_array($local) && is_array($latest) &&
            ($base === [] || !array_is_list($base)) &&
            ($local === [] || !array_is_list($local)) &&
            ($latest === [] || !array_is_list($latest))) {
            $result = $latest;
            foreach (array_unique(array_merge(array_keys($base), array_keys($local))) as $key) {
                $in_base = array_key_exists($key, $base);
                $in_local = array_key_exists($key, $local);
                $in_latest = array_key_exists($key, $latest);
                if ($in_base && $in_local && $local[$key] === $base[$key]) {
                    continue;
                }
                if (!$in_local) {
                    unset($result[$key]);
                } elseif (!$in_base) {
                    $result[$key] = $in_latest && is_array($local[$key]) && is_array($latest[$key])
                        ? self::merge([], $local[$key], $latest[$key])
                        : $local[$key];
                } elseif (!$in_latest) {
                    $result[$key] = $local[$key];
                } else {
                    $result[$key] = self::merge($base[$key], $local[$key], $latest[$key]);
                }
            }
            return $result;
        }
        return $local;
    }

    /**
     * Merge dirty-setting descriptions without dropping concurrent additions.
     * @param array $base prior descriptions
     * @param array $local current local descriptions
     * @param array $latest latest shared descriptions
     * @return array merged descriptions
     */
    private static function merge_changes($base, $local, $latest) {
        if ($local === $base) {
            return $latest;
        }
        if (array_slice($local, 0, count($base)) === $base) {
            return array_merge($latest, array_slice($local, count($base)));
        }
        if ($local === [] && array_slice($latest, 0, count($base)) === $base) {
            return array_slice($latest, count($base));
        }
        return $local;
    }

    /**
     * Merge local data with the latest shared state and persist it.
     * @param array $data local settings and dirty descriptions
     * @return array resulting shared settings and dirty descriptions
     * @throws Exception if shared state is unopened, missing, invalid, or repeatedly conflicts
     */
    public function update(array $data) {
        if ($this->baseline === null) {
            throw new LogicException('Shared state has not been opened');
        }
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $stored = $this->store->read($this->username);
            if ($stored === null) {
                throw new RuntimeException('The shared state is missing');
            }
            $record = self::decode_record($stored['payload']);
            if (!hash_equals($record['generation'], $this->generation)) {
                throw new Hm_Shared_State_Reauthentication_Required('The shared state was replaced; sign in again');
            }
            $latest = self::decode_data($record, $this->key);
            $merged = [
                'config' => self::merge($this->baseline['config'], $data['config'], $latest['config']),
                'changed_settings' => self::merge_changes(
                    $this->baseline['changed_settings'],
                    $data['changed_settings'],
                    $latest['changed_settings']
                ),
            ];
            if ($merged === $latest) {
                $this->baseline = $data;
                return $latest;
            }
            $record['data'] = self::encode_data($merged, $this->key);
            if ($this->store->compare_and_swap($this->username, $stored['revision'], self::encode_record($record))) {
                $this->baseline = $data;
                return $merged;
            }
        }
        throw new RuntimeException('Shared-state update conflicted repeatedly');
    }

    /**
     * Re-encrypt the shared-state key using a new account password.
     * @param string $password new account password
     * @return void
     * @throws Exception if shared state is missing, invalid, or repeatedly conflicts
     */
    public function change_password($password) {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $stored = $this->store->read($this->username);
            if ($stored === null) {
                throw new RuntimeException('The shared state is missing');
            }
            $record = self::decode_record($stored['payload']);
            if (!hash_equals($record['generation'], $this->generation)) {
                throw new Hm_Shared_State_Reauthentication_Required('The shared state was replaced; sign in again');
            }
            $record = array_merge($record, self::wrap_key($this->key, $password));
            if ($this->store->compare_and_swap($this->username, $stored['revision'], self::encode_record($record))) {
                return;
            }
        }
        throw new RuntimeException('Shared-state password change conflicted repeatedly');
    }

    /**
     * Save the latest merged snapshot and clear only the changes included in it.
     * @param array $data local settings and dirty descriptions
     * @param callable $save_permanently callback that persists the merged settings
     * @return array resulting settings and dirty descriptions
     * @throws Throwable if synchronization or permanent saving fails
     */
    public function save(array $data, callable $save_permanently) {
        $baseline = $this->baseline;
        try {
            return $this->store->synchronized($this->username, function () use ($data, $save_permanently) {
                $snapshot = $this->update($data);
                $this->baseline = $snapshot;
                $save_permanently($snapshot['config']);
                $snapshot['changed_settings'] = [];
                return $this->update($snapshot);
            });
        } catch (Throwable $exception) {
            $this->baseline = $baseline;
            throw $exception;
        }
    }
}
