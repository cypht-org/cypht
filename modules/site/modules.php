<?php

/**
 * example site modules
 * @package modules
 * @subpackage site
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage site/handler
 */
class Hm_Handler_site_http_headers extends Hm_Handler_Module {
    public function process() {
        /* output custom headers here */
    }
}

/**
 * @subpackage site/handler
 */
class Hm_Handler_disable_servers_page extends Hm_Handler_Module {
    public function process() {
        Hm_Dispatch::page_redirect($this->build_page_url('home'));
    }
}

/**
 * Embedded deployments: logout does not exist.
 *
 * Login is automatic and owned by the host application, so hide_logout keeps the
 * button out of the UI and this handler makes sure the route cannot end the session either
 *
 * Registered via replace_module('handler', 'logout', 'site_logout') in setup.php.
 * @subpackage site/handler
 */
class Hm_Handler_site_logout extends Hm_Handler_logout {
    public function process() {
        if (!$this->config->get('hide_logout', false)) {
            parent::process();
            return;
        }
        if ($this->page !== 'logout'
            && !array_key_exists('logout', $this->request->post)
            && !array_key_exists('save_and_logout', $this->request->post)) {
            return;
        }
        Hm_Debug::add('Logout is disabled, ignoring logout request', 'info');
        Hm_Dispatch::page_redirect($this->build_page_url('home'));
    }
}

/**
 * Save settings without re-prompting for a password the user does not have.
 *
 * Core's save flow re-authenticates before saving because the password doubles as the
 * settings encryption key. A backend that supplies its own key (Custom_User_Config,
 * via CYPHT_CONFIG_ENCRYPTION_SECRET) does not need one, and under token auth the
 * prompt can never be satisfied - the user gets "Incorrect password, could not save
 * settings" for settings that were in fact saved.
 *
 * Registered via replace_module('handler', 'process_save_form', 'site_process_save_form').
 * @subpackage site/handler
 */
class Hm_Handler_site_process_save_form extends Hm_Handler_process_save_form {
    public function process() {
        if ($this->save_needs_password()) {
            parent::process();
            return;
        }
        $save = false;
        $logout = false;
        if (array_key_exists('save_settings_permanently', $this->request->post)) {
            $save = true;
        } elseif (array_key_exists('save_settings_permanently_then_logout', $this->request->post)) {
            $save = true;
            $logout = true;
        }
        if (!$save) {
            return;
        }
        $user = $this->session->get('username', false);
        if (!$user) {
            Hm_Msgs::add('Could not save settings: no user in session', 'warning');
            return;
        }
        try {
            $this->user_config->save($user, false);
            $this->session->set('changed_settings', array());
            Hm_Msgs::add('Settings saved', 'info');
            if ($logout && !$this->config->get('hide_logout', false)) {
                $this->session->destroy($this->request);
                Hm_Msgs::add('Session destroyed on logout', 'info');
            }
        } catch (Exception $e) {
            Hm_Msgs::add('Could not save settings: '.$e->getMessage(), 'warning');
        }
    }

    /**
     * Ask the user config backend whether saving needs a user supplied key.
     * Backends that do not implement the method keep the stock behaviour.
     * @return bool
     */
    private function save_needs_password() {
        if (!method_exists($this->user_config, 'save_needs_password')) {
            return true;
        }
        return (bool) $this->user_config->save_needs_password();
    }
}

/*
 * Idle timeout is a logout, so it must not fire when the host app owns the session.
 * Guarded because the idle_timer module set may not be enabled; note that it has to
 * appear BEFORE "site" in CYPHT_MODULES for this class to be defined. Simply dropping
 * idle_timer from CYPHT_MODULES is the more robust option for an embedded install.
 */
if (class_exists('Hm_Handler_idle_time_check')) {
    /**
     * @subpackage site/handler
     */
    class Hm_Handler_site_idle_time_check extends Hm_Handler_idle_time_check {
        public function process() {
            if ($this->config->get('hide_logout', false)) {
                Hm_Debug::add('IDLETIMER: skipped, logout is disabled', 'info');
                return;
            }
            parent::process();
        }
    }
}
