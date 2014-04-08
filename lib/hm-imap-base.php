<?php

/*  hm-imap-base.php: Base class for a generic PHP5 IMAP client library.

    This code is derived from the IMAP library used in Hastymail2 (www.hastymail.org)
    and is covered by the same license restrictions (GPL2)

    Hastymail is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Hastymail is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Hastymail; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


/* base functions for IMAP communication */
class Hm_IMAP_Base {

    public $cached_response = false;           // flag to indicate we are using a cached response
    protected $handle = false;                 // fsockopen handle to the IMAP server
    protected $debug = array();                // debug messages
    protected $commands = array();             // list of IMAP commands issued
    protected $responses = array();            // list of raw IMAP responses
    protected $current_command = false;        // current/latest IMAP command issued
    protected $max_read = false;               // limit on allowable read size
    protected $command_count = 0;              // current command number
    protected $cache_data = array();           // cache data
    protected $supported_extensions = array(); // IMAP extensions in the CAPABILITY response
    protected $enabled_extensions = array();   // IMAP extensions validated by the ENABLE response
    protected $capability = false;             // IMAP CAPABILITY response
    protected $server_id = array();            // server ID response values


    /* attributes that can be set for the IMAP connaction */
    protected $config = array('server', 'port', 'tls', 'read_only',
        'utf7_folders', 'auth', 'search_charset', 'sort_speedup', 'folder_max',
        'use_cache', 'max_history', 'blacklisted_extensions', 'app_name', 'app_version',
        'app_vendor', 'app_support_url', 'cache_limit', 'no_caps');

    /* supported extensions */
    protected $client_extensions = array('SORT', 'COMPRESS', 'NAMESPACE', 'CONDSTORE',
        'ENABLE', 'QRESYNC', 'MOVE', 'SPECIAL-USE', 'LIST-STATUS', 'UNSELECT', 'ID', 'X-GM-EXT-1',
        'ESEARCH', 'ESORT', 'QUOTA', 'LIST-EXTENDED');

    /* extensions to declare with ENABLE */
    protected $declared_extensions = array('CONDSTORE', 'QRESYNC');

    /**
     * increment the imap command prefix such that it counts
     * up on each command sent. ('A1', 'A2', ...)
     *
     * @return int new command count
     */
    private function command_number() {
        $this->command_count += 1;
        return $this->command_count;
    }

    /**
     * Read IMAP literal found during parse_line().
     *
     * @param $size int size of the IMAP literal to read
     * @param $max int max size to allow
     * @param $current int current size read
     * @param $line_length int amount to read in using fgets()
     *
     * @return array the data read and any "left over" data
     *               that was inadvertantly on the same line as
     *               the last fgets result
     */
    private function read_literal($size, $max, $current, $line_length) {
        $left_over = false;
        $literal_data = $this->fgets($line_length);
        $lit_size = strlen($literal_data);
        $current += $lit_size;
        while ($lit_size < $size) {
            $chunk = $this->fgets($line_length);
            $chunk_size = strlen($chunk);
            $lit_size += $chunk_size;
            $current += $chunk_size;
            $literal_data .= $chunk;
            if ($max && $current > $max) {
                $this->max_read = true;
                break;
            }
        }
        if ($this->max_read) {
            while ($lit_size < $size) {
                $temp = $this->fgets($line_length);
                $lit_size += strlen($temp);
            }
        }
        elseif ($size < strlen($literal_data)) {
            $left_over = substr($literal_data, $size);
            $literal_data = substr($literal_data, 0, $size);
        }
        return array($literal_data, $left_over);
    }

    /**
     * IMAP message part numbers are like one half integer and one half string :) This
     * routine "increments" them correctly
     *
     * @param $part string IMAP part number
     *
     * @return string part number incremented by one
     */
    protected function update_part_num($part) {
        if (!strstr($part, '.')) {
            $part++;
        }
        else {
            $parts = explode('.', $part);
            $parts[(count($parts) - 1)]++;
            $part = implode('.', $parts);
        }
        return $part;
    }

