<?php

/**
 * Handler and output modules for the "API and MCP" settings page
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/mcp/hm-mcp.php';

/**
 * Process the settings page forms and load the data it shows
 * @subpackage mcp/handler
 */
class Hm_Handler_mcp_settings_page extends Hm_Handler_Module {

    /* allowed personal token lifetimes in days, 0 is no expiration */
    const TOKEN_DAYS = [0, 7, 30, 90, 365];

    /* failed password confirmations allowed per user in the window */
    const PASSWORD_ATTEMPTS = 10;
    const PASSWORD_WINDOW = 900;

    public function process() {
        $username = $this->session->get('username', false);
        $mcp_config = new Hm_MCP_Config($this->config);
        $store = new Hm_MCP_Store($this->config);
        $available = $username && $store->available();
        $accounts = Hm_MCP_Permissions::account_list($this->user_config->get('imap_servers', []));

        $this->out('page_title', 'API and MCP');
        $this->out('mcp_available', $available);
        $this->out('mcp_configured', $mcp_config->is_configured());
        $this->out('mcp_public_url', $mcp_config->public_url());
        $this->out('mcp_accounts', $accounts);
        if (!$available) {
            return;
        }

        $action = $this->request->post['mcp_action'] ?? '';
        if (is_string($action) && $action !== '') {
            $this->handle_action($action, $store, $username, array_column($accounts, 'id'));
        }

        $connections = [];
        foreach ($store->connections($username) as $connection) {
            unset($connection['sealed_password'], $connection['sealed_cache']);
            $kind = $connection['kind'] === 'oauth' ? 'refresh' : $connection['kind'];
            $connection['expires_at'] = $store->token_expiry($connection['id'], $kind);
            $connections[] = $connection;
        }
        $this->out('mcp_settings', $store->settings($username));
        $this->out('mcp_connections', $connections);
    }

    /**
     * @param string $action submitted action
     * @param Hm_MCP_Store $store storage
     * @param string $username current user
     * @param array $account_ids configured account ids
     * @return void
     */
    private function handle_action($action, $store, $username, $account_ids) {
        $settings = $store->settings($username, true);
        switch ($action) {
            case 'save_settings':
                $this->save_settings($store, $username, $settings, $account_ids);
                break;
            case 'create_token':
                $this->create_token($store, $username, $settings, $account_ids);
                break;
            case 'update_connection':
                $this->update_connection($store, $username, $settings, $account_ids);
                break;
            case 'revoke_connection':
                $connection_id = (string) ($this->request->post['mcp_connection_id'] ?? '');
                if ($store->delete_connection($connection_id, $username)) {
                    Hm_Msgs::add('Connection revoked');
                } else {
                    Hm_Msgs::add('Connection not found', 'warning');
                }
                break;
        }
    }

    /**
     * Save the global switch, permissions and accounts
     * @return void
     */
    private function save_settings($store, $username, $settings, $account_ids) {
        $post = $this->request->post;
        $mode = ($post['mcp_account_mode'] ?? 'all') === 'selected' ? 'selected' : 'all';
        $ids = self::selected_accounts($post['mcp_account_ids'] ?? [], $account_ids);
        if ($mode === 'selected' && !$ids) {
            Hm_Msgs::add('Select at least one account', 'warning');
            return;
        }
        $saved = $store->save_settings($username, [
            'enabled' => !empty($post['mcp_enabled']),
            'permissions' => Hm_MCP_Permissions::from_keys($post['mcp_permissions'] ?? []),
            'accounts' => ['mode' => $mode, 'ids' => $mode === 'selected' ? $ids : []],
        ]);
        if ($saved) {
            Hm_Msgs::add('API and MCP settings saved');
        } else {
            Hm_Msgs::add('Could not save the settings', 'danger');
        }
    }

