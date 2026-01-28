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

if (!hm_exists('vendor_detection_get_vendor_by_id')) {
    function vendor_detection_get_vendor_by_id($vendor_id, $registry = null) {
        if (!$vendor_id) {
            return array();
        }
        if ($registry === null) {
            $registry = vendor_detection_load_registry();
        }
        $registry = vendor_detection_normalize_registry($registry);
        foreach ($registry['vendors'] as $vendor) {
            if (!empty($vendor['vendor_id']) && $vendor['vendor_id'] === $vendor_id) {
                return $vendor;
            }
        }
        return array();
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

if (!hm_exists('vendor_detection_load_datarequests_registry')) {
    function vendor_detection_load_datarequests_registry($path = null) {
        static $cached = null;
        $use_cache = $path === null;
        if ($use_cache && $cached !== null) {
            return $cached;
        }
        if (!$path) {
            $path = APP_PATH.'assets/data/datarequests_companies.json';
        }
        $registry = array(
            'entities' => array(),
            'companies' => array()
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
            if (isset($data['entities']) && is_array($data['entities'])) {
                $registry['entities'] = vendor_detection_normalize_entities($data['entities']);
            } elseif (isset($data['companies']) && is_array($data['companies'])) {
                $registry['companies'] = vendor_detection_normalize_companies($data['companies']);
            } elseif (vendor_detection_is_assoc_array($data)) {
                $registry['companies'] = vendor_detection_normalize_companies($data);
            } elseif (isset($data['vendors']) && is_array($data['vendors'])) {
                $registry['companies'] = vendor_detection_normalize_companies($data['vendors']);
            }
        }
        if ($use_cache) {
            $cached = $registry;
        }
        return $registry;
    }
}

if (!hm_exists('vendor_detection_match_data_request')) {
    function vendor_detection_match_data_request($vendor_detection, $msg_headers, $registry = null) {
        if (!is_array($vendor_detection)) {
            $vendor_detection = array();
        }
        if ($registry === null) {
            $registry = vendor_detection_load_datarequests_registry();
        }
        $entities = $registry['entities'] ?? array();
        $companies = $registry['companies'] ?? array();
        if (empty($entities) && empty($companies)) {
            return array();
        }
        $controller_domains = vendor_detection_get_controller_domains($msg_headers, $vendor_detection);
        $platform_domains = vendor_detection_unique_lowercase($vendor_detection['platform_domains'] ?? array());
        $vendor_id = $vendor_detection['vendor_id'] ?? '';
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('[data_request_match_debug] '.json_encode(array(
                'controller_domains' => $controller_domains,
                'vendor_id' => $vendor_id,
                'platform_domains' => $platform_domains,
                'has_vendor_detection' => !empty($vendor_detection)
            )));
        }

        if (!empty($controller_domains)) {
            if (!empty($entities)) {
                foreach ($controller_domains as $controller_domain) {
                    foreach ($entities as $entity) {
                        $match = vendor_detection_match_entity_controller($entity, $controller_domain);
                        if ($match) {
                            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                                error_log('[data_request_match_debug] matched controller domain');
                            }
                            return $match;
                        }
                    }
                }
            } else {
                foreach ($controller_domains as $controller_domain) {
                    foreach ($companies as $entry) {
                        $match = vendor_detection_match_data_request_entry(
                            $entry,
                            $controller_domain,
                            $vendor_id,
                            $platform_domains,
                            true
                        );
                        if ($match) {
                            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                                error_log('[data_request_match_debug] matched controller domain');
                            }
                            return $match;
                        }
                    }
                }
            }
        }

        if (!empty($controller_domains)) {
            if (!empty($entities)) {
                foreach ($controller_domains as $controller_domain) {
                    foreach ($entities as $entity) {
                        $match = vendor_detection_match_entity_domain_fallback($entity, $controller_domain);
                        if ($match) {
                            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                                error_log('[data_request_match_debug] matched controller domain fallback');
                            }
                            return $match;
                        }
                    }
                }
            } else {
                foreach ($controller_domains as $controller_domain) {
                    foreach ($companies as $entry) {
                        $match = vendor_detection_match_data_request_domain_fallback($entry, $controller_domain);
                        if ($match) {
                            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                                error_log('[data_request_match_debug] matched controller domain fallback');
                            }
                            return $match;
                        }
                    }
                }
            }
        }

        if (!empty($entities)) {
            foreach ($entities as $entity) {
                $match = vendor_detection_match_entity_vendor($entity, $vendor_id, $platform_domains);
                if ($match) {
                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log('[data_request_match_debug] matched vendor fallback');
                    }
                    return $match;
                }
            }
        } else {
            $first_controller = $controller_domains[0] ?? '';
            foreach ($companies as $entry) {
                $match = vendor_detection_match_data_request_entry(
                    $entry,
                    $first_controller,
                    $vendor_id,
                    $platform_domains,
                    false
                );
                if ($match) {
                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log('[data_request_match_debug] matched vendor fallback');
                    }
                    return $match;
                }
            }
        }

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('[data_request_match_debug] no match');
        }
        return array();
    }
}