    /**
     * break up a "line" response from imap. If we find
     * a literal we read ahead on the stream and include it.
     *
     * @param $line string data read from the IMAP server
     * @param $current_size int size of current read operation
     * @param $max int maximum input size to allow
     * @param $line_length int chunk size to read literals with
     *
     * @return array a line continuation marker and the parsed data
     *               from the IMAP server
     */
    protected function parse_line($line, $current_size, $max, $line_length) {
        /* make it a bit easier to find "atoms" */
        $line = str_replace(')(', ') (', $line);

        /* will hold the line parts */
        $parts = array();

        /* flag to control if the line continues */
        $line_cont = false;

        /* line size */
        $len = strlen($line);

        /* walk through the line */
        for ($i=0;$i<$len;$i++) {

            /* this will hold one "atom" from the parsed line */
            $chunk = '';

            /* if we hit a newline exit the loop */
            if ($line{$i} == "\r" || $line{$i} == "\n") {
                $line_cont = false;
                break;
            }

            /* skip spaces */
            if ($line{$i} == ' ') {
                continue;
            }

            /* capture special chars as "atoms" */
            elseif ($line{$i} == '*' || $line{$i} == '[' || $line{$i} == ']' || $line{$i} == '(' || $line{$i} == ')') {
                $chunk = $line{$i};
            }
        
            /* regex match a quoted string */
            elseif ($line{$i} == '"') {
                if (preg_match("/^(\"[^\"\\\]*(?:\\\.[^\"\\\]*)*\")/", substr($line, $i), $matches)) {
                    $chunk = substr($matches[1], 1, -1);
                }
                $i += strlen($chunk) + 1;
            }

            /* IMAP literal */
            elseif ($line{$i} == '{') {
                $end = strpos($line, '}');
                if ($end !== false) {
                    $literal_size  = substr($line, ($i + 1), ($end - $i - 1));
                }
                $lit_result = $this->read_literal($literal_size, $max, $current_size, $line_length);
                $chunk = $lit_result[0];
                if (!isset($lit_result[1]) || $lit_result[1] != "\r\n") {
                    $line_cont = true;
                }
                $i = $len;
            }

            /* all other atoms */
            else {
                $marker = -1;

                /* don't include these three trailing chars in the atom */
                foreach (array(' ', ')', ']') as $v) {
                    $tmp_marker = strpos($line, $v, $i);
                    if ($tmp_marker !== false && ($marker == -1 || $tmp_marker < $marker)) {
                        $marker = $tmp_marker;
                    }
                }

                /* slice out the chunk */
                if ($marker !== false && $marker !== -1) {
                    $chunk = substr($line, $i, ($marker - $i));
                    $i += strlen($chunk) - 1;
                }
                else {
                    $chunk = rtrim(substr($line, $i));
                    $i += strlen($chunk);
                }
            }

            /* if we found a worthwhile chunk add it to the results set */
            if ($chunk) {
                $parts[] = $chunk;
            }
        }
        return array($line_cont, $parts);
    }

    /**
     * wrapper around fgets using $this->handle
     *
     * @param $len int max read length for fgets
     *
     * @return string data read from the IMAP server
     */
    protected function fgets($len=false) {
        if (is_resource($this->handle) && !feof($this->handle)) {
            if ($len) {
                return fgets($this->handle, $len);
            }
            else {
                return fgets($this->handle);
            }
        }
        return '';
    }

    /**
     * loop through "lines" returned from imap and parse them with parse_line() and read_literal.
     * it can return the lines in a raw format, or parsed into atoms. It also supports a maximum
     * number of lines to return, in case we did something stupid like list a loaded unix homedir
     *
     * @param $max int max size of response allowed
     * @param $chunked bool flag to parse the data into IMAP "atoms"
     * @param $line_length chunk size to read in literals using fgets
     * @param $sort bool flag for non-compliant sort result parsing speed up
     *
     * @return array of parsed or raw results
     */
    protected function get_response($max=false, $chunked=false, $line_length=8192, $sort=false) {
        /* defaults and results containers */
        $result = array();
        $current_size = 0;
        $chunked_result = array();
        $last_line_cont = false;
        $line_cont = false;
        $c = -1;
        $n = -1;

        /* start of do -> while loop to read from the IMAP server */
        do {
            $n++;

            /* if we loose connection to the server while reading terminate */
            if (!is_resource($this->handle) || feof($this->handle)) {
                break;
            }

            /* read in a line up to 8192 bytes */
            $result[$n] = $this->fgets($line_length);

            /* keep track of how much we have read and break out if we max out. This can
             * happen on large messages. We need this check to ensure we don't exhaust available
             * memory */
            $current_size += strlen($result[$n]);
            if ($max && $current_size > $max) {
                $this->max_read = true;
                break;
            }

            /* if the line is longer than 8192 bytes keep appending more reads until we find
             * an end of line char. Keep checking the max read length as we go */
            while(substr($result[$n], -2) != "\r\n" && substr($result[$n], -1) != "\n") {
                if (!is_resource($this->handle) || feof($this->handle)) {
                    break;
                }
                $result[$n] .= $this->fgets($line_length);
                if ($result[$n] === false) {
                    break;
                }
                $current_size += strlen($result[$n]);
                if ($max && $current_size > $max) {
                    $this->max_read = true;
                    break 2;
                }
            }

            /* check line continuation marker and grab previous index and parsed chunks */
            if ($line_cont) {
                $last_line_cont = true;
                $pres = $n - 1;
                if ($chunks) {
                    $pchunk = $c;
                }
            }

            /* If we are using quick parsing of the IMAP SORT response we know the results are simply space
             * delimited UIDs so quickly explode(). Otherwise we have to follow the spec and look for quoted
             * strings and literals in the parse_line() routine. */
            if ($sort) {
                $line_cont = false;
                $chunks = explode(' ', trim($result[$n]));
            }

            /* properly parse the line */
            else {
                list($line_cont, $chunks) = $this->parse_line($result[$n], $current_size, $max, $line_length);
            }

            /* merge lines that should have been recieved as one and add to results */
            if ($chunks && !$last_line_cont) {
                $c++;
            }
            if ($last_line_cont) {
                $result[$pres] .= ' '.implode(' ', $chunks);
                if ($chunks) {
                    $line_bits = array_merge($chunked_result[$pchunk], $chunks);
                    $chunked_result[$pchunk] = $line_bits;
                }
                $last_line_cont = false;
            }

            /* add line and parsed bits to result set */
            else {
                $result[$n] = implode(' ', $chunks);
                if ($chunked) {
                    $chunked_result[$c] = $chunks;
                }
            }

            /* check for untagged error condition. This represents a server problem but there is no reason
             * we can't attempt to recover with the partial response we received up until this point */
            if (substr(strtoupper($result[$n]), 0, 6) == '* BYE ') {
                break;
            }

        /* end outer loop when we receive the tagged response line */
        } while (substr($result[$n], 0, strlen('A'.$this->command_count)) != 'A'.$this->command_count);

        /* return either raw or parsed result */
        $this->responses[] = $result;
        if ($chunked) {
            $result = $chunked_result;
        }
        if ($this->current_command && isset($this->commands[$this->current_command])) {
            $start_time = $this->commands[$this->current_command];
            $this->commands[$this->current_command] = microtime(true) - $start_time;
            if (count($this->commands) >= $this->max_history) {
                array_shift($this->commands);
                array_shift($this->responses);
            }
        }
        return $result;
    }