    /**
     * Create a personal access token after confirming the password
     * @return void
     */
    private function create_token($store, $username, $settings, $account_ids) {
        $post = $this->request->post;
        $password = (string) ($post['mcp_password'] ?? '');
        if ($password === '') {
            Hm_Msgs::add('Your password is required', 'warning');
            return;
        }
        list($permissions, $accounts, $error) = $this->connection_limits($settings, $account_ids);
        if ($error) {
            Hm_Msgs::add($error, 'warning');
            return;
        }
        $rate_key = 'password:'.$username;
        if (!$store->rate_limit($rate_key, self::PASSWORD_ATTEMPTS, self::PASSWORD_WINDOW)) {
            Hm_Msgs::add('Too many attempts. Try again later.', 'warning');
            return;
        }
        if (!$this->session->auth($username, $password)) {
            Hm_Msgs::add('Incorrect password', 'warning');
            return;
        }
        $store->rate_reset($rate_key);
        $name = self::clean_name($post['mcp_name'] ?? '', 'Personal access token');
        $days = (int) ($post['mcp_expires'] ?? 90);
        if (!in_array($days, self::TOKEN_DAYS, true)) {
            $days = 90;
        }
        try {
            list($id, $key) = $store->create_connection($username, 'personal', $name, $password,
                ['permissions' => $permissions, 'accounts' => $accounts]);
            $token = $store->issue_token($id, 'personal', $key, $days * 86400);
        } catch (Exception $e) {
            Hm_Msgs::add('Could not create the token', 'danger');
            return;
        }
        $this->out('mcp_new_token', ['name' => $name, 'token' => $token]);
        /* show the token in this response instead of redirecting, so it is never stored */
        $this->out('no_redirect', true);
        Hm_Msgs::add('Token created');
    }

    /**
     * Rename a connection or change its permissions and accounts
     * @return void
     */
    private function update_connection($store, $username, $settings, $account_ids) {
        $post = $this->request->post;
        $connection = $store->connection((string) ($post['mcp_connection_id'] ?? ''));
        if (!$connection || $connection['username'] !== $username || $connection['status'] === 'pending') {
            Hm_Msgs::add('Connection not found', 'warning');
            return;
        }
        list($permissions, $accounts, $error) = $this->connection_limits($settings, $account_ids);
        if ($error) {
            Hm_Msgs::add($error, 'warning');
            return;
        }
        $store->update_connection($connection['id'], [
            'name' => self::clean_name($post['mcp_name'] ?? '', $connection['name']),
            'permissions' => $permissions,
            'accounts' => $accounts,
        ]);
        Hm_Msgs::add('Connection updated');
    }

    /**
     * Read the permission and account restriction of a connection form
     * @param array $settings global settings
     * @param array $account_ids configured account ids
     * @return array [permissions or null to inherit, accounts or null to inherit, error message]
     */
    private function connection_limits($settings, $account_ids) {
        $post = $this->request->post;
        $permissions = null;
        if (($post['mcp_perm_mode'] ?? 'inherit') === 'custom') {
            /* a connection can never exceed the global permissions */
            $permissions = Hm_MCP_Permissions::from_keys($post['mcp_permissions'] ?? [], $settings['permissions']);
        }
        $accounts = null;
        if (($post['mcp_account_mode'] ?? 'inherit') === 'selected') {
            $allowed = Hm_MCP_Permissions::effective_accounts($settings['accounts'], null, $account_ids);
            $accounts = self::selected_accounts($post['mcp_account_ids'] ?? [], $allowed);
            if (!$accounts) {
                return [null, null, 'Select at least one account'];
            }
        }
        return [$permissions, $accounts, false];
    }

    /**
     * @param mixed $values submitted account ids
     * @param array $account_ids valid account ids
     * @return array submitted ids that are valid
     */
    private static function selected_accounts($values, $account_ids) {
        $values = is_array($values) ? array_map('strval', $values) : [];
        return array_values(array_intersect(array_map('strval', $account_ids), $values));
    }

    /**
     * @param mixed $name submitted name
     * @param string $default name used when empty
     * @return string
     */
    private static function clean_name($name, $default) {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $name));
        $name = mb_substr($name, 0, 100);
        return $name === '' ? $default : $name;
    }
}

/**
 * Link to the settings page in the settings menu
 * @subpackage mcp/output
 */
class Hm_Output_mcp_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_mcp"><a class="unread_link" href="'.$this->build_page_url('mcp').'">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-plug-fill menu-icon"></i>';
        }
        $res .= $this->trans('API and MCP').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Content of the "API and MCP" settings page
 * @subpackage mcp/output
 */
class Hm_Output_mcp_settings_content extends Hm_Output_Module {

