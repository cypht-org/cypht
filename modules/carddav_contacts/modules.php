<?php

/**
 * Carddav contact modules
 * @package modules
 * @subpackage carddav_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/carddav_contacts/hm-carddav.php';

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_process_add_carddav_contact_from_msg extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_source', 'contact_value'));
        if (!$success) {
            return;
        }
        $details = $this->config->dump();
        list($type, $source) = explode(':', $form['contact_source']);
        if ($type != 'carddav' || !array_key_exists($source, $details)) {
            return;
        }
        $detail = $details[$source];
        $auths = $this->user_config->get('carddav_contacts_auth_setting', array());
        if (!array_key_exists($source, $auths)) {
            return;
        }
        $auth = $auths[$source];
        $pass = false;
        if (array_key_exists('pass', $auth)) {
            $pass = $auth['pass'];
        }
        $parts = process_address_fld($form['contact_value']);
        if (!is_array($parts) || count($parts) == 0 || !is_array($parts[0]) ||
            !array_key_exists('email', $parts[0]) || !trim($parts[0]['email'])) {
                Hm_Msgs::add('Unable to add contact', 'warning');
                return;
        }
        $email = $parts[0]['email'];
        if (array_key_exists('label', $parts[0]) && trim($parts[0]['label'])) {
            $fn = $parts[0]['label'];
        }
        else {
            $fn = $email;
        }
        $carddav = new Hm_Carddav($source, $detail['server'], $auth['user'], $pass);
        if ($carddav->add_contact(array('carddav_fn' => $fn, 'carddav_email' => $email))) {
            Hm_Msgs::add('Contact Added');
        }
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_process_delete_carddav_contact extends Hm_Handler_Module {
    public function process() {
        $details = $this->config->dump();
        list($success, $form) = $this->process_form(array('contact_type', 'contact_source', 'contact_id'));
        if (!$success || $form['contact_type'] != 'carddav' || !array_key_exists($form['contact_source'], $details)) {
            return;
        }
        $auths = $this->user_config->get('carddav_contacts_auth_setting', array());
        $detail = $details[$form['contact_source']];
        if (!array_key_exists($form['contact_source'], $auths)) {
            return;
        }
        $auth = $auths[$form['contact_source']];
        $pass = false;
        if (array_key_exists('pass', $auth)) {
            $pass = $auth['pass'];
        }
        $carddav = new Hm_Carddav($form['contact_source'], $detail['server'], $auth['user'], $pass);
        $contacts = $this->get('contact_store');
        $contact = $contacts->get($form['contact_id']);
        if ($carddav->delete_contact($contact)) {
            Hm_Msgs::add('Contact Deleted');
            $this->out('contact_deleted', 1);
        }
        else {
            Hm_Msgs::add('Could not delete contact', 'warning');
        }
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_process_add_carddav_contact extends Hm_Handler_Module {
    public function process() {
        $details = $this->config->dump();
        list($success, $form) = $this->process_form(array('contact_source', 'carddav_email', 'carddav_fn', 'add_contact'));
        if (!$success || !array_key_exists($form['contact_source'], $details)) {
            return;
        }
        $auths = $this->user_config->get('carddav_contacts_auth_setting', array());
        $detail = $details[$form['contact_source']];
        if (!array_key_exists($form['contact_source'], $auths)) {
            return;
        }
        $auth = $auths[$form['contact_source']];
        $pass = false;
        if (array_key_exists('pass', $auth)) {
            $pass = $auth['pass'];
        }
        $carddav = new Hm_Carddav($form['contact_source'], $detail['server'], $auth['user'], $pass);
        if ($carddav->add_contact($this->request->post)) {
            Hm_Msgs::add('Contact Added');
        }
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_process_edit_carddav_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        $details = $this->config->dump();
        list($success, $form) = $this->process_form(array('contact_source', 'contact_id', 'carddav_email',
            'carddav_fn', 'edit_contact'));
        if (!$success || !array_key_exists($form['contact_source'], $details)) {
            return;
        }
        $contact = $contacts->get($form['contact_id']);
        $auths = $this->user_config->get('carddav_contacts_auth_setting', array());
        $detail = $details[$form['contact_source']];
        if (!array_key_exists($form['contact_source'], $auths)) {
            return;
        }
        $auth = $auths[$form['contact_source']];
        $pass = false;
        if (array_key_exists('pass', $auth)) {
            $pass = $auth['pass'];
        }
        $carddav = new Hm_Carddav($form['contact_source'], $detail['server'], $auth['user'], $pass);
        if ($carddav->update_contact($contact, $this->request->post)) {
            Hm_Msgs::add('Contact Updated');
        }
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_load_edit_carddav_contact extends Hm_Handler_Module {
    public function process() {
        $details = $this->config->dump();
        if (array_key_exists('contact_source', $this->request->get) &&
            array_key_exists('contact_type', $this->request->get) &&
            $this->request->get['contact_type'] == 'carddav' &&
            array_key_exists($this->request->get['contact_source'], $details) &&
            array_key_exists('contact_id', $this->request->get)) {

            $contacts = $this->get('contact_store');
            $contact = $contacts->get($this->request->get['contact_id']);
            if (is_object($contact)) {
                $current = $contact->export();
                $current['id'] = $this->request->get['contact_id'];
                $this->out('current_carddav_contact', $current);
            }
        }
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_load_carddav_contacts extends Hm_Handler_Module {
    public function process() {

        $contacts = $this->get('contact_store');
        $auths = $this->user_config->get('carddav_contacts_auth_setting', array());
        $details = $this->config->dump();

        foreach ($details as $name => $vals) {
            /* TODO: enable when edit/add is working */
            $this->append('contact_edit', sprintf('carddav:%s', $name));
            if (!array_key_exists($name, $auths)) {
                continue;
            }
            $pass = '';
            if (array_key_exists('pass', $auths[$name])) {
                $pass = $auths[$name]['pass'];
            }
            $carddav = new Hm_Carddav($name, $vals['server'], $auths[$name]['user'], $pass);
            $carddav->get_vcards();
            $contacts->import($carddav->addresses);
            $this->append('contact_sources', 'carddav');
        }
        $this->out('contact_store', $contacts, false);
        $this->out('carddav_sources', $details);
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_load_carddav_settings extends Hm_Handler_Module {
    public function process() {
        $this->out('carddav_settings', config('carddav'));
        $this->out('carddav_auth', $this->user_config->get('carddav_contacts_auth_setting', array()));
    }
}