    /**
     * put a prefix on a command and send it to the server
     *
     * @param $command string/array IMAP command
     * @param $piped bool if true builds a command set out of $command
     *
     * @return void
     */
    protected function send_command($command) {
        $this->cached_response = false;
        $command = 'A'.$this->command_number().' '.$command;

        /* send the command out to the server */
        if (is_resource($this->handle)) {
            $res = @fputs($this->handle, $command);
            if (!$res) {
                $this->debug[] = 'Error communicating with IMAP server: '.trim($command);
            }
        }

        /* save the command and time for the IMAP debug output option */
        if (strstr($command, 'LOGIN')) {
            $command = 'LOGIN';
        }
        $this->commands[trim($command)] = microtime( true );
        $this->current_command = trim($command);
    }

    /**
     * determine if an imap response returned an "OK", returns true or false
     *
     * @param $data array parsed IMAP response
     * @param $chunked bool flag defining the type of $data
     *
     * @return bool true to indicate a success response from the IMAP server
     */
    protected function check_response($data, $chunked=false, $log_failures=true) {
        $result = false;

        /* find the last bit of the parsed response and look for the OK atom */
        if ($chunked) {
            if (!empty($data) && isset($data[(count($data) - 1)])) {
                $vals = $data[(count($data) - 1)];
                if ($vals[0] == 'A'.$this->command_count) {
                    if (strtoupper($vals[1]) == 'OK') {
                        $result = true;
                    }
                }
            }
        }

        /* pattern match the last line of a raw response */
        else {
            $line = array_pop($data);
            if (preg_match("/^A".$this->command_count." OK/i", $line)) {
                $result = true;
            }
        }
        if (!$result && $log_failures) {
            $this->debug[] = 'Command FAILED: '.$this->current_command;
        }
        return $result;
    }

    /**
     * convert UTF-7 encoded forlder names to UTF-8
     *
     * @param $string string mailbox name to encode
     * 
     * @return encoded mailbox
     */
    protected function utf7_decode($string) {
        if ($this->utf7_folders) {
            $string = mb_convert_encoding($string, "UTF-8", "UTF7-IMAP" );
        }
        return $string;
    }

    /**
     * convert UTF-8 encoded forlder names to UTF-7
     *
     * @param $string string mailbox name to decode
     * 
     * @return decoded mailbox
     */
    protected function utf7_encode($string) {
        if ($this->utf7_folders) {
            $string = mb_convert_encoding($string, "UTF7-IMAP", "UTF-8" );
        }
        return $string;
    }

    /**
     * type checks
     *
     * @param $val string value to check
     * @param $type string type of value to check against
     *
     * @return bool true if the type check passed
     */
    protected function input_validate($val, $type) {
        $imap_search_charsets = array(
            'UTF-8',
            'US-ASCII',
            '',
        );
        $imap_keywords = array(
            'ARRIVAL',    'DATE',    'FROM',      'SUBJECT',
            'CC',         'TO',      'SIZE',      'UNSEEN',
            'SEEN',       'FLAGGED', 'UNFLAGGED', 'ANSWERED',
            'UNANSWERED', 'DELETED', 'UNDELETED', 'TEXT',
            'ALL', 'DRAFT', 'NEW', 'RECENT', 'OLD', 'UNDRAFT'
        );
        $valid = false;
        switch ($type) {
            case 'search_str':
                if (preg_match("/^[^\r\n]+$/", $val)) {
                    $valid = true;
                }
                break;
            case 'msg_part':
                if (preg_match("/^[\d\.]+$/", $val)) {
                    $valid = true;
                }
                break;
            case 'charset':
                if (!$val || in_array(strtoupper($val), $imap_search_charsets)) {
                    $valid = true;
                }
                break;
            case 'uid':
                if (ctype_digit((string) $val)) {
                    $valid = true;
                }
                break;
            case 'uid_list';
                if (preg_match("/^(\d+\s*,*\s*|(\d+|\*):(\d+|\*))+$/", $val)) {
                    $valid = true;
                }
                break;
            case 'mailbox';
                if (preg_match("/^[^\r\n]+$/", $val)) {
                    $valid = true;
                }
                break;
            case 'keyword';
                if (in_array(strtoupper($val), $imap_keywords)) {
                    $valid = true;
                }
                break;
        }
        return $valid;
    }

