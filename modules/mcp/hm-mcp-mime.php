<?php

/**
 * MIME messages written by the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Builds the messages saved as drafts or sent, and reads drafts back.
 * Messages are plain text, or text with an HTML alternative when written in
 * Markdown, with optional attachments. Drafts keep Bcc recipients in the Bcc and
 * X-Original-Bcc headers, the latter is what the Cypht compose page reads.
 * @subpackage mcp/lib
 */
class Hm_MCP_Mime {

    /* addresses accepted in To, Cc and Bcc together */
    const MAX_RECIPIENTS = 100;

    /* message ids kept in the References header */
    const MAX_REFERENCES = 20;

    /* longest attachment file name kept, in characters */
    const MAX_FILENAME = 100;

    /* ------------------------------------------------------------ addresses */

    /**
     * @param mixed $email address without a name
     * @return bool
     */
    public static function valid_email($email) {
        if (!is_string($email) || $email === '' || strlen($email) > 254) {
            return false;
        }
        $atext = "[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~-]+";
        $label = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
        if (!preg_match('/^'.$atext.'(?:\.'.$atext.')*@'.$label.'(?:\.'.$label.')*$/', $email)) {
            return false;
        }
        return strlen(strstr($email, '@', true)) <= 64;
    }

    /**
     * Parse one address: "Name <user@example.com>" or "user@example.com"
     * @param mixed $value address
     * @param string $field argument name for errors
     * @return array ['name', 'email']
     * @throws Hm_MCP_Error
     */
    public static function parse_address($value, $field = 'address') {
        $value = is_string($value) ? trim($value) : '';
        $name = '';
        $email = $value;
        if (preg_match('/^(.*?)\s*<([^<>]*)>$/su', $value, $matches)) {
            $name = trim($matches[1]);
            $email = trim($matches[2]);
            if (strlen($name) >= 2 && $name[0] === '"' && substr($name, -1) === '"') {
                $name = preg_replace('/\\\\(.)/su', '$1', substr($name, 1, -1));
            }
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) || !self::valid_email($email)) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('%s: "%s" is not a valid email address.', $field,
                mb_substr(Hm_MCP_Format::text($value), 0, 100)));
        }
        return ['name' => self::header_text($name), 'email' => $email];
    }

    /**
     * Parse a list of addresses, without duplicates
     * @param mixed $values list of addresses
     * @param string $field argument name for errors
     * @return array list of ['name', 'email']
     * @throws Hm_MCP_Error
     */
    public static function parse_addresses($values, $field) {
        $res = [];
        foreach ((is_array($values) ? $values : [$values]) as $value) {
            $res[] = self::parse_address($value, $field);
        }
        return self::unique($res);
    }

    /**
     * Keep valid addresses from parsed headers, without duplicates
     * @param array $list list of ['name', 'email']
     * @return array
     */
    public static function unique($list) {
        $res = [];
        foreach ($list as $address) {
            $email = (string) ($address['email'] ?? '');
            if (self::valid_email($email) && !isset($res[strtolower($email)])) {
                $res[strtolower($email)] = ['name' => self::header_text($address['name'] ?? ''), 'email' => $email];
            }
        }
        return array_values($res);
    }

    /**
     * Remove addresses that appear in other lists
     * @param array $list addresses to filter
     * @param array $excluded lists of addresses or lower case emails
     * @return array
     */
    public static function without($list, ...$excluded) {
        $skip = [];
        foreach ($excluded as $addresses) {
            foreach ($addresses as $address) {
                $skip[strtolower(is_array($address) ? (string) ($address['email'] ?? '') : (string) $address)] = true;
            }
        }
        return array_values(array_filter($list, function ($address) use ($skip) {
            return !isset($skip[strtolower($address['email'])]);
        }));
    }

    /**
     * @param array $address ['name', 'email']
     * @return string header value
     */
    public static function format_address($address) {
        $phrase = self::phrase($address['name'] ?? '');
        return $phrase === '' ? $address['email'] : $phrase.' <'.$address['email'].'>';
    }

    /**
     * @param array $list addresses
     * @return string header value, one address per line
     */
    public static function format_addresses($list) {
        return implode(",\r\n ", array_map([self::class, 'format_address'], $list));
    }

    /* -------------------------------------------------------------- headers */

    /**
     * @param mixed $value text
     * @return string single line text without control characters
     */
    public static function header_text($value) {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', Hm_MCP_Format::text((string) $value));
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    /**
     * Display name of an address, quoted or encoded as needed
     * @param string $name display name
     * @return string
     */
    public static function phrase($name) {
        $name = self::header_text($name);
        if ($name === '') {
            return '';
        }
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            return self::encoded_words($name);
        }
        if (preg_match("/^[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~ -]+$/", $name)) {
            return $name;
        }
        return '"'.addcslashes($name, '"\\').'"';
    }

    /**
     * Unstructured header text such as the subject
     * @param string $value text
     * @return string encoded and folded value
     */
    public static function unstructured($value) {
        $value = self::header_text($value);
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return self::encoded_words($value);
        }
        return wordwrap($value, 70, "\r\n ", false);
    }

    /**
     * RFC 2047 encoded words of at most 75 characters, never splitting a character
     * @param string $text UTF-8 text
     * @return string words separated by folding white space
     */
    public static function encoded_words($text) {
        $words = [];
        $chunk = '';
        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            if (strlen($chunk.$char) > 45) {
                $words[] = '=?UTF-8?B?'.base64_encode($chunk).'?=';
                $chunk = '';
            }
            $chunk .= $char;
        }
        if ($chunk !== '') {
            $words[] = '=?UTF-8?B?'.base64_encode($chunk).'?=';
        }
        return implode("\r\n ", $words);
    }

    /**
     * New Message-ID in the domain of the sender
     * @param string $email sender address
     * @return string
     */
    public static function message_id($email) {
        $domain = strtolower((string) substr((string) strrchr((string) $email, '@'), 1));
        return '<'.bin2hex(random_bytes(16)).'@'.($domain !== '' ? $domain : 'localhost').'>';
    }

    /**
     * Message ids that can be written in In-Reply-To and References
     * @param mixed $value header value or list of ids
     * @return array
     */
    public static function clean_ids($value) {
        $ids = is_array($value) ? $value : Hm_MCP_Format::message_ids((string) $value);
        return array_values(array_unique(array_filter($ids, function ($id) {
            return is_string($id) && strlen($id) <= 250 && preg_match('/^<[\x21-\x3B\x3D\x3F-\x7E]+>$/', $id);
        })));
    }

    /* ---------------------------------------------------------------- parts */

    /**
     * @param mixed $text body text
     * @return string valid UTF-8 with LF line endings, no control characters or trailing space
     */
    public static function normalize_text($text) {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        return rtrim(preg_replace('/[ \t]+$/m', '', $text));
    }

    /**
     * Quoted-printable text with CRLF line breaks and no line starting with a dot
     * @param string $text text with LF line endings
     * @return string
     */
    protected static function qp($text) {
        $encoded = quoted_printable_encode(str_replace("\n", "\r\n", $text)."\r\n");
        return preg_replace('/^\./m', '=2E', $encoded);
    }

    /**
     * @param string $text text
     * @param string $subtype plain or html
     * @return array ['headers', 'body']
     */
    public static function text_part($text, $subtype = 'plain') {
        return ['headers' => ['Content-Type: text/'.$subtype.'; charset=UTF-8', 'Content-Transfer-Encoding: quoted-printable'],
            'body' => self::qp(self::normalize_text($text))];
    }

    /**
     * @param mixed $type content type
     * @return string a safe type/subtype, application/octet-stream when not valid
     */
    public static function content_type($type) {
        $type = strtolower(trim(explode(';', (string) $type)[0]));
        if (strlen($type) > 100 || !preg_match('/^[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*$/', $type) || strpos($type, 'multipart/') === 0) {
            return 'application/octet-stream';
        }
        return $type;
    }

    /**
     * @param mixed $name file name from a client or a message
     * @return string a single file name without paths, quotes or control characters
     */
    public static function filename($name) {
        $name = basename(str_replace('\\', '/', self::header_text((string) $name)));
        $name = trim(str_replace(['"', '\\', '/'], '_', $name), " .");
        if (mb_strlen($name) > self::MAX_FILENAME) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $keep = $extension !== '' && mb_strlen($extension) < 12 ? '.'.$extension : '';
            $name = rtrim(mb_substr($name, 0, self::MAX_FILENAME - mb_strlen($keep)), ' .').$keep;
        }
        return $name === '' ? 'attachment' : $name;
    }

    /**
     * Attachment part
     * @param array $attachment filename, content_type, data, optional disposition and content_id
     * @return array ['headers', 'body']
     */
    public static function attachment_part($attachment) {
        $type = self::content_type($attachment['content_type'] ?? '');
        $name = self::filename($attachment['filename'] ?? '');
        $data = (string) $attachment['data'];
        $encoding = 'base64';
        if (strpos($type, 'message/') === 0) {
            /* attached messages must not be base64 encoded (RFC 2046) */
            $data = str_replace(["\r\n", "\r"], "\n", $data);
            $longest = max(array_map('strlen', explode("\n", $data)));
            if (strpos($data, "\0") === false && $longest <= 998) {
                $encoding = preg_match('/[\x80-\xFF]/', $data) ? '8bit' : '7bit';
                $data = rtrim(str_replace("\n", "\r\n", $data), "\r\n")."\r\n";
            } else {
                $type = 'application/octet-stream';
                $data = (string) $attachment['data'];
            }
        }
        $safe = preg_match('/^[A-Za-z0-9._ ()+,=@-]+$/', $name);
        $quoted = $safe ? $name : self::encoded_words($name);
        $disposition = ($attachment['disposition'] ?? 'attachment') === 'inline' ? 'inline' : 'attachment';
        $charset = strpos($type, 'text/') === 0 && preg_match('/[\x80-\xFF]/', $data) && mb_check_encoding($data, 'UTF-8') ? '; charset=UTF-8' : '';
        $headers = [
            sprintf('Content-Type: %s%s; name="%s"', $type, $charset, $quoted),
            sprintf('Content-Disposition: %s; filename="%s"', $disposition, $quoted),
            /* the Cypht compose page names draft attachments after the description. Some servers
               decode it into 8-bit text in BODYSTRUCTURE, so it is kept to plain ASCII */
            'Content-Description: '.($safe ? $name : self::ascii_name($name)),
            'Content-Transfer-Encoding: '.$encoding,
        ];
        $content_id = trim((string) ($attachment['content_id'] ?? ''), '<> ');
        if ($content_id !== '' && preg_match('/^[\x21-\x3B\x3D\x3F-\x7E]{1,200}$/', $content_id)) {
            $headers[] = 'Content-ID: <'.$content_id.'>';
        }
        return ['headers' => $headers, 'body' => $encoding === 'base64' ? chunk_split(base64_encode($data), 76, "\r\n") : $data];
    }

    /**
     * @param string $name UTF-8 file name
     * @return string the name with only safe ASCII characters
     */
    public static function ascii_name($name) {
        if (class_exists('Normalizer')) {
            $name = preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($name, Normalizer::FORM_D));
        }
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : false;
        $ascii = trim(preg_replace('/[^A-Za-z0-9._ ()+,=@-]+/', '_', $ascii === false ? $name : $ascii), ' _');
        return $ascii === '' ? 'attachment' : $ascii;
    }

    /**
     * @param string $subtype mixed or alternative
     * @param array $parts list of ['headers', 'body']
     * @return array ['headers', 'body']
     */
    protected static function multipart($subtype, $parts) {
        $boundary = '=_cypht_'.bin2hex(random_bytes(12));
        $chunks = [];
        foreach ($parts as $part) {
            $chunks[] = '--'.$boundary."\r\n".implode("\r\n", $part['headers'])."\r\n\r\n".$part['body'];
        }
        return ['headers' => ['Content-Type: multipart/'.$subtype.'; boundary="'.$boundary.'"'],
            'body' => implode("\r\n", $chunks)."\r\n--".$boundary."--\r\n"];
    }

    /* --------------------------------------------------------------- bodies */

    /**
     * Markdown to HTML with raw HTML removed and only safe links
     * @param string $markdown text
     * @return string HTML fragment
     */
    public static function markdown_html($markdown) {
        $converter = new League\CommonMark\GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            /* a line break in the text is a line break in the message */
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
        return trim((string) $converter->convert($markdown));
    }

    /**
     * @param string $text plain text
     * @return string escaped HTML keeping the line breaks
     */
    public static function text_html($text) {
        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }

    /**
     * Plain text and optional HTML version of a message body
     * @param string $text text written for the message
     * @param string $format text or markdown
     * @param array|null $quote ['type' => reply|forward, 'lead_in', 'text'] original message to include
     * @return array [plain text, HTML document or null]
     */
    public static function bodies($text, $format, $quote = null) {
        $text = self::normalize_text($text);
        $plain = $text;
        $quoted = '';
        if ($quote) {
            $original = self::normalize_text($quote['text']);
            $quoted = $quote['type'] === 'reply' ? format_reply_text($original) : $original;
            $plain = ltrim($text."\n\n".$quote['lead_in']."\n\n".$quoted, "\n");
        }
        if ($format !== 'markdown') {
            return [$plain, null];
        }
        $html = $text !== '' ? self::markdown_html($text) : '';
        if ($quote) {
            $original = self::text_html(self::normalize_text($quote['text']));
            $html .= "\n<p>".self::text_html($quote['lead_in'])."</p>\n".($quote['type'] === 'reply'
                ? '<blockquote type="cite" style="margin:0 0 0 .8ex;border-left:1px solid #ccc;padding-left:1ex">'.$original.'</blockquote>'
                : '<div>'.$original.'</div>');
        }
        return [$plain, "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"></head><body>\n".$html."\n</body></html>"];
    }

    /* ---------------------------------------------------------------- build */

    /**
     * Build a message
     * @param array $spec from, to, cc, bcc, reply_to (lists of ['name', 'email'], from is one),
     *                    subject, text, html (null for plain text), message_id, in_reply_to,
     *                    references, attachments, headers (extra name => value), date,
     *                    draft (keep Bcc in the message)
     * @return string message with CRLF line endings
     */
    public static function build($spec) {
        $lines = [
            'Date: '.date('r', $spec['date'] ?? time()),
            'From: '.self::format_address($spec['from']),
        ];
        if (!empty($spec['reply_to'])) {
            $lines[] = 'Reply-To: '.self::format_addresses($spec['reply_to']);
        }
        foreach (['to' => 'To', 'cc' => 'Cc'] as $key => $name) {
            if (!empty($spec[$key])) {
                $lines[] = $name.': '.self::format_addresses($spec[$key]);
            }
        }
        if (!empty($spec['draft']) && !empty($spec['bcc'])) {
            $lines[] = 'Bcc: '.self::format_addresses($spec['bcc']);
        }
        $lines[] = 'Subject: '.self::unstructured($spec['subject'] ?? '');
        $lines[] = 'Message-ID: '.$spec['message_id'];
        $in_reply_to = self::clean_ids($spec['in_reply_to'] ?? '');
        if ($in_reply_to) {
            $lines[] = 'In-Reply-To: '.$in_reply_to[0];
        }
        $references = array_slice(self::clean_ids($spec['references'] ?? []), -self::MAX_REFERENCES);
        if ($references) {
            $lines[] = 'References: '.implode("\r\n ", $references);
        }
        $version = defined('CYPHT_VERSION') ? 'Cypht '.CYPHT_VERSION : 'Cypht';
        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'User-Agent: '.$version;
        $lines[] = 'X-Mailer: '.$version;
        if (!empty($spec['draft']) && !empty($spec['bcc'])) {
            $lines[] = 'X-Original-Bcc: '.self::format_addresses($spec['bcc']);
        }
        foreach ((array) ($spec['headers'] ?? []) as $name => $value) {
            if (preg_match('/^X-[A-Za-z0-9-]{1,60}$/', (string) $name)) {
                $lines[] = $name.': '.self::unstructured($value);
            }
        }
        $body = self::text_part($spec['text'] ?? '');
        if (($spec['html'] ?? null) !== null) {
            $body = self::multipart('alternative', [$body, self::text_part($spec['html'], 'html')]);
        }
        if (!empty($spec['attachments'])) {
            $parts = [$body];
            foreach ($spec['attachments'] as $attachment) {
                $parts[] = self::attachment_part($attachment);
            }
            unset($body);
            $body = self::multipart('mixed', $parts);
            unset($parts);
        }
        return implode("\r\n", array_merge($lines, $body['headers']))."\r\n\r\n".$body['body'];
    }

    /* ---------------------------------------------------------------- parse */

    /**
     * Read a draft back into the values build() takes
     * @param string $raw message source
     * @return array from, to, cc, bcc, reply_to, subject, text, html, in_reply_to, references,
     *               attachments (with part_id, the IMAP part number)
     */
    public static function parse($raw) {
        $message = (new ZBateson\MailMimeParser\MailMimeParser())->parse((string) $raw, false);
        $addresses = function ($name) use ($message) {
            $header = $message->getHeaderAs($name, ZBateson\MailMimeParser\Header\AddressHeader::class);
            $res = [];
            if ($header) {
                foreach ($header->getAddresses() as $address) {
                    $res[] = ['name' => (string) $address->getName(), 'email' => (string) $address->getEmail()];
                }
            }
            return self::unique($res);
        };
        $raw_header = function ($name) use ($message) {
            $header = $message->getHeader($name);
            return $header ? (string) $header->getRawValue() : '';
        };
        $numbers = self::part_numbers($message);
        $attachments = [];
        foreach ($message->getAllAttachmentParts() as $part) {
            $stream = $part->getBinaryContentStream();
            $disposition = strtolower((string) $part->getContentDisposition('attachment'));
            $attachments[] = [
                'part_id' => $numbers[spl_object_id($part)] ?? null,
                'filename' => self::filename($part->getFilename() ?? ''),
                'content_type' => self::content_type($part->getContentType('application/octet-stream')),
                'disposition' => $disposition === 'inline' ? 'inline' : 'attachment',
                'content_id' => (string) $part->getContentId(),
                'data' => $stream ? $stream->getContents() : '',
            ];
        }
        $from = $addresses('From');
        $html = $message->getHtmlContent();
        return [
            'from' => $from[0] ?? null,
            'reply_to' => $addresses('Reply-To'),
            'to' => $addresses('To'),
            'cc' => $addresses('Cc'),
            'bcc' => $addresses('Bcc') ?: $addresses('X-Original-Bcc'),
            'subject' => self::header_text((string) $message->getSubject()),
            'text' => (string) $message->getTextContent(),
            'html' => $html === null || trim($html) === '' ? null : $html,
            'in_reply_to' => self::clean_ids($raw_header('In-Reply-To')),
            'references' => self::clean_ids($raw_header('References')),
            'attachments' => $attachments,
        ];
    }

    /**
     * IMAP part numbers (RFC 3501 section 6.4.5) of the parts of a parsed message
     * @param object $message parsed message
     * @return array spl_object_id(part) => part number
     */
    protected static function part_numbers($message) {
        $res = [];
        $walk = function ($part, $prefix) use (&$walk, &$res) {
            foreach (array_values($part->getChildParts()) as $index => $child) {
                $number = ($prefix === '' ? '' : $prefix.'.').($index + 1);
                $res[spl_object_id($child)] = $number;
                if ($child instanceof ZBateson\MailMimeParser\Message\IMimePart && $child->isMultiPart()) {
                    $walk($child, $number);
                }
            }
        };
        if ($message->isMultiPart()) {
            $walk($message, '');
        } else {
            $res[spl_object_id($message)] = '1';
        }
        return $res;
    }
}
