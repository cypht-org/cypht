
<?php

require_once APP_PATH.'modules/sievefilters/functions.php';

class Hm_Sieve_Client_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false)
    {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $imap_debug = array();
            if (is_array($imap_account)) {
                $imap_debug = array_intersect_key($imap_account, array_flip(array(
                    'name',
                    'server',
                    'host',
                    'port',
                    'tls',
                    'sieve_config_host',
                    'sieve_config_port',
                    'sieve_config_tls'
                )));
            }
            error_log('[sieve_block_debug] sieve init input: '.json_encode(array(
                'has_imap_account' => (bool) $imap_account,
                'imap_account' => $imap_debug
            )));
        }
        if ($imap_account && ! empty($imap_account['sieve_config_host'])) {
            // Check if module nux is enabled and if it is, get the sieve host from the services
            if($is_nux_supported && $sieve_config = get_sieve_host_from_services($imap_account['server'])) {
                $sieve_host = $sieve_config['host'];
                $sieve_port = $sieve_config['port'];
                $sieve_tls = $sieve_config['tls'];
            } else {
                list($sieve_host, $sieve_port, $sieve_tls) = parse_sieve_config_host($imap_account);
            }
            $client = new PhpSieveManager\ManageSieve\Client($sieve_host, $sieve_port);
            $client->connect($imap_account['user'], $imap_account['pass'], $sieve_tls, "", "PLAIN");
            return $client;
        } else {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('[sieve_block_debug] sieve init missing sieve_config_host');
            }
            $errorMsg = 'Invalid config host';
            if (isset($imap_account['name'])) {
                $errorMsg .= ' for ' . $imap_account['name'];
            }
            throw new Exception($errorMsg);
        }
    }
}