    /* permissions that need a warning about Cypht settings saved by the web UI */
    const SETTINGS_BACKED = ['tags', 'contacts_write'];

    /* permissions that can delete data */
    const DESTRUCTIVE = ['delete_permanent'];

    protected function output() {
        $res = '<div class="mcp_settings_page px-0">'.
            '<div class="content_title px-3"><i class="bi bi-plug-fill me-2"></i>'.$this->trans('API and MCP').'</div>'.
            '<p class="px-3 pt-3 mb-0 text-secondary">'.
            $this->trans('Connect AI assistants and scripts to your email with the Model Context Protocol (MCP) or the REST API.').'</p>';
        if (!$this->get('mcp_available')) {
            return $res.'<div class="alert alert-warning m-3">'.
                $this->trans('API and MCP access is not available because its database tables are missing. The site administrator must run the database setup.').
                '</div></div>';
        }
        if (!$this->get('mcp_configured')) {
            $res .= '<div class="alert alert-warning mx-3 mt-3 mb-0">'.
                $this->trans('The public URL is not configured. The site administrator must set MCP_PUBLIC_URL before clients can connect.').
                '</div>';
        }
        $settings = $this->get('mcp_settings', Hm_MCP_Store::default_settings());
        $res .= $this->new_token_box();
        $res .= $this->settings_form($settings);
        $res .= $this->connections($settings);
        $res .= $this->token_form($settings);
        $res .= $this->connect_help();
        return $res.'</div>';
    }

    /**
     * @return string hidden form fields shared by every form
     */
    private function form_start($action, $class = '') {
        return '<form method="post" action="'.$this->build_page_url('mcp').'" class="'.$this->html_safe($class).'" autocomplete="off">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<input type="hidden" name="mcp_action" value="'.$this->html_safe($action).'" />';
    }

    /**
     * @param string $title section title
     * @param string $icon bootstrap icon name
     * @return string
     */
    private function section($title, $icon) {
        return '<div class="settings_subtitle p-3 border-bottom mt-4"><i class="bi bi-'.$icon.' me-2"></i>'.$this->trans($title).'</div>';
    }

    /**
     * Box with a token that was just created
     * @return string
     */
    private function new_token_box() {
        $new = $this->get('mcp_new_token');
        if (!is_array($new) || empty($new['token'])) {
            return '';
        }
        return '<div class="alert alert-success mx-3 mt-3 mb-0 mcp_new_token" role="status">'.
            '<div class="fw-semibold mb-1">'.$this->trans('New personal access token').': '.$this->html_safe($new['name']).'</div>'.
            '<div class="mb-2">'.$this->trans('Copy this token now. It will not be shown again.').'</div>'.
            '<div class="input-group">'.
            '<input type="text" class="form-control font-monospace" readonly id="mcp_new_token_value" value="'.$this->html_safe($new['token']).'" aria-label="'.$this->trans('New personal access token').'" />'.
            '<button type="button" class="btn btn-outline-secondary mcp_copy" data-target="mcp_new_token_value" data-copied="'.$this->trans('Copied').'">'.
            '<i class="bi bi-clipboard me-1"></i>'.$this->trans('Copy').'</button>'.
            '</div></div>';
    }

