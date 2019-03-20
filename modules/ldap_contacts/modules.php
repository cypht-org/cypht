<?php

/**
 * LDAP contact modules
 * @package modules
 * @subpackage ldap_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/ldap_contacts/hm-ldap-contacts.php';

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_add_ldap_contact_from_message extends Hm_Handler_Module {
    public function process() {
        $ldap_config = $this->get('ldap_config');
        list($success, $form) = $this->process_form(array('contact_source', 'contact_value'));
        if (!$success) {
            return;
        }
        list($type, $source) = explode(':', $form['contact_source']);
        if ($type == 'ldap' && array_key_exists($source, $ldap_config)) {
            $addresses = Hm_Address_Field::parse($form['contact_value']);
            $config = $ldap_config[$source];
            if (count($config) == 0) {
                Hm_Msgs::add('ERRUnable to add contact');
                return;
            }
            $ldap = new Hm_LDAP_Contacts($config);
            if (!empty($addresses)) {
                $contacts = $this->get('contact_store');
                if ($ldap->connect()) {
                    foreach ($addresses as $vals) {
                        $atts = array('mail' => $vals['email'], 'objectclass' => $config['objectclass']);
                        if (array_key_exists('name', $vals) && trim($vals['name'])) {
                            $dn = sprintf('cn=%s,%s', $vals['name'], $config['base_dn']);
                            $atts['sn'] = $vals['name'];
                            $atts['cn'] = $vals['name'];
                            $atts['displayname'] = $vals['name'];
                        }
                        else {
                            $dn = sprintf('cn=%s,%s', str_replace(array('<', '>'), '', $vals['email']), $config['base_dn']);
                            $atts['cn'] = str_replace(array('<', '>'), '', $vals['email']);
                            $atts['sn'] = $atts['cn'];
                        }
                        if ($ldap->add($atts, $dn)) {
                            Hm_Msgs::add('Contact Added');
                        }
                        else {
                            Hm_Msgs::add('ERRUnable to add contact');
                        }
                    }
                }
            }
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_delete_ldap_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        $ldap_config = ldap_config($this->config);
        $sources = array_keys($ldap_config);
        list($success, $form) = $this->process_form(array('contact_type', 'contact_source', 'contact_id'));
        if ($success && $form['contact_type'] == 'ldap' && in_array($form['contact_source'], $sources, true)) {
            $config = $ldap_config[$form['contact_source']];
            $contact = $contacts->get($form['contact_id']);
            if (!$contact) {
                Hm_Msgs::add('ERRUnable to find contact to delete');
            }
            $ldap = new Hm_LDAP_Contacts($config);
            if ($ldap->connect()) {
                $flds = $contact->value('all_fields');
                if ($ldap->delete($flds['dn'])) {
                    Hm_Msgs::add('Contact Deleted');
                    $this->out('contact_deleted', 1);
                }
                else {
                    Hm_Msgs::add('ERRCould not delete contact');
                }
            }
            else {
                Hm_Msgs::add('ERRCould not delete contact');
            }
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_ldap_fields extends Hm_Handler_Module {
    public function process() {
        $form = $this->get('ldap_entry_data', array());
        if (!is_array($form) || count($form) == 0) {
            return;
        }
        $config = ldap_config($this->config, $form['ldap_source']);
        $dn = sprintf('cn=%s %s,%s', $form['ldap_first_name'], $form['ldap_last_name'], $config['base_dn']);
        $cn = sprintf('%s %s', $form['ldap_first_name'], $form['ldap_last_name']);
        $result = array('cn' => $cn, 'objectclass' => $config['objectclass']);
        $ldap_map = array(
            'ldap_first_name' => 'givenname',
            'ldap_last_name' => 'sn',
            'ldap_displayname' => 'displayname',
            'ldap_mail' => 'mail',
            'ldap_locality' => 'l',
            'ldap_state' => 'st',
            'ldap_street' => 'street',
            'ldap_postalcode' => 'postalcode',
            'ldap_title' => 'title',
            'ldap_phone' => 'telephonenumber',
            'ldap_fax' => 'facsimiletelephonenumber',
            'ldap_mobile' => 'mobile',
            'ldap_room' => 'roomnumber',
            'ldap_car' => 'carlicense',
            'ldap_org' => 'o',
            'ldap_org_unit' => 'ou',
            'ldap_org_dpt' => 'departmentnumber',
            'ldap_emp_num' => 'employeenumber',
            'ldap_emp_type' => 'employeetype',
            'ldap_lang' => 'preferredlanguage',
            'ldap_uri' => 'labeleduri'
        );
        foreach ($ldap_map as $name => $val) {
            if (array_key_exists($name, $form)) {
                $result[$val] = $form[$name];
            }
            elseif (array_key_exists($name, $this->request->post) && trim($this->request->post[$name])) {
                $result[$val] = $this->request->post[$name];
            }
        }
        $this->out('entry_dn', $dn);
        $this->out('ldap_entry_data', $result, false);
        $this->out('ldap_config', $config, false);
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_update_ldap_server extends Hm_Handler_Module {
    public function process() {
        if ($this->get('ldap_action') != 'update') {
            return;
        }
        $entry = $this->get('ldap_entry_data', array());
        if (!is_array($entry) || count($entry) == 0) {
            return;
        }
        $new_dn = $this->get('entry_dn');
        $old_dn = get_ldap_value('dn', $this);
        $config = $this->get('ldap_config');
        $ldap = new Hm_LDAP_Contacts($config);
        if ($ldap->connect()) {
            if ($new_dn != $old_dn) {
                $rdn = sprintf('cn=%s', $entry['cn']);
                $parent = $config['base_dn'];
                if (!$ldap->rename($old_dn, $rdn, $parent)) {
                    Hm_Msgs::add('ERRUnable to update contact');
                    return;
                }
            }
            if ($ldap->modify($entry, $new_dn)) {
                Hm_Msgs::add('Contact Updated');
            }
            else {
                Hm_Msgs::add('ERRUnable to update contact');
            }
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_add_to_ldap_server extends Hm_Handler_Module {
    public function process() {
        if ($this->get('ldap_action') != 'add') {
            return;
        }
        $entry = $this->get('ldap_entry_data', array());
        if (!is_array($entry) || count($entry) == 0) {
            return;
        }
        $config = $this->get('ldap_config');
        $dn = $this->get('entry_dn');
        $ldap = new Hm_LDAP_Contacts($config);
        if ($ldap->connect()) {
            if ($ldap->add($entry, $dn)) {
                Hm_Msgs::add('Contact Added');
            }
            else {
                Hm_Msgs::add('ERRCould not add contact');
            }
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_update_ldap_contact extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('ldap_source', 'contact_source',
            'ldap_first_name', 'update_ldap_contact', 'ldap_last_name', 'ldap_mail'));
        if ($success && $form['contact_source'] == 'ldap') {
            $this->out('ldap_entry_data', $form, false);
            $this->out('ldap_action', 'update');
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_add_ldap_contact extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_source', 'ldap_first_name',
            'add_ldap_contact', 'ldap_last_name', 'ldap_mail', 'ldap_source'));
        if ($success && $form['contact_source'] == 'ldap') {
            $this->out('ldap_entry_data', $form, false);
            $this->out('ldap_action', 'add');
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_load_ldap_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($contacts, $ldap_config) = fetch_ldap_contacts($this->config, $this->user_config, $contacts);
        $this->append('contact_sources', 'ldap');
        $edit = false;
        $sources = array();
        foreach ($ldap_config as $name => $vals) {
            if (is_array($vals) && array_key_exists('read_write', $vals) && $vals['read_write']) {
                $this->append('contact_edit', sprintf('ldap:%s', $name));
                $sources[] = $name;
                $edit = true;
            }
        }
        $this->out('ldap_edit', $edit);
        $this->out('ldap_sources', $sources);
        $this->out('contact_store', $contacts, false);
        $this->out('ldap_config', $ldap_config, false);
    }
}
/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_load_edit_ldap_contact extends Hm_Handler_Module {
    public function process() {
        $ldap_config = $this->get('ldap_config');
        if (array_key_exists('contact_source', $this->request->get) &&
            array_key_exists('contact_type', $this->request->get) &&
            $this->request->get['contact_type'] == 'ldap' &&
            array_key_exists($this->request->get['contact_source'], $ldap_config) &&
            array_key_exists('contact_id', $this->request->get)) {

            $contacts = $this->get('contact_store');
            $contact = $contacts->get($this->request->get['contact_id']);
            if (is_object($contact)) {
                $current = $contact->export();
                $current['id'] = $this->request->get['contact_id'];
                $this->out('current_ldap_contact', $current);
            }
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_process_ldap_auth_settings extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('save_settings', $this->request->post)) {
            return;
        }
        $connections = $this->get('ldap_contact_connections');
        $users = array();
        $passwords = array();
        $results = $connections;
        if (array_key_exists('ldap_usernames', $this->request->post)) {
            $users = $this->request->post['ldap_usernames'];
        }
        if (array_key_exists('ldap_passwords', $this->request->post)) {
            $passwords = $this->request->post['ldap_passwords'];
        }
        foreach ($connections as $name => $vals) {
            if (array_key_exists($name, $users)) {
                $results[$name]['user'] = $users[$name];
            }
            if (array_key_exists($name, $passwords)) {
                $results[$name]['pass'] = $passwords[$name];
            }
        }
        if (count($results) > 0) {
            $new_settings = $this->get('new_user_settings');
            $new_settings['ldap_contacts_auth_setting'] = $results;
            $this->out('new_user_settings', $new_settings, false);
        }
    }
}

/**
 * @subpackage ldap_contacts/handler
 */
