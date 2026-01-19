<?php
/**
 * Vendor detection helpers
 * @package modules
 * @subpackage vendor_detection
 */
if (!defined('DEBUG_MODE')) { die(); }

use ZBateson\MailMimeParser\MailMimeParser;

if (!hm_exists('vendor_detection_default_confidence_rules')) {
    function vendor_detection_default_confidence_rules() {
        return array(
            'high' => array('dkim_domain', 'header_name', 'header_prefix'),
            'medium' => array('return_path_domain', 'received_domain'),
            'low' => array('from_domain', 'reply_to_domain')
        );
    }
}

if (!hm_exists('vendor_detection_load_registry')) {
    function vendor_detection_load_registry($path = null) {
        static $cached = null;
        $use_cache = $path === null;
        if ($use_cache && $cached !== null) {
            return $cached;
        }
        if (!$path) {
            $path = APP_PATH.'assets/data/vendor_senders.json';
        }
        $registry = array(
            'confidence_rules' => vendor_detection_default_confidence_rules(),
            'vendors' => array()
        );
        if (!is_readable($path)) {
            if ($use_cache) {
                $cached = $registry;
            }
            return $registry;
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (is_array($data)) {
            if (isset($data['confidence_rules']) && is_array($data['confidence_rules'])) {
                $registry['confidence_rules'] = $data['confidence_rules'];
            }
            if (isset($data['vendors']) && is_array($data['vendors'])) {
                $registry['vendors'] = $data['vendors'];
            }
        }
        if ($use_cache) {
            $cached = $registry;
        }
        return $registry;
    }
}

if (!hm_exists('vendor_detection_detect_sender')) {
    function vendor_detection_detect_sender($msg_headers, $msg_source = '', $registry = null) {
        if (!is_array($msg_headers) || empty($msg_headers)) {
            return array();
        }
        if ($registry === null) {
            $registry = vendor_detection_load_registry();
        }
        $registry = vendor_detection_normalize_registry($registry);
        $signals = vendor_detection_extract_message_signals($msg_headers, $msg_source);
        $best_match = null;
        foreach ($registry['vendors'] as $vendor) {
            $match = vendor_detection_match_vendor($signals, $vendor, $registry['confidence_rules']);
            if (!$match) {
                continue;
            }
            if ($best_match === null || vendor_detection_is_better_match($match, $best_match)) {
                $best_match = $match;
            }
        }
        return $best_match ? $best_match : array();
    }
}

if (!hm_exists('vendor_detection_normalize_registry')) {
    function vendor_detection_normalize_registry($registry) {
        $normalized = array(
            'confidence_rules' => vendor_detection_default_confidence_rules(),
            'vendors' => array()
        );
        if (is_array($registry)) {
            if (isset($registry['confidence_rules']) && is_array($registry['confidence_rules'])) {
                $normalized['confidence_rules'] = $registry['confidence_rules'];
            }
            if (isset($registry['vendors']) && is_array($registry['vendors'])) {
                $normalized['vendors'] = $registry['vendors'];
            }
        }
        return $normalized;
    }
}

if (!hm_exists('vendor_detection_extract_message_signals')) {
    function vendor_detection_extract_message_signals($msg_headers, $msg_source = '') {
        $headers = vendor_detection_normalize_headers($msg_headers);
        $signals = array(
            'header_names' => array_keys($headers),
            'dkim_domains' => array(),
            'return_path_domains' => array(),
            'received_domains' => array(),
            'from_domains' => array(),
            'reply_to_domains' => array()
        );

        $message = vendor_detection_parse_message($msg_source);
        $dkim_values = vendor_detection_get_header_values($message, 'DKIM-Signature');
        foreach ($dkim_values as $value) {
            $signals['dkim_domains'] = array_merge(
                $signals['dkim_domains'],
                vendor_detection_extract_dkim_domains($value)
            );
        }
        $return_values = vendor_detection_get_header_values($message, 'Return-Path');
        foreach ($return_values as $value) {
            $signals['return_path_domains'] = array_merge(
                $signals['return_path_domains'],
                vendor_detection_extract_domains_from_address_header($value)
            );
        }
        $received_values = vendor_detection_get_header_values($message, 'Received');
        foreach ($received_values as $value) {
            $signals['received_domains'] = array_merge(
                $signals['received_domains'],
                vendor_detection_extract_domains_from_tokens($value)
            );
        }
        $from_values = vendor_detection_get_header_values($message, 'From');
        foreach ($from_values as $value) {
            $signals['from_domains'] = array_merge(
                $signals['from_domains'],
                vendor_detection_extract_domains_from_address_header($value)
            );
        }
        $reply_values = vendor_detection_get_header_values($message, 'Reply-To');
        foreach ($reply_values as $value) {
            $signals['reply_to_domains'] = array_merge(
                $signals['reply_to_domains'],
                vendor_detection_extract_domains_from_address_header($value)
            );
        }

        foreach ($signals as $key => $vals) {
            if (is_array($vals)) {
                $signals[$key] = vendor_detection_unique_lowercase($vals);
            }
        }
        return $signals;
    }
}

if (!hm_exists('vendor_detection_normalize_headers')) {
    function vendor_detection_normalize_headers($msg_headers) {
        if (function_exists('lc_headers')) {
            $msg_headers = lc_headers($msg_headers);
        }
        $headers = array();
        foreach ($msg_headers as $name => $value) {
            $key = mb_strtolower(trim($name));
            if (!isset($headers[$key])) {
                $headers[$key] = array();
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $headers[$key][] = $item;
                }
            } else {
                $headers[$key][] = $value;
            }
        }
        return $headers;
    }
}

