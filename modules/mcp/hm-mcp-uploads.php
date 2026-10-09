<?php

/**
 * Files attached to messages through the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Reads attachments passed by clients: file links from ChatGPT (openai/fileParams)
 * or base64 content. Links are only downloaded over HTTPS from the hosts in
 * MCP_UPLOAD_ALLOWED_HOSTS, from public addresses, with a size limit. The same
 * checks are used to fetch OAuth client metadata documents.
 * @subpackage mcp/lib
 */
class Hm_MCP_Uploads {

    /* redirects followed for one download, each one checked again */
    const MAX_REDIRECTS = 3;

    const CONNECT_TIMEOUT = 10;
    const TIMEOUT = 30;

    /* largest total of base64 content in one call, in decoded bytes */
    const MAX_INLINE_BYTES = 10485760;

    /* callable(string $url, string $host, string $ip, int $max_bytes, int $timeout): array [status, headers, body], replaces curl in tests */
    public $fetcher = null;

    /* callable(string $host): array of IP addresses, replaces DNS lookups in tests */
    public $resolver = null;

    /* Hm_MCP_Config */
    private $config;

    /**
     * @param Hm_MCP_Config $config MCP settings
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * @return int largest total size of the attachments of one message, in bytes
     */
    public function max_bytes() {
        return $this->config->int('max_upload_bytes', 26214400, 1024);
    }