if (!hm_exists('vendor_detection_match_data_request_entry')) {
    function vendor_detection_match_data_request_entry($entry, $controller_domain, $vendor_id, $platform_domains, $controller_only) {
        $domains = vendor_detection_lowercase_list($entry['domains'] ?? array());
        $vendor_ids = vendor_detection_lowercase_list($entry['vendor_ids'] ?? array());
        $type = $entry['type'] ?? '';
        $is_controller = $type === 'controller';

        if ($controller_only && !$is_controller) {
            return array();
        }

        if ($controller_domain) {
            foreach ($domains as $domain) {
                if (vendor_detection_domain_matches($controller_domain, $domain)) {
                    return vendor_detection_build_data_request_match($entry, 'controller_domain', $controller_domain, array());
                }
            }
        }

        if ($controller_only) {
            return array();
        }

        if ($vendor_id && in_array(mb_strtolower($vendor_id), $vendor_ids, true)) {
            return vendor_detection_build_data_request_match($entry, 'vendor_id', $vendor_id, array());
        }

        foreach ($platform_domains as $platform_domain) {
            foreach ($domains as $domain) {
                if (vendor_detection_domain_matches($platform_domain, $domain)) {
                    return vendor_detection_build_data_request_match($entry, 'platform_domain', $platform_domain, array());
                }
            }
        }

        return array();
    }
}

if (!hm_exists('vendor_detection_build_data_request_match')) {
    function vendor_detection_build_data_request_match($entry, $match_type, $match_value, $entity) {
        return array(
            'vendor_id' => $entry['slug'] ?? ($entry['id'] ?? ''),
            'vendor_name' => $entry['name'] ?? '',
            'company_type' => $entry['type'] ?? '',
            'country' => $entry['country'] ?? '',
            'datarequests_url' => $entry['datarequests_url'] ?? '',
            'gdpr' => $entry['gdpr'] ?? array(),
            'entity_id' => $entity['id'] ?? '',
            'entity_name' => $entity['name'] ?? '',
            'match_type' => $match_type,
            'match_value' => $match_value
        );
    }
}

if (!hm_exists('vendor_detection_match_entity_controller')) {
    function vendor_detection_match_entity_controller($entity, $controller_domain) {
        $entries = $entity['entries'] ?? array();
        foreach ($entries as $entry) {
            if (($entry['type'] ?? '') !== 'controller') {
                continue;
            }
            $domains = vendor_detection_lowercase_list($entry['domains'] ?? array());
            foreach ($domains as $domain) {
                if (vendor_detection_domain_matches($controller_domain, $domain)) {
                    return vendor_detection_build_data_request_match($entry, 'controller_domain', $controller_domain, $entity);
                }
            }
        }
        return array();
    }
}

if (!hm_exists('vendor_detection_match_entity_domain_fallback')) {
    function vendor_detection_match_entity_domain_fallback($entity, $controller_domain) {
        $entries = $entity['entries'] ?? array();
        foreach ($entries as $entry) {
            $domains = vendor_detection_lowercase_list($entry['domains'] ?? array());
            foreach ($domains as $domain) {
                if (vendor_detection_domain_matches($controller_domain, $domain)) {
                    return vendor_detection_build_data_request_match($entry, 'controller_domain', $controller_domain, $entity);
                }
            }
        }
        return array();
    }
}

if (!hm_exists('vendor_detection_match_data_request_domain_fallback')) {
    function vendor_detection_match_data_request_domain_fallback($entry, $controller_domain) {
        $domains = vendor_detection_lowercase_list($entry['domains'] ?? array());
        foreach ($domains as $domain) {
            if (vendor_detection_domain_matches($controller_domain, $domain)) {
                return vendor_detection_build_data_request_match($entry, 'controller_domain', $controller_domain, array());
            }
        }
        return array();
    }
}

if (!hm_exists('vendor_detection_match_entity_vendor')) {
    function vendor_detection_match_entity_vendor($entity, $vendor_id, $platform_domains) {
        $vendor_ids = vendor_detection_lowercase_list($entity['vendor_ids'] ?? array());
        $entries = $entity['entries'] ?? array();
        $vendor_id = mb_strtolower((string) $vendor_id);

        if ($vendor_id && in_array($vendor_id, $vendor_ids, true)) {
            $entry = vendor_detection_pick_entity_fallback_entry($entries);
            if ($entry) {
                return vendor_detection_build_data_request_match($entry, 'vendor_id', $vendor_id, $entity);
            }
        }

        foreach ($platform_domains as $platform_domain) {
            foreach ($entries as $entry) {
                $domains = vendor_detection_lowercase_list($entry['domains'] ?? array());
                foreach ($domains as $domain) {
                    if (vendor_detection_domain_matches($platform_domain, $domain)) {
                        return vendor_detection_build_data_request_match($entry, 'platform_domain', $platform_domain, $entity);
                    }
                }
            }
        }

        return array();
    }
}

