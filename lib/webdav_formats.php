<?php

/**
 * Structures to parse/build vCards (RFC-6350) and Ical events (RFC-5545)
 * @package framework
 * @subpackage webdav
 */

/**
 * Trait with line parsing logic
 */
trait Hm_Card_Line_Parse {

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
}

/**
 * Class for parsing vCard/iCal data
 */
class Hm_Card_Parse {

    use Hm_Card_Line_Parse;

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
     * Load an already parsed card
     * @param array $data parsed card data
     * @return void
     */
    public function import_parsed($data) {
        if (array_key_exists('raw', $data)) {
            $this->raw_card = $data['raw'];
            unset($data['raw']);
        }
        $this->data = $data;
    }

    /**
     * Return parsed data for an input
     * @return array
     */
    public function raw_data() {
        return $this->raw_card;
    }

    /**
     * Format as vcard
     */
    public function build_card() {
        $new_card = array();
        foreach ($this->data as $name => $val) {
            if (method_exists($this, 'format_vcard_'.$name)) {
                $res = $this->{'format_vcard_'.$name}();
            }
            else {
                $res = $this->format_vcard_generic($name);
            }
            if (is_array($res) && $res) {
                $new_card = array_merge($new_card, $res);
            }
            elseif ($res) {
                $new_card[] = $res;
            }
        }
        return implode("\n", $new_card);
    }

    /**
     * Return parsed data for an input
     * @return array
     */
    public function parsed_data() {
        return $this->data;
    }

    /**
     * Get the value for a field
     */
    public function fld_val($name, $type=false, $default=false, $all=false) {
        if (!array_key_exists($name, $this->data)) {
            return $default;
        }
        $fld = $this->data[$name];
        if ($all) {
            return $fld;
        }
        foreach ($fld as $vals) {
            if ($this->is_type($type, $vals)) {
                if (array_key_exists('formatted', $vals)) {
                    return $vals['formatted']['values'];
                }
                return $vals['values'];
            }
        }
        if (array_key_exists('formatted', $fld[0])) {
            return $fld[0]['formatted']['values'];
        }
        else {
            return $fld[0]['values'];
        }
    }

