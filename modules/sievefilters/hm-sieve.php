
<?php

require_once APP_PATH.'modules/sievefilters/functions.php';

class Hm_Sieve_Client_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false)
    {
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
            $errorMsg = 'Invalid config host';
            if (isset($imap_account['name'])) {
                $errorMsg .= ' for ' . $imap_account['name'];
            }
            throw new Exception($errorMsg);
        }
    }
}