    /*
     * check for hacky stuff
     *
     * @param $val string value to check
     * @param $type string type the value should match
     *
     * @return bool true if the value matches the type spec
     */
    protected function is_clean($val, $type) {
        if (!$this->input_validate($val, $type)) {
            $this->debug[] = 'INVALID IMAP INPUT DETECTED: '.$type.' : '.$val;
            return false;
        }
        return true;
    }

    /**
     * overwrite defaults with supplied config array
     *
     * @param $config array associative array of configuration options
     *
     * @return void
     */
    protected function apply_config( $config ) {
        foreach($config as $key => $val) {
            if (in_array($key, $this->config)) {
                $this->{$key} = $val;
            }
        }
    }

}

/* IMAP specific parsing routines */
class Hm_IMAP_Parser extends Hm_IMAP_Base {

    /**
     * A single message part structure. This is a MIME type in the message that does NOT contain
     * any other attachments or additonal MIME types
     *
     * @param $array array low level parsed BODYSTRUCTURE response segment
     *
     * @return array strucutre representing the MIME format
     */
    protected function parse_single_part($array) {
        $vals = $array[0];
        array_shift($vals);
        array_pop($vals);
        $atts = array('name', 'filename', 'type', 'subtype', 'charset', 'id', 'description', 'encoding',
            'size', 'lines', 'md5', 'disposition', 'language', 'location', 'att_size', 'c_date', 'm_date');
        $res = array();
        if (count($vals) > 7) {
            $res['type'] = strtolower(trim(array_shift($vals)));
            $res['subtype'] = strtolower(trim(array_shift($vals)));
            if ($vals[0] == '(') {
                array_shift($vals);
                while($vals[0] != ')') {
                    if (isset($vals[0]) && isset($vals[1])) {
                        $res[strtolower($vals[0])] = $vals[1];
                        $vals = array_splice($vals, 2);
                    }
                }
                array_shift($vals);
            }
            else {
                array_shift($vals);
            }
            $res['id'] = array_shift($vals);
            $res['description'] = array_shift($vals);
            $res['encoding'] = strtolower(array_shift($vals));
            $res['size'] = array_shift($vals);
            if ($res['type'] == 'text' && isset($vals[0])) {
                $res['lines'] = array_shift($vals);
            }
            if (isset($vals[0]) && $vals[0] != ')') {
                $res['md5'] = array_shift($vals);
            }
            if (isset($vals[0]) && $vals[0] == '(') {
                array_shift($vals);
            }
            if (isset($vals[0]) && $vals[0] != ')') {
                $res['disposition'] = array_shift($vals);
                if (strtolower($res['disposition']) == 'attachment' && $vals[0] == '(') {
                    array_shift($vals);
                    $len = count($vals);
                    $flds = array('filename' => 'name', 'size' => 'att_size', 'creation-date' => 'c_date', 'modification-date' => 'm_date');
                    $index = 0;
                    for ($i=0;$i<$len;$i++) {
                        if ($vals[$i] == ')') {
                            $index = $i;
                            break;
                        }
                        if (isset($vals[$i]) && isset($flds[strtolower($vals[$i])]) && isset($vals[($i + 1)]) && $vals[($i + 1)] != ')') {
                            $res[$flds[strtolower($vals[$i])]] = $vals[($i + 1)];
                            $i++;
                        }
                    }
                    if ($index) {
                        array_splice($vals, 0, $index);
                    }
                    else {
                        array_shift($vals);
                    }
                    while ($vals[0] == ')') {
                        array_shift($vals);
                    }
                }
            }
            if (isset($vals[0])) {
                $res['language'] = array_shift($vals);
            }
            if (isset($vals[0])) {
                $res['location'] = array_shift($vals);
            }
            foreach ($atts as $v) {
                if (!isset($res[$v]) || trim(strtoupper($res[$v])) == 'NIL') {
                    $res[$v] = false;
                }
                else {
                    if ($v == 'charset') {
                        $res[$v] = strtolower(trim($res[$v]));
                    }
                    else {
                        $res[$v] = trim($res[$v]);
                    }
                }
            }
            if (!isset($res['name'])) {
                $res['name'] = 'message';
            }
        }
        return $res;
    }