    /**
     * Look for a sepcific type
     */
    private function is_type($type, $vals) {
        if (!$type) {
            return false;
        }
        if (!array_key_exists('type', $vals)) {
            return false;
        }
        if (is_array($vals['type']) && in_array($type, $vals['type'])) {
            return true;
        }
        elseif (strtolower($type) == strtolower($vals['type'])) {
            return true;
        }
        return false;
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
     * Parse input file
     * @param array $lines lines of input to parse
     * @return boolean
     */
    private function parse($lines) {
        foreach ($lines as $line) {
            $id = md5($line);
            $vals = $this->split_value($line, ':', 2);
            $prop = $this->parse_prop($vals[0]);
            if ($this->invalid_prop($prop['prop'])) {
                continue;
            }
            $data = $prop['params'];
            $data['values'] = $this->flatten(
                $this->process_value($vals[1], $prop['prop']));
            $data['id'] = $id;
            if (array_key_exists(strtolower($prop['prop']), $this->data)) {
                $this->data[strtolower($prop['prop'])][] = $data;
            }
            else {
                $this->data[strtolower($prop['prop'])] = array($data);
            }
        }
        $this->data['raw'] = $this->raw_card;
        $this->parse_values();
        return count($this->data) > 0;
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

    /**
     * Catch-all for formatting vcard fields  that don't need specific formatting
     * @param string $name the field name
     * @return array
     */
    protected function format_vcard_generic($name) {
        $res = array();
        if (in_array($name, array('raw'), true)) {
            return;
        }
        $vals = $this->fld_val($name, false, array(), true);
        if (count($vals) == 0) {
            $res;
        }
        foreach ($vals as $val) {
            $name = substr($name, 0, 2) == 'x-' ? $name : strtoupper($name);
            $params = array_merge(array($name), $this->build_vcard_params($val));
            $res[] = sprintf("%s:%s", implode(';', $params), $val['values']);
        }
        return $res;
    }

    /**
     * Build the vcard entry paramater string
     * @param array field values
     * @return array
     */
    protected function build_vcard_params($fld_val) {
        $props = array();
        foreach ($this->parameters as $param) {
            if (array_key_exists(strtolower($param), $fld_val)) {
                $props[] = sprintf('%s=%s', strtoupper($param),
                    $this->combine($fld_val[strtolower($param)]));
            }
        }
        return $props;
    }

    /**
     * Combine an array value if needed, return formatted value
     * @param mixed $val the value to combine
     * @return string
     */
    protected function combine($val) {
        if (is_array($val)) {
            return implode(',', array_map(array($this, 'vcard_format'), $val));
        }
        return $this->vcard_format($val);
    }

    /**
     * Clean a vcard value
     * TODO: make escaping more robust
     * @param string $val the value to format
     * @return string
     */
    protected function vcard_format($val) {
        return str_replace(array(',', "\n"), array('\,', '\n'), $val);
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
        'TYPE', 'PREF', 'LABEL', 'VALUE', 'LANGUAGE',
        'MEDIATYPE', 'ALTID', 'PID', 'CALSCALE',
        'SORT-AS', 'GEO', 'TZ'
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

    /* CONVERT VCARD INPUT */

    /**
     * Parse the name field
     * @param array $vals name field values
     * @return array
     */
    protected function parse_n($vals) {
        foreach ($vals as $index => $name) {
            $flds = $this->split_value($name['values'], ';', 5);
            $vals[$index]['values'] = array(
                'lastname' => $flds[0],
                'firstname' => $flds[1],
                'additional' => $flds[2],
                'prefixes' => $flds[3],
                'suffixes' => $flds[4]
            );
        }
        return $vals;
    }

    /**
     * Convert an address from vcard to an internal struct
     * @param array $vals address values
     * @return array
     */
    protected function format_addr($vals) {
        $name = 'address';
        if (array_key_exists('type', $vals)) {
            $name = sprintf('%s_address', strtolower($vals['type']));
        }
        $vals = $vals['values'];
        $street = $vals['street'];
        if (!$street && $vals['po']) {
            $steet = $vals['po'];
        }
        $value = sprintf('%s, %s, %s, %s, %s', $street, $vals['locality'], $vals['region'],
            $vals['country'], $vals['postal_code']);
        return array('name' => $name, 'values' => $value);
    }

    /**
     * Parse an address field value
     * @param array $vals address values
     * @return array
     */
    protected function parse_adr($vals) {
        foreach ($vals as $index => $addr) {
            $flds = $this->split_value($addr['values'], ';', 7);
            $vals[$index]['values'] = array(
                'po' => $flds[0],
                'apartment' => $flds[1],
                'street' => $flds[2],
                'locality' => $flds[3],
                'region' => $flds[4],
                'postal_code' => $flds[5],
                'country' => $flds[6]
            );
            $vals[$index]['formatted'] = $this->format_addr($vals[$index]);
        }
        return $vals;
    }

    /* CONVERT TO VCARD OUTPUT */

    /**
     * Format a name field for vcard output
     * @return string
     */
    protected function format_vcard_n() {
        $n = $this->fld_val('n');
        return sprintf("N:%s;%s;%s;%s;%s", $n['lastname'], $n['firstname'],
            $n['additional'], $n['prefixes'], $n['suffixes']);
    }

    /**
     * Format addresses to vcard
     * @return array
     */
    protected function format_vcard_adr() {
        $res = array();
        foreach ($this->fld_val('adr', array(), false, true) as $adr) {
            $parts = $adr['values'];
            $params = array_merge(array('ADR'), $this->build_vcard_params($adr));
            $res[] = sprintf('%s:%s;%s;%s;%s;%s;%s;%s', implode(';', $params),
                $parts['po'], $parts['apartment'], $parts['street'], $parts['locality'],
                $parts['region'], $parts['postal_code'], $parts['country']);
        }
        return $res;
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
            $dt = $vals[0]['values'];
            if (substr($dt, -1, 1) == 'Z') {
                $vals[0]['tzid'] = 'UTC';
                $dt = substr($dt, 0, -1);
            }
            $vals[$index]['values'] = date_parse_from_format('Ymd\THis', $dt);
        }
        return $vals;
    }
}
