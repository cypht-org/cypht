<?php

/**
 * Formatting helpers for MCP and REST responses
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Converts mail data to safe, model friendly values
 * @subpackage mcp/lib
 */
class Hm_MCP_Format {

    /* invisible characters used to hide text from people: zero width, bidi controls, Unicode tags */
    const INVISIBLE = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}\x{E0000}-\x{E007F}]/u';

    /* elements whose content is never text */
    const SKIP_TAGS = ['head', 'script', 'style', 'noscript', 'template', 'svg', 'math', 'object', 'embed',
        'iframe', 'frame', 'frameset', 'select', 'option', 'textarea', 'button', 'input', 'audio', 'video',
        'canvas', 'map', 'title', 'meta', 'link', 'base'];

    /* elements that start a new line */
    const BLOCK_TAGS = ['address', 'article', 'aside', 'blockquote', 'center', 'dd', 'details', 'dialog', 'div',
        'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody', 'thead',
        'tfoot', 'tr', 'ul', 'body', 'html'];

    /* inline styles that hide content */
    const HIDDEN_STYLE = '/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden|mso-hide\s*:\s*all|'.
        'font-size\s*:\s*0(?:\.0+)?(?:px|pt|em|rem|%)?\s*(?:!important)?\s*(?:;|$)|'.
        'opacity\s*:\s*0(?:\.0+)?\s*(?:!important)?\s*(?:;|$)|max-height\s*:\s*0(?:px)?\s*(?:!important)?\s*(?:;|$)|'.
        '(?:width|height)\s*:\s*0(?:px)?\s*(?:!important)?\s*(?:;|$))/i';

    /* nesting limit when walking HTML */
    const MAX_DEPTH = 200;

    /* ----------------------------------------------------------------- ids */

    /**
     * Opaque id for a message
     * @param string $account account id
     * @param string $folder folder identifier
     * @param string $uid message uid
     * @return string
     */
    public static function message_id($account, $folder, $uid) {
        return 'msg_'.Hm_MCP_Crypto::b64url(json_encode([(string) $account, (string) $folder, (string) $uid],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Decode a message id
     * @param mixed $id value from message_id()
     * @return array [account, folder, uid]
     * @throws Hm_MCP_Error
     */
    public static function parse_message_id($id) {
        if (is_string($id) && strpos($id, 'msg_') === 0 && strlen($id) <= 2000) {
            $raw = Hm_MCP_Crypto::b64url_decode(substr($id, 4));
            $parts = $raw === false ? null : json_decode($raw, true);
            if (is_array($parts) && count($parts) === 3 && array_is_list($parts)) {
                list($account, $folder, $uid) = $parts;
                if (is_string($account) && $account !== '' && is_string($folder) && $folder !== '' &&
                    is_string($uid) && $uid !== '' && !preg_match('/[\r\n\0]/', $folder.$uid)) {
                    return [$account, $folder, $uid];
                }
            }
        }
        throw new Hm_MCP_Error('invalid_argument', 'The message id is not valid. Use an id returned by list_messages or search_messages.');
    }

    /* ---------------------------------------------------------------- text */

    /**
     * Make a value safe to return: valid UTF-8, no control or invisible characters
     * @param mixed $value text
     * @param bool $multiline keep line breaks and tabs
     * @return string
     */
    public static function text($value, $multiline = false) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1');
        }
        $value = preg_replace(self::INVISIBLE, '', $value);
        if ($multiline) {
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        } else {
            $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
            $value = trim(preg_replace('/\s{2,}/u', ' ', $value));
        }
        return (string) $value;
    }

    /**
     * Clean a plain text body
     * @param string $text message text
     * @return string
     */
    public static function plain_text($text) {
        $text = self::text($text, true);
        $text = preg_replace('/[ \t]+$/m', '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /**
     * Limit a text to a number of characters
     * @param string $text text
     * @param int $max maximum characters
     * @return array [text, truncated, total characters]
     */
    public static function truncate($text, $max) {
        $total = mb_strlen($text);
        if ($total <= $max) {
            return [$text, false, $total];
        }
        return [mb_substr($text, 0, $max), true, $total];
    }

    /**
     * Convert HTML to readable text. Scripts, styles and elements hidden from the
     * reader (a common way to hide instructions in email) are removed, links keep
     * their target and images keep their description.
     * @param string $html HTML document or fragment
     * @return string
     */
    public static function html_to_text($html) {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8, ISO-8859-1');
        }
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $out = '';
        self::walk($doc, $out, 0);
        $text = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = self::text($text, true);
        $lines = array_map(function ($line) {
            return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line));
        }, explode("\n", $text));
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /**
     * @param DOMNode $node node to walk
     * @param string $out text collected so far
     * @param int $depth nesting depth
     * @return void
     */
    private static function walk($node, &$out, $depth) {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= preg_replace('/\s+/u', ' ', $child->nodeValue);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::SKIP_TAGS, true) || self::is_hidden($child)) {
                continue;
            }
            switch ($tag) {
                case 'br':
                    $out .= "\n";
                    continue 2;
                case 'hr':
                    $out .= "\n---\n";
                    continue 2;
                case 'img':
                    $alt = trim((string) $child->getAttribute('alt'));
                    if ($alt !== '') {
                        $out .= ' [image: '.$alt.'] ';
                    }
                    continue 2;
                case 'td':
                case 'th':
                    $out .= ' | ';
                    self::walk($child, $out, $depth + 1);
                    continue 2;
                case 'a':
                    $before = strlen($out);
                    self::walk($child, $out, $depth + 1);
                    $label = trim(substr($out, $before));
                    $href = trim((string) $child->getAttribute('href'));
                    if (preg_match('/^(https?:|mailto:)/i', $href) && $href !== $label && 'mailto:'.$label !== $href) {
                        $out .= ' ('.$href.')';
                    }
                    continue 2;
            }
            $block = in_array($tag, self::BLOCK_TAGS, true);
            if ($block) {
                $out .= "\n";
            }
            if ($tag === 'li') {
                $out .= '- ';
            }
            if ($tag === 'blockquote') {
                $quoted = '';
                self::walk($child, $quoted, $depth + 1);
                $quoted = trim(html_entity_decode($quoted, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $out .= implode("\n", array_map(function ($line) { return '> '.trim($line); }, explode("\n", $quoted)));
                $out .= "\n";
                continue;
            }
            self::walk($child, $out, $depth + 1);
            if ($block) {
                $out .= "\n";
            }
        }
    }

    /**
     * @param DOMElement $element element
     * @return bool true when the element is hidden from the reader
     */
    private static function is_hidden($element) {
        if ($element->hasAttribute('hidden') || strtolower($element->getAttribute('aria-hidden')) === 'true') {
            return true;
        }
        $style = (string) $element->getAttribute('style');
        if ($style !== '' && preg_match(self::HIDDEN_STYLE, $style)) {
            return true;
        }
        $type = strtolower($element->getAttribute('type'));
        return $type === 'hidden';
    }

    /* ----------------------------------------------------------- addresses */

    /**
     * Parse an address header
     * @param string $value header value
     * @return array list of ['name' => string, 'email' => string]
     */
    public static function addresses($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        $res = [];
        foreach (process_address_fld($value) as $addr) {
            if (empty($addr['email'])) {
                continue;
            }
            $res[] = ['name' => self::text($addr['label'] ?? ''), 'email' => self::text($addr['email'])];
        }
        return $res;
    }

    /**
     * Parse the first address of a header
     * @param string $value header value
     * @return array|null ['name', 'email']
     */
    public static function address($value) {
        $list = self::addresses($value);
        return $list ? $list[0] : null;
    }

    /**
     * Message-ID values from a header (In-Reply-To, References)
     * @param string $value header value
     * @return array list of ids including the angle brackets
     */
    public static function message_ids($value) {
        preg_match_all('/<[^<>\s]+>/', (string) $value, $matches);
        return array_values(array_unique($matches[0]));
    }

    /* --------------------------------------------------------------- dates */

    /**
     * Parse a mail date. The day of the week is ignored: it is redundant in RFC 5322
     * dates and PHP would otherwise move a date with a wrong weekday to another day.
     * @param string $value date header or IMAP internal date
     * @return int|false unix time
     */
    public static function timestamp($value) {
        $value = trim((string) $value);
        $value = preg_replace('/\s*\([^)]*\)\s*$/', '', $value);
        $value = preg_replace('/^[A-Za-z]{3,9},?\s+(?=\d)/', '', $value);
        if ($value === '') {
            return false;
        }
        return strtotime($value);
    }

    /**
     * Convert a mail date to ISO 8601 in the user's timezone
     * @param string $value date header or IMAP internal date
     * @return string|null
     */
    public static function date($value) {
        $time = self::timestamp($value);
        return $time === false ? null : date('c', $time);
    }

    /**
     * Parse a date argument (YYYY-MM-DD or ISO 8601)
     * @param string $value argument
     * @param string $name argument name for the error message
     * @return int unix time
     * @throws Hm_MCP_Error
     */
    public static function parse_date_arg($value, $name) {
        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/', $value)) {
            $time = strtotime($value);
            if ($time !== false) {
                return $time;
            }
        }
        throw new Hm_MCP_Error('invalid_argument', sprintf('%s must be a date like 2026-01-31 or an ISO 8601 date-time.', $name));
    }

    /* --------------------------------------------------------------- flags */

    /**
     * @param string $flags IMAP flag list
     * @return array ['unread', 'flagged', 'answered', 'draft']
     */
    public static function flags($flags) {
        $flags = strtolower((string) $flags);
        return [
            'unread' => strpos($flags, '\\seen') === false,
            'flagged' => strpos($flags, '\\flagged') !== false,
            'answered' => strpos($flags, '\\answered') !== false,
            'draft' => strpos($flags, '\\draft') !== false,
        ];
    }

    /**
     * Case insensitive header lookup
     * @param array $headers header name => value
     * @param string $name header name
     * @return string
     */
    public static function header($headers, $name) {
        if (!is_array($headers)) {
            return '';
        }
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) end($value) : (string) $value;
            }
        }
        return '';
    }
}

/**
 * Translations from the Cypht language files, for text written outside the
 * output modules (OAuth pages, reply lead-ins)
 * @subpackage mcp/lib
 */
class Hm_MCP_Strings {

    /* language code => translations */
    private static $loaded = [];

    /**
     * @param mixed $lang language code such as en, es or pt-BR
     * @return string a language with a file, en when unknown
     */
    public static function code($lang) {
        $lang = is_string($lang) ? $lang : '';
        if (!preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})?$/', $lang) || !is_readable(APP_PATH.'language/'.$lang.'.php')) {
            return 'en';
        }
        return $lang;
    }

    /**
     * @param mixed $lang language code
     * @return array translations of the language
     */
    public static function load($lang) {
        $lang = self::code($lang);
        if (!array_key_exists($lang, self::$loaded)) {
            $strings = require APP_PATH.'language/'.$lang.'.php';
            self::$loaded[$lang] = is_array($strings) ? $strings : [];
        }
        return self::$loaded[$lang];
    }

    /**
     * @param mixed $lang language code
     * @param string $string English text
     * @return string translated text, the English text when there is no translation
     */
    public static function trans($lang, $string) {
        $value = self::load($lang)[$string] ?? false;
        return is_string($value) ? $value : $string;
    }
}