    /**
     * filter out alternative mime types to simplify the end result
     *
     * @param $struct array nested array representing structure
     * @param $filter string mime type to prioritize
     * @param $parent_type string parent type to limit to
     * @param $cnt counter used in recursion
     *
     * @return $array filtered structure array excluding alternatives
     */
    protected function filter_alternatives($struct, $filter, $parent_type=false, $cnt=0) {
        $filtered = array();
        if (!is_array($struct) || empty($struct)) {
            return array($filtered, $cnt);
        }
        if (!$parent_type) {
            if (isset($struct['subtype'])) {
                $parent_type = $struct['subtype'];
            }
        }
        foreach ($struct as $index => $value) {
            if ($parent_type == 'alternative' && isset($value['subtype']) && $value['subtype'] != $filter) {
                    $cnt += 1;
                }
            else {
                $filtered[$index] = $value;
            }
            if (isset($value['subs']) && is_array($value['subs'])) {
                if (isset($struct['subtype'])) {
                    $parent_type = $struct['subtype'];
                }
                else {
                    $parent_type = false;
                }
                list($filtered[$index]['subs'], $cnt) = $this->filter_alternatives($value['subs'], $filter, $parent_type, $cnt);
            }
        }
        return array($filtered, $cnt);
    }

    /**
     * parse a multi-part mime message part
     *
     * @param $array array low level parsed BODYSTRUCTURE response segment
     * @param $part_num int IMAP message part number
     *
     * @return array structure representing the MIME format
     */
    protected function parse_multi_part($array, $part_num) {
        $struct = array();
        $index = 0;
        foreach ($array as $vals) {
            if ($vals[0] != '(') {
                break;
            }
            $type = strtolower($vals[1]);
            $sub = strtolower($vals[2]);
            $part_type = 1;
            switch ($type) {
                case 'message':
                    switch ($sub) {
                        case 'delivery-status':
                        case 'external-body':
                        case 'disposition-notification':
                        case 'rfc822-headers':
                            break;
                        default:
                            $part_type = 2;
                            break;
                    }
                    break;
            }
            if ($vals[0] == '(' && $vals[1] == '(') {
                $part_type = 3;
            }
            if ($part_type == 1) {
                $struct[$part_num] = $this->parse_single_part(array($vals));
                $part_num = $this->update_part_num($part_num);
            }
            elseif ($part_type == 2) {
                $parts = $this->split_toplevel_result($vals);
                $struct[$part_num] = $this->parse_rfc822($parts[0], $part_num);
                $part_num = $this->update_part_num($part_num);
            }
            else {
                $parts = $this->split_toplevel_result($vals);
                $struct[$part_num]['subs'] = $this->parse_multi_part($parts, $part_num.'.1');
                $part_num = $this->update_part_num($part_num);
            }
            $index++;
        }
        if (isset($array[$index][0])) {
            $struct['type'] = 'message';
            $struct['subtype'] = $array[$index][0];
        }
        return $struct;
    }
    
    /**
     * Parse a rfc822 message "container" part type
     *
     * @param $array array low level parsed BODYSTRUCTURE response segment
     * @param $part_num int IMAP message part number
     *
     * @return array strucutre representing the MIME format
     */
    protected function parse_rfc822($array, $part_num) {
        $res = array();
        array_shift($array);
        $res['type'] = strtolower(trim(array_shift($array)));
        $res['subtype'] = strtolower(trim(array_shift($array)));
        if ($array[0] == '(') {
            array_shift($array);
            while($array[0] != ')') {
                if (isset($array[0]) && isset($array[1])) {
                    $res[strtolower($array[0])] = $array[1];
                    $array = array_splice($array, 2);
                }
            }
            array_shift($array);
        }
        else {
            array_shift($array);
        }
        $res['id'] = array_shift($array);
        $res['description'] = array_shift($array);
        $res['encoding'] = strtolower(array_shift($array));
        $res['size'] = array_shift($array);
        $envelope = array();
        if ($array[0] == '(') {
            array_shift($array);
            $index = 0;
            $level = 1;
            foreach ($array as $i => $v) {
                if ($level == 0) {
                    $index = $i;
                    break;
                }
                $envelope[] = $v;
                if ($v == '(') {
                    $level++;
                }
                if ($v == ')') {
                    $level--;
                }
            }
            if ($index) {
                $array = array_splice($array, $index);
            }
        }
        $res = $this->parse_envelope($envelope, $res);
        $parts = $this->split_toplevel_result($array); 
        $res['subs'] = $this->parse_multi_part($parts, $part_num.'.1', $part_num);
        return $res;
    }

    /**
     *  helper function for parsing bodystruct responses
     *
     *  @param $array array low level parsed BODYSTRUCTURE response segment
     *
     *  @return array low level parsed data split at specific points in the result
     */
    protected function split_toplevel_result($array) {
        if (empty($array) || $array[1] != '(') {
            return array($array);
        }
        $level = 0;
        $i = 0;
        $res = array();
        foreach ($array as $val) {
            if ($val == '(') {
                $level++;
            }
            $res[$i][] = $val;
            if ($val == ')') {
                $level--;
            }
            if ($level == 1) {
                $i++;
            }
        }
        return array_splice($res, 1, -1);
    }

