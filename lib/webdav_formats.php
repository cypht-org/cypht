<?php

/**
 * Structures to parse/build vCards (RFC-6350) and Ical events (RFC-5545)
 * @package framework
 * @subpackage webdav
 */

/**
 * Class for parsing vCard/iCal data
 */
class Hm_Card_Parse {

    /* input format version */
    protected $version = '';

    /* original input data unchanged */
    protected $raw_card = '';

    /* placeholder for parsed data */
    protected $data = array();

    /* list of valid parameters for the file type */
    protected $parameters = array();

    /* list of valid properties for the file type */
    protected $properties = array();

    /**
     * init
     */
    public function __construct() {
    }

    /**
     * Import a single set of values
     * @param string $str string data from the input file
     * @return boolean
     */
    public function import($str) {
        $this->data = array();
        $this->raw_card = $str;
        $lines = explode("\n", $this->unfold($this->standard_eol($str)));
        if ($this->is_valid($lines)) {
            return $this->parse($lines);
        }
        return false;
    }

    /**
     * Return parsed data for an input
     * @return array
     */
    public function raw_data() {
        return $this->raw_card;
    }

    /**
     * Return parsed data for an input
     * @return array
     */
    public function parsed_data() {
        return $this->data;
    }

    /**
     * Split a value skipping escaped characters
     * @param string $line value to split
     * @param string $delim value to split on
     * @param integer $limit max number of splits (-1 for all)
     * @return array
     */
    protected function split_value($line, $delim, $limit) {
        $res = preg_split("/\\\\.(*SKIP)(*FAIL)|$delim/s", $line, $limit);
        return $res;
    }

    /**
     * Normalize End-Of-Line chars
     * @param string $str string input
     * @return string
     */
    private function standard_eol($str) {
        return rtrim(str_replace(array( "\r\n", "\n\r", "\r"), "\n", $str));

    }

    /**
     * Unfold values that span > 1 line
     * @param string $str string input
     * @return string
     */
    private function unfold($str) {
        return preg_replace("/\n\s{1}/m", '', $str);
    }

    /**
     * Flatten a list with 1 value to a scaler
     * @param array $arr list to flatten
     * @return array
     */
    private function flatten($arr) {
        if (is_array($arr) && count($arr) == 1) {
            return array_pop($arr);
        }
        return $arr;
    }

    /**
     * Process the value portion of an input line
     * @param string $value value to process
     * @param string $type property type
     * @return array
     */
    private function process_value($value, $type=false) {
        $res = array();
        foreach ($this->split_value($value, ',', -1) as $val) {
            $res[] = str_replace(array('\n', '\,'), array("\n", ','), $val);
        }
        return $res;
    }

    /**
     * Parse input file
     * @param array $lines lines of input to parse
     * @return boolean
     */
    private function parse($lines) {
        foreach ($lines as $line) {
            $vals = $this->split_value($line, ':', 2);
            $prop = $this->parse_prop($vals[0]);
            if ($this->invalid_prop($prop['prop'])) {
                continue;
            }
            $data = $prop['params'];
            $data['value'] = $this->flatten(
                $this->process_value($vals[1], $prop['prop']));
            if (array_key_exists(strtolower($prop['prop']), $this->data)) {
                $this->data[strtolower($prop['prop'])][] = $data;
            }
            else {
                $this->data[strtolower($prop['prop'])] = array($data);
            }
        }
        $this->parse_values();
        $this->flatten_all();
        return count($this->data) > 0;
    }

    /**
     * Validate a property
     * @param string $prop property name
     * @return boolean
     */
    private function invalid_prop($prop) {
        if (strtolower(substr($prop, 0, 2)) == 'x-') {
            return false;
        }
        foreach ($this->properties as $name => $value) {
            if (strtolower($prop) == strtolower($value)) {
                return false;
            }
        }
        Hm_Debug::add(sprintf("%s invalid prop found: %s", $this->format, $prop));
        return true;
    }