    /**
     * Global switch, permissions and accounts
     * @param array $settings global settings
     * @return string
     */
    private function settings_form($settings) {
        $res = $this->section('Access', 'shield-lock-fill');
        $res .= '<div class="px-3 mt-3">'.$this->form_start('save_settings', 'mcp_settings_form');
        $res .= '<div class="form-check form-switch mb-1">'.
            '<input type="hidden" name="mcp_enabled" value="0" />'.
            '<input class="form-check-input" type="checkbox" role="switch" id="mcp_enabled" name="mcp_enabled" value="1"'.($settings['enabled'] ? ' checked' : '').' />'.
            '<label class="form-check-label fw-semibold" for="mcp_enabled">'.$this->trans('Allow MCP clients and the REST API to access my email').'</label>'.
            '</div><div class="form-text mb-3">'.$this->trans('When this is off, every connection and token stops working until you turn it on again.').'</div>';

        $res .= '<h6 class="mt-4">'.$this->trans('Permissions').'</h6>'.
            '<div class="form-text mb-2">'.$this->trans('These permissions apply to every connection. A connection can be limited further, but never above these.').'</div>';
        foreach (Hm_MCP_Permissions::keys() as $key) {
            $id = 'mcp_global_perm_'.$key;
            $res .= '<div class="form-check mb-2">'.
                '<input class="form-check-input" type="checkbox" name="mcp_permissions[]" value="'.$key.'" id="'.$id.'"'.(!empty($settings['permissions'][$key]) ? ' checked' : '').' />'.
                '<label class="form-check-label" for="'.$id.'">'.$this->trans(Hm_MCP_Permissions::label($key)).$this->permission_badge($key).'</label>'.
                '<div class="form-text mt-0">'.$this->trans(Hm_MCP_Permissions::description($key)).'</div></div>';
        }
        $res .= '<div class="form-text mb-3"><i class="bi bi-info-circle me-1"></i>'.
            $this->trans('Tags and contacts are saved in your Cypht settings. If an open Cypht session has unsaved changes, saving them later can overwrite changes made through the API.').'</div>';

        $res .= '<h6 class="mt-4">'.$this->trans('Accounts').'</h6>';
        $selected = $settings['accounts']['mode'] === 'selected';
        $res .= $this->radio('mcp_account_mode', 'all', 'All accounts', !$selected, 'mcp_global_accounts_all', 'mcp_mode').
            $this->radio('mcp_account_mode', 'selected', 'Only the selected accounts', $selected, 'mcp_global_accounts_selected', 'mcp_mode');
        $res .= '<div class="ms-4 mt-2 mcp_choices" data-mode-name="mcp_account_mode" data-mode-value="selected">'.
            $this->account_checkboxes('mcp_global_acc_', $selected ? $settings['accounts']['ids'] : [], null).'</div>';
        $res .= '<button type="submit" class="btn btn-primary mt-3">'.$this->trans('Save').'</button></form></div>';
        return $res;
    }

    /**
     * @param string $key permission key
     * @return string badge markup
     */
    private function permission_badge($key) {
        if (in_array($key, self::DESTRUCTIVE, true)) {
            return ' <span class="badge text-bg-danger ms-1">'.$this->trans('Can delete data').'</span>';
        }
        if ($key === 'send') {
            return ' <span class="badge text-bg-warning ms-1">'.$this->trans('Sends email for you').'</span>';
        }
        if (in_array($key, self::SETTINGS_BACKED, true)) {
            return ' <i class="bi bi-info-circle ms-1 text-secondary"></i>';
        }
        return '';
    }

    /**
     * @return string radio input markup
     */
    private function radio($name, $value, $label, $checked, $id, $class = '') {
        return '<div class="form-check">'.
            '<input class="form-check-input '.$class.'" type="radio" name="'.$name.'" value="'.$value.'" id="'.$id.'"'.($checked ? ' checked' : '').' />'.
            '<label class="form-check-label" for="'.$id.'">'.$this->trans($label).'</label></div>';
    }

    /**
     * Checkbox list of mail accounts
     * @param string $prefix element id prefix
     * @param array $checked checked account ids
     * @param array|null $allowed account ids that can be selected, null for all
     * @return string
     */
    private function account_checkboxes($prefix, $checked, $allowed) {
        $accounts = $this->get('mcp_accounts', []);
        if (!$accounts) {
            return '<div class="form-text">'.$this->trans('No email accounts are configured yet.').'</div>';
        }
        $res = '';
        foreach ($accounts as $index => $account) {
            $id = $prefix.$index;
            $enabled = $allowed === null || in_array($account['id'], $allowed, true);
            $label = $account['name'] !== '' ? $account['name'] : $account['user'];
            $detail = $account['user'] !== '' && $account['user'] !== $label ? ' <span class="text-secondary">('.$this->html_safe($account['user']).')</span>' : '';
            if ($account['hidden']) {
                $detail .= ' <span class="badge text-bg-light">'.$this->trans('hidden').'</span>';
            }
            if (!$enabled) {
                $detail .= ' <span class="text-secondary small">'.$this->trans('Disabled globally').'</span>';
            }
            $res .= '<div class="form-check">'.
                '<input class="form-check-input" type="checkbox" name="mcp_account_ids[]" value="'.$this->html_safe($account['id']).'" id="'.$id.'"'.
                (in_array($account['id'], $checked, true) && $enabled ? ' checked' : '').($enabled ? '' : ' disabled').' />'.
                '<label class="form-check-label" for="'.$id.'">'.$this->html_safe($label).$detail.'</label></div>';
        }
        return $res;
    }

