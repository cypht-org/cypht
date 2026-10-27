<?php

class Hm_Handler_gateway_sieve_status extends Hm_Handler_Module {
    public function process() {
        if (!$this->module_is_supported('sievefilters')) {
            gateway_json_error('sieve capability is unavailable', 501);
        }
        if (!class_exists('Hm_IMAP_List', false)) {
            gateway_json_error('mail account capability is unavailable', 501);
        }
        $enabled = (bool)$this->user_config->get(
            'enable_sieve_filter_setting',
            defined('DEFAULT_ENABLE_SIEVE_FILTER') ? DEFAULT_ENABLE_SIEVE_FILTER : false
        );
        $rows = array();
        foreach (Hm_IMAP_List::dump() as $id => $account) {
            if (!is_array($account)) continue;
            $configured = !empty($account['sieve_config_host']);
            $type = isset($account['type']) ? strtolower((string)$account['type']) : 'imap';
            $rows[] = array(
                'account_id' => (string)$id,
                'name' => isset($account['name']) ? (string)$account['name'] : 'Mail account',
                'protocol' => $type,
                'enabled' => $enabled && $configured,
                'configured' => $configured,
                'status' => !$enabled ? 'disabled' : ($configured ? 'configured' : 'not_configured'),
                'remote_probe' => false
            );
        }
        gateway_json_ok($rows);
    }
}