    /**
     * parse an envelope address
     *
     * @param $array array parsed sections from a BODYSTRUCTURE envelope address
     *
     * @return string string representation of the address
     */
    protected function parse_envelope_address($array) {
        $count = count($array) - 1;
        $string = '';
        $name = false;
        $mail = false;
        $domain = false;
        for ($i = 0;$i<$count;$i+= 6) {
            if (isset($array[$i + 1])) {
                $name = $array[$i + 1];
            }
            if (isset($array[$i + 3])) {
                $mail = $array[$i + 3];
            }
            if (isset($array[$i + 4])) {
                $domain = $array[$i + 4];
            }
            if ($name && strtoupper($name) != 'NIL') {
                $name = str_replace(array('"', "'"), '', $name);
                if ($string != '') {
                    $string .= ', ';
                }
                if ($name != $mail.'@'.$domain) {
                    $string .= '"'.$name.'" ';
                }
                if ($mail && $domain) {
                    $string .= $mail.'@'.$domain;
                }
            }
            if ($mail && $domain) {
                $string .= $mail.'@'.$domain;
            }
            $name = false;
            $mail = false;
            $domain = false;
        }
        return $string;
    }

    /**
     * parse a message envelope
     *
     * @param $array array parsed message envelope from a BODYSTRUCTURE response
     * @param $res current BODYSTRUCTURE representation
     *
     * @return array updated $res with message envelope details
     */
    protected function parse_envelope($array, $res) {
        $flds = array('date', 'subject', 'from', 'sender', 'reply-to', 'to', 'cc', 'bcc', 'in-reply-to', 'message_id');
        foreach ($flds as $val) {
            if (strtoupper($array[0]) != 'NIL') {
                if ($array[0] == '(') {
                    array_shift($array);
                    $parts = array();
                    $index = 0;
                    $level = 1;
                    foreach ($array as $i => $v) {
                        if ($level == 0) {
                            $index = $i;
                            break;
                        }
                        $parts[] = $v;
                        if ($v == '(') {
                            $level++;
                        }
                        if ($v == ')') {
                            $level--;
                        }
                    }
                    if ($index) {
                        $array = array_splice($array, $index);
                        $res[$val] = $this->parse_envelope_address($parts);
                    }
                }
                else {
                    $res[$val] = array_shift($array);
                }
            }
            else {
                $res[$val] = false;
            }
        }
        return $res;
    }

    /**
     * helper method to grab values from the SELECT response
     *
     * @param $vals array low level parsed select response segment
     * @param $offset int offset in the list to search for a value
     * @param $key string value in the array to start from
     *
     * @return int the adjacent value
     */
    protected function get_adjacent_response_value($vals, $offset, $key) {
        foreach ($vals as $i => $v) {
            $i += $offset;
            if (isset($vals[$i]) && $vals[$i] == $key) {
                return $v;
            }
        }
        return 0;
    }

    /**
     * helper function to cllect flags from the SELECT response
     *
     * @param $vals array low level parsed select response segment
     *
     * @return array list of flags
     */
    protected function get_flag_values($vals) {
        $collect_flags = false;
        $res = array();
        foreach ($vals as $i => $v) {
            if ($v == ')') {
                $collect_flags = false;
            }
            if ($collect_flags) {
                $res[] = $v;
            }
            if ($v == '(') {
                $collect_flags = true;
            }
        }
        return $res;
    }

    /**
     * compare filter keywords against message flags
     *
     * @param $filter string message type to filter by
     * @param $flags string IMAP flag value string
     *
     * @return bool true if the message matches the filter
     */ 
    protected function flag_match($filter, $flags) {
        $res = false;
        switch($filter) {
            case 'ANSWERED':
            case 'SEEN':
            case 'DRAFT':
            case 'DELETED':
                $res = stristr($flags, $filter);
                break;
            case 'UNSEEN':
            case 'UNDRAFT':
            case 'UNDELETED':
            case 'UNFLAGGED':
            case 'UNANSWERED':
                $res = !stristr($flags, str_replace('UN', '', $filter));
                break;
        }
        return $res;
    }

    /**
     * build a list of IMAP extensions from the capability response
     *
     * @return void
     */
    protected function parse_extensions_from_capability() {
        $extensions = array();
        foreach (explode(' ', $this->capability) as $word) {
            if (!in_array(strtolower($word), array('ok', 'completed', 'imap4rev1', 'capability'))) {
                $extensions[] = strtolower($word);
            }
        }
        $this->supported_extensions = $extensions;
    }

    /**
     * build a QRESYNC IMAP extension paramater for a SELECT statement
     *
     * @return string param to use
     */
    protected function build_qresync_params() {
        $param = '';
        if (isset($this->selected_mailbox['detail'])) {
            $box = $this->selected_mailbox['detail'];
            if (isset($box['uidvalidity']) && $box['uidvalidity'] && isset($box['modseq']) && $box['modseq']) {
                if ($this->is_clean($box['uidvalidity'], 'uid') && $this->is_clean($box['modseq'], 'uid')) {
                    $param = sprintf(' (QRESYNC (%s %s))', $box['uidvalidity'], $box['modseq']);
                }
            }
        }
        return $param;
    }

