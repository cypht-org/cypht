<?php

/**
 * Temporary attachment download links
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Issues and serves /api/v1/files/{capability} links. A link is a random token
 * that only gives access to one attachment, expires after a short time, carries
 * the connection key (wrapped) and stops working when the connection is revoked,
 * the read permission is removed or the account is no longer allowed.
 * @subpackage mcp/lib
 */
class Hm_MCP_Files {

    const TOKEN_PATTERN = '/^cyp_f_[A-Za-z0-9_-]{43}$/';

    /* types never sent with their own content type, so a browser cannot render them */
    const RISKY_TYPES = ['text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
        'text/javascript', 'application/javascript', 'application/x-javascript', 'text/ecmascript',
        'application/ecmascript', 'application/x-shockwave-flash', 'text/vnd.wap.wml', 'application/pdf+xml'];

    private $services;

    /**
     * @param Hm_MCP_Services $services services
     */
    public function __construct($services) {
        $this->services = $services;
    }

    /**
     * @return int link lifetime in seconds
     */
    public function ttl() {
        return min(86400, $this->services->config->int('file_link_ttl', 900, 60));
    }

    /**
     * Create a download link
     * @param Hm_MCP_Principal $principal authenticated principal
     * @param string $account_id account id
     * @param string $folder folder id
     * @param string $uid message uid
     * @param string $part_id part id
     * @return array [url, unix expiration time]
     */
    public function issue($principal, $account_id, $folder, $uid, $part_id) {
        $ttl = $this->ttl();
        $token = $this->services->store()->issue_token($principal->connection_id(), 'file', $principal->key, $ttl, [
            'account_id' => (string) $account_id,
            'folder' => (string) $folder,
            'uid' => (string) $uid,
            'part_id' => (string) $part_id,
        ]);
        return [$this->services->config->api_url().'/files/'.$token, $this->services->store()->now() + $ttl];
    }

    /**
     * Serve a download link
     * @param Hm_MCP_Http_Request $request request
     * @param string $token capability from the URL
     * @return Hm_MCP_Http_Response
     */
    public function download($request, $token) {
        if (!in_array($request->method, ['GET', 'HEAD'], true)) {
            return self::message(405, 'Method not allowed.', ['Allow' => 'GET, HEAD']);
        }
        if (!preg_match(self::TOKEN_PATTERN, $token)) {
            return self::message(404, 'This download link is not valid.');
        }
        $auth = $this->services->auth()->authenticate($token, ['file']);
        if (!$auth['ok']) {
            if ($auth['status'] === 401) {
                return self::message(404, 'This download link has expired or is no longer valid. Ask for a new one.');
            }
            return self::message($auth['status'], $auth['message']);
        }
        $principal = $auth['principal'];
        $data = $auth['token']['data'] ?? [];
        $mail = $this->services->mail($principal);
        $store = $this->services->store();
        $log = function ($outcome, $summary) use ($store, $principal) {
            $store->log_activity([
                'username' => $principal->username,
                'connection_id' => $principal->connection_id(),
                'connection_name' => $principal->connection_name(),
                'channel' => 'link',
                'operation' => 'download_attachment',
                'permission' => 'read',
                'outcome' => $outcome,
                'summary' => $summary,
            ]);
        };
        if (!$principal->can('read')) {
            $log('denied', ['error' => 'permission_denied']);
            return self::message(403, 'Reading email is disabled for this connection in the Cypht settings.');
        }
        if (!$store->rate_limit('ops:'.$principal->connection_id(), Hm_MCP_Executor::RATE_LIMIT, Hm_MCP_Executor::RATE_WINDOW)) {
            return self::message(429, 'Too many requests. Try again in a few minutes.');
        }
        try {
            $ctx = $mail->context();
            $account = $ctx->account($data['account_id'] ?? '');
            $mailbox = $ctx->mailbox($account['id']);
            list($part_id, $part) = $mail->find_part($mailbox, (string) ($data['folder'] ?? ''), (string) ($data['uid'] ?? ''),
                (string) ($data['part_id'] ?? ''));
        } catch (Hm_MCP_Error $e) {
            $mail->finish();
            $log(in_array($e->error_code, ['permission_denied', 'account_not_allowed'], true) ? 'denied' : 'error', ['error' => $e->error_code]);
            return self::message($e->http_status(), $e->getMessage());
        } catch (Throwable $e) {
            $mail->finish();
            Hm_Debug::add('MCP download failed: '.$e->getMessage(), 'danger');
            $log('error', ['error' => 'internal_error']);
            return self::message(500, 'The download failed because of a server error.');
        }
        $info = Hm_MCP_Mail::attachment_info($part_id, $part);
        $response = new Hm_MCP_Http_Response(200, self::headers($info, $part));
        $log('ok', ['account_id' => $account['id'], 'content_type' => $info['content_type']]);
        if ($request->method === 'HEAD') {
            $mail->finish();
            return $response;
        }
        $folder = (string) $data['folder'];
        $uid = (string) $data['uid'];
        $response->stream = function () use ($mailbox, $folder, $uid, $part_id, $mail) {
            try {
                /* the content type was already sent, the callback has nothing to do */
                $mailbox->stream_message_part($folder, $uid, $part_id, function () {});
            } finally {
                $mail->finish();
            }
        };
        return $response;
    }

    /**
     * Response headers for a download
     * @param array $info value from Hm_MCP_Mail::attachment_info()
     * @param array $part structure part
     * @return array
     */
    public static function headers($info, $part) {
        $type = self::safe_type($info['content_type']);
        $charset = strtolower((string) ($part['attributes']['charset'] ?? ''));
        if ($charset !== '' && strpos($type, 'text/') === 0 && preg_match('/^[a-z0-9._-]{1,40}$/', $charset)) {
            $type .= '; charset='.$charset;
        }
        return [
            'Content-Type' => $type,
            'Content-Disposition' => self::disposition($info['filename']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Frame-Options' => 'DENY',
        ];
    }

    /**
     * @param string $type content type from the message
     * @return string content type to send
     */
    public static function safe_type($type) {
        $type = strtolower(trim((string) $type));
        if (!preg_match('/^[a-z0-9][a-z0-9!#$&^_.+-]{0,126}\/[a-z0-9][a-z0-9!#$&^_.+-]{0,126}$/', $type) ||
            in_array($type, self::RISKY_TYPES, true) || strpos($type, 'multipart/') === 0 || strpos($type, 'message/') === 0) {
            return 'application/octet-stream';
        }
        return $type;
    }

    /**
     * Content-Disposition header that always downloads
     * @param string $filename file name
     * @return string
     */
    public static function disposition($filename) {
        $name = trim(str_replace(["\r", "\n", '"', '\\', '/', "\0"], '_', (string) $filename));
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'attachment';
        }
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
        }
        $ascii = preg_replace('/[^\x20-\x7E]/u', '_', $name);
        return sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $ascii, rawurlencode($name));
    }

    /**
     * Plain text response for people who open a link
     * @param int $status HTTP status
     * @param string $text message
     * @param array $headers extra headers
     * @return Hm_MCP_Http_Response
     */
    public static function message($status, $text, $headers = []) {
        return new Hm_MCP_Http_Response($status, array_merge([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ], $headers), $text."\n");
    }
}