/**
 * @subpackage carddav_contacts/handler
 */
class Hm_Handler_process_carddav_auth_settings extends Hm_Handler_Module {
    public function process() {
        if (!array_key_exists('save_settings', $this->request->post)) {
            return;
        }
        $settings = $this->user_config->get('carddav_contacts_auth_setting', array());
        $users = array();
        $passwords = array();
        $results = $settings;
        if (array_key_exists('carddav_usernames', $this->request->post)) {
            $users = $this->request->post['carddav_usernames'];
        }
        if (array_key_exists('carddav_passwords', $this->request->post)) {
            $passwords = $this->request->post['carddav_passwords'];
        }

        if(empty($settings)){
            $settings = array_fill_keys(array_keys($users),[]);
        }
        foreach ($settings as $name => $vals) {
            if (array_key_exists($name, $users)) {
                $results[$name]['user'] = $users[$name];
            }
            if (array_key_exists($name, $passwords)) {
                $results[$name]['pass'] = $passwords[$name];
            }
        }
        if (count($results) > 0) {
            $new_settings = $this->get('new_user_settings');
            $new_settings['carddav_contacts_auth_setting'] = $results;
            $this->out('new_user_settings', $new_settings, false);
        }
    }
}

/**
 * @subpackage carddav_contacts/output
 */
class Hm_Output_carddav_auth_settings extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('carddav_settings', array());
        $auths = $this->get('carddav_auth', array());
        if (count($settings) == 0) {
            return;
        }
        $res = '<tr><td data-target=".carddav_settings" colspan="2" class="settings_subtitle cursor-pointer border-bottom p-2">'.
            '<i class="bi bi-people-fill fs-5 me-2"></i>'.
            $this->trans('CardDav Addressbooks').'</td></tr>';
        foreach ($settings as $name => $vals) {
            $user = '';
            $pass = false;
            if (array_key_exists($name, $auths)) {
                $user = $auths[$name]['user'];
                if (array_key_exists('pass', $auths[$name]) && $auths[$name]['pass']) {
                    $pass = true;
                }
            }
            $res .= '<tr class="carddav_settings"><td>'.$this->html_safe($name).'</td><td>';
            $res .= '<input autocomplete="username" type="text" value="'.$user.'" name="carddav_usernames['.$this->html_safe($name).']" ';
            $res .= 'placeholder="'.$this->trans('Username').'" class="form-control warn_on_paste" /> <input type="password" class="form-control warn_on_paste"';
            if ($pass) {
                $res .= 'disabled="disabled" placeholder="'.$this->trans('Password saved').'" ';
                $res .= 'name="carddav_passwords['.$this->html_safe($name).']" /> <input type="button" ';
                $res .= 'value="'.$this->trans('Unlock').'" class="carddav_password_change btn btn-primary" /></td></tr>';
            }
            else {
                $res .= 'autocomplete="new-password" placeholder="'.$this->trans('Password').'" ';
                $res .= 'name="carddav_passwords['.$this->html_safe($name).']" /></td></tr>';
            }
        }
        return $res;
    }
}

