<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */


/**
 * Base class for a generic PHP5 IMAP client library.
 * This code is derived from the IMAP library used in Hastymail2 (www.hastymail.org)
 * and is covered by the same license restrictions (GPL2)
 * @subpackage imap/lib
*/
class Hm_IMAP_Base {

    public $cached_response = false;           // flag to indicate we are using a cached response
    public $supported_extensions = array(); // IMAP extensions in the CAPABILITY response
    protected $handle = false;                 // fsockopen handle to the IMAP server
    protected $debug = array();                // debug messages
    protected $commands = array();             // list of IMAP commands issued
    protected $responses = array();            // list of raw IMAP responses
    protected $current_command = false;        // current/latest IMAP command issued
    protected $max_read = false;               // limit on allowable read size
    protected $command_count = 0;              // current command number
    protected $cache_data = array();           // cache data
    protected $enabled_extensions = array();   // IMAP extensions validated by the ENABLE response
    protected $capability = false;             // IMAP CAPABILITY response
    protected $server_id = array();            // server ID response values
    protected $literal_overflow = false;
    public $struct_object = false;          


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
     * @return int new command count
     */
    private function command_number() {
        $this->command_count += 1;
        return $this->command_count;
    }

    /**
     * Read IMAP literal found during parse_line().
     * @param int $size size of the IMAP literal to read
     * @param int $max max size to allow
     * @param int $current current size read
     * @param int $line_length amount to read in using fgets()
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
     * break up a "line" response from imap. If we find
     * a literal we read ahead on the stream and include it.
     * @param string $line data read from the IMAP server
     * @param int $current_size size of current read operation
     * @param int $max maximum input size to allow
     * @param int $line_length chunk size to read literals with
     * @return array a line continuation marker and the parsed data
     *               from the IMAP server
     */
    protected function parse_line($line, $current_size, $max, $line_length) {
        /* make it a bit easier to find "atoms" */
        $line = str_replace(')(', ') (', $line);
        $this->literal_overflow = false;

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
            if ($line[$i] == "\r" || $line[$i] == "\n") {
                $line_cont = false;
                break;
            }

            /* skip spaces */
            if ($line[$i] == ' ') {
                continue;
            }

            /* capture special chars as "atoms" */
            elseif ($line[$i] == '*' || $line[$i] == '[' || $line[$i] == ']' || $line[$i] == '(' || $line[$i] == ')') {
                $chunk = $line[$i];
            }
        
            /* regex match a quoted string */
            elseif ($line[$i] == '"') {
                if (preg_match("/^(\"[^\"\\\]*(?:\\\.[^\"\\\]*)*\")/", substr($line, $i), $matches)) {
                    $chunk = substr($matches[1], 1, -1);
                }
                $i += strlen($chunk) + 1;
            }

            /* IMAP literal */
            elseif ($line[$i] == '{') {
                $end = strpos($line, '}');
                if ($end !== false) {
                    $literal_size  = substr($line, ($i + 1), ($end - $i - 1));
                }
                $lit_result = $this->read_literal($literal_size, $max, $current_size, $line_length);
                $chunk = $lit_result[0];
                if (!isset($lit_result[1]) || $lit_result[1] != "\r\n") {
                    $line_cont = true;
                }
                if (isset($lit_result[1]) && $lit_result[1] != "\r\n" && strlen($lit_result[1]) > 0) {
                    $this->literal_overflow = $lit_result[1];
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
     * @param int $len max read length for fgets
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
     * @param int $max max size of response allowed
     * @param bool $chunked flag to parse the data into IMAP "atoms"
     * @param int $line_length chunk size to read in literals using fgets
     * @param bool $sort flag for non-compliant sort result parsing speed up
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
            if (Hm_Functions::stream_ended($this->handle)) {
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
                while ($this->literal_overflow) {
                    $lit_text = $this->literal_overflow;
                    $this->literal_overflow = false;
                    $current_size += strlen($lit_text);
                    list($line_cont, $new_chunks) = $this->parse_line($lit_text, $current_size, $max, $line_length);
                    $chunks = array_merge($chunks, $new_chunks);
                }
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

            /* check for challenge strings */
            if (substr($result[$n], 0, 1) == '+') {
                if (preg_match("/^\+ ([a-zA-Z0-9=]+)$/", $result[$n], $matches)) {
                    break;
                }
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
     * @param mixed $command IMAP command
     * @param bool $no_prefix flag to skip adding the prefix
     * @return void
     */
    protected function send_command($command, $no_prefix=false) {
        $this->cached_response = false;
        if (!$no_prefix) {
            $command = 'A'.$this->command_number().' '.$command;
        }

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
     * @param array $data parsed IMAP response
     * @param bool $chunked flag defining the type of $data
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
     * @param string $string mailbox name to encode
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
     * @param string $string mailbox name to decode
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
     * @param string $val value to check
     * @param string $type type of value to check against
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
            'ALL', 'DRAFT', 'NEW', 'RECENT', 'OLD', 'UNDRAFT',
            'BODY'
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
                if (preg_match("/^[0-9,*:]+$/", $val)) {
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
     * @param string $val value to check
     * @param string $type type the value should match
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
     * @param array $config associative array of configuration options
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

