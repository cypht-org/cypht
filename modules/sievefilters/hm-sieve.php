<?php

use PhpSieveManager\ManageSieve\Interfaces\SieveCache;

require_once APP_PATH.'modules/sievefilters/functions.php';

class Hm_Sieve_Cache implements SieveCache
{
    private Hm_Cache $cache;

    public function __construct(Hm_Cache $cache)
    {
        $this->cache = $cache;
    }

    public function get(string $key)
    {
        $value = $this->cache->get('sieve_' . $key, null, true);
        error_log('[SieveCache] ' . ($value !== null ? 'HIT' : 'MISS') . ' - ' . $key);
        return $value;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        error_log('[SieveCache] SET - ' . $key);
        return (bool) $this->cache->set('sieve_' . $key, $value, $ttl, true);
    }

    public function delete(string $key): bool
    {
        error_log('[SieveCache] DELETE - ' . $key);
        return (bool) $this->cache->del('sieve_' . $key, true);
    }
}

class Hm_Sieve_Client_Factory {
    private static array $instances = [];

    public static function get(int|string $account_id): ?PhpSieveManager\ManageSieve\Client
    {
        return self::$instances[$account_id] ?? null;
    }

    public function init($imap_account = null, bool $is_nux_supported = false, ?Hm_Cache $cache = null): PhpSieveManager\ManageSieve\Client
    {
        if (!$imap_account || empty($imap_account['sieve_config_host'])) {
            $errorMsg = 'Invalid config host';
            if (isset($imap_account['name'])) {
                $errorMsg .= ' for ' . $imap_account['name'];
            }
            throw new Exception($errorMsg);
        }

        $id = $imap_account['id'] ?? null;

        if ($id !== null && isset(self::$instances[$id])) {
            return self::$instances[$id];
        }

        if ($is_nux_supported && $sieve_config = get_sieve_host_from_services($imap_account['server'])) {
            $sieve_host = $sieve_config['host'];
            $sieve_port = $sieve_config['port'];
            $sieve_tls  = $sieve_config['tls'];
        } else {
            list($sieve_host, $sieve_port, $sieve_tls) = parse_sieve_config_host($imap_account);
        }

        $client = new PhpSieveManager\ManageSieve\Client($sieve_host, $sieve_port);
        if ($cache !== null) {
            $client->setCache(new Hm_Sieve_Cache($cache));
        }
        $client->connect($imap_account['user'], $imap_account['pass'], $sieve_tls, "", "PLAIN");

        if ($id !== null) {
            self::$instances[$id] = $client;
        }

        return $client;
    }
}
