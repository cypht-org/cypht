
<?php

class Hm_Sieve_Client_Factory {
    public function init($user_config = null, $imap_account = null)
    {
        if ($imap_account && ! empty($imap_account['sieve_config_host'])) {
            list($sieve_host, $sieve_port) = parse_sieve_config_host($imap_account['sieve_config_host']);
            $client = new PhpSieveManager\ManageSieve\Client($sieve_host, $sieve_port);
            $client->connect($imap_account['user'], $imap_account['pass'], $imap_account['sieve_tls'], "", "PLAIN");
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