    /**
     * Permission and account restriction fields of a connection form
     * @param string $prefix element id prefix
     * @param array $settings global settings
     * @param array|null $permissions connection permissions, null to inherit
     * @param array|null $accounts connection accounts, null to inherit
     * @return string
     */
    private function connection_limit_fields($prefix, $settings, $permissions, $accounts) {
        $custom = is_array($permissions);
        $res = '<div class="mt-3"><div class="fw-semibold mb-1">'.$this->trans('Permissions for this connection').'</div>'.
            $this->radio('mcp_perm_mode', 'inherit', 'Same as global', !$custom, $prefix.'perm_inherit', 'mcp_mode').
            $this->radio('mcp_perm_mode', 'custom', 'Custom', $custom, $prefix.'perm_custom', 'mcp_mode').
            '<div class="ms-4 mt-1 mcp_choices" data-mode-name="mcp_perm_mode" data-mode-value="custom">';
        foreach (Hm_MCP_Permissions::keys() as $key) {
            $id = $prefix.'perm_'.$key;
            $allowed = !empty($settings['permissions'][$key]);
            $checked = $allowed && ($custom ? !empty($permissions[$key]) : true);
            $res .= '<div class="form-check">'.
                '<input class="form-check-input" type="checkbox" name="mcp_permissions[]" value="'.$key.'" id="'.$id.'"'.
                ($checked ? ' checked' : '').($allowed ? '' : ' disabled').' />'.
                '<label class="form-check-label" for="'.$id.'">'.$this->trans(Hm_MCP_Permissions::label($key)).
                ($allowed ? '' : ' <span class="text-secondary small">'.$this->trans('Disabled globally').'</span>').'</label></div>';
        }
        $res .= '</div></div>';

        $selected = is_array($accounts);
        $allowed_accounts = Hm_MCP_Permissions::effective_accounts($settings['accounts'], null,
            array_column($this->get('mcp_accounts', []), 'id'));
        $res .= '<div class="mt-3"><div class="fw-semibold mb-1">'.$this->trans('Accounts for this connection').'</div>'.
            $this->radio('mcp_account_mode', 'inherit', 'Same as global', !$selected, $prefix.'acc_inherit', 'mcp_mode').
            $this->radio('mcp_account_mode', 'selected', 'Only the selected accounts', $selected, $prefix.'acc_selected', 'mcp_mode').
            '<div class="ms-4 mt-1 mcp_choices" data-mode-name="mcp_account_mode" data-mode-value="selected">'.
            $this->account_checkboxes($prefix.'acc_', $selected ? $accounts : [], $allowed_accounts).'</div></div>';
        return $res;
    }