if (!hm_exists('vendor_detection_pick_entity_fallback_entry')) {
    function vendor_detection_pick_entity_fallback_entry($entries) {
        $processor = null;
        foreach ($entries as $entry) {
            if (($entry['type'] ?? '') === 'processor') {
                $processor = $entry;
                break;
            }
        }
        if ($processor) {
            return $processor;
        }
        return $entries[0] ?? null;
    }
}

if (!hm_exists('vendor_detection_get_controller_domain')) {
    function vendor_detection_get_controller_domain($vendor_detection, $msg_headers) {
        $domains = vendor_detection_get_controller_domains($msg_headers, $vendor_detection);
        return $domains[0] ?? '';
    }
}

if (!hm_exists('vendor_detection_get_controller_domains')) {
    function vendor_detection_get_controller_domains($msg_headers, $vendor_detection = array()) {
        $domains = array();
        if (is_array($vendor_detection) && !empty($vendor_detection['controller_domain'])) {
            $domains[] = $vendor_detection['controller_domain'];
        }
        if (function_exists('lc_headers')) {
            $headers = lc_headers($msg_headers);
            foreach (array('reply-to', 'from', 'return-path') as $header_name) {
                $value = $headers[$header_name] ?? '';
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $domains = array_merge($domains, vendor_detection_extract_domains_from_address_header($item));
                    }
                } elseif ($value) {
                    $domains = array_merge($domains, vendor_detection_extract_domains_from_address_header($value));
                }
            }
        }
        $normalized = array();
        foreach ($domains as $domain) {
            $normalized_domain = vendor_detection_normalize_domain($domain);
            if ($normalized_domain) {
                $normalized[] = $normalized_domain;
            }
        }
        return vendor_detection_unique_lowercase($normalized);
    }
}

if (!hm_exists('vendor_detection_normalize_domain')) {
    function vendor_detection_normalize_domain($value) {
        if (!$value) {
            return '';
        }
        $value = trim($value);
        if (strpos($value, '@') !== false) {
            $parts = explode('@', $value);
            $value = end($parts);
        }
        $value = trim($value, ". \t\r\n");
        $value = mb_strtolower($value);
        if (filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return $value;
        }
        return '';
    }
}

if (!hm_exists('vendor_detection_domain_matches')) {
    function vendor_detection_domain_matches($value, $domain) {
        if (!$value || !$domain) {
            return false;
        }
        $value = mb_strtolower($value);
        $domain = mb_strtolower($domain);
        if ($value === $domain) {
            return true;
        }
        return substr($value, -1 * (strlen($domain) + 1)) === '.'.$domain;
    }
}

if (!hm_exists('vendor_detection_get_datarequests_base_url')) {
    function vendor_detection_get_datarequests_base_url($language) {
        $language = mb_strtolower((string) $language);
        if ($language) {
            $parts = preg_split('/[_-]/', $language);
            $language = $parts[0] ?? $language;
        }
        $supported = array('fr', 'de', 'es', 'it');
        if ($language && in_array($language, $supported, true)) {
            return 'https://'.$language.'.datarequests.org';
        }
        return 'https://www.datarequests.org';
    }
}

if (!hm_exists('vendor_detection_build_datarequests_generator_url')) {
    function vendor_detection_build_datarequests_generator_url($company_slug, $request_type, $language) {
        if (!$company_slug || !$request_type) {
            return '';
        }
        $base_url = vendor_detection_get_datarequests_base_url($language);
        $query = http_build_query(array(
            'company' => $company_slug,
            'request_type' => $request_type
        ));
        return $base_url.'/generator?'.$query;
    }
}

if (!hm_exists('vendor_detection_normalize_companies')) {
    function vendor_detection_normalize_companies($companies) {
        $normalized = array();
        foreach ($companies as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (empty($entry['slug'])) {
                $entry['slug'] = is_string($key) ? $key : '';
            }
            $normalized[] = $entry;
        }
        return $normalized;
    }
}

if (!hm_exists('vendor_detection_normalize_entities')) {
    function vendor_detection_normalize_entities($entities) {
        $normalized = array();
        foreach ($entities as $key => $entity) {
            if (!is_array($entity)) {
                continue;
            }
            if (empty($entity['id'])) {
                $entity['id'] = is_string($key) ? $key : '';
            }
            $entries = $entity['entries'] ?? array();
            $entity['entries'] = vendor_detection_normalize_companies($entries);
            $normalized[] = $entity;
        }
        return $normalized;
    }
}

if (!hm_exists('vendor_detection_is_assoc_array')) {
    function vendor_detection_is_assoc_array($array) {
        if (!is_array($array)) {
            return false;
        }
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }
}
