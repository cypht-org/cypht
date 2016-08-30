<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

/**
 * Cache related methods
 * @subpackage imap/lib
 */
class Hm_IMAP_Cache extends Hm_IMAP_Parser {

    /**
     * update the cache untagged QRESYNC FETCH responses
     * @param array $data low level parsed IMAP response segment
     * @return int 1 if the cache was updated
     */
    protected function update_cache_data($data) {
        if (!$this->use_cache) {
            return 0;
        }
        $res = 0;
        if (in_array('VANISHED', $data)) {
            $uid = $this->get_adjacent_response_value($data, -1, 'VANISHED');
            if ($this->is_clean($uid, 'uid')) {
                if (isset($this->cache_data[$this->selected_mailbox['name']])) {
                    $key = $this->selected_mailbox['name'];
                    foreach ($this->cache_data[$key] as $command => $result) {
                        if (strstr($command, 'UID FETCH')) {
                            if (isset($result[$uid])) {
                                unset($this->cache_data[$key][$command][$uid]);
                                $this->debug[] = sprintf('Removed message from cache using QRESYNC response (uid: %s)', $uid);
                                $res = 1;
                            }
                        }
                        elseif (strstr($command, 'UID SORT')) {
                            $index = array_search($uid, $result);
                            if ($index !== false) {
                                unset($this->cache_data[$key][$command][$index]);
                                $this->debug[] = sprintf('Removed message from cache using QRESYNC response (uid: %s)', $uid);
                                $res = 1;
                            }
                        }
                        elseif (strstr($command, 'UID SEARCH')) {
                            $index = array_search($uid, $result);
                            if ($index !== false) {
                                unset($this->cache_data[$key][$command][$index]);
                                $this->debug[] = sprintf('Removed message from cache using QRESYNC response (uid: %s)', $uid);
                                $res = 1;
                            }
                        }
                    }
                }
            }
        }
        else {
            $flags = array();
            $uid = $this->get_adjacent_response_value($data, -1, 'UID');
            if ($this->is_clean($uid, 'uid')) {
                $flag_start = array_search('FLAGS', $data);
                if ($flag_start !== false) {
                    $flags = $this->get_flag_values(array_slice($data, $flag_start));
                }
            }
            if ($uid) {
                if (isset($this->cache_data[$this->selected_mailbox['name']])) {
                    $key = $this->selected_mailbox['name'];
                    foreach ($this->cache_data[$key] as $command => $result) {
                        if (strstr($command, 'UID FETCH')) {
                            if (isset($result[$uid]['flags'])) {
                                $this->cache_data[$key][$command][$uid]['flags'] = implode(' ', $flags);
                                $this->debug[] = sprintf('Updated cache data from QRESYNC response (uid: %s)', $uid);
                                $res = 1;
                            }
                            elseif (isset($result['flags'])) {
                                $this->cache_data[$key][$command]['flags'] = implode(' ', $flags);
                                $this->debug[] = sprintf('Updated cache data from QRESYNC response (uid: %s)', $uid);
                                $res = 1;
                            }
                            elseif (isset($result['Flags'])) {
                                $this->cache_data[$key][$command]['Flags'] = implode(' ', $flags);
                                $this->debug[] = sprintf('Updated cache data from QRESYNC response (uid: %s)', $uid);
                                $res = 1;
                            }
                        }
                    }
                }
            }
        }
        return $res;
    }

    /**
     * cache certain IMAP command return values for re-use
     * @param array $res low level parsed IMAP response
     * @return array initial low level parsed IMAP response argument
     */
    protected function cache_return_val($res, $command) {
        if (!$this->use_cache) {
            return $res;
        }
        $command = str_replace(array("\r", "\n"), array(''), preg_replace("/^A\d+ /", '', $command));
        if (preg_match("/^SELECT/", $command)) {
            $this->cache_data['SELECT'][$command] = $res;
        }
        elseif (preg_match("/^EXAMINE/", $command)) {
            $this->cache_data['EXAMINE'][$command] = $res;
        }
        elseif (preg_match("/^LIST/", $command)) {
            $this->cache_data['LIST'][$command] = $res;
        }
        elseif (preg_match("/^LSUB/", $command)) {
            $this->cache_data['LSUB'][$command] = $res;
        }
        elseif (preg_match("/^NAMESPACE/", $command)) {
            $this->cache_data['NAMESPACE'] = $res;
        }
        elseif ($this->selected_mailbox) {
            $key = $this->selected_mailbox['name'];
            $this->cache_data[$key][$command] = $res;
        }
        $count = 0;
        foreach ($this->cache_data as $commands) {
            $count += count($commands);
        }
        if ($count > $this->cache_limit) {
            $this->prune_cache($count);
        }
        return $res;
    }

    /**
     * search for cache entries to prune
     * @param int $count current number of cache entries
     * @param array $exclude list of cache keys to skip
     * @return array list of key tuples of cache entries to prune
     */
    protected function collect_cache_entries_to_prune($count, $exclude) {
        $to_remove = array();
        if ($count > $this->cache_limit) {
            foreach ($this->cache_data as $key => $commands) {
                if ( in_array( $key, $exclude ) ) {
                    continue;
                }
                foreach ($commands as $command => $value) {
                    $to_remove[] = array($key, $command); 
                    $count--;
                    if ($count == $this->cache_limit) {
                        break 2;
                    }
                }
            }
        }
        return $to_remove;
    }