    /**
     * parse GETQUOTAROOT and GETQUOTA responses
     *
     * @param $data array low level parsed IMAP response segment
     *
     * @return array list of properties
     */
    protected function parse_quota_response($vals) {
        $current = 0;
        $max = 0;
        $name = '';
        if (in_array('QUOTA', $vals)) {
            $name = $this->get_adjacent_response_value($vals, -1, 'QUOTA');
            if ($name == '(') {
                $name = '';
            }
        }
        if (in_array('STORAGE', $vals)) {
            $current = $this->get_adjacent_response_value($vals, -1, 'STORAGE');
            $max = $this->get_adjacent_response_value($vals, -2, 'STORAGE');
        }
        return array($name, $max, $current);
    }

    /**
     * collect useful untagged responses about mailbox state from certain command responses
     *
     * @param $data array low level parsed IMAP response segment
     *
     * @return array list of properties
     */
    protected function parse_untagged_responses($data) {
        $cache_updates = 0;
        $cache_updated = 0;
        $qresync = false;
        $attributes = array(
            'uidnext' => false,
            'unseen' => false,
            'uidvalidity' => false,
            'exists' => false,
            'pflags' => false,
            'recent' => false,
            'modseq' => false,
            'flags' => false,
            'nomodseq' => false
        );
        foreach($data as $vals) {
            if (in_array('NOMODSEQ', $vals)) {
                $attributes['nomodseq'] = true;
            }
            if (in_array('MODSEQ', $vals)) {
                $attributes['modseq'] = $this->get_adjacent_response_value($vals, -2, 'MODSEQ');
            }
            if (in_array('HIGHESTMODSEQ', $vals)) {
                $attributes['modseq'] = $this->get_adjacent_response_value($vals, -1, 'HIGHESTMODSEQ');
            }
            if (in_array('UIDNEXT', $vals)) {
                $attributes['uidnext'] = $this->get_adjacent_response_value($vals, -1, 'UIDNEXT');
            }
            if (in_array('UNSEEN', $vals)) {
                $attributes['unseen'] = $this->get_adjacent_response_value($vals, -1, 'UNSEEN');
            }
            if (in_array('UIDVALIDITY', $vals)) {
                $attributes['uidvalidity'] = $this->get_adjacent_response_value($vals, -1, 'UIDVALIDITY');
            }
            if (in_array('EXISTS', $vals)) {
                $attributes['exists'] = $this->get_adjacent_response_value($vals, 1, 'EXISTS');
            }
            if (in_array('RECENT', $vals)) {
                $attributes['recent'] = $this->get_adjacent_response_value($vals, 1, 'RECENT');
            }
            if (in_array('PERMANENTFLAGS', $vals)) {
                $attributes['pflags'] = $this->get_flag_values($vals);
            }
            if (in_array('FLAGS', $vals) && !in_array('MODSEQ', $vals)) {
                $attributes['flags'] = $this->get_flag_values($vals);
            }
            if (in_array('FETCH', $vals) || in_array('VANISHED', $vals)) {
                $cache_updates++;
                $cache_updated += $this->update_cache_data($vals);
            }
        }
        if ($cache_updates && $cache_updates == $cache_updated) {
            $qresync = true;
        }
        return array($qresync, $attributes);
    }

    /**
     * parse untagged ESEARCH/ESORT responses
     *
     * @param $vals array low level parsed IMAP response segment
     *
     * @return array list of ESEARCH response values
     */
    protected function parse_esearch_response($vals) {
        $res = array();
        if (in_array('MIN', $vals)) {
            $res['min'] = $this->get_adjacent_response_value($vals, -1, 'MIN');
        }
        if (in_array('MAX', $vals)) {
            $res['max'] = $this->get_adjacent_response_value($vals, -1, 'MAX');
        }
        if (in_array('COUNT', $vals)) {
            $res['count'] = $this->get_adjacent_response_value($vals, -1, 'COUNT');
        }
        if (in_array('ALL', $vals)) {
            $res['all'] = $this->get_adjacent_response_value($vals, -1, 'ALL');
        }
        return $res;
    }

    /**
     * examine NOOP/SELECT/EXAMINE untagged responses to determine if the mailbox state changed
     *
     * @param $attributes array list of attribute name/value pairs
     *
     * @return void
     */
    protected function check_mailbox_state_change($attributes, $cached_state=false, $mailbox=false) {
        if (!$cached_state) {
            if ($this->selected_mailbox) {
                $cached_state = $this->selected_mailbox;
            }
            if (!$cached_state) {
                return;
            }
        }
        $full_change = false;
        $partial_change = false;

        foreach($attributes as $name => $value) {
            if ($value !== false) {
                if (isset($cached_state[$name]) && $cached_state[$name] != $value) {
                    if ($name == 'uidvalidity') {
                        $full_change = true;
                    }
                    else {
                        $partial_change = true;
                    }
                }
            }
        }
        if ($full_change || $attributes['nomodseq']) {
            $this->bust_cache($mailbox);
        }
        elseif ($partial_change) {
            $this->bust_cache($mailbox, false);
        }
    }