    /**
     * Validate a paramater
     * @param string $param parameter to validate
     * @return boolean
     */
    private function invalid_param($param) {
        foreach ($this->parameters as $val) {
            if (strtolower($param) == strtolower($val)) {
                return false;
            }
        }
        Hm_Debug::add(sprintf("%s invalid param found: %s",
            $this->format, $param));
        return true;
    }

    /**
     * Parse a property value
     * @param string $prop property to parse
     * @return array
     */
    private function parse_prop($prop) {
        $vals = $this->split_value($prop, ';', -1);
        $res = array(
            'prop' => $vals[0],
            'params' => array()
        );
        if (count($vals) > 1) {
            $res['params'] = $this->parse_prop_params($vals);
        }
        return $res;
    }

    /**
     * Parse parameters in a property field
     * @param array $vals list of parameters
     * @return array
     */
    private function parse_prop_params($vals) {
        $res = array();
        array_shift($vals);
        foreach ($vals as $val) {
            $pair = $this->split_value($val, '=', 2);
            if (count($pair) > 1) {
                if ($this->invalid_param($pair[0])) {
                    continue;
                }
                $res[strtolower($pair[0])] = $this->flatten(
                    $this->process_value($pair[1]));
            }
        }
        return $res;
    }
    /**
     * Unnest an array
     */
    private function unnest($vals) {
        $res = array();
        foreach ($vals as $val) {
            foreach ($val as $v) {
                $res[] = $v;
            }
        }
        return $res;
    }

    /**
     * Replace single element lists with scaler values
     * @return void
     */
    private function flatten_all() {
        foreach ($this->data as $prop => $vals) {
            if ($prop == 'begin' || $prop == 'end') {
                $this->data[$prop] = $this->unnest($vals);
            }
            else {
                $this->data[$prop] = $this->flatten($this->flatten($vals));
            }
        }
    }

    /**
     * Top level validation of input data
     * @param array $lines input data by line
     * @return boolean
     */
    private function is_valid($lines) {
        $res = true;
        if (count($lines) < 4) {
            $res = false;
        }
        if (count($lines) > 0 && strtolower(substr($lines[0], 0, 5)) != 'begin') {
            $res = false;
        }
        if (count($lines) > 1 && strtolower(substr($lines[1], 0, 7)) != 'version') {
            $res = false;
        }
        if (count($lines) && strtolower(substr($lines[(count($lines) - 1)], 0, 3)) != 'end') {
            $res = false;
        }
        if (!$res) {
            Hm_Debug::add(sprintf('Invalid %s format', $this->format));
            return false;
        }
        $version = $this->split_value($lines[1], ':', 2);
        if (count($version) > 1) {
            $this->version = $version[1];
        }
        return true;
    }

    /**
     * Parse values that require it
     * @return void
     */
    private function parse_values() {
        foreach ($this->data as $prop => $values) {
            $method = sprintf('parse_%s', $prop);
            if (method_exists($this, $method)) {
                $this->data[$prop] = $this->$method($values);
            }
        }
    }
}

/**
 * Class for parsing vCard data
 */
class Hm_VCard extends Hm_Card_Parse {
    protected $format = 'vCard';
    protected $raw_card = '';
    protected $data = array();
    protected $parameters = array(
        'LANGUAGE', 'VALUE', 'PREF', 'ALTID',
        'LABEL', 'PID', 'TYPE', 'MEDIATYPE',
        'CALSCALE', 'SORT-AS', 'GEO', 'TZ'
    );
    protected $properties = array(
        'BEGIN', 'VERSION', 'END', 'FN', 'N',
        'KIND', 'BDAY', 'ANNIVERSARY', 'GENDER',
        'PRODID', 'REV', 'UID', 'SOURCE', 'XML',
        'NICKNAME', 'PROTO', 'ADR', 'TEL',
        'EMAIL', 'IMPP', 'LANG', 'TZ', 'GEO',
        'TITLE', 'ROLE', 'LOGO', 'ORG', 'MEMBER',
        'RELATED', 'CATEGORIES', 'NOTE', 'SOUND',
        'CLIENTPIDMAP', 'PHOTO', 'URL', 'KEY',
        'FBURL', 'CALADRURI', 'CALURI'
    );