class Hm_Handler_load_ldap_settings extends Hm_Handler_Module {
    public function process() {
        $connections = array();
        foreach (ldap_config($this->config) as $name => $vals) {
            if (array_key_exists('auth', $vals) && $vals['auth']) {
                if ((!array_key_exists('user', $vals) || !$vals['user']) &&
                    (!array_key_exists('pass', $vals) || !$vals['pass'])) {
                    $connections[$name] = $vals;
                }
            }
        }
        $this->out('ldap_contacts_auth', $this->user_config->get('ldap_contacts_auth_setting'));
        $this->out('ldap_contact_connections', $connections);
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_auth_settings extends Hm_Output_Module {
    protected function output() {
        $connections = $this->get('ldap_contact_connections', array());
        $auths = $this->get('ldap_contacts_auth', array());
        if (count($connections) > 0) {
            $res = '<tr><td data-target=".ldap_settings" colspan="2" class="settings_subtitle">'.
                '<img alt="" src="'.Hm_Image_Sources::$people.'" width="16" height="16" />'.
                $this->trans('LDAP Addressbooks').'</td></tr>';
            foreach ($connections as $name => $con) {
                $user = '';
                $pass = false;
                if (array_key_exists($name, $auths)) {
                    $user = $auths[$name]['user'];
                    if (array_key_exists('pass', $auths[$name]) && $auths[$name]['pass']) {
                        $pass = true;
                    }
                }
                $res .= '<tr class="ldap_settings"><td>'.$this->html_safe($name).'</td><td>';
                $res .= '<input autocomplete="username" type="text" value="'.$user.'" name="ldap_usernames['.$this->html_safe($name).']" ';
                $res .= 'placeholder="'.$this->trans('Username').'" /> <input type="password" ';
                if ($pass) {
                    $res .= 'disabled="disabled" placeholder="'.$this->trans('Password saved').'" ';
                    $res .= 'name="ldap_passwords['.$this->html_safe($name).']" /> <input type="button" ';
                    $res .= 'value="'.$this->trans('Unlock').'" class="ldap_password_change" /></td></tr>';
                }
                else {
                    $res .= 'autocomplete="new-password" placeholder="'.$this->trans('Password').'" ';
                    $res .= 'name="ldap_passwords['.$this->html_safe($name).']" /></td></tr>';
                }
            }
            return $res;
        }
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_contact_form_end extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        return '</div></form></div>';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_first_name extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $name = get_ldap_value('givenname', $this);
        return '<label class="screen_reader" for="ldap_first_name">'.$this->trans('First Name').'</label>'.
            '<input required placeholder="'.$this->trans('First Name').'" id="ldap_first_name" '.
            'type="text" name="ldap_first_name" value="'.$this->html_safe($name).'" /> *<br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_submit extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $label = 'Add';
        $name = 'add_ldap_contact';
        if ($this->get('current_ldap_contact')) {
            $label = 'Update';
            $name = 'update_ldap_contact';
        }
        return '<input name="'.$name.'" type="submit" value="'.$this->trans($label).'" />'.
            '<input type="button" class="reset_contact" value="'.$this->trans('Cancel').'" />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_last_name extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $name = get_ldap_value('sn', $this);
        return '<label class="screen_reader" for="ldap_last_name">'.$this->trans('Last Name').'</label>'.
            '<input required placeholder="'.$this->trans('Last Name').'" id="ldap_last_name" type="text" '.
            'name="ldap_last_name" value="'.$this->html_safe($name).'" /> *<br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_title extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $title = get_ldap_value('title', $this);
        return '<label class="screen_reader" for="ldap_title">'.$this->trans('Title').'</label>'.
            '<input placeholder="'.$this->trans('Title').'" id="ldap_title" type="text" name="ldap_title" '.
            'value="'.$this->html_safe($title).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_contact_form_start extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $sources = $this->get('ldap_sources');
        $title = $this->trans('Add LDAP');
        $form_class='contact_form';
        $current = $this->get('current_ldap_contact');
        $current_source = false;
        if ($current) {
            $form_class = 'contact_update_form';
            $current_source = $current['source'];
            $title = sprintf($this->trans('Update LDAP - %s'), $this->html_safe($current_source));
        }
        if ($current_source) {
            $source = '<input type="hidden" name="ldap_source" value="'.$this->html_safe($current_source).'" />';
        }
        else {
            $source = '<select name="ldap_source">';
            foreach ($sources as $name) {
                $source .= '<option value="'.$this->html_safe($name).'">'.$this->html_safe($name).'</option>';
            }
            $source .= '</select><br />';
        }
        return '<div class="add_contact"><form class="add_contact_form" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="server_title">'.$title.
            '<img alt="" class="menu_caret" src="'.Hm_Image_Sources::$chevron.'" width="8" height="8" /></div>'.
            '<div class="'.$form_class.'"><input type="hidden" name="contact_source" value="ldap" />'.$source;
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_displayname extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('displayname', $this);
        return '<label class="screen_reader" for="ldap_displayname">'.$this->trans('Display Name').'</label>'.
            '<input placeholder="'.$this->trans('Display Name').'" id="ldap_displayname" type="text" name="ldap_displayname" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_mail extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('email_address', $this);
        return '<label class="screen_reader" for="ldap_mail">'.$this->trans('E-mail Address').'</label>'.
            '<input required placeholder="'.$this->trans('E-mail Address').'" id="ldap_mail" type="email" name="ldap_mail" '.
            'value="'.$this->html_safe($val).'" /> *<br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_phone extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('phone_number', $this);
        return '<label class="screen_reader" for="ldap_phone">'.$this->trans('Telephone Number').'</label>'.
            '<input placeholder="'.$this->trans('Telephone Number').'" id="ldap_phone" type="text" name="ldap_phone" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_fax extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('facsimiletelephonenumber', $this);
        return '<label class="screen_reader" for="ldap_fax">'.$this->trans('Fax Number').'</label>'.
            '<input placeholder="'.$this->trans('Fax Number').'" id="ldap_fax" type="text" name="ldap_fax" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_mobile extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('mobile', $this);
        return '<label class="screen_reader" for="ldap_mobile">'.$this->trans('Mobile Number').'</label>'.
            '<input placeholder="'.$this->trans('Mobile Number').'" id="ldap_mobile" type="text" name="ldap_mobile" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_room extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('roomnumber', $this);
        return '<label class="screen_reader" for="ldap_room">'.$this->trans('Room Number').'</label>'.
            '<input placeholder="'.$this->trans('Room Number').'" id="ldap_room" type="text" name="ldap_room" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_car extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('carlicense', $this);
        return '<label class="screen_reader" for="ldap_car">'.$this->trans('License Plate Number').'</label>'.
            '<input placeholder="'.$this->trans('License Plate Number').'" id="ldap_car" type="text" name="ldap_car" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_org extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('o', $this);
        return '<label class="screen_reader" for="ldap_org">'.$this->trans('Organization').'</label>'.
            '<input placeholder="'.$this->trans('Organization').'" id="ldap_org" type="text" name="ldap_org" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_org_unit extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('ou', $this);
        return '<label class="screen_reader" for="ldap_org_unit">'.$this->trans('Organization Unit').'</label>'.
            '<input placeholder="'.$this->trans('Organization Unit').'" id="ldap_org_unit" type="text" name="ldap_org_unit" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_org_dpt extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('departmentnumber', $this);
        return '<label class="screen_reader" for="ldap_org_dpt">'.$this->trans('Department Number').'</label>'.
            '<input placeholder="'.$this->trans('Department Number').'" id="ldap_org_dpt" type="text" name="ldap_org_dpt" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_emp_num extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('employeenumber', $this);
        return '<label class="screen_reader" for="ldap_emp_num">'.$this->trans('Employee Number').'</label>'.
            '<input placeholder="'.$this->trans('Employee Number').'" id="ldap_emp_num" type="text" name="ldap_emp_num" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_emp_type extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('employeetype', $this);
        return '<label class="screen_reader" for="ldap_emp_type">'.$this->trans('Employment Type').'</label>'.
            '<input placeholder="'.$this->trans('Employment Type').'" id="ldap_emp_type" type="text" name="ldap_emp_type" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_lang extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('preferredlanguage', $this);
        return '<label class="screen_reader" for="ldap_lang">'.$this->trans('Language').'</label>'.
            '<input placeholder="'.$this->trans('Language').'" id="ldap_lang" type="text" name="ldap_lang" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_uri extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('labeleduri', $this);
        return '<label class="screen_reader" for="ldap_uri">'.$this->trans('Website').'</label>'.
            '<input placeholder="'.$this->trans('Website').'" id="ldap_uri" type="text" name="ldap_uri" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_locality extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('l', $this);
        return '<label class="screen_reader" for="ldap_locality">'.$this->trans('Locality').'</label>'.
            '<input placeholder="'.$this->trans('Locality').'" id="ldap_locality" type="text" name="ldap_locality" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_street extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('street', $this);
        return '<label class="screen_reader" for="ldap_street">'.$this->trans('Street').'</label>'.
            '<input placeholder="'.$this->trans('Street').'" id="ldap_street" type="text" name="ldap_street" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_state extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('st', $this);
        return '<label class="screen_reader" for="ldap_state">'.$this->trans('State').'</label>'.
            '<input placeholder="'.$this->trans('State').'" id="ldap_state" type="text" name="ldap_state" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/output
 */
class Hm_Output_ldap_form_postalcode extends Hm_Output_Module {
    protected function output() {
        if (!$this->get('ldap_edit')) {
            return;
        }
        $val = get_ldap_value('postalcode', $this);
        return '<label class="screen_reader" for="ldap_postalcode">'.$this->trans('Postal Code').'</label>'.
            '<input placeholder="'.$this->trans('Postal Code').'" id="ldap_postalcode" type="text" name="ldap_postalcode" '.
            'value="'.$this->html_safe($val).'" /><br />';
    }
}

/**
 * @subpackage ldap_contacts/functions
 */
if (!hm_exists('get_ldap_value')) {
function get_ldap_value($fld, $mod) {
    $current = $mod->get('current_ldap_contact');
    if (!is_array($current) || !array_key_exists('all_fields', $current)) {
        return '';
    }
    if (array_key_exists($fld, $current['all_fields'])) {
        return $current['all_fields'][$fld];
    }
    if (array_key_exists($fld, $current)) {
        return $current[$fld];
    }
    return '';
}}

/**
 * @subpackage ldap_contacts/functions
 */
if (!hm_exists('fetch_ldap_contacts')) {
function fetch_ldap_contacts($config, $user_config, $contact_store, $session=false) {
    $ldap_config = ldap_config($config);
    $ldap_config = ldap_add_user_auth($ldap_config, $user_config->get('ldap_contacts_auth_setting', array()));
    if (count($ldap_config) > 0) {
        foreach ($ldap_config as $name => $vals) {
            $vals['name'] = $name;
            $ldap = new Hm_LDAP_Contacts($vals);
            if ($ldap->connect()) {
                $contacts = $ldap->fetch();
                if (count($contacts) > 0) {
                    $contact_store->import($contacts);
                }
            }
        }
    }
    return array($contact_store, $ldap_config);
}}

/**
 * @subpackage ldap_contacts/functions
 */
if (!hm_exists('ldap_add_user_auth')) {
function ldap_add_user_auth($ldap_config, $auths) {
    if (!is_array($ldap_config) || !is_array($auths)) {
        return $ldap_config;
    }
    foreach ($auths as $name => $vals) {
        if (array_key_exists($name, $ldap_config)) {
            if (array_key_exists('user', $vals)) {
                if (!$vals['user']) {
                    continue;
                }
                $user = sprintf('cn=%s,%s', $vals['user'], $ldap_config[$name]['base_dn']);
                $ldap_config[$name]['user'] = $user;
            }
            if (array_key_exists('pass', $vals)) {
                $ldap_config[$name]['pass'] = $vals['pass'];
            }
        }
    }
    return $ldap_config;
}}

/**
 * @subpackage ldap_contacts/functions
 */
if (!hm_exists('ldap_config')) {
function ldap_config($config, $key=false) {
    $details = get_ini($config, 'ldap.ini', true);
    if ($key && array_key_exists($key, $details)) {
        return $details[$key];
    }
    return $details;
}}

