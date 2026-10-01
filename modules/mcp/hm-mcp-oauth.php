<?php

/**
 * OAuth 2.1 authorization server for MCP clients such as ChatGPT
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Self-contained HTML pages for the sign in and consent steps
 * @subpackage mcp/lib
 */
class Hm_MCP_OAuth_Page {

    /* language code of the page */
    public $lang = 'en';

    /* text direction, ltr or rtl */
    public $dir = 'ltr';

    /* application name shown above the title */
    public $app_name = 'Cypht';

    /* translations from the Cypht language file */
    private $strings = [];

    const CSS = ':root{color-scheme:light dark}*{box-sizing:border-box}'.
        'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;'.
        'background:#f3f4f6;color:#111827;font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'.
        'main{width:100%;max-width:460px;background:#fff;border:1px solid #d1d5db;border-radius:12px;padding:28px}'.
        '.app{margin:0 0 4px;color:#4b5563;font-size:14px;font-weight:600}'.
        'h1{margin:0 0 16px;font-size:22px;line-height:1.3}p{margin:0 0 12px}'.
        '.muted,.hint,.check small{color:#4b5563;font-size:14px}.hint{margin:6px 0 0}'.
        '.error{border:1px solid #b91c1c;background:#fef2f2;color:#7f1d1d;border-radius:8px;padding:10px 12px;margin:0 0 16px}'.
        'label{display:block;font-weight:600;margin:14px 0 4px}'.
        'input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1px solid #6b7280;border-radius:8px;font:inherit;background:#fff;color:inherit}'.
        'input:focus-visible,button:focus-visible{outline:3px solid #2563eb;outline-offset:2px}'.
        'fieldset{border:1px solid #d1d5db;border-radius:8px;margin:16px 0 0;padding:6px 14px 10px}legend{font-weight:600;padding:0 6px}'.
        '.check{display:flex;gap:10px;align-items:flex-start;margin:10px 0}.check input{margin:4px 0 0;width:18px;height:18px;flex:none}'.
        '.check label{margin:0}.check small{display:block;font-weight:400}'.
        '.actions{display:flex;gap:12px;margin-top:22px;flex-wrap:wrap}'.
        'button{flex:1;min-width:120px;padding:10px 16px;border-radius:8px;font:inherit;font-weight:600;cursor:pointer;'.
        'border:1px solid #1d4ed8;background:#1d4ed8;color:#fff}button.secondary{background:#fff;color:#111827;border-color:#6b7280}'.
        '@media (prefers-color-scheme:dark){body{background:#111827;color:#f9fafb}main{background:#1f2937;border-color:#374151}'.
        '.app,.muted,.hint,.check small{color:#d1d5db}input[type=text],input[type=password]{background:#111827;border-color:#9ca3af}'.
        'button.secondary{background:#1f2937;color:#f9fafb;border-color:#9ca3af}fieldset{border-color:#4b5563}'.
        '.error{background:#450a0a;color:#fecaca;border-color:#f87171}}';

    /**
     * @param string $lang Cypht language code
     * @param string $app_name application name
     */
    public function __construct($lang, $app_name = 'Cypht') {
        $this->app_name = (string) $app_name !== '' ? (string) $app_name : 'Cypht';
        $this->set_language($lang);
    }