    /**
     * List of connections and tokens
     * @param array $settings global settings
     * @return string
     */
    private function connections($settings) {
        $res = $this->section('Connections', 'link-45deg');
        $res .= '<p class="px-3 mt-3 mb-2 text-secondary">'.$this->trans('Apps connected with OAuth, such as ChatGPT, and your personal access tokens.').'</p>';
        $connections = $this->get('mcp_connections', []);
        if (!$connections) {
            return $res.'<p class="px-3 fst-italic">'.$this->trans('No connections yet.').'</p>';
        }
        $names = [];
        foreach ($this->get('mcp_accounts', []) as $account) {
            $names[$account['id']] = $account['name'] !== '' ? $account['name'] : $account['user'];
        }
        $kinds = ['oauth' => 'OAuth app', 'personal' => 'Personal token', 'runner' => 'Scheduled sends runner'];
        $res .= '<div class="list-group mx-3 mcp_connections">';
        foreach ($connections as $index => $connection) {
            $res .= '<div class="list-group-item">'.
                '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2"><div>'.
                '<div class="fw-semibold">'.$this->html_safe($connection['name']).
                ' <span class="badge text-bg-secondary ms-1">'.$this->trans($kinds[$connection['kind']] ?? $connection['kind']).'</span>'.
                ($connection['status'] === 'reauth' ? ' <span class="badge text-bg-warning ms-1">'.$this->trans('Needs to reconnect').'</span>' : '').
                '</div><div class="small text-secondary">'.
                $this->trans('Created').': '.$this->html_safe($this->format_time($connection['created_at'])).' · '.
                $this->trans('Last used').': '.$this->html_safe($this->format_time($connection['last_used_at'])).' · '.
                $this->trans('Expires').': '.$this->html_safe($this->format_expiry($connection['expires_at'])).
                '</div><div class="small">'.
                $this->trans('Permissions').': '.$this->html_safe($this->permission_summary($connection['permissions'])).' · '.
                $this->trans('Accounts').': '.$this->html_safe($this->account_summary($connection['accounts'], $names)).
                '</div></div>'.
                $this->form_start('revoke_connection', 'mcp_revoke_form').
                '<input type="hidden" name="mcp_connection_id" value="'.$this->html_safe($connection['id']).'" />'.
                '<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>'.$this->trans('Revoke').'</button></form>'.
                '</div>';
            if ($connection['kind'] !== 'runner') {
                $res .= '<details class="mt-2"><summary>'.$this->trans('Edit').'</summary>'.
                    $this->form_start('update_connection', 'mt-2').
                    '<input type="hidden" name="mcp_connection_id" value="'.$this->html_safe($connection['id']).'" />'.
                    '<label class="form-label" for="mcp_conn_name_'.$index.'">'.$this->trans('Name').'</label>'.
                    '<input class="form-control" type="text" maxlength="100" name="mcp_name" id="mcp_conn_name_'.$index.'" value="'.$this->html_safe($connection['name']).'" />'.
                    $this->connection_limit_fields('mcp_conn_'.$index.'_', $settings, $connection['permissions'], $connection['accounts']).
                    '<button type="submit" class="btn btn-primary btn-sm mt-3">'.$this->trans('Save').'</button></form></details>';
            }
            $res .= '</div>';
        }
        return $res.'</div>';
    }

    /**
     * @param array|null $permissions connection permissions
     * @return string
     */
    private function permission_summary($permissions) {
        if (!is_array($permissions)) {
            return $this->trans('Same as global');
        }
        $labels = [];
        foreach (Hm_MCP_Permissions::keys() as $key) {
            if (!empty($permissions[$key])) {
                $labels[] = $this->trans(Hm_MCP_Permissions::label($key));
            }
        }
        return $labels ? implode(', ', $labels) : $this->trans('None');
    }

    /**
     * @param array|null $accounts connection accounts
     * @param array $names account id => name
     * @return string
     */
    private function account_summary($accounts, $names) {
        if (!is_array($accounts)) {
            return $this->trans('Same as global');
        }
        $labels = [];
        foreach ($accounts as $id) {
            $labels[] = $names[$id] ?? $id;
        }
        return $labels ? implode(', ', $labels) : $this->trans('None');
    }

    /**
     * @param int $time unix time
     * @return string
     */
    private function format_time($time) {
        return $time ? date('Y-m-d H:i', (int) $time) : $this->trans('Never');
    }

    /**
     * @param int|null $time unix time, 0 for no expiration, null when expired
     * @return string
     */
    private function format_expiry($time) {
        if ($time === null) {
            return $this->trans('Expired');
        }
        return $time ? date('Y-m-d', (int) $time) : $this->trans('No expiration');
    }