/**
 * @subpackage carddav_contacts/output
 */
class Hm_Output_carddav_contacts_form extends Hm_Output_Module {
    protected function output() {

        $email = '';
        $name = '';
        $phone = '';
        $sources = $this->get('carddav_sources', array());
        if (count($sources) == 0) {
            return '';
        }
        $form_class = 'contact_form';
        $button = '<input class="add_contact_submit btn btn-primary" type="submit" name="add_contact" value="'.$this->trans('Add').'" />';
        $title = $this->trans('Add Carddav');
        $current = $this->get('current_carddav_contact', array());
        $current_source = false;
        if (!empty($current)) {
            $current_source = $current['source'];
            if (array_key_exists('email_address', $current)) {
                $email = $current['email_address'];
            }
            if (array_key_exists('fn', $current)) {
                $name = $current['fn'];
            }
            if (array_key_exists('phone_number', $current)) {
                $phone = $current['phone_number'];
            }
            $form_class = 'contact_update_form mt-3';
            $title = sprintf($this->trans('Update Carddav - %s'), $this->html_safe($current['source']));
            $button = '<input type="hidden" name="contact_id" value="'.$this->html_safe($current['id']).'" />'.
                '<input class="edit_contact_submit btn btn-primary" type="submit" name="edit_contact" value="'.$this->trans('Update').'" />';
        }
        if ($current_source) {
            $target = '<input type="hidden" name="carddav_email_id" value="'.$this->html_safe($current['carddav_email_id']).'" />'.
                '<input type="hidden" name="carddav_phone_id" value="'.$this->html_safe($current['carddav_phone_id']).'" />'.
                '<input type="hidden" name="carddav_fn_id" value="'.$this->html_safe($current['carddav_fn_id']).'" />'.
                '<input type="hidden" name="contact_source" value="'.$this->html_safe($current_source).'" />';
        }
        else {
            $target = '<label class="form-label" for="contact_source">'.$this->trans('Account').'</label><select id="contact_source" '.
                'name="contact_source" class="form-control">';
            foreach ($sources as $src => $details) {
                $target .= '<option value="'.$this->html_safe($src).'">'.$this->html_safe($src).'</option>';
            }
            $target .= '</select><br />';
        }
        return '<div class="add_contact_responsive"><form class="add_contact_form" method="POST">'.
            '<button class="server_title mt-2 btn btn-light"><i class="bi bi-person-add me-2"></i>'.$title.'</button>'.
            '<div class="'.$form_class.'">'.$target.''.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<label class="form-label" for="carddav_email">'.$this->trans('E-mail Address').' *</label>'.
            '<input required placeholder="'.$this->trans('E-mail Address').'" id="carddav_email" type="email" name="carddav_email" '.
            'value="'.$this->html_safe($email).'" class="form-control" /><br />'.
            '<label class="form-label" for="carddav_fn">'.$this->trans('Full Name').' *</label>'.
            '<input required placeholder="'.$this->trans('Full Name').'" id="carddav_fn" type="text" name="carddav_fn" '.
            'value="'.$this->html_safe($name).'" class="form-control" /><br />'.
            '<label class="form-label" for="carddav_phone">'.$this->trans('Telephone Number').'</label>'.
            '<input placeholder="'.$this->trans('Telephone Number').'" id="carddav_phone" type="text" name="carddav_phone" '.
            'value="'.$this->html_safe($phone).'" class="form-control"><br />'.$button.' <input type="button" class="reset_contact btn btn-secondary" value="'.
            $this->trans('Cancel').'" /></div></form></div>';
    }
}