    /**
     * helper function to build IMAP LIST commands
     *
     * @param $lsub bool flag to use LSUB
     *
     * @return array IMAP LIST/LSUB commands
     */
    protected function build_list_commands($lsub, $mailbox, $keyword) {
        $commands = array();
        if ($lsub) {
            $imap_command = 'LSUB';
        }
        else {
            $imap_command = 'LIST';
        }
        $namespaces = $this->get_namespaces();
        foreach ($namespaces as $nsvals) {

            /* build IMAP command */
            $namespace = $nsvals['prefix'];
            $delim = $nsvals['delim'];
            $ns_class = $nsvals['class'];
            if (strtoupper($namespace) == 'INBOX') { 
                $namespace = '';
            }

            /* send command to the IMAP server and fetch the response */
            if ($mailbox && $namespace) {
                $namespace .= $delim.$mailbox;
            }
            else {
                $namespace .= $mailbox;
            }
            if ($this->is_supported('LIST-STATUS')) {
                $status = ' RETURN (';
                if ($this->is_supported('LIST-EXTENDED')) {
                    $status .= 'CHILDREN ';
                }
                $status .= 'STATUS (MESSAGES UNSEEN UIDVALIDITY UIDNEXT RECENT))';
            }
            else {
                $status = '';
            }
            $commands[] = array($imap_command.' "'.$namespace."\" \"$keyword\"$status\r\n", $namespace);
        }
        return $commands;
    }

    /**
     * parse an untagged STATUS response
     *
     * @param $response array low level parsed IMAP response segment
     *
     * @return array list of mailbox attributes
     */
    protected function parse_status_response($response) {
        $attributes = array(
            'messages' => false,
            'uidvalidity' => false,
            'uidnext' => false,
            'recent' => false,
            'unseen' => false
        );
        foreach ($response as $vals) {
            if (in_array('MESSAGES', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'MESSAGES');
                if (ctype_digit((string)$res)) {
                    $attributes['messages'] = $this->get_adjacent_response_value($vals, -1, 'MESSAGES');
                }
            }
            if (in_array('UIDNEXT', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'UIDNEXT');
                if (ctype_digit((string)$res)) {
                    $attributes['uidnext'] = $this->get_adjacent_response_value($vals, -1, 'UIDNEXT');
                }
            }
            if (in_array('UIDVALIDITY', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'UIDVALIDITY');
                if (ctype_digit((string)$res)) {
                    $attributes['uidvalidity'] = $this->get_adjacent_response_value($vals, -1, 'UIDVALIDITY');
                }
            }
            if (in_array('RECENT', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'RECENT');
                if (ctype_digit((string)$res)) {
                    $attributes['recent'] = $this->get_adjacent_response_value($vals, -1, 'RECENT');
                }
            }
            if (in_array('UNSEEN', $vals)) {
                $res = $this->get_adjacent_response_value($vals, -1, 'UNSEEN');
                if (ctype_digit((string)$res)) {
                    $attributes['unseen'] = $this->get_adjacent_response_value($vals, -1, 'UNSEEN');
                }
            }
        }
        return $attributes;
    }

}

/* cache related methods */
class Hm_IMAP_Cache extends Hm_IMAP_Parser {

    /**
     * update the cache untagged QRESYNC FETCH responses
     *
     * @param $data array low level parsed IMAP response segment
     *
     * @return int 1 if the cache was updated
     */
    protected function update_cache_data($data) {
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
     *
     * @param $res array low level parsed IMAP response
     * 
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
     *
     * @param $count int current number of cache entries
     * @param $exclude array list of cache keys to skip
     *
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
     *
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
     *
     * @param $command string IMAP command to check
     * @param $check_only bool flag to avoid double logging
     *
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
            $msg = 'Cache hit for: '.$command;
            $res = $this->cache_data['LIST'][$command];
        }
        elseif (preg_match("/^LSUB /", $command) && isset($this->cache_data['LSUB'][$command])) {
            $msg = 'Cache hit for: '.$command;
            $res = $this->cache_data['LSUB'][$command];
        }
        elseif (preg_match("/^NAMESPACE/", $command) && isset($this->cache_data['NAMESPACE'])) {
            $msg = 'Cache hit for: '.$command;
            $res = $this->cache_data['NAMESPACE'];
        }
        elseif ($this->selected_mailbox) {

            $box = $this->selected_mailbox['name'];

            if (isset($this->cache_data[$box][$command])) {
                $msg = 'Cache hit for: '.$box.' with: '.$command;
                $res = $this->cache_data[$box][$command];
            }
        }
        if ($msg) {
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
     *
     * @param $type string can be one of LIST, LSUB, ALL, or a mailbox name
     *
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
     *
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
     *
     * @param $data string serialized cache data from dump_cache()
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
?>