    /**
     * Form to create a personal access token
     * @param array $settings global settings
     * @return string
     */
    private function token_form($settings) {
        $res = $this->section('Create a personal access token', 'key-fill');
        $res .= '<div class="px-3 mt-3"><p class="text-secondary">'.
            $this->trans('Use personal access tokens with clients that accept a bearer token, such as Claude Code, Codex, Cursor or scripts. ChatGPT connects with OAuth instead and does not need a token.').
            '</p>'.$this->form_start('create_token', 'mcp_token_form');
        $res .= '<div class="row g-3"><div class="col-md-6">'.
            '<label class="form-label" for="mcp_token_name">'.$this->trans('Token name').'</label>'.
            '<input class="form-control" type="text" maxlength="100" required name="mcp_name" id="mcp_token_name" placeholder="Claude Code" />'.
            '</div><div class="col-md-6">'.
            '<label class="form-label" for="mcp_token_expires">'.$this->trans('Expiration').'</label>'.
            '<select class="form-select" name="mcp_expires" id="mcp_token_expires">';
        $options = [7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year', 0 => 'No expiration'];
        foreach ($options as $days => $label) {
            $res .= '<option value="'.$days.'"'.($days === 90 ? ' selected' : '').'>'.$this->trans($label).'</option>';
        }
        $res .= '</select></div></div>';
        $res .= $this->connection_limit_fields('mcp_new_', $settings, null, null);
        $res .= '<div class="row mt-3"><div class="col-md-6">'.
            '<label class="form-label" for="mcp_token_password">'.$this->trans('Your Cypht password').'</label>'.
            '<input class="form-control" type="password" required name="mcp_password" id="mcp_token_password" autocomplete="current-password" />'.
            '<div class="form-text">'.$this->trans('Your password lets this token decrypt your account settings. It is stored encrypted and can only be used together with the token.').'</div>'.
            '</div></div>';
        $res .= '<button type="submit" class="btn btn-primary mt-3">'.$this->trans('Create token').'</button></form></div>';
        return $res;
    }

    /**
     * Instructions to connect clients
     * @return string
     */
    private function connect_help() {
        $res = $this->section('How to connect', 'plug');
        $base = $this->get('mcp_public_url', '');
        if (!$base) {
            return $res.'<p class="px-3 mt-3 fst-italic">'.
                $this->trans('The public URL is not configured. The site administrator must set MCP_PUBLIC_URL before clients can connect.').'</p>';
        }
        $mcp = $base.'/mcp';
        $api = $base.'/api/v1';
        $res .= '<div class="px-3 mt-3 mcp_connect_help">'.
            '<dl class="row mb-2">'.
            '<dt class="col-sm-3">'.$this->trans('MCP server URL').'</dt><dd class="col-sm-9"><code>'.$this->html_safe($mcp).'</code></dd>'.
            '<dt class="col-sm-3">'.$this->trans('REST API base URL').'</dt><dd class="col-sm-9"><code>'.$this->html_safe($api).'</code></dd>'.
            '<dt class="col-sm-3">'.$this->trans('OpenAPI description').'</dt><dd class="col-sm-9"><code>'.$this->html_safe($api.'/openapi.json').'</code></dd>'.
            '</dl>';
        $res .= '<h6 class="mt-3">ChatGPT</h6><ol>'.
            '<li>'.$this->trans('In ChatGPT, open Settings, then Security and login, and turn on Developer mode.').'</li>'.
            '<li>'.$this->trans('Add a new plugin or connector with the MCP server URL and choose OAuth as the authentication.').'</li>'.
            '<li>'.$this->trans('When ChatGPT opens this site, sign in with your Cypht username and password and choose what the connection can do.').'</li>'.
            '</ol>';
        $examples = [
            'Claude Code' => 'claude mcp add --transport http cypht '.$mcp.' --header "Authorization: Bearer YOUR_TOKEN"',
            'Codex' => "codex mcp add cypht --url ".$mcp." --bearer-token-env-var CYPHT_MCP_TOKEN\n\n".
                "# or in ~/.codex/config.toml\n[mcp_servers.cypht]\nurl = \"".$mcp."\"\nbearer_token_env_var = \"CYPHT_MCP_TOKEN\"",
            'Cursor, VS Code and other clients' => json_encode(['mcpServers' => ['cypht' => [
                'url' => $mcp, 'headers' => ['Authorization' => 'Bearer YOUR_TOKEN']]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'REST API' => 'curl -H "Authorization: Bearer YOUR_TOKEN" "'.$api.'/messages?view=unread"',
        ];
        foreach ($examples as $title => $code) {
            $res .= '<h6 class="mt-3">'.$this->trans($title).'</h6><pre class="mcp_code"><code>'.$this->html_safe($code).'</code></pre>';
        }
        $res .= '<div class="form-text mb-3">'.$this->trans('Replace YOUR_TOKEN with a personal access token. Clients that support OAuth, like Claude Code, can also sign in without a token.').'</div></div>';
        return $res;
    }
}