    /**
     * Load a Cypht language file, English when the code is unknown
     * @param mixed $lang language code such as en, es or pt-BR
     * @return void
     */
    public function set_language($lang) {
        $lang = Hm_MCP_Strings::code($lang);
        $this->strings = Hm_MCP_Strings::load($lang);
        $this->lang = (string) ($this->strings['interface_lang'] ?? $lang);
        $this->dir = ($this->strings['interface_direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
    }

    /**
     * @param string $string English text
     * @return string translated text
     */
    public function trans($string) {
        $value = $this->strings[$string] ?? false;
        return is_string($value) ? $value : $string;
    }

    /**
     * Translate and escape for HTML
     * @param string $string English text
     * @return string
     */
    public function text($string) {
        return self::esc($this->trans($string));
    }

    /**
     * @param mixed $value text
     * @return string HTML escaped text
     */
    public static function esc($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Build the page response
     * @param string $title translated page title
     * @param string $body HTML content
     * @param array $form_targets extra CSP form-action sources, where a form submission may redirect to
     * @param int $status HTTP status
     * @return Hm_MCP_Http_Response
     */
    public function render($title, $body, $form_targets = [], $status = 200) {
        $html = '<!DOCTYPE html><html lang="'.self::esc($this->lang).'" dir="'.$this->dir.'"><head><meta charset="utf-8">'.
            '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">'.
            '<title>'.self::esc($title.' - '.$this->app_name).'</title><style>'.self::CSS.'</style></head><body>'.
            '<main aria-labelledby="mcp_title"><p class="app">'.self::esc($this->app_name).'</p>'.
            '<h1 id="mcp_title">'.self::esc($title).'</h1>'.$body.'</main></body></html>';
        $response = Hm_MCP_Http_Response::html($html, $status);
        /* Chrome checks form-action on the redirect that follows a form submission */
        $sources = array_merge(["'self'"], array_values(array_unique(array_filter($form_targets))));
        $response->with_header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; ".
            'form-action '.implode(' ', $sources)."; frame-ancestors 'none'; base-uri 'none'");
        /* keep the Origin header on our own form posts, browsers send "null" with no-referrer */
        $response->with_header('Referrer-Policy', 'same-origin');
        return $response;
    }
}

/**
 * Authorization server: metadata (RFC 8414), dynamic client registration (RFC 7591),
 * authorization code flow with PKCE and issuer identification (RFC 9207),
 * refresh token rotation and token revocation (RFC 7009)
 *
 * Signing in creates a pending connection that holds the Cypht password sealed with
 * a new connection key. The key is only stored wrapped by the tokens issued for that
 * connection: first the consent ticket, then the authorization code, then the access
 * and refresh tokens.
 * @subpackage mcp/lib
 */
class Hm_MCP_OAuth {

    /* authorization codes are exchanged right away */
    const CODE_TTL = 300;

    /* time to finish the consent step after signing in */
    const CONSENT_TTL = 900;

    /* a rotated refresh token is still accepted this many seconds, for parallel or retried refreshes */
    const REFRESH_GRACE = 30;

    /* sign in attempts per username and per client address */
    const LOGIN_ATTEMPTS = 10;
    const LOGIN_IP_ATTEMPTS = 30;
    const LOGIN_WINDOW = 900;

    /* client registrations per client address */
    const REGISTER_ATTEMPTS = 30;
    const REGISTER_WINDOW = 3600;

    /* client metadata documents (CIMD): seconds a fetched copy is used, oldest copy used when
       the document cannot be fetched again, size and time limits, fetches per client address */
    const CIMD_TTL = 3600;
    const CIMD_MAX_STALE = 604800;
    const CIMD_MAX_BYTES = 16384;
    const CIMD_TIMEOUT = 5;
    const CIMD_FETCHES = 30;
    const CIMD_WINDOW = 600;

    /* registration and request limits */
    const MAX_REDIRECT_URIS = 10;
    const MAX_REGISTRATION_BYTES = 16384;
    const MAX_STATE_LENGTH = 2048;

    /* URI schemes that can never receive an authorization response */
    const BLOCKED_SCHEMES = ['javascript', 'data', 'vbscript', 'file', 'about', 'blob', 'filesystem', 'ftp', 'ws', 'wss',
        'mailto', 'tel', 'sms', 'intent', 'chrome', 'view-source', 'jar'];

    /* messages shown on the pages */
    const MESSAGES = [
        'missing_login' => 'Enter your username and password.',
        'invalid_login' => 'Invalid username or password',
        'rate_limited' => 'Too many attempts. Try again later.',
        'settings_locked' => 'Your Cypht settings could not be opened. Sign in to Cypht in the browser once, then try again.',
        'code_required' => 'Enter the code from your authenticator app.',
        'code_invalid' => '2 factor authentication code does not match',
        'disabled' => 'API and MCP access is turned off for this account. Turn it on in Settings, API and MCP, then try again.',
        'no_permission' => 'Select at least one permission',
        'no_account' => 'Select at least one account',
        'unknown_client' => 'This app is not registered with this server. Remove it in the app and add it again.',
        'client_unavailable' => 'The information of this app could not be loaded. Try again in a few minutes.',
        'no_redirect' => 'The app did not say where to return after signing in.',
        'bad_redirect' => 'The return address of this app does not match its registration.',
        'expired' => 'This sign in has expired. Go back to the app and connect again.',
        'cross_site' => 'This form was sent from another site and was blocked.',
        'unavailable' => 'The server cannot store connections right now. Try again later.',
        'failed' => 'The connection could not be created. Try again later.',
    ];

    /* Hm_MCP_Services */
    public $services;

    /* callable(string $username, string $password): bool, replaces the Cypht login check in tests */
    public $credential_checker = null;

    /* callable(string $username, string $password): object|false, replaces loading the user settings in tests */
    public $user_config_loader = null;

    /* Hm_MCP_Config */
    private $config;

    /* site configuration */
    private $site_config;

    /**
     * @param Hm_MCP_Services $services shared services
     */
    public function __construct($services) {
        $this->services = $services;
        $this->config = $services->config;
        $this->site_config = $services->site_config;
    }

    /**
     * @return Hm_MCP_Store
     */
    private function store() {
        return $this->services->store();
    }

    /* ------------------------------------------------------------- metadata */

    /**
     * Authorization server metadata (RFC 8414)
     * @return Hm_MCP_Http_Response
     */
    public function metadata() {
        return Hm_MCP_Http_Response::json([
            'issuer' => $this->config->issuer(),
            'authorization_endpoint' => $this->config->url('/oauth/authorize'),
            'token_endpoint' => $this->config->url('/oauth/token'),
            'registration_endpoint' => $this->config->url('/oauth/register'),
            'revocation_endpoint' => $this->config->url('/oauth/revoke'),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => Hm_MCP_Permissions::scopes(),
            'authorization_response_iss_parameter_supported' => true,
        ] + ($this->cimd_hosts() ? ['client_id_metadata_document_supported' => true] : []), 200, ['Cache-Control' => 'public, max-age=300']);
    }

    /* --------------------------------------------------------- registration */

    /**
     * Dynamic client registration (RFC 7591). Only public clients are registered.
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    public function register($request) {
        $store = $this->store();
        if (!$store->available()) {
            return Hm_MCP_Http_Response::oauth_error(503, 'temporarily_unavailable', 'The authorization server storage is not available.');
        }
        if (!$store->rate_limit('register:'.$this->client_ip($request), self::REGISTER_ATTEMPTS, self::REGISTER_WINDOW)) {
            return Hm_MCP_Http_Response::oauth_error(429, 'too_many_requests', 'Too many client registrations. Try again later.',
                ['Retry-After' => (string) self::REGISTER_WINDOW]);
        }
        if (strlen($request->body()) > self::MAX_REGISTRATION_BYTES) {
            return Hm_MCP_Http_Response::oauth_error(413, 'invalid_client_metadata', 'The client metadata is too large.');
        }
        $meta = $request->json();
        if (!is_array($meta) || !$meta || array_keys($meta) === range(0, count($meta) - 1)) {
            return Hm_MCP_Http_Response::oauth_error(400, 'invalid_client_metadata', 'The request body must be a JSON object with the client metadata.');
        }
        $uris = $meta['redirect_uris'] ?? null;
        if (!is_array($uris) || !$uris || count($uris) > self::MAX_REDIRECT_URIS) {
            return Hm_MCP_Http_Response::oauth_error(400, 'invalid_redirect_uri',
                sprintf('redirect_uris must list between 1 and %d redirect URIs.', self::MAX_REDIRECT_URIS));
        }
        foreach ($uris as $uri) {
            if (!self::valid_redirect_uri($uri)) {
                return Hm_MCP_Http_Response::oauth_error(400, 'invalid_redirect_uri',
                    'Redirect URIs must use HTTPS, HTTP on a loopback address, or an application scheme, without a fragment or credentials.');
            }
        }
        $grants = $meta['grant_types'] ?? ['authorization_code'];
        if (!is_array($grants) || !in_array('authorization_code', $grants, true)) {
            return Hm_MCP_Http_Response::oauth_error(400, 'invalid_client_metadata', 'grant_types must include authorization_code.');
        }
        $types = $meta['response_types'] ?? ['code'];
        if (!is_array($types) || !in_array('code', $types, true)) {
            return Hm_MCP_Http_Response::oauth_error(400, 'invalid_client_metadata', 'response_types must include code.');
        }
        $name = self::clean_text($meta['client_name'] ?? '', 100);
        $client_uri = is_string($meta['client_uri'] ?? null) && Hm_MCP_Config::valid_public_url($meta['client_uri'])
            && strtolower((string) parse_url($meta['client_uri'], PHP_URL_SCHEME)) === 'https' ? $meta['client_uri'] : null;
        $client_id = Hm_MCP_Crypto::random_id('cl_', 16);
        $uris = array_values(array_unique($uris));
        $saved = $store->save_client([
            'client_id' => $client_id,
            'kind' => 'dcr',
            'client_name' => $name,
            'redirect_uris' => $uris,
            'metadata' => array_filter([
                'client_uri' => $client_uri,
                'software_id' => self::clean_text($meta['software_id'] ?? '', 100),
                'software_version' => self::clean_text($meta['software_version'] ?? '', 50),
            ], function ($value) { return is_string($value) && $value !== ''; }),
        ]);
        if (!$saved) {
            return Hm_MCP_Http_Response::oauth_error(500, 'server_error', 'The client could not be registered.');
        }
        /* public clients only: any other authentication method requested is replaced by none */
        $res = [
            'client_id' => $client_id,
            'client_id_issued_at' => $store->now(),
            'client_name' => $name,
            'redirect_uris' => $uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ];
        if ($client_uri) {
            $res['client_uri'] = $client_uri;
        }
        return Hm_MCP_Http_Response::json($res, 201, ['Pragma' => 'no-cache']);
    }

    /**
     * Redirect URIs must be HTTPS, HTTP on a loopback host (RFC 8252 section 7.3)
     * or a private-use scheme of a native app (RFC 8252 section 7.1)
     * @param mixed $uri candidate redirect URI
     * @return bool
     */
    public static function valid_redirect_uri($uri) {
        if (!is_string($uri) || $uri === '' || strlen($uri) > 2000 || preg_match('/[\x00-\x20\x7F"\'<>\\\\^`{|}#]/', $uri)) {
            return false;
        }
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['scheme']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'https') {
            return !empty($parts['host']) && self::valid_host($parts['host']);
        }
        if ($scheme === 'http') {
            return !empty($parts['host']) && Hm_MCP_Config::is_loopback_host($parts['host']);
        }
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) && !in_array($scheme, self::BLOCKED_SCHEMES, true);
    }

    /**
     * @param string $host host name from parse_url
     * @return bool
     */
    private static function valid_host($host) {
        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $host)
            || (bool) preg_match('/^\[[0-9a-f:.]+\]$/i', $host);
    }

    /**
     * Compare a requested redirect URI with a registered one. Loopback redirects
     * may use any port (RFC 8252 section 7.3), everything else must match exactly.
     * @param string $registered registered URI
     * @param string $requested requested URI
     * @return bool
     */
    public static function redirect_matches($registered, $requested) {
        if ($registered === $requested) {
            return true;
        }
        $a = parse_url($registered);
        $b = parse_url($requested);
        if (!is_array($a) || !is_array($b) || strtolower($a['scheme'] ?? '') !== 'http' || strtolower($b['scheme'] ?? '') !== 'http') {
            return false;
        }
        if (!Hm_MCP_Config::is_loopback_host($a['host'] ?? '') || strtolower($a['host']) !== strtolower($b['host'] ?? '')) {
            return false;
        }
        return ($a['path'] ?? '') === ($b['path'] ?? '') && ($a['query'] ?? '') === ($b['query'] ?? '')
            && !isset($b['user']) && !isset($b['pass']) && !isset($b['fragment']);
    }

    /* -------------------------------------------------------- authorization */

    /**
     * Authorization endpoint: sign in page, then consent page
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    public function authorize($request) {
        if (!in_array($request->method, ['GET', 'POST'], true)) {
            return Hm_MCP_Http_Response::error(405, 'method_not_allowed', 'Method not allowed', ['Allow' => 'GET, POST']);
        }
        if (!$this->store()->available()) {
            return $this->error_page('unavailable', 503);
        }
        if ($request->method === 'GET') {
            $req = $this->check_request($request->query, $request);
            if (isset($req['page'])) {
                return $req['page'];
            }
            if (isset($req['error'])) {
                return $this->redirect_error($req['redirect_uri'], $req['error'], $req['description'], $req['state']);
            }
            return $this->login_page($req);
        }
        if (!$this->same_origin($request)) {
            return $this->error_page('cross_site', 403);
        }
        $form = $request->form();
        if (self::param($form, 'step') === 'consent') {
            return $this->consent($form);
        }
        return $this->login($request, $form);
    }

    /**
     * Validate an authorization request (RFC 6749 section 4.1.1, RFC 7636, RFC 8707)
     * @param array $params request parameters
     * @param Hm_MCP_Http_Request $request request details
     * @return array the request, ['page' => response] when the user cannot be sent
     *               back to the client, or ['error', 'description', 'redirect_uri', 'state']
     */
    private function check_request($params, $request) {
        $client_id = self::param($params, 'client_id', 255);
        $client = $client_id ? $this->find_client($client_id, $request, true) : false;
        if (!$client) {
            $unavailable = $client_id && self::is_cimd_id($client_id) && $this->cimd_host_allowed($client_id);
            return ['page' => $this->error_page($unavailable ? 'client_unavailable' : 'unknown_client', 400)];
        }
        $redirect_param = self::param($params, 'redirect_uri', 2000);
        if ($redirect_param === null) {
            return ['page' => $this->error_page('bad_redirect', 400)];
        }
        $redirect_uri = $redirect_param;
        if ($redirect_uri === '') {
            if (count($client['redirect_uris']) !== 1) {
                return ['page' => $this->error_page('no_redirect', 400)];
            }
            $redirect_uri = (string) $client['redirect_uris'][0];
        }
        $registered = false;
        foreach ($client['redirect_uris'] as $uri) {
            if (self::redirect_matches((string) $uri, $redirect_uri)) {
                $registered = true;
                break;
            }
        }
        if (!$registered || !self::valid_redirect_uri($redirect_uri)) {
            return ['page' => $this->error_page('bad_redirect', 400)];
        }
        $state = self::param($params, 'state', self::MAX_STATE_LENGTH);
        $fail = function ($error, $description) use ($redirect_uri, $state) {
            return ['error' => $error, 'description' => $description, 'redirect_uri' => $redirect_uri, 'state' => (string) $state];
        };
        if ($state === null) {
            return $fail('invalid_request', 'The state parameter is too long.');
        }
        if (self::param($params, 'response_type') !== 'code') {
            return $fail('unsupported_response_type', 'Only the authorization code flow is supported.');
        }
        $challenge = (string) self::param($params, 'code_challenge', 128);
        if ($challenge === '') {
            return $fail('invalid_request', 'PKCE is required: send code_challenge with code_challenge_method S256.');
        }
        if (self::param($params, 'code_challenge_method') !== 'S256') {
            return $fail('invalid_request', 'Only the S256 code challenge method is supported.');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge)) {
            return $fail('invalid_request', 'The code challenge is not a valid S256 value.');
        }
        $resource = self::param($params, 'resource', 2000);
        if ($resource === null || ($resource !== '' && !$this->resource_matches($resource))) {
            return $fail('invalid_target', 'This server only issues tokens for '.$this->config->resource());
        }
        $scope = self::param($params, 'scope', 2000);
        return [
            'client' => $client,
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'redirect_uri_given' => $redirect_param !== '',
            'state' => $state,
            'code_challenge' => $challenge,
            'scope' => self::requested_scope((string) $scope),
            'resource' => $this->config->resource(),
            /* original values, posted again by the sign in form */
            'params' => [
                'response_type' => 'code',
                'client_id' => $client_id,
                'redirect_uri' => $redirect_param,
                'state' => $state,
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
                'scope' => (string) $scope,
                'resource' => $resource,
            ],
        ];
    }

    /**
     * Requested permissions as a normalized scope string. No scope, or no
     * known scope, asks for everything the user allows.
     * @param string $scope space separated scopes
     * @return string
     */
    public static function requested_scope($scope) {
        $permissions = Hm_MCP_Permissions::from_scope($scope);
        if (!array_filter($permissions)) {
            $permissions = array_fill_keys(Hm_MCP_Permissions::keys(), true);
        }
        return Hm_MCP_Permissions::to_scope($permissions);
    }

    /**
     * @param string $resource requested resource indicator
     * @return bool
     */
    private function resource_matches($resource) {
        return rtrim($resource, '/') === rtrim($this->config->resource(), '/');
    }

    /**
     * Sign in step
     * @param Hm_MCP_Http_Request $request request details
     * @param array $form posted values
     * @return Hm_MCP_Http_Response
     */
    private function login($request, $form) {
        $req = $this->check_request($form, $request);
        if (isset($req['page'])) {
            return $req['page'];
        }
        if (isset($req['error'])) {
            return $this->redirect_error($req['redirect_uri'], $req['error'], $req['description'], $req['state']);
        }
        if (self::param($form, 'decision') === 'deny') {
            return $this->redirect_error($req['redirect_uri'], 'access_denied', 'The user cancelled the request.', $req['state']);
        }
        $username = rtrim((string) self::param($form, 'username', 250));
        $password = is_string($form['password'] ?? null) && strlen($form['password']) <= 1024 ? $form['password'] : '';
        if ($username === '' || $password === '') {
            return $this->login_page($req, 'missing_login', $username);
        }
        $store = $this->store();
        $rate_key = 'password:'.$username;
        if (!$store->rate_limit('login_ip:'.$this->client_ip($request), self::LOGIN_IP_ATTEMPTS, self::LOGIN_WINDOW)
            || !$store->rate_limit($rate_key, self::LOGIN_ATTEMPTS, self::LOGIN_WINDOW)) {
            return $this->login_page($req, 'rate_limited', $username, 429);
        }
        if (!$this->check_credentials($username, $password)) {
            return $this->login_page($req, 'invalid_login', $username);
        }
        $user_config = $this->load_user_config($username, $password);
        if (!$user_config) {
            return $this->login_page($req, 'settings_locked', $username);
        }
        if ($this->two_factor_required($user_config)) {
            $code = preg_replace('/\s+/', '', (string) self::param($form, 'code', 32));
            if ($code === '') {
                return $this->login_page($req, 'code_required', $username);
            }
            if (!$this->two_factor_valid($user_config, $username, $code)) {
                return $this->login_page($req, 'code_invalid', $username);
            }
        }
        $store->rate_reset($rate_key);
        $settings = $store->settings($username);
        if (!$settings['enabled']) {
            return $this->login_page($req, 'disabled', $username);
        }
        $name = $req['client']['client_name'] !== '' ? $req['client']['client_name'] : 'OAuth app';
        try {
            list($id, $key) = $store->create_connection($username, 'oauth', $name, $password,
                ['client_id' => $req['client_id'], 'status' => 'pending']);
            $ticket = $store->issue_token($id, 'consent', $key, self::CONSENT_TTL, self::request_data($req));
        } catch (Exception $e) {
            Hm_Debug::add('MCP authorization failed: '.$e->getMessage(), 'warning');
            return $this->error_page('failed', 500);
        }
        return $this->consent_page($req, $username, $user_config, $settings, $ticket);
    }

    /**
     * Consent step
     * @param array $form posted values
     * @return Hm_MCP_Http_Response
     */
    private function consent($form) {
        $store = $this->store();
        $ticket = (string) self::param($form, 'ticket', 200);
        $found = $ticket !== '' ? $store->find_token($ticket, ['consent']) : false;
        $connection = $found ? $store->connection($found['connection_id']) : false;
        if (!$connection || $connection['kind'] !== 'oauth' || $connection['status'] !== 'pending') {
            return $this->error_page('expired', 400);
        }
        $req = (array) $found['data'];
        $client = $this->find_client((string) ($req['client_id'] ?? ''), null, false);
        if (!$client) {
            $store->delete_connection($connection['id']);
            return $this->error_page('unknown_client', 400);
        }
        $req['client'] = $client;
        $state = (string) ($req['state'] ?? '');
        if (self::param($form, 'decision') !== 'allow') {
            if ($store->mark_token_used($found['hash'])) {
                $store->delete_connection($connection['id']);
            }
            return $this->redirect_error($req['redirect_uri'], 'access_denied', 'The user denied the request.', $state);
        }
        $username = $connection['username'];
        $settings = $store->settings($username);
        $password = $store->open_password($connection, $found['key']);
        $user_config = $password === false ? false : $this->load_user_config($username, $password);
        if (!$settings['enabled'] || !$user_config) {
            $store->mark_token_used($found['hash']);
            $store->delete_connection($connection['id']);
            return $this->redirect_error($req['redirect_uri'], 'access_denied', 'Access is not available for this account.', $state);
        }
        $offered = self::offered_permissions($req['scope'], $settings);
        $chosen = array_values(array_intersect($offered, self::string_list($form['permissions'] ?? [])));
        $allowed = self::allowed_accounts($user_config, $settings);
        $chosen_accounts = array_values(array_intersect(array_column($allowed, 'id'), self::string_list($form['accounts'] ?? [])));
        $error = '';
        if ($offered && !$chosen) {
            $error = 'no_permission';
        } elseif ($allowed && !$chosen_accounts) {
            $error = 'no_account';
        }
        if ($error) {
            return $this->consent_page($req, $username, $user_config, $settings, $ticket, $error, $chosen, $chosen_accounts);
        }
        if (!$store->mark_token_used($found['hash'])) {
            return $this->error_page('expired', 400);
        }
        /* choosing everything that is offered keeps following the global settings */
        $everything = $req['scope'] === self::requested_scope('');
        $permissions = $everything && count($chosen) === count($offered) ? null : Hm_MCP_Permissions::from_keys($chosen);
        $accounts = count($chosen_accounts) === count($allowed) ? null : $chosen_accounts;
        try {
            $store->update_connection($connection['id'], ['permissions' => $permissions, 'accounts' => $accounts]);
            $data = self::request_data($req);
            unset($data['state']);
            $code = $store->issue_token($connection['id'], 'code', $found['key'], self::CODE_TTL, $data);
        } catch (Exception $e) {
            Hm_Debug::add('MCP authorization failed: '.$e->getMessage(), 'warning');
            return $this->error_page('failed', 500);
        }
        return $this->redirect_result($req['redirect_uri'], ['code' => $code, 'state' => $state]);
    }

    /**
     * @param array $req validated request
     * @return array values kept with the consent ticket and the authorization code
     */
    private static function request_data($req) {
        return [
            'client_id' => $req['client_id'],
            'redirect_uri' => $req['redirect_uri'],
            'redirect_uri_given' => (bool) $req['redirect_uri_given'],
            'state' => (string) $req['state'],
            'code_challenge' => $req['code_challenge'],
            'scope' => $req['scope'],
            'resource' => $req['resource'],
        ];
    }

    /**
     * Permission keys that are requested and allowed by the global settings
     * @param string $scope requested scope
     * @param array $settings user settings
     * @return array
     */
    private static function offered_permissions($scope, $settings) {
        $requested = Hm_MCP_Permissions::from_scope($scope);
        $global = Hm_MCP_Permissions::normalize($settings['permissions']);
        $res = [];
        foreach (Hm_MCP_Permissions::keys() as $key) {
            if ($requested[$key] && $global[$key]) {
                $res[] = $key;
            }
        }
        return $res;
    }

    /**
     * Accounts allowed by the global settings
     * @param object $user_config user settings
     * @param array $settings MCP settings
     * @return array account list entries
     */
    private static function allowed_accounts($user_config, $settings) {
        $accounts = Hm_MCP_Permissions::account_list($user_config->get('imap_servers', []));
        $allowed = Hm_MCP_Permissions::effective_accounts($settings['accounts'], null, array_column($accounts, 'id'));
        return array_values(array_filter($accounts, function ($account) use ($allowed) {
            return in_array($account['id'], $allowed, true);
        }));
    }

    /**
     * @param mixed $values submitted list
     * @return array strings
     */
    private static function string_list($values) {
        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }

    /**
     * Send the authorization response with the issuer (RFC 9207)
     * @param string $redirect_uri validated redirect URI
     * @param array $params response parameters
     * @return Hm_MCP_Http_Response
     */
    private function redirect_result($redirect_uri, $params) {
        $params = array_filter($params, function ($value) { return $value !== null && $value !== ''; });
        $params['iss'] = $this->config->issuer();
        $url = $redirect_uri.(strpos($redirect_uri, '?') === false ? '?' : '&').http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return Hm_MCP_Http_Response::redirect($url)->with_header('Referrer-Policy', 'no-referrer');
    }

    /**
     * @param string $redirect_uri validated redirect URI
     * @param string $error OAuth error code
     * @param string $description error description
     * @param string $state client state
     * @return Hm_MCP_Http_Response
     */
    private function redirect_error($redirect_uri, $error, $description, $state) {
        return $this->redirect_result($redirect_uri, ['error' => $error, 'error_description' => $description, 'state' => $state]);
    }

    /**
     * Form posts must come from this site
     * @param Hm_MCP_Http_Request $request request details
     * @return bool
     */
    private function same_origin($request) {
        $site = strtolower($request->header('sec-fetch-site'));
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }
        $origin = $request->header('origin');
        if ($origin === '') {
            return true;
        }
        $parts = parse_url($origin);
        if (!is_array($parts) || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        return $this->config->host_allowed(strtolower($parts['host']));
    }

    /* ----------------------------------------------------------------- pages */

    /**
     * @param object|null $user_config user settings, for the language
     * @return Hm_MCP_OAuth_Page
     */
    private function page($user_config = null) {
        $lang = $this->site_config->get('default_language', $this->site_config->get('default_setting_language', 'en'));
        if ($user_config) {
            $lang = $user_config->get('language_setting', $lang);
        }
        return new Hm_MCP_OAuth_Page($lang, (string) $this->site_config->get('app_name', 'Cypht'));
    }

    /**
     * @param string $message key of MESSAGES
     * @param int $status HTTP status
     * @return Hm_MCP_Http_Response
     */
    private function error_page($message, $status) {
        $page = $this->page();
        $body = '<div class="error" role="alert">'.$page->text(self::MESSAGES[$message]).'</div>'.
            '<p class="muted">'.$page->text('Close this window and try again from the app.').'</p>';
        return $page->render($page->trans('The app could not be connected'), $body, [], $status);
    }

    /**
     * @param Hm_MCP_OAuth_Page $page page
     * @param array $client registered client
     * @return string escaped client name
     */
    private static function client_label($page, $client) {
        return $client['client_name'] !== '' ? Hm_MCP_OAuth_Page::esc($client['client_name']) : $page->text('An unnamed app');
    }

    /**
     * Where the browser goes after the flow, shown to help users spot impostors
     * @param Hm_MCP_OAuth_Page $page page
     * @param string $uri redirect URI
     * @return string escaped label
     */
    private static function redirect_label($page, $uri) {
        $parts = parse_url($uri);
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme === 'https') {
            return Hm_MCP_OAuth_Page::esc(strtolower($parts['host']).(isset($parts['port']) ? ':'.(int) $parts['port'] : ''));
        }
        if ($scheme === 'http') {
            return $page->text('this computer').' ('.Hm_MCP_OAuth_Page::esc(strtolower($parts['host']).(isset($parts['port']) ? ':'.(int) $parts['port'] : '')).')';
        }
        return sprintf($page->text('the %s app'), Hm_MCP_OAuth_Page::esc($scheme));
    }

    /**
     * CSP source that allows the redirect after a form submission
     * @param string $uri validated redirect URI
     * @return string
     */
    private static function form_target($uri) {
        $parts = parse_url($uri);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (in_array($scheme, ['http', 'https'], true)) {
            if ($host === '' || $host[0] === '[') {
                /* CSP host sources cannot express IPv6 literals */
                return $scheme.':';
            }
            return $scheme.'://'.$host.(isset($parts['port']) ? ':'.(int) $parts['port'] : '');
        }
        return $scheme.':';
    }

    /**
     * @param array $params hidden form values
     * @return string HTML
     */
    private static function hidden_fields($params) {
        $res = '';
        foreach ($params as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $res .= '<input type="hidden" name="'.Hm_MCP_OAuth_Page::esc($name).'" value="'.Hm_MCP_OAuth_Page::esc($value).'">';
        }
        return $res;
    }

    /**
     * Sign in page
     * @param array $req validated request
     * @param string $error key of MESSAGES
     * @param string $username username to fill in
     * @param int $status HTTP status
     * @return Hm_MCP_Http_Response
     */
    private function login_page($req, $error = '', $username = '', $status = 200) {
        $page = $this->page();
        $body = '<p>'.sprintf($page->text('%s wants to use your mail.'), '<strong>'.self::client_label($page, $req['client']).'</strong>').'</p>'.
            '<p class="muted">'.sprintf($page->text('After you sign in, you will return to %s.'), '<strong>'.self::redirect_label($page, $req['redirect_uri']).'</strong>').'</p>';
        if ($error) {
            $body .= '<div class="error" role="alert">'.$page->text(self::MESSAGES[$error]).'</div>';
        }
        $body .= '<form method="post" action="'.Hm_MCP_OAuth_Page::esc($this->config->url('/oauth/authorize')).'">'.
            self::hidden_fields($req['params'] + ['step' => 'login']).
            '<label for="mcp_username">'.$page->text('Username').'</label>'.
            '<input type="text" id="mcp_username" name="username" value="'.Hm_MCP_OAuth_Page::esc($username).'" autocomplete="username" '.
            'autocapitalize="none" spellcheck="false" required'.($username === '' ? ' autofocus' : '').'>'.
            '<label for="mcp_password">'.$page->text('Password').'</label>'.
            '<input type="password" id="mcp_password" name="password" autocomplete="current-password" required'.($username !== '' ? ' autofocus' : '').'>';
        if ($this->two_factor_available()) {
            $body .= '<label for="mcp_code">'.$page->text('Two-factor code').'</label>'.
                '<input type="text" id="mcp_code" name="code" inputmode="numeric" autocomplete="one-time-code" aria-describedby="mcp_code_hint">'.
                '<p class="hint" id="mcp_code_hint">'.$page->text('Only if two-factor authentication is turned on for your account.').'</p>';
        }
        $body .= '<div class="actions"><button type="submit" name="decision" value="allow">'.$page->text('Continue').'</button>'.
            '<button type="submit" name="decision" value="deny" class="secondary" formnovalidate>'.$page->text('Cancel').'</button></div></form>';
        return $page->render($page->trans('Sign in to connect an app'), $body, [self::form_target($req['redirect_uri'])], $status);
    }

    /**
     * Consent page
     * @param array $req request values
     * @param string $username signed in user
     * @param object $user_config user settings
     * @param array $settings MCP settings
     * @param string $ticket consent ticket
     * @param string $error key of MESSAGES
     * @param array|null $checked checked permission keys, null for all
     * @param array|null $checked_accounts checked account ids, null for all
     * @return Hm_MCP_Http_Response
     */
    private function consent_page($req, $username, $user_config, $settings, $ticket, $error = '', $checked = null, $checked_accounts = null) {
        $page = $this->page($user_config);
        $body = '<p>'.sprintf($page->text('Allow %s to use your mail?'), '<strong>'.self::client_label($page, $req['client']).'</strong>').'</p>'.
            '<p class="muted">'.sprintf($page->text('Signed in as %s.'), '<strong>'.Hm_MCP_OAuth_Page::esc($username).'</strong>').' '.
            sprintf($page->text('You will return to %s.'), '<strong>'.self::redirect_label($page, $req['redirect_uri']).'</strong>').'</p>';
        if ($error) {
            $body .= '<div class="error" role="alert">'.$page->text(self::MESSAGES[$error]).'</div>';
        }
        $body .= '<form method="post" action="'.Hm_MCP_OAuth_Page::esc($this->config->url('/oauth/authorize')).'">'.
            self::hidden_fields(['step' => 'consent', 'ticket' => $ticket]).
            '<fieldset><legend>'.$page->text('It will be able to').'</legend>';
        $offered = self::offered_permissions($req['scope'], $settings);
        foreach ($offered as $index => $key) {
            $id = 'mcp_perm_'.$index;
            $body .= '<div class="check"><input type="checkbox" id="'.$id.'" name="permissions[]" value="'.Hm_MCP_OAuth_Page::esc($key).'"'.
                ($checked === null || in_array($key, $checked, true) ? ' checked' : '').'>'.
                '<label for="'.$id.'">'.$page->text(Hm_MCP_Permissions::label($key)).
                '<small>'.$page->text(Hm_MCP_Permissions::description($key)).'</small></label></div>';
        }
        if (!$offered) {
            $body .= '<p class="hint">'.$page->text('Nothing is turned on in your settings yet. The app will not be able to do anything until you turn on permissions in Settings, API and MCP.').'</p>';
        }
        $body .= '</fieldset><fieldset><legend>'.$page->text('Accounts').'</legend>';
        $accounts = self::allowed_accounts($user_config, $settings);
        foreach ($accounts as $index => $account) {
            $id = 'mcp_account_'.$index;
            $label = $account['name'] !== '' ? $account['name'] : $account['user'];
            $detail = $account['user'] !== '' && $account['user'] !== $label ? '<small>'.Hm_MCP_OAuth_Page::esc($account['user']).'</small>' : '';
            if ($account['hidden']) {
                $detail .= '<small>'.$page->text('hidden').'</small>';
            }
            $body .= '<div class="check"><input type="checkbox" id="'.$id.'" name="accounts[]" value="'.Hm_MCP_OAuth_Page::esc($account['id']).'"'.
                ($checked_accounts === null || in_array($account['id'], $checked_accounts, true) ? ' checked' : '').'>'.
                '<label for="'.$id.'">'.Hm_MCP_OAuth_Page::esc($label).$detail.'</label></div>';
        }
        if (!$accounts) {
            $body .= '<p class="hint">'.$page->text('No email accounts are configured yet.').'</p>';
        }
        $body .= '</fieldset><p class="hint">'.$page->text('Only permissions that are turned on in Settings, API and MCP are offered. You can change or remove this access there at any time.').'</p>'.
            '<div class="actions"><button type="submit" name="decision" value="allow">'.$page->text('Allow').'</button>'.
            '<button type="submit" name="decision" value="deny" class="secondary">'.$page->text('Deny').'</button></div></form>';
        return $page->render($page->trans('Connect an app to your mail'), $body, [self::form_target($req['redirect_uri'])]);
    }

    /* ------------------------------------------------- client metadata (CIMD) */

    /**
     * @return array hosts allowed to publish client metadata documents, empty when CIMD is off
     */
    private function cimd_hosts() {
        return $this->config->list_setting('cimd_allowed_hosts');
    }

    /**
     * A client id that is the URL of a client metadata document: HTTPS, with a path,
     * without credentials, fragment or dot segments
     * @param mixed $client_id client id
     * @return bool
     */
    public static function is_cimd_id($client_id) {
        if (!is_string($client_id) || strlen($client_id) > 255 || strpos($client_id, 'https://') !== 0) {
            return false;
        }
        $parts = parse_url($client_id);
        if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path === '/' || preg_match('#(^|/)\.\.?(/|$)#', $path) || preg_match('/[\x00-\x20\x7F"<>\\^`{|}]/', $client_id)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $client_id client metadata URL
     * @return bool the host may publish client metadata documents
     */
    private function cimd_host_allowed($client_id) {
        $host = strtolower(rtrim((string) parse_url($client_id, PHP_URL_HOST), '.'));
        return $host !== '' && Hm_MCP_Config::host_in_list($host, $this->cimd_hosts());
    }

    /**
     * A registered client, or a client described by a metadata document
     * @param string $client_id client id
     * @param Hm_MCP_Http_Request|null $request request, for the fetch rate limit
     * @param bool $refresh fetch the document again when the stored copy is old
     * @return array|false
     */
    private function find_client($client_id, $request, $refresh) {
        $store = $this->store();
        if (!self::is_cimd_id($client_id)) {
            $client = $store->client($client_id);
            return $client && $client['kind'] !== 'cimd' ? $client : false;
        }
        if (!$this->cimd_host_allowed($client_id)) {
            return false;
        }
        $client = $store->client($client_id);
        $client = $client && $client['kind'] === 'cimd' ? $client : false;
        $age = $client ? $store->now() - (int) ($client['metadata']['fetched_at'] ?? 0) : null;
        if ($client && (!$refresh || $age < self::CIMD_TTL)) {
            return $client;
        }
        if (!$refresh || !$request) {
            return false;
        }
        $fresh = $this->fetch_cimd($client_id, $request);
        if ($fresh) {
            return $fresh;
        }
        /* a short outage of the publisher does not break existing apps */
        return $client && $age < self::CIMD_MAX_STALE ? $client : false;
    }

    /**
     * Fetch, check and store a client metadata document
     * @param string $client_id document URL
     * @param Hm_MCP_Http_Request $request request details
     * @return array|false client
     */
    private function fetch_cimd($client_id, $request) {
        $store = $this->store();
        if (!$store->rate_limit('cimd:'.$this->client_ip($request), self::CIMD_FETCHES, self::CIMD_WINDOW)) {
            return false;
        }
        try {
            list($status, $headers, $body) = $this->services->uploads()->get_document($client_id, $this->cimd_hosts(),
                self::CIMD_MAX_BYTES, self::CIMD_TIMEOUT);
        } catch (Hm_MCP_Error $e) {
            Hm_Debug::add('MCP client metadata fetch failed: '.$e->getMessage(), 'warning');
            return false;
        }
        if ($status !== 200) {
            Hm_Debug::add(sprintf('MCP client metadata fetch returned HTTP %d', $status), 'warning');
            return false;
        }
        $document = json_decode((string) $body, true);
        $client = self::cimd_client($client_id, $document, $store->now());
        if (!$client || !$store->save_client($client)) {
            Hm_Debug::add('MCP client metadata document rejected', 'warning');
            return false;
        }
        return $store->client($client_id);
    }

    /**
     * Check a client metadata document (draft-ietf-oauth-client-id-metadata-document)
     * @param string $client_id URL the document was fetched from
     * @param mixed $document decoded JSON
     * @param int $now current time
     * @return array|false client to store
     */
    public static function cimd_client($client_id, $document, $now) {
        if (!is_array($document) || !$document || array_is_list($document) || ($document['client_id'] ?? null) !== $client_id) {
            return false;
        }
        $uris = $document['redirect_uris'] ?? null;
        if (!is_array($uris) || !$uris || count($uris) > self::MAX_REDIRECT_URIS) {
            return false;
        }
        foreach ($uris as $uri) {
            if (!self::valid_redirect_uri($uri)) {
                return false;
            }
        }
        foreach (['grant_types' => 'authorization_code', 'response_types' => 'code'] as $field => $needed) {
            if (array_key_exists($field, $document) && (!is_array($document[$field]) || !in_array($needed, $document[$field], true))) {
                return false;
            }
        }
        /* public clients only: the document must allow "none" */
        $methods = $document['token_endpoint_auth_methods_supported'] ?? null;
        $method = $document['token_endpoint_auth_method'] ?? 'none';
        if (is_array($methods) ? !in_array('none', $methods, true) : $method !== 'none') {
            return false;
        }
        $name = self::clean_text($document['client_name'] ?? '', 100);
        $client_uri = is_string($document['client_uri'] ?? null) && strpos($document['client_uri'], 'https://') === 0
            && Hm_MCP_Config::valid_public_url(rtrim($document['client_uri'], '/')) ? $document['client_uri'] : '';
        return [
            'client_id' => $client_id,
            'kind' => 'cimd',
            'client_name' => $name !== '' ? $name : strtolower((string) parse_url($client_id, PHP_URL_HOST)),
            'redirect_uris' => array_values(array_unique($uris)),
            'metadata' => array_filter(['fetched_at' => (int) $now, 'client_uri' => $client_uri]),
        ];
    }

    /* ---------------------------------------------------------------- tokens */

    /**
     * Token endpoint
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    public function token($request) {
        $store = $this->store();
        if (!$store->available()) {
            return self::token_error(503, 'temporarily_unavailable', 'The authorization server storage is not available.');
        }
        $params = self::body_params($request);
        $client_id = self::param($params, 'client_id', 255);
        $basic = self::basic_client_id($request);
        if ($basic !== null) {
            if ($client_id && $client_id !== $basic) {
                return self::token_error(400, 'invalid_request', 'The client_id does not match the client credentials.');
            }
            $client_id = $basic;
        }
        $client = $client_id ? $this->find_client($client_id, $request, false) : false;
        if (!$client) {
            return self::token_error(401, 'invalid_client', 'The client is not registered with this server.');
        }
        $resource = self::param($params, 'resource', 2000);
        if ($resource === null || ($resource !== '' && !$this->resource_matches($resource))) {
            return self::token_error(400, 'invalid_target', 'This server only issues tokens for '.$this->config->resource());
        }
        switch (self::param($params, 'grant_type', 64)) {
            case 'authorization_code':
                return $this->exchange_code($params, $client);
            case 'refresh_token':
                return $this->refresh($params, $client);
            case '':
            case null:
                return self::token_error(400, 'invalid_request', 'The grant_type parameter is required.');
        }
        return self::token_error(400, 'unsupported_grant_type', 'Only authorization_code and refresh_token are supported.');
    }

    /**
     * Exchange an authorization code
     * @param array $params request values
     * @param array $client registered client
     * @return Hm_MCP_Http_Response
     */
    private function exchange_code($params, $client) {
        $store = $this->store();
        $found = $store->find_token((string) self::param($params, 'code', 200), ['code'], true);
        if (!$found) {
            return self::token_error(400, 'invalid_grant', 'The authorization code is invalid or expired.');
        }
        if ($found['used_at'] > 0 || !$store->mark_token_used($found['hash'])) {
            /* a code used twice may have been stolen: revoke what it produced (RFC 6749 section 4.1.2) */
            $this->revoke_grant($found['connection_id'], 'code_reuse');
            return self::token_error(400, 'invalid_grant', 'The authorization code was already used.');
        }
        $data = (array) $found['data'];
        if (($data['client_id'] ?? '') !== $client['client_id']) {
            return self::token_error(400, 'invalid_grant', 'The authorization code was issued to another client.');
        }
        $redirect_uri = self::param($params, 'redirect_uri', 2000);
        if ((!empty($data['redirect_uri_given']) || $redirect_uri !== '') && $redirect_uri !== ($data['redirect_uri'] ?? '')) {
            return self::token_error(400, 'invalid_grant', 'The redirect_uri does not match the authorization request.');
        }
        $verifier = (string) self::param($params, 'code_verifier', 128);
        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)
            || !Hm_MCP_Crypto::equals((string) ($data['code_challenge'] ?? ''), Hm_MCP_Crypto::pkce_s256($verifier))) {
            return self::token_error(400, 'invalid_grant', 'The code_verifier does not match the code challenge.');
        }
        $connection = $store->connection($found['connection_id']);
        if (!$connection || $connection['kind'] !== 'oauth' || $connection['status'] !== 'pending') {
            return self::token_error(400, 'invalid_grant', 'The authorization is no longer valid.');
        }
        $store->update_connection($connection['id'], ['status' => 'active', 'last_used_at' => $store->now()]);
        $store->touch_client($client['client_id']);
        $this->log($connection, 'authorize', 'ok', [
            'client' => $connection['name'],
            'permissions' => $connection['permissions'] === null ? 'global' : implode(', ', array_keys(array_filter($connection['permissions']))),
            'accounts' => $connection['accounts'] === null ? 'global' : count($connection['accounts']),
        ]);
        return $this->issue_tokens($connection, $found['key'], $client);
    }

    /**
     * Rotate a refresh token
     * @param array $params request values
     * @param array $client registered client
     * @return Hm_MCP_Http_Response
     */
    private function refresh($params, $client) {
        $store = $this->store();
        $found = $store->find_token((string) self::param($params, 'refresh_token', 200), ['refresh'], true);
        if (!$found || (($found['data']['client_id'] ?? '') !== $client['client_id'])) {
            return self::token_error(400, 'invalid_grant', 'The refresh token is invalid or expired.');
        }
        $connection = $store->connection($found['connection_id']);
        if (!$connection || $connection['kind'] !== 'oauth' || $connection['status'] === 'pending') {
            return self::token_error(400, 'invalid_grant', 'The refresh token is invalid or expired.');
        }
        if ($connection['status'] === 'reauth') {
            $this->revoke_grant($connection['id'], 'password_changed');
            return self::token_error(400, 'invalid_grant', 'The Cypht password changed. Connect again to restore access.');
        }
        if ($found['used_at'] > 0 || !$store->mark_token_used($found['hash'])) {
            $used_at = $found['used_at'] ?: $store->now();
            if ($store->now() - $used_at > self::REFRESH_GRACE) {
                /* replay of an old refresh token: assume it was stolen and end the connection */
                $this->revoke_grant($connection['id'], 'refresh_reuse');
                return self::token_error(400, 'invalid_grant', 'The refresh token was already used.');
            }
        }
        return $this->issue_tokens($connection, $found['key'], $client);
    }

    /**
     * Issue an access token and a refresh token
     * @param array $connection connection
     * @param string $key connection key
     * @param array $client registered client
     * @return Hm_MCP_Http_Response
     */
    private function issue_tokens($connection, $key, $client) {
        $store = $this->store();
        $settings = $store->settings($connection['username']);
        $scope = Hm_MCP_Permissions::to_scope(Hm_MCP_Permissions::effective($settings['permissions'], $connection['permissions']));
        $access_ttl = $this->config->int('access_token_ttl', 3600, 60);
        $refresh_ttl = $this->config->int('refresh_token_ttl', 2592000, 3600);
        $data = ['client_id' => $client['client_id'], 'resource' => $this->config->resource(), 'scope' => $scope];
        try {
            $access = $store->issue_token($connection['id'], 'access', $key, $access_ttl, $data);
            $refresh = $store->issue_token($connection['id'], 'refresh', $key, $refresh_ttl, $data);
        } catch (Exception $e) {
            return self::token_error(500, 'server_error', 'The tokens could not be issued.');
        }
        $res = ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => $access_ttl, 'refresh_token' => $refresh];
        if ($scope !== '') {
            $res['scope'] = $scope;
        }
        return Hm_MCP_Http_Response::json($res, 200, ['Pragma' => 'no-cache']);
    }

    /**
     * Token revocation (RFC 7009). Revoking a refresh token ends the connection.
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    public function revoke($request) {
        $store = $this->store();
        if (!$store->available()) {
            return self::token_error(503, 'temporarily_unavailable', 'The authorization server storage is not available.');
        }
        $params = self::body_params($request);
        $client_id = self::basic_client_id($request) ?? (string) self::param($params, 'client_id', 255);
        $found = $store->find_token((string) self::param($params, 'token', 200), ['access', 'refresh'], true);
        if ($found && ($client_id === '' || $client_id === ($found['data']['client_id'] ?? ''))) {
            if ($found['kind'] === 'refresh') {
                $this->revoke_grant($found['connection_id'], 'client');
            } else {
                $store->delete_token($found['hash']);
            }
        }
        return new Hm_MCP_Http_Response(200, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    /**
     * End an OAuth connection and everything issued for it
     * @param string $connection_id connection id
     * @param string $reason code_reuse, refresh_reuse, password_changed or client
     * @return void
     */
    private function revoke_grant($connection_id, $reason) {
        $store = $this->store();
        $connection = $store->connection($connection_id);
        if (!$connection || $connection['kind'] !== 'oauth') {
            return;
        }
        if ($connection['status'] !== 'pending') {
            $this->log($connection, 'revoke', $reason === 'client' ? 'ok' : 'denied', ['reason' => $reason]);
        }
        $store->delete_connection($connection_id);
    }

    /**
     * @param array $connection connection
     * @param string $operation authorize or revoke
     * @param string $outcome ok, denied or error
     * @param array $summary details without secrets
     * @return void
     */
    private function log($connection, $operation, $outcome, $summary) {
        $this->store()->log_activity([
            'username' => $connection['username'],
            'connection_id' => $connection['id'],
            'connection_name' => $connection['name'],
            'channel' => 'oauth',
            'operation' => $operation,
            'outcome' => $outcome,
            'summary' => $summary,
        ]);
    }

    /**
     * Token endpoint error (RFC 6749 section 5.2)
     * @param int $status HTTP status
     * @param string $error error code
     * @param string $description description
     * @return Hm_MCP_Http_Response
     */
    private static function token_error($status, $error, $description) {
        return Hm_MCP_Http_Response::oauth_error($status, $error, $description, ['Pragma' => 'no-cache']);
    }

    /* --------------------------------------------------------------- helpers */

    /**
     * @param array $values request values
     * @param string $name parameter name
     * @param int $max longest accepted value
     * @return string|null '' when missing, null when not a string or too long
     */
    private static function param($values, $name, $max = 512) {
        if (!is_array($values) || !array_key_exists($name, $values)) {
            return '';
        }
        $value = $values[$name];
        if (!is_string($value) || strlen($value) > $max) {
            return null;
        }
        return $value;
    }

    /**
     * Form values of a token or revocation request, JSON is accepted as a fallback
     * @param Hm_MCP_Http_Request $request request details
     * @return array
     */
    private static function body_params($request) {
        $params = $request->form();
        if (!$params && strpos(strtolower($request->header('content-type')), 'application/json') === 0) {
            $params = $request->json() ?: [];
        }
        return is_array($params) ? $params : [];
    }

    /**
     * Client id from HTTP Basic credentials, the secret is ignored for public clients
     * @param Hm_MCP_Http_Request $request request details
     * @return string|null
     */
    private static function basic_client_id($request) {
        if (!preg_match('/^Basic\s+([A-Za-z0-9+\/=]+)\s*$/i', $request->header('authorization'), $matches)) {
            return null;
        }
        $decoded = base64_decode($matches[1], true);
        if ($decoded === false || strpos($decoded, ':') === false) {
            return null;
        }
        $id = urldecode(explode(':', $decoded, 2)[0]);
        return $id === '' ? null : $id;
    }

    /**
     * @param Hm_MCP_Http_Request $request request details
     * @return string client address
     */
    private function client_ip($request) {
        return $request->client_ip($this->config->string('client_ip_header'));
    }

    /**
     * @param mixed $value text from client metadata
     * @param int $max longest kept length
     * @return string single line text without control or invisible characters
     */
    private static function clean_text($value, $max) {
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/\p{Cf}+/u', '', $value);
        $value = preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]+/u', ' ', (string) $value);
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
        return mb_substr($value, 0, $max);
    }

    /**
     * Check a Cypht username and password with the configured authentication
     * @param string $username username
     * @param string $password password
     * @return bool
     */
    private function check_credentials($username, $password) {
        if ($this->credential_checker) {
            return (bool) ($this->credential_checker)($username, $password);
        }
        try {
            $session = (new Hm_Session_Setup($this->site_config))->setup_session();
            return (bool) $session->auth($username, $password);
        } catch (Exception $e) {
            Hm_Debug::add('MCP sign in failed: '.$e->getMessage(), 'warning');
            return false;
        }
    }

    /**
     * Load and decrypt the user settings
     * @param string $username username
     * @param string $password password
     * @return object|false
     */
    private function load_user_config($username, $password) {
        if ($this->user_config_loader) {
            return ($this->user_config_loader)($username, $password);
        }
        $user_config = load_user_config_object($this->site_config);
        $user_config->load($username, $password);
        return empty($user_config->decrypt_failed) ? $user_config : false;
    }

    /**
     * @return bool true when the 2fa module set can be used on this site
     */
    private function two_factor_available() {
        return in_array('2fa', (array) $this->site_config->get_modules(), true) && (bool) $this->site_config->get('2fa_secret', false);
    }

    /**
     * @param object $user_config user settings
     * @return bool true when the user turned on two-factor authentication
     */
    private function two_factor_required($user_config) {
        return $this->two_factor_available() && (bool) $user_config->get('2fa_enable_setting', false);
    }

    /**
     * Check a TOTP or backup code the same way the 2fa module does
     * @param object $user_config user settings
     * @param string $username username
     * @param string $code submitted code
     * @return bool
     */
    private function two_factor_valid($user_config, $username, $code) {
        require_once APP_PATH.'modules/2fa/modules.php';
        $length = $this->site_config->get('2fa_simple', false) ? 15 : 64;
        $secret = create_secret($this->site_config->get('2fa_secret', ''), $username, $length);
        if (preg_match('/^[0-9]{6}$/', $code) && check_2fa_pin($code, $secret)) {
            return true;
        }
        $backup = $user_config->get('2fa_backup_codes_setting', []);
        return is_array($backup) && preg_match('/^[0-9]{9}$/', $code) && in_array((int) $code, $backup, true);
    }
}