    /**
     * @param int $bytes size limit
     * @return Hm_MCP_Error
     */
    public static function too_large($bytes) {
        return new Hm_MCP_Error('payload_too_large', sprintf('The attachments are larger than the limit of %s MB for one message.',
            rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.')));
    }

    /**
     * Download a file passed by ChatGPT
     * @param array $file ['download_url', 'file_id', 'mime_type', 'file_name']
     * @param int $budget bytes still allowed
     * @param int $timeout seconds available
     * @return array ['filename', 'content_type', 'data']
     * @throws Hm_MCP_Error
     */
    public function from_file($file, $budget, $timeout = self::TIMEOUT) {
        $url = (string) ($file['download_url'] ?? '');
        $current = $url;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            list($host, $ip) = $this->check_url($current);
            list($status, $headers, $body) = $this->fetch($current, $host, $ip, $budget, max(5, min(self::TIMEOUT, (int) $timeout)));
            if (in_array($status, [301, 302, 303, 307, 308], true) && !empty($headers['location'])) {
                $current = self::resolve_location($current, (string) $headers['location']);
                continue;
            }
            if ($status !== 200) {
                throw new Hm_MCP_Error('upstream_error', sprintf('The file could not be downloaded (HTTP status %d). The link may have expired.', $status));
            }
            if (strlen($body) > $budget) {
                throw self::too_large($this->max_bytes());
            }
            $name = (string) ($file['file_name'] ?? '');
            if (trim($name) === '') {
                $name = rawurldecode(basename((string) parse_url($current, PHP_URL_PATH)));
            }
            $type = Hm_MCP_Mime::content_type($file['mime_type'] ?? '');
            if ($type === 'application/octet-stream') {
                $type = Hm_MCP_Mime::content_type($headers['content-type'] ?? '');
            }
            return ['filename' => Hm_MCP_Mime::filename($name), 'content_type' => self::detect($type, $body), 'data' => $body];
        }
        throw new Hm_MCP_Error('upstream_error', 'The file could not be downloaded: too many redirects.');
    }

    /**
     * Decode a file sent with its content
     * @param array $item ['filename', 'content_type', 'content_base64']
     * @param int $budget bytes still allowed
     * @return array ['filename', 'content_type', 'data']
     * @throws Hm_MCP_Error
     */
    public function from_base64($item, $budget) {
        $content = preg_replace('/\s+/', '', (string) ($item['content_base64'] ?? ''));
        if (strlen($content) > ($budget + 3) * 4 / 3 + 4) {
            throw self::too_large(min($this->max_bytes(), self::MAX_INLINE_BYTES));
        }
        $data = base64_decode($content, true);
        if ($data === false) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('The content of "%s" is not valid base64.', Hm_MCP_Mime::filename($item['filename'] ?? '')));
        }
        if (strlen($data) > $budget) {
            throw self::too_large(min($this->max_bytes(), self::MAX_INLINE_BYTES));
        }
        return ['filename' => Hm_MCP_Mime::filename($item['filename'] ?? ''),
            'content_type' => self::detect(Hm_MCP_Mime::content_type($item['content_type'] ?? ''), $data), 'data' => $data];
    }

    /**
     * @param string $type declared content type
     * @param string $data file content
     * @return string the declared type, or one detected from the content
     */
    protected static function detect($type, $data) {
        if ($type !== 'application/octet-stream' || $data === '' || !function_exists('finfo_open')) {
            return $type;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? finfo_buffer($finfo, $data) : false;
        return $detected ? Hm_MCP_Mime::content_type($detected) : $type;
    }

    /**
     * Check a download link
     * @param string $url link
     * @return array [host, IP address to connect to]
     * @throws Hm_MCP_Error
     */
    public function check_url($url) {
        return $this->check_https_url($url, $this->config->list_setting('upload_allowed_hosts'),
            'File links must be HTTPS links on the default port.',
            'Files cannot be downloaded from %s. The server administrator can allow the host in MCP_UPLOAD_ALLOWED_HOSTS.');
    }

    /**
     * Fetch a small document, such as OAuth client metadata, without following redirects
     * @param string $url document URL
     * @param array $allowed allowed host patterns
     * @param int $max_bytes largest body accepted
     * @param int $timeout seconds
     * @return array [status, lower case headers, body]
     * @throws Hm_MCP_Error
     */
    public function get_document($url, $allowed, $max_bytes, $timeout) {
        list($host, $ip) = $this->check_https_url($url, $allowed, 'Document URLs must be HTTPS links on the default port.',
            'Documents cannot be fetched from %s.');
        return $this->fetch($url, $host, $ip, $max_bytes, $timeout, ['Accept: application/json']);
    }

    /**
     * Check that a URL is HTTPS on the default port, on an allowed host that only
     * resolves to public addresses
     * @param string $url URL
     * @param array $allowed allowed host patterns
     * @param string $scheme_error message when the URL is not a plain HTTPS URL
     * @param string $host_error message when the host is not allowed, %s is the host
     * @return array [host, IP address to connect to]
     * @throws Hm_MCP_Error
     */
    public function check_https_url($url, $allowed, $scheme_error, $host_error) {
        $parts = parse_url((string) $url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new Hm_MCP_Error('invalid_argument', $scheme_error);
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        if (!Hm_MCP_Config::host_in_list($host, $allowed)) {
            throw new Hm_MCP_Error('invalid_argument', sprintf($host_error, $host));
        }
        $ips = $this->resolve($host);
        if (!$ips) {
            throw new Hm_MCP_Error('upstream_error', sprintf('The host %s could not be resolved.', $host));
        }
        foreach ($ips as $ip) {
            if (!self::public_ip($ip)) {
                throw new Hm_MCP_Error('invalid_argument', sprintf('The host %s resolves to a private address.', $host));
            }
        }
        return [$host, $ips[0]];
    }

    /**
     * @param string $ip address
     * @return bool true for a public unicast address
     */
    public static function public_ip($ip) {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            && !preg_match('/^(100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|0\.|::ffff:|64:ff9b:|fc|fd|fe[89ab])/i', $ip);
    }

    /**
     * @param string $host host name
     * @return array IP addresses, IPv4 first
     */
    protected function resolve($host) {
        if ($this->resolver) {
            return array_values((array) ($this->resolver)($host));
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        foreach ((is_array($records) ? $records : []) as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
        }
        foreach ((is_array($records) ? $records : []) as $record) {
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        if (!$ips) {
            $ips = (array) @gethostbynamel($host);
        }
        return array_values(array_unique(array_filter($ips)));
    }

    /**
     * @param string $base current URL
     * @param string $location Location header
     * @return string absolute URL
     */
    protected static function resolve_location($base, $location) {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = 'https://'.$parts['host'];
        if (strpos($location, '//') === 0) {
            return 'https:'.$location;
        }
        if (strpos($location, '/') === 0) {
            return $origin.$location;
        }
        $path = (string) ($parts['path'] ?? '/');
        return $origin.substr($path, 0, (int) strrpos($path, '/') + 1).$location;
    }

    /**
     * Download with the connection pinned to a checked address
     * @param array $request_headers extra request headers
     * @return array [status, lower case headers, body]
     * @throws Hm_MCP_Error
     */
    protected function fetch($url, $host, $ip, $max_bytes, $timeout, $request_headers = []) {
        if ($this->fetcher) {
            return ($this->fetcher)($url, $host, $ip, $max_bytes, $timeout);
        }
        if (!function_exists('curl_init')) {
            throw new Hm_MCP_Error('not_supported', 'The PHP curl extension is required to download files.');
        }
        $body = '';
        $headers = [];
        $too_large = false;
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RESOLVE => [$host.':443:'.(strpos($ip, ':') !== false ? '['.$ip.']' : $ip)],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Cypht',
            CURLOPT_HTTPHEADER => $request_headers,
            CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers, &$too_large, $max_bytes) {
                if (preg_match('/^HTTP\//i', $line)) {
                    $headers = [];
                } elseif (strpos($line, ':') !== false) {
                    list($name, $value) = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                    if (strtolower(trim($name)) === 'content-length' && (int) trim($value) > $max_bytes) {
                        $too_large = true;
                        return 0;
                    }
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$body, &$too_large, $max_bytes) {
                if (strlen($body) + strlen($chunk) > $max_bytes) {
                    $too_large = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($too_large) {
            throw self::too_large($this->max_bytes());
        }
        if ($ok === false) {
            throw new Hm_MCP_Error('upstream_error', 'The file could not be downloaded. The link may have expired.');
        }
        return [$status, $headers, $body];
    }
}
