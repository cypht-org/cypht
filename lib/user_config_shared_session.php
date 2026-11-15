<?php

/**
 * Bridges shared user state and a request's user configuration.
 */
class Hm_User_Config_Shared_Session {
    private $state;
    private $session;
    private $config;
    private $username;

    /**
     * Connect a shared state to a session and its user configuration.
     * @param Hm_User_Config_Shared_State $state shared-state manager
     * @param Hm_Session $session authenticated session
     * @param Hm_Config $config user configuration
     * @param string $username account name
     * @return void
     */
    public function __construct(Hm_User_Config_Shared_State $state, $session, $config, $username) {
        $this->state = $state;
        $this->session = $session;
        $this->config = $config;
        $this->username = $username;
    }

    /**
     * Create the configured file- or database-backed shared-state store.
     * @param Hm_Site_Config_File $site_config site configuration
     * @return Hm_User_Config_Shared_Store configured store
     * @throws Exception if the configured store cannot be created
     */
    public static function create_store($site_config) {
        $type = $site_config->get('user_config_type', 'file');
        if ($type === 'file') {
            return new Hm_User_Config_Shared_File_Store($site_config->get('user_settings_dir', false));
        }
        if ($type === 'DB') {
            $dbh = Hm_DB::connect($site_config);
            if (!$dbh) {
                throw new RuntimeException('Unable to connect to the shared-state database');
            }
            return new Hm_User_Config_Shared_DB_Store($dbh);
        }
        throw new RuntimeException('The configured user settings backend does not support shared state');
    }

    /**
     * Determine whether the configured backends support shared state.
     * @param Hm_Site_Config_File $site_config site configuration
     * @return bool whether shared state is supported
     */
    public static function supports($site_config) {
        return in_array($site_config->get('user_config_type', 'file'), ['file', 'DB'], true) &&
            $site_config->get('session_type', 'PHP') !== 'custom';
    }

    /**
     * Open or initialize shared user state using the account password.
     * @param string $password account password
     * @return void
     * @throws Exception if permanent settings cannot be decrypted or shared state cannot be opened
     */
    public function login($password) {
        if ($this->config->decrypt_failed) {
            throw new RuntimeException('Permanent settings could not be decrypted');
        }
        $data = $this->state->open($password, $this->config->dump());
        $this->session->set('shared_state_credentials', $this->state->credentials());
        $this->apply($data);
        $this->config->attach_shared_session($this);
    }

    /**
     * Resume shared user state using credentials in the current session.
     * @return void
     * @throws Exception if the session needs to sign in again or shared state is missing or invalid
     */
    public function resume() {
        $credentials = $this->session->get('shared_state_credentials');
        if (!is_array($credentials)) {
            throw new Hm_Shared_State_Reauthentication_Required('Sign in again to enable shared settings');
        }
        $this->apply($this->state->resume($credentials));
        $this->config->attach_shared_session($this);
    }

    /**
     * Apply shared-state settings and dirty descriptions to the request.
     * @param array $data shared-state settings and dirty descriptions
     * @return void
     */
    private function apply(array $data) {
        $this->config->reload($data['config'], $this->username);
        $this->session->set('user_data', $data['config']);
        $this->session->set('changed_settings', $data['changed_settings']);
    }

    /**
     * Capture the request's current settings and dirty descriptions.
     * @return array local settings and dirty descriptions
     */
    private function snapshot() {
        return [
            'config' => $this->config->dump(),
            'changed_settings' => $this->session->get('changed_settings', []),
        ];
    }

    /**
     * Flush the request's current settings to the shared state.
     * @return void
     * @throws Exception if the shared state is unopened, invalid, or cannot be updated
     */
    public function flush() {
        $this->state->update($this->snapshot());
    }

    /**
     * Update a setting through the shared configuration bridge.
     * @param string $name setting name
     * @param mixed $value setting value
     * @return void
     * @throws Throwable if updating the shared state fails
     */
    public function set_user_value($name, $value) {
        $this->config->set($name, $value);
    }

    /**
     * Save shared state to permanent settings.
     * @param string|false $password account password, or false for unencrypted config
     * @param bool $preserve_local keep this request's values after saving
     * @return void
     * @throws Throwable if synchronization, storage, or permanent saving fails
     */
    public function save($password, $preserve_local = false) {
        $local = $this->snapshot();
        try {
            $data = $this->state->save($local, function ($config) use ($password) {
                $this->config->reload($config, $this->username);
                if ($this->config->save($this->username, $password) === false) {
                    throw new RuntimeException('Unable to save permanent settings');
                }
            });
        } catch (Throwable $exception) {
            $this->config->reload($local['config'], $this->username);
            throw $exception;
        }
        if ($preserve_local) {
            $local['changed_settings'] = [];
            $this->state->rebase_local($local);
            $this->apply($local);
        } else {
            $this->apply($data);
        }
    }

    /**
     * Rewrap the shared-state key with a new account password.
     * @param string $password new account password
     * @return void
     * @throws Exception if rewrapping the key fails or the generation was replaced
     */
    public function change_password($password) {
        $this->state->change_password($password);
    }

    /**
     * Detach this bridge from the user configuration object.
     * @return void
     */
    public function detach() {
        $this->config->attach_shared_session(null);
    }
}