if (!hm_exists('vendor_detection_parse_message')) {
    function vendor_detection_parse_message($msg_source) {
        if (!$msg_source) {
            return null;
        }
        try {
            $parser = new MailMimeParser();
            return $parser->parse($msg_source, false);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!hm_exists('vendor_detection_get_header_values')) {
    function vendor_detection_get_header_values($message, $header_name) {
        if (!$message) {
            return array();
        }
        $values = array();
        $offset = 0;
        while (($header = $message->getHeader($header_name, $offset)) !== null) {
            $values[] = $header->getValue();
            $offset++;
        }
        return $values;
    }
}

if (!hm_exists('vendor_detection_extract_dkim_domains')) {
    function vendor_detection_extract_dkim_domains($value) {
        $domains = array();
        foreach (explode(';', $value) as $segment) {
            $segment = trim($segment);
            if (stripos($segment, 'd=') === 0) {
                $domain = trim(substr($segment, 2));
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }
        return $domains;
    }
}

if (!hm_exists('vendor_detection_extract_domains_from_address_header')) {
    function vendor_detection_extract_domains_from_address_header($value) {
        $domains = array();
        if (function_exists('process_address_fld')) {
            $addresses = process_address_fld($value);
            foreach ($addresses as $addr) {
                if (!empty($addr['email']) && strpos($addr['email'], '@') !== false) {
                    $parts = explode('@', $addr['email']);
                    $domains[] = end($parts);
                }
            }
        }
        return $domains;
    }
}

if (!hm_exists('vendor_detection_extract_domains_from_tokens')) {
    function vendor_detection_extract_domains_from_tokens($value) {
        $domains = array();
        $tokens = vendor_detection_split_tokens($value);
        foreach ($tokens as $token) {
            $domain = vendor_detection_token_to_domain($token);
            if ($domain) {
                $domains[] = $domain;
            }
        }
        return $domains;
    }
}

if (!hm_exists('vendor_detection_split_tokens')) {
    function vendor_detection_split_tokens($value) {
        $replace = array('(', ')', '[', ']', '<', '>', ';', '"', '\'', "\r", "\n", "\t", ',', '=');
        $clean = str_replace($replace, ' ', $value);
        $clean = str_replace('  ', ' ', $clean);
        return array_filter(explode(' ', $clean), function($token) {
            return $token !== '';
        });
    }
}

if (!hm_exists('vendor_detection_token_to_domain')) {
    function vendor_detection_token_to_domain($token) {
        $token = trim($token, ".:;");
        if (!$token) {
            return '';
        }
        if (strpos($token, '@') !== false) {
            $parts = explode('@', $token);
            $token = end($parts);
        }
        $token = mb_strtolower($token);
        if (filter_var($token, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return $token;
        }
        return '';
    }
}
if (!hm_exists('vendor_detection_unique_lowercase')) {
    function vendor_detection_unique_lowercase($vals) {
        $unique = array();
        foreach ($vals as $val) {
            $val = mb_strtolower(trim($val));
            if ($val && !in_array($val, $unique, true)) {
                $unique[] = $val;
            }
        }
        return $unique;
    }
}

if (!hm_exists('vendor_detection_match_vendor')) {
    function vendor_detection_match_vendor($signals, $vendor, $default_rules) {
        $vendor_id = $vendor['vendor_id'] ?? '';
        $vendor_name = $vendor['name'] ?? '';
        if (!$vendor_id || !$vendor_name) {
            return null;
        }
        $evidence = array();
        $matched_domains = array();
        $header_names = $signals['header_names'];

        $dkim_domains = vendor_detection_lowercase_list($vendor['dkim_domains'] ?? array());
        $return_path_domains = vendor_detection_lowercase_list($vendor['return_path_domains'] ?? array());
        $received_domains = vendor_detection_lowercase_list($vendor['received_domains'] ?? array());
        $platform_domains = vendor_detection_lowercase_list($vendor['platform_domains'] ?? array());
        $header_names_expected = vendor_detection_lowercase_list($vendor['header_names'] ?? array());
        $header_prefixes = vendor_detection_lowercase_list($vendor['header_prefixes'] ?? array());

        $matched = vendor_detection_match_domains($signals['dkim_domains'], $dkim_domains);
        foreach ($matched as $domain) {
            $evidence[] = vendor_detection_build_evidence('dkim_domain', $domain, 'DKIM domain matched vendor registry');
            $matched_domains[] = $domain;
        }
        $matched = vendor_detection_match_domains($signals['return_path_domains'], $return_path_domains);
        foreach ($matched as $domain) {
            $evidence[] = vendor_detection_build_evidence('return_path_domain', $domain, 'Return-Path domain matched vendor registry');
            $matched_domains[] = $domain;
        }
        $matched = vendor_detection_match_domains($signals['received_domains'], $received_domains);
        foreach ($matched as $domain) {
            $evidence[] = vendor_detection_build_evidence('received_domain', $domain, 'Received chain matched vendor registry');
            $matched_domains[] = $domain;
        }
        foreach ($header_names_expected as $header_name) {
            if (in_array($header_name, $header_names, true)) {
                $evidence[] = vendor_detection_build_evidence('header_name', $header_name, 'Vendor-specific header matched');
            }
        }
        foreach ($header_prefixes as $prefix) {
            foreach ($header_names as $header_name) {
                if (strpos($header_name, $prefix) === 0) {
                    $evidence[] = vendor_detection_build_evidence('header_prefix', $header_name, 'Vendor-specific header prefix matched');
                }
            }
        }
        $from_matches = vendor_detection_match_domains($signals['from_domains'], $platform_domains);
        foreach ($from_matches as $domain) {
            $evidence[] = vendor_detection_build_evidence('from_domain', $domain, 'From domain matched vendor platform');
            $matched_domains[] = $domain;
        }
        $reply_matches = vendor_detection_match_domains($signals['reply_to_domains'], $platform_domains);
        foreach ($reply_matches as $domain) {
            $evidence[] = vendor_detection_build_evidence('reply_to_domain', $domain, 'Reply-To domain matched vendor platform');
            $matched_domains[] = $domain;
        }

        if (empty($evidence)) {
            return null;
        }

        $confidence_rules = $default_rules;
        if (isset($vendor['confidence_rules']) && is_array($vendor['confidence_rules'])) {
            $confidence_rules = $vendor['confidence_rules'];
        }
        $confidence = vendor_detection_pick_confidence($evidence, $confidence_rules);
        if (!$confidence) {
            return null;
        }

        $controller_domain = '';
        $from_domain = $signals['from_domains'][0] ?? '';
        $platform_domain_pool = $platform_domains ? $platform_domains : array_merge($dkim_domains, $return_path_domains, $received_domains);
        $platform_domain_pool = vendor_detection_unique_lowercase($platform_domain_pool);
        if ($from_domain && !in_array($from_domain, $platform_domain_pool, true)) {
            $controller_domain = $from_domain;
        }

        return array(
            'vendor_id' => $vendor_id,
            'vendor_name' => $vendor_name,
            'confidence' => $confidence,
            'evidence' => $evidence,
            'platform_domains' => vendor_detection_unique_lowercase($matched_domains),
            'controller_domain' => $controller_domain
        );
    }
}

if (!hm_exists('vendor_detection_match_domains')) {
    function vendor_detection_match_domains($message_domains, $vendor_domains) {
        if (empty($message_domains) || empty($vendor_domains)) {
            return array();
        }
        $matches = array();
        foreach ($message_domains as $domain) {
            if (in_array($domain, $vendor_domains, true)) {
                $matches[] = $domain;
            }
        }
        return vendor_detection_unique_lowercase($matches);
    }
}

if (!hm_exists('vendor_detection_pick_confidence')) {
    function vendor_detection_pick_confidence($evidence, $confidence_rules) {
        $types = array();
        foreach ($evidence as $item) {
            if (isset($item['type'])) {
                $types[$item['type']] = true;
            }
        }
        $order = array('high', 'medium', 'low');
        foreach ($order as $level) {
            if (!isset($confidence_rules[$level]) || !is_array($confidence_rules[$level])) {
                continue;
            }
            foreach ($confidence_rules[$level] as $type) {
                if (isset($types[$type])) {
                    return $level;
                }
            }
        }
        return '';
    }
}

if (!hm_exists('vendor_detection_is_better_match')) {
    function vendor_detection_is_better_match($candidate, $current) {
        $rank = array('high' => 3, 'medium' => 2, 'low' => 1);
        $candidate_rank = $rank[$candidate['confidence']] ?? 0;
        $current_rank = $rank[$current['confidence']] ?? 0;
        if ($candidate_rank !== $current_rank) {
            return $candidate_rank > $current_rank;
        }
        $candidate_count = isset($candidate['evidence']) ? count($candidate['evidence']) : 0;
        $current_count = isset($current['evidence']) ? count($current['evidence']) : 0;
        return $candidate_count > $current_count;
    }
}

if (!hm_exists('vendor_detection_build_evidence')) {
    function vendor_detection_build_evidence($type, $value, $description) {
        return array(
            'type' => $type,
            'value' => $value,
            'description' => $description
        );
    }
}

if (!hm_exists('vendor_detection_lowercase_list')) {
    function vendor_detection_lowercase_list($vals) {
        if (!is_array($vals)) {
            return array();
        }
        return vendor_detection_unique_lowercase($vals);
    }
}
