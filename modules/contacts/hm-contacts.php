<?php

/**
 * Contacts modules
 * @package modules
 * @subpackage contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage contacts/lib
 */
class Hm_Contact_Store {

    use Hm_Repository {
        Hm_Repository::save as repo_save;
        Hm_Repository::get as repo_get;
    }

    private $data = array();
    private $sort_fld = false;

    public function init($user_config, $session) {
        self::initRepo('contacts', $user_config, $session, $this->data, function($initial) {
            foreach ($initial as $contact) {
                $this->add_contact($contact, false);
            }
        });
    }

    public static function save() {
        $local_contacts = array_map(function($c) {
            $c->update('type', 'local');
            return $c->export();
        }, array_filter(self::$entities, function($c) {
            return ! $c->value('external');
        }));
        self::$user_config->set(self::$name, $local_contacts);
        self::$session->set('user_data', self::$user_config->dump());
    }

    public function __construct() {
    }

    public function add_contact($data, $save = true) {
        $contact = new Hm_Contact($data);
        if ($contact->value('external')) {
            $save = false;
        }
        self::add($contact, $save);
        return true;
    }

    public function get($id, $default=false, $email_address=""){
        $contact = self::repo_get($id);
        if ($contact) {
            return $contact;
        }

        if(!empty($email_address)){
            $res = false;
            foreach (self::getAll() as $id => $contact) {
                if ($contact->value('email_address') == $email_address) {
                    $res = $contact;
                    break;
                }
            }

            return $res;
        }

        return $default;
    }

    public function search($flds) {
        $res = array();
        $found = array();
        foreach ($flds as $fld => $term) {
            foreach (self::getAll() as $id => $contact) {
                if (array_key_exists($contact->value('email_address'), $found)) {
                    continue;
                }
                if ($this->search_contact($contact, $fld, $term)) {
                    $res[$id] = [$id, $contact];
                    $found[$contact->value('email_address')] = 1;
                }
            }
        }
        return $res;
    }

    protected function search_contact($contact, $fld, $term) {
        if (mb_stristr($contact->value($fld, ''), $term)) {
            return true;
        }
        return false;
    }

    public function update_contact_fld($contact, $name, $value)  {
        return $contact->update($name, $value);
    }

    public function update_contact($id, $flds) {
        if (!$contact = $this->get($id)) {
            return false;
        }
        $failures = 0;
        foreach ($flds as $name => $value) {
            $failures += (int) !$this->update_contact_fld($contact, $name, $value);
        }
        if ($failures == 0) {
            $this->edit($id, $contact);
        }
        return $failures > 0 ? false : true;
    }

    public function delete($id) {
        return self::del($id);
    }


    public function dump() {
        return $this->data;
    }

    public function export($source = 'local') {
        return array_map(function($contact) { return $contact->export(); },
            array_filter($this->data, function($contact) use ($source) { return $contact->value('source') == $source; })
        );
    }

    public function import($data) {
        foreach ($data as $contact) {
            $contact['external'] = true;
            $contact = new Hm_Contact($contact);
            self::add($contact, false);
        }
    }

    public function reset() {
        $this->data = array();
    }

    public function page($page, $size, $data = null) {
        if ($page < 1) {
            return array();
        }
        if ($data === null) {
            $data = $this->data;
        }
        return array_slice($data, (($page - 1)*$size), $size, true);
    }

    public function group_by($column = 'group') {
        if (!is_string($column) && !is_int($column) && !is_float($column)) {
            trigger_error('group_by(): The key should be a string, an integer, or a float', E_USER_ERROR);
            return null;
        }
    
        $_key = $column;
        $grouped = [];
        $defaultGroup = 'Collected Recipients';
    
        foreach ($this->data as $value) {
            $columnValue = null;
    
            if (is_array($value) && isset($value[$_key])) {
                $columnValue = $value[$_key];
            }
            elseif (is_object($value) && method_exists($value, 'value') && $value->value($_key)) {
                $columnValue = $value->value($_key);
            }
    
            if ($columnValue === null) {
                $columnValue = $defaultGroup;
            }
    
            $grouped[$columnValue][] = $value;
        }
    
        if (func_num_args() > 1) {
            $args = func_get_args();
            foreach ($grouped as $key => $values) {
                $params = array_merge([ $values ], array_slice($args, 1));
                $grouped[$key] = call_user_func_array([ $this, 'group_by' ], $params);
            }
        }
    
        return $grouped;
    }
    
    

    public function paginate_grouped($column, $page, $size) {
        $grouped = $this->group_by($column);
        $paginated = [];
        foreach ($grouped as $key => $group) {
            $paginated[$key] = $this->page($page, $size, $group);
        }
        return $paginated;
    }
    
    public function sort($fld) {
        $this->sort_fld = $fld;
        uasort($this->data, array($this, 'sort_callback'));
    }

    public function sort_callback($a, $b) {
        return strcmp($a->value($this->sort_fld), $b->value($this->sort_fld));
    }
}

/**
 * @subpackage contacts/lib
 */
class Hm_Contact {

    private $data = array();

    function __construct($data) {
        $this->build($data);
    }

    function build($data) {
        foreach ($data as $name => $value) {
            $this->data[$name] = $value;
        }
    }

    function update($fld, $val) {
        $this->data[$fld] = $val;
        return true;
    }

    function value($fld, $default=false) {
        if (array_key_exists($fld, $this->data)) {
            return $this->data[$fld];
        }
        return $default;
    }

    function export() {
        return $this->data;
    }
}

/**
 * @subpackage contacts/lib
 */
class Hm_Address_Field {

    public static function parse($string) {
        $marker = true;
        $string = str_replace(array('<', '>'), array(' <', '> '), $string);
        $string = preg_replace("/\s{2,}/", ' ', $string);
        $results = array();

        while ($marker !== false) {
            list($marker, $token, $string) = self::get_token($string);
            if (is_email_address($token)) {
                list($name, $marker) = self::find_name_field($string);
                if ($marker > -1) {
                    $string = mb_substr($string, 0, $marker);
                }
                else {
                    $marker = false;
                }
                $results[] = array('email' => $token, 'name' => $name);
            }
        }
        return $results;
    }

    private static function get_token($string) {
        $marker = mb_strrpos($string, ' ');
        $token = trim(ltrim(mb_substr($string, $marker)), '<>');
        $string = mb_substr($string, 0, $marker);
        return array($marker, $token, $string);
    }

    private static function is_quote($string, $i, $quote) {
        if (in_array($string[$i], array('"', "'"), true)) {
            if (!self::embeded_quote($string, $i)) {
                $quote = $quote ? false : true;
            }
        }
        return $quote;
    }

    private static function find_name_field($string) {
        $quote = false;
        $result = '';
        for ($i = mb_strlen($string) - 1;$i>-1; $i--) {
            $quote = self::is_quote($string, $i, $quote);
            if (self::delimiter_found($string, $i, $quote)) {
                break;
            }
            $result .= $string[$i];
        }
        return array(strrev(trim(trim($result),'"\'')), $i);
    }

    private static function embeded_quote($string, $i) {
        return $i > 0 && $string[$i -1] == '\\';
    }

    private static function delimiter_found($string, $i, $quote) {
        return !$quote && in_array($string[$i], array(',', ';'), true);
    }
}
