<?php

/**
 * Errors returned by MCP tools and REST endpoints
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * An error the client can act on. The message is shown to the client and must
 * never contain secrets or message content.
 * @subpackage mcp/lib
 */
class Hm_MCP_Error extends Exception {

    /* machine readable error code => HTTP status for the REST API */
    const STATUS = [
        'invalid_argument' => 400,
        'reauth_required' => 401,
        'permission_denied' => 403,
        'account_not_allowed' => 403,
        'access_disabled' => 403,
        'not_found' => 404,
        'conflict' => 409,
        'payload_too_large' => 413,
        'rate_limited' => 429,
        'internal_error' => 500,
        'not_supported' => 501,
        'upstream_error' => 502,
        'unavailable' => 503,
    ];

    /* machine readable error code */
    public $error_code;

    /* extra non sensitive details */
    public $details;

    /**
     * @param string $code error code, a key of STATUS
     * @param string $message human readable message
     * @param array $details extra details
     */
    public function __construct($code, $message, $details = []) {
        parent::__construct($message);
        $this->error_code = array_key_exists($code, self::STATUS) ? $code : 'internal_error';
        $this->details = $details;
    }

    /**
     * @return int HTTP status code
     */
    public function http_status() {
        return self::STATUS[$this->error_code];
    }

    /**
     * @return array error object for responses
     */
    public function to_array() {
        $res = ['code' => $this->error_code, 'message' => $this->getMessage()];
        if ($this->details) {
            $res['details'] = $this->details;
        }
        return $res;
    }
}