    protected function parse_n($vals) {
        foreach ($vals as $index => $name) {
            $flds = $this->split_value($name['value'], ';', 5);
            $vals[$index]['value'] = array(
                'lastname' => $flds[0],
                'firstname' => $flds[1],
                'additional' => $flds[2],
                'prefixes' => $flds[3],
                'suffixes' => $flds[4]
            );
        }
        return $vals;
    }

    protected function parse_adr($vals) {
        foreach ($vals as $index => $addr) {
            $flds = $this->split_value($addr['value'], ';', 7);
            $vals[$index]['value'] = array(
                'po' => $flds[0],
                'apartment' => $flds[1],
                'street' => $flds[2],
                'locality' => $flds[3],
                'region' => $flds[4],
                'postal_code' => $flds[5],
                'country' => $flds[6]
            );
        }
        return $vals;
    }
}

/**
 * Class for parsing iCal data
 */
class Hm_ICal extends Hm_Card_Parse {
    protected $format = 'iCal';
    protected $raw_card = '';
    protected $data = array();
    protected $parameters = array(
        'ALTREP', 'CN', 'CUTYPE', 'DELEGATED-FROM',
        'DELEGATED-TO', 'DIR', 'ENCODING', 'FMTTYPE',
        'FBTYPE', 'LANGUAGE', 'MEMBER', 'PARTSTAT',
        'RANGE', 'RELATED', 'RELTYPE', 'ROLE', 'RSVP',
        'SENT-BY', 'TZID', 'VALUE'
    );
    protected $properties = array(
        'BEGIN', 'VERSION', 'END', 'CALSCALE',
        'METHOD', 'PRODID', 'ATTACH', 'CATEGORIES',
        'CLASS', 'COMMENT', 'DESCRIPTION', 'GEO',
        'LOCATION', 'PERCENT-COMPLETE', 'PRIORITY',
        'RESOURCES', 'STATUS', 'SUMMARY', 'COMPLETED',
        'DTEND', 'DUE', 'DTSTART', 'DURATION', 'FREEBUSY',
        'TRANSP', 'TZID', 'TZNAME', 'TZOFFSETFROM',
        'TZOFFSETTO', 'TZURL', 'ATTENDEE', 'CONTACT',
        'ORGANIZER', 'RECURRENCE-ID', 'RELATED-TO',
        'URL', 'UID', 'EXDATE', 'EXRULE', 'RDATE',
        'RRULE', 'ACTION', 'REPEAT', 'TRIGGER',
        'CREATED', 'DTSTAMP', 'LAST-MODIFIED',
        'SEQUENCE', 'REQUEST-STATUS'
    );

    protected function parse_due($vals) {
        return $this->parse_dt($vals);
    }

    protected function parse_dtstamp($vals) {
        return $this->parse_dt($vals);
    }

    protected function parse_dtend($vals) {
        return $this->parse_dt($vals);
    }
    protected function parse_dtstart($vals) {
        return $this->parse_dt($vals);
    }
    protected function parse_trigger($vals) {
        return $this->parse_dt($vals);
    }

    protected function parse_dt($vals) {
        foreach ($vals as $index => $dates) {
            $dt = $vals[0]['value'];
            if (substr($dt, -1, 1) == 'Z') {
                $vals[0]['tzid'] = 'UTC';
                $dt = substr($dt, 0, -1);
            }
            $vals[$index]['value'] = date_parse_from_format('Ymd\THis', $dt);
        }
        return $vals;
    }
}