    /**
     * prune the IMAP cache if it needs it
     * @return void
     */
    protected function prune_cache($count) {
        $current_key = false;
        $to_remove = array();
        if (isset($this->selected_mailbox['name'])) {
            if (isset($this->cache_data[$this->selected_mailbox['name']])) {
                $current_key = $this->selected_mailbox['name'];
            }
        }
        $to_remove = $this->collect_cache_entries_to_prune($count, array($current_key, 'LIST', 'LSUB', 'NAMESPACE' ));
        $count -= count($to_remove);
        if ($count > $this->cache_limit) {
            $to_remove = $this->collect_cache_entries_to_prune($count, array($current_key));
            $count -= count($to_remove);
            if ($count > $this->cache_limit) {
                $to_remove = $this->collect_cache_entries_to_prune($count, array());
                $count -= count($to_remove);
            }
        }
        if (!empty($to_remove)) {
            foreach($to_remove as $keys) {
                $this->debug[] = sprintf('Unset cache at (%s) for key (%s)', $keys[0], $keys[1]);
                unset($this->cache_data[$keys[0]][$keys[1]]);
            }
        }
    }

    /**
     * determine if the current command can be served from cache
     * @param string $command IMAP command to check
     * @param bool $check_only flag to avoid double logging
     * @return mixed cached result or false if there is no cached data to use
     */
    protected function check_cache($command, $check_only=false) {
        if (!$this->use_cache) {
            return false;
        }
        $command = str_replace(array("\r", "\n"), array(''), preg_replace("/^A\d+ /", '', $command));
        $res = false;
        $msg = '';
        if (preg_match("/^SELECT/", $command) && isset($this->cache_data['SELECT'][$command])) {
            $res = $this->cache_data['SELECT'][$command];
            $msg = 'Found cached mailbox state: '.$command;
        }
        elseif (preg_match("/^EXAMINE/", $command) && isset($this->cache_data['EXAMINE'][$command])) {
            $res = $this->cache_data['EXAMINE'][$command];
            $msg = 'Found cached mailbox state: '.$command;
        }
        elseif (preg_match("/^LIST/ ", $command) && isset($this->cache_data['LIST'][$command])) {
            $msg = 'IMAP Cache hit for: '.$command;
            $res = $this->cache_data['LIST'][$command];
        }
        elseif (preg_match("/^LSUB /", $command) && isset($this->cache_data['LSUB'][$command])) {
            $msg = 'IMAP Cache hit for: '.$command;
            $res = $this->cache_data['LSUB'][$command];
        }
        elseif (preg_match("/^NAMESPACE/", $command) && isset($this->cache_data['NAMESPACE'])) {
            $msg = 'IMAP Cache hit for: '.$command;
            $res = $this->cache_data['NAMESPACE'];
        }
        elseif ($this->selected_mailbox) {

            $box = $this->selected_mailbox['name'];

            if (isset($this->cache_data[$box][$command])) {
                $msg = 'IMAP Cache hit for: '.$box.' with: '.$command;
                $res = $this->cache_data[$box][$command];
            }
        }
        if ($msg) {
            Hm_Debug::add($msg);
            $this->cached_response = true;
            $this->debug[] = $msg;
        }
        if ($check_only) {
            return $res ? true : false;
        }
        return $res;
    }

    /**
     * invalidate parts of the data cache
     * @param string $type can be one of LIST, LSUB, ALL, or a mailbox name
     * @return void
     */
    public function bust_cache($type, $full=true) {
        if (!$this->use_cache) {
            return;
        }
        switch($type) {
            case 'LIST':
                if (isset($this->cache_data['LIST'])) {
                    unset($this->cache_data['LIST']);
                    $this->debug[] = 'cache busted: '.$type;
                }
                break;
            case 'LSUB':
                if (isset($this->cache_data['LSUB'])) {
                    unset($this->cache_data['LSUB']);
                    $this->debug[] = 'cache busted: '.$type;
                }
                break;
            case 'ALL':
                $this->cache_data = array();
                $this->debug[] = 'cache busted: '.$type;
                break;
            default:
                if (isset($this->cache_data[$type])) {
                    if (!$full) {
                        foreach ($this->cache_data[$type] as $command => $res) {
                            if (!preg_match("/^UID FETCH/", $command)) { 
                                unset($this->cache_data[$type][$command]);
                                $this->debug[] = 'Partial cache flush: '.$command;
                            }
                        }
                    }
                    else {
                        unset($this->cache_data[$type]);
                        $this->debug[] = 'cache busted: '.$type;
                    }
                }
                break;
        }
    }

    /**
     * output a string version of the cache that can be re-used
     * @return string serialized version of the cache data
     */
    public function dump_cache( $type = 'string') {
        if ($type == 'array') {
            return $this->cache_data;
        }
        elseif ($type == 'gzip') {
            return gzcompress(serialize($this->cache_data));
        }
        else {
            return serialize($this->cache_data);
        }
    }

    /**
     * load cache data from the output of dump_cache()
     * @param string $data serialized cache data from dump_cache()
     * @return void
     */
    public function load_cache($data, $type='string') {
        if ($type == 'array') {
            if (is_array($data)) {
                $this->cache_data = $data;
                $this->debug[] = 'Cache loaded: '.count($this->cache_data);
            }
        }
        elseif ($type == 'gzip') {
            $data = unserialize(gzuncompress($data));
            if (is_array($data)) {
                $this->cache_data = $data;
                $this->debug[] = 'Cache loaded: '.count($this->cache_data);
            }
        }
        else {
            $data = unserialize($data);
            if (is_array($data)) {
                $this->cache_data = $data;
                $this->debug[] = 'Cache loaded: '.count($this->cache_data);
            }
        }
    }

}
