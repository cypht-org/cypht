<?php

/**
 * Contact modules
 * @package modules
 * @subpackage contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/contacts/hm-contacts.php';
require APP_PATH.'modules/contacts/functions.php';

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_contact_auto_collect_setting extends Hm_Handler_Module {
    public function process() {
        function contact_auto_collect_callback($val) {
            return $val;
        }
        process_site_setting('contact_auto_collect', $this, 'contact_auto_collect_callback', false, true);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_store_contact_message extends Hm_Handler_Module {
    public function process() {
        if ($this->get('collect_contacts', false)) {
            $addresses = process_address_fld($this->request->post['compose_to']);
            $contacts = $this->get('contact_store');
            $contact_list = $contacts->getAll();
            $existingEmails = array_column($contact_list, 'email_address');
            // Extract email addresses from the new format of $addresses
            $newEmails = array_column($addresses, 'email');
            if (!empty($newEmails)) {
                $newContacts = array_filter($newEmails, function ($email) use ($existingEmails) {
                    return !in_array($email, $existingEmails);
                });

                if (!empty($newContacts)) {
                    $newContacts = array_map(function ($email) {
                        return ['source' => 'local', 'email_address' => $email, 'display_name' => $email, 'group' => 'Collected Recipients'];
                    }, $newContacts);
                    $contacts->add_contact($newContacts[0]);
                    $this->session->record_unsaved('Contact Added');
                    Hm_Msgs::add('Contact Added');
                }
            }
        }
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_store_contact_allow_images extends Hm_Handler_Module {
    public function process() {
        if ($this->get('imap_allow_images', false) && $this->get('collect_contacts', false)) {
            $email = str_replace(['<', '>'], '', $this->get('collected_contact_email', ''));
            $name = $this->get('collected_contact_name', '');
            $contacts = $this->get('contact_store');
            $contact_list = $contacts->getAll();
            $existingEmails = array_column($contact_list, 'email_address');
            if (!in_array($email, $existingEmails)) {
                $contacts->add_contact(['source' => 'local', 'email_address' => $email, 'display_name' => $name, 'group' => 'Trusted Senders']);
                $this->session->record_unsaved('Contact Added');
                Hm_Msgs::add('Contact Added');
            }
        }
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_autocomplete_contact extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_value'));
        $results = array();
        if ($success) {
            $val = trim($form['contact_value']);
            $contacts = $this->get('contact_store');
            $contacts->sort('email_address');
            $results = array_slice($contacts->search(array(
                'display_name' => $val,
                'email_address' => $val
            )), 0, 10);
        }
        $this->out('contact_suggestions', $results);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_find_message_contacts extends Hm_Handler_Module {
    public function process() {
        $existing = $this->get('contact_store');
        $addr_headers = array('to', 'cc', 'bcc', 'sender', 'reply-to', 'from');
        $headers = $this->get('msg_headers', array());
        $addresses = array();
        foreach ($headers as $name => $value) {
            if (in_array(mb_strtolower($name), $addr_headers, true)) {
                $values = is_array($value) ? $value : array($value);
                foreach ($values as $val) {
                    foreach (Hm_Address_Field::parse($val) as $v) {
                        if (!$existing->search(array('email_address' => $v['email']))) {
                            $addresses[] = $v;
                        }
                    }
                }
            }
        }
        $this->out('contact_addresses', $addresses);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_send_to_contact extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('contact_id', $this->request->get)) {
            $contacts = $this->get('contact_store');
            $contact = $contacts->get($this->request->get['contact_id']);
            
            if ($contact) {
                $to = sprintf('%s <%s>', $contact->value('display_name'), $contact->value('email_address'));
                $this->out('compose_draft', array('draft_to' => $to, 'draft_subject' => '', 'draft_body' => ''));
            }
        }
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_load_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = new Hm_Contact_Store();
        $contacts->init($this->user_config, $this->session);
        $page = 1;
        if (array_key_exists('contact_page', $this->request->get)) {
            $page = $this->request->get['contact_page'];
        }
        $this->out('contact_page', $page);
        $this->out('contact_store', $contacts, false);
        $this->out('enable_warn_contacts_cc_not_exist_in_list_contact', $this->user_config->get('enable_warn_contacts_cc_not_exist_in_list_contact_setting', false));

    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_export_contacts extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('contact_source', $this->request->get)) {
            $source = $this->request->get['contact_source'];
            $contacts = $this->get('contact_store');
            $contact_list = $contacts->getAll();
            if ($source != 'all') {
                $contact_list = $contacts->export($source);
            }

            Hm_Functions::header('Content-Type: text/csv');
            Hm_Functions::header('Content-Disposition: attachment; filename="'.$source.'_contacts.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, array('display_name', 'email_address', 'phone_number'));
            foreach ($contact_list as $contact) {
                $contact_data = is_array($contact) ? $contact : $contact->export();
                fputcsv($output, array($contact_data['display_name'], $contact_data['email_address'], $contact_data['phone_number']));
            }
            fclose($output);
            exit;
        }
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contact_auto_collect_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        $reset = '';
        if (array_key_exists('contact_auto_collect', $settings) && $settings['contact_auto_collect']) {
            $checked = ' checked="checked"';
        } else {
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise refresh_list reset_default_value_checkbox"></i></span>';
        }

        return '<tr class="general_setting"><td><label for="contact_auto_collect">' .
            $this->trans('Automatically add outgoing email addresses') . '</label></td>' .
            '<td><input class="form-check-input" type="checkbox" ' . $checked . ' id="contact_auto_collect" name="contact_auto_collect" data-default-value="true" value="1" />' . $reset . '</td></tr>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_contacts"><a class="unread_link" href="?page=contacts">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-people-fill menu-icon"></i>';
        }
        $res .= '<span class="nav-label">'. $this->trans('Contacts').'</span></a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_start extends Hm_Output_Module {
    protected function output() {
        $contact_source_list = $this->get('contact_sources', array());
        $actions = '<div class="src_title fs-5 mb-2">'.$this->trans('Export Contacts as CSV').'</div>';
        $actions .= '<div class="list_src"><a href="?page=export_contact&amp;contact_source=all">'.$this->trans('All Contacts').'</a></div>';
        foreach ($contact_source_list as $value) {
            $actions .= '<div class="list_src"><a href="?page=export_contact&amp;contact_source='.$this->html_safe($value).'">'.$this->html_safe($this->html_safe($value).' Contacts').'</a></div>';
        }
        $res = '<div class="contacts_content p-0"><div class="content_title d-flex gap-2 justify-content-between px-3 align-items-center"><div class="d-flex gap-2 align-items-center">'.$this->trans('Contacts'). '</div><div class="list_controls source_link d-flex gap-2 align-items-center"><a href="#" title="' . $this->trans('Export Contacts') . '" class="refresh_list">' .
            '<i class="bi bi-download" width="16" height="16" onclick="listControlsMenu()"></i></a></div></div>'.
            '<div class="list_actions">'.$actions.'</div>';

        $res .= '<div class="app-container">';
        $res .= '<div class="container-fluid py-5">';
        
        // category tabs starts here
        $res .= '<div class="row mb-4">';
        $res .= '<div class="col-12 px-2">';

        return $res;
        // end tabs
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_end extends Hm_Output_Module {
    protected function output() {
        
        $res = '</div>';
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_add_message_contacts extends Hm_Output_Module {
    protected function output() {
        $addresses = $this->get('contact_addresses');
        $headers = $this->get('msg_headers');
        $backends = $this->get('contact_edit', array());
        if (!empty($addresses) && count($backends) > 0) {
            $res = '<div class="add_contact_row position-absolute top-0 end-0 z-3 p-2 d-flex align-content-center gap-3"><a href="#" title="'.
            $this->html_safe('Add Contact').'" onclick="$(\'.add_contact_controls\').toggle(); return false;">'.
                '<i class="bi bi-person-fill-add fs-3" ></i></a><div class="add_contact_controls"><div class="row g-1 mt-1"><div class="col"><select id="add_contact" class="form-select form-select-sm">';
            foreach ($addresses as $vals) {
                $res .= '<option value="'.$this->html_safe($vals['name']).' '.$this->html_safe($vals['email']).
                    '">'.$this->html_safe($vals['name']).' &lt;'.$this->html_safe($vals['email']).'&gt;</option>';
            }
            $res .= '</select></div> <div class="col"><select id="contact_source" class="form-select form-select-sm">';
            foreach ($backends as $val) {
                $res .= '<option value="'.$this->html_safe($val).'">'.$this->html_safe($val).'</option>';
            }
            $res .= '</select></div> <div class="col"><input onclick="return add_contact_from_message_view()" class="add_contact_button w-100 btn btn-primary btn-sm" '.
                'type="button" value="'.$this->trans('Add Contact').'"></div></div></div></div>';
            $headers = $headers.$res;
        }
        $this->out('msg_headers', $headers, false);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_check_imported_contacts extends Hm_Handler_Module
{
    public function process()
    {
        $imported_contact = $this->session->get('imported_contact', array());
        $this->session->del('imported_contact');
        $this->out('imported_contact', $imported_contact);
        $is_ldap_contacts_module_enabled = $this->module_is_supported('ldap_contacts');
        $this->out('is_ldap_contacts_module_enabled', $is_ldap_contacts_module_enabled);
        $is_local_contacts_module_enabled = $this->module_is_supported('local_contacts');
        $this->out('is_local_contacts_module_enabled', $is_local_contacts_module_enabled);
    }
}

/**
 * Process input from the enable collect address on send setting
 * @subpackage core/handler
 */
class Hm_Handler_process_enable_collect_address_on_send_setting extends Hm_Handler_Module {
    /**
     * Allowed vals are bool true/false
     */
    public function process() {
        function enable_collect_address_on_send_callback($val) {
            return $val;
        }
        process_site_setting('enable_collect_address_on_send', $this, 'enable_collect_address_on_send_callback', DEFAULT_ENABLE_COLLECT_ADDRESS_ON_SEND, true);
    }
}

/**
 * Option for the "allow search in all flagged folders" setting
 * This setting allows searching flagged messages in all folders, not just the INBOX one.
 * @subpackage core/output
 */
class Hm_Output_enable_collect_address_on_send_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_enable_collect_address_on_send_setting
     */
    protected function output() {
        $settings = $this->get('user_settings', array());
        if (array_key_exists('enable_collect_address_on_send', $settings) && $settings['enable_collect_address_on_send']) {
            $checked = ' checked="checked"';
            if($settings['enable_collect_address_on_send'] !== DEFAULT_ENABLE_COLLECT_ADDRESS_ON_SEND) {
                $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise fs-6 cursor-pointer refresh_list reset_default_value_checkbox"></i></span>';
            }
        }
        else {
            $checked = '';
            $reset='';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="enable_collect_address_on_send">'.
            $this->trans('Enable collect address on send').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.
            ' value="1" id="enable_collect_address_on_send" name="enable_collect_address_on_send" data-default-value="'.(DEFAULT_ENABLE_COLLECT_ADDRESS_ON_SEND ? 'true' : 'false') . '"/>'.$reset.'</td></tr>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_list extends Hm_Output_Module {
    protected function output() {
        $imported_contact = $this->get('imported_contact', array());
        if (count($this->get('contact_sources', array())) == 0) {
            return '<div class="no_contact_sources">'.$this->trans('No contact backends are enabled!').
                '<br />'.$this->trans('At least one backend must be enabled in the config/app.php file to use contacts.').'</div>';
        }
        $per_page = 15;
        $current_page = $this->get('contact_page', 1);

        $contacts = $this->get('contact_store');
        $editable = $this->get('contact_edit', array());

        // Get total contact count for pagination
        $total_contacts = 0;
        if ($contacts) {
            $total_contacts = $contacts->count();
        }
        $total_pages = ceil($total_contacts / $per_page);

        $tabIndex = 1;
        $contactGroups = [];
        if ($contacts) {
            foreach ($contacts->paginate_grouped('group', $current_page, $per_page) as $key => $contact) {
                if (!array_key_exists($key, $contactGroups)) {
                    $contactGroups[$key] = [];
                }
                $contactGroups[$key][] = $contact;
            }
        }

        $collectedRecipientsContacts = [];
        $trustedSendersContacts = [];
        $personalAddressesContacts = [];
        $tabsHtml = '';

        foreach ($contactGroups as $groupName => $groupContacts) {
            switch (strtolower($groupName)) {
                case 'collected recipients':
                    $collectedRecipientsContacts = $groupContacts;
                    break;
                case 'trusted senders':
                    $trustedSendersContacts = $groupContacts;
                    break;
                case 'personal addresses':
                    $personalAddressesContacts = $groupContacts;
                    break;
                default:
                    $personalAddressesContacts = array_merge($personalAddressesContacts, $groupContacts);
                    break;
            }
        }

        $predefinedTabs = [
            ['Collected Recipients', $collectedRecipientsContacts, 'collected-recipients', 'bi-people-fill'],
            ['Trusted Senders', $trustedSendersContacts, 'trusted-senders', 'bi-person-check-fill'],
            ['Personal Addresses', $personalAddressesContacts, 'personal-addresses', 'bi-person-badge-fill']
        ];

        $tabIndex = 1;
        foreach ($predefinedTabs as $tabData) {
            list($groupName, $groupContacts, $targetId, $iconClass) = $tabData;
            
            $activeClass = (strtolower($groupName) === 'collected recipients') ? ' active' : '';
            
            $contactCount = array_sum(array_map('count', $groupContacts));
            
            $tabsHtml .= '<button class="category-tab tab-'.$targetId.$activeClass.'" data-target="'.$targetId.'">';
            $tabsHtml .= '<div class="tab-icon">';
            $tabsHtml .= '<i class="bi ' . $iconClass . '"></i>';
            $tabsHtml .= '</div>';
            $tabsHtml .= '<div class="tab-content">';
            $tabsHtml .= '<h3 class="tab-title">';
            $tabsHtml .= htmlspecialchars($groupName);
            $tabsHtml .= '</h3>';
            $tabsHtml .= '<p class="tab-description">';
            $tabsHtml .= 'Description for '.htmlspecialchars($groupName);
            $tabsHtml .= '</p>';
            $tabsHtml .= '</div>';
            $tabsHtml .= '<div class="tab-badge">';
            $tabsHtml .= $contactCount;
            $tabsHtml .= '</div>';
            $tabsHtml .= '</button>';
            $tabIndex++;
        }

        $res = '<div class="category-tabs-container">';
        $res .= '<div class="category-tabs">';
        $res .= $tabsHtml;

        $res .= '</div>';

        $res .= '<div class="action-buttons">';

        if ($this->get('is_ldap_contacts_module_enabled', false)) {
            $res .= '<button class="btn btn-primary action-btn-add" data-bs-toggle="modal" data-bs-target="#ldapContactModal"><i class="bi bi-person-plus-fill"></i>';
            $res .= 'Add LDAP';
            $res .= '</button>';
        }

        if( $this->get('is_local_contacts_module_enabled', false)) {
            $res .= '<button class="btn btn-success action-btn-add" data-bs-toggle="modal" data-bs-target="#localContactModal"><i class="bi bi-person-plus"></i>';
            $res .= 'Add Local';
            $res .= '</button>';
        }

        $res .= '</div>';

        $res .= '</div>';

        $res .= '<div class="row p-3">';
        $res .= '<div class="col-12 px-0">';

        $contactGroupsToDisplay = [
            'list-collected' => ['Collected Recipients', $collectedRecipientsContacts, 'collected-recipients', true],
            'list-trusted-senders' => ['Trusted Senders', $trustedSendersContacts, 'trusted-senders', false],
            'list-personal' => ['Personal Addresses', $personalAddressesContacts, 'personal-addresses', false]
        ];

        foreach ($contactGroupsToDisplay as $containerClass => $groupData) {
            list($listTitle, $contactsToShow, $contentId, $isActive) = $groupData;
            $activeClass = $isActive ? ' active' : '';
            
            $contactCount = array_sum(array_map('count', $contactsToShow));
            
            $res .= '<div id="' . $contentId . '" class="contact-list-container ' . $containerClass . ' tab-content-section' . $activeClass . '">';

            $res .= '<div class="contact-list-header">';
            $res .= '<h2 class="list-title">';
            $res .= $listTitle;
            $res .= '</h2>';
            $res .= '<span class="list-count">';
            $res .= $contactCount;
            $res .= '</span>';
            $res .= '</div>';

            if (!empty($contactsToShow)) {
                $res .= '<div class="table-responsive">';
                $res .= '<table class="table contact-table">';

                $res .= '<thead>';
                $res .= '<tr>';
                $res .= '<th>Name</th>';
                $res .= '<th>Source</th>';
                $res .= '<th>E-mail</th>';
                $res .= '<th>Phone</th>';
                $res .= '<th>Actions</th>';
                $res .= '</tr>';
                $res .= '</thead>';
                $res .= '<tbody>';

                foreach ($contactsToShow as $contact) {
                    foreach ($contact as $c) {
                        $name = $c->value('display_name');
                        if (!trim($name)) {
                            $name = $c->value('fn');
                        }
                        if (!trim($name)) {
                            $name = $c->value('email_address');
                        }
                        
                        $initials = get_initials($name);
                        $avatarColor = get_avatar_color($c->value('id'));

                        $res .= '<tr class="contact-row">';
                        
                        $res .= '<td>';
                        $res .= '<div class="contact-info">';
                        $res .= '<div class="contact-avatar-small" style="background: ' . $avatarColor . '">';
                        $res .= '<span>' . $this->html_safe($initials) . '</span>';
                        $res .= '</div>';
                        $res .= '<span class="contact-name-text">' . $this->html_safe($name) . '</span>';
                        $res .= '</div>';
                        $res .= '</td>';

                        $res .= '<td>';
                        $res .= '<div class="contact-source-cell">';
                        $res .= '<i class="bi bi-database-fill"></i>';
                        $res .= '<span>' . $this->html_safe($c->value('source')) . '</span>';
                        $res .= '</div>';
                        $res .= '</td>';

                        $res .= '<td>';
                        $res .= '<div class="contact-email-cell">';
                        $res .= '<i class="bi bi-envelope-fill"></i>';
                        $res .= '<span>' . $this->html_safe($c->value('email_address')) . '</span>';
                        $res .= '</div>';
                        $res .= '</td>';

                        $res .= '<td>';
                        $res .= '<div class="contact-phone-cell">';
                        $res .= '<i class="bi bi-telephone-fill"></i>';
                        $res .= '<span>' . $this->html_safe($c->value('phone_number')) . '</span>';
                        $res .= '</div>';
                        $res .= '</td>';

                        $res .= '<td>';
                        $res .= '<div class="contact-actions">';
                        
                        if (in_array($c->value('type').':'.$c->value('source'), $editable, true)) {
                            $edit_url = '?page=contacts&amp;contact_id='.$this->html_safe($c->value('id')).'&amp;contact_source='.
                                $this->html_safe($c->value('source')).'&amp;contact_type='.
                                $this->html_safe($c->value('type')).'&amp;contact_page='.$current_page;
                            $res .= '<a href="'.$edit_url.'" class="action-btn action-btn-edit" title="Modifier">';
                            $res .= '<i class="bi bi-pencil-fill"></i>';
                            $res .= '</a>';
                        }

                        $send_to_url = '?page=compose&amp;contact_id='.$this->html_safe($c->value('id')).
                            '&amp;contact_source='.$this->html_safe($c->value('source')).
                            '&amp;contact_type='.$this->html_safe($c->value('type'));
                        $res .= '<a href="'.$send_to_url.'" class="action-btn action-btn-more" title="Envoyer à">';
                        $res .= '<i class="bi bi-envelope-fill"></i>';
                        $res .= '</a>';
                        
                        if (in_array($c->value('type').':'.$c->value('source'), $editable, true)) {
                            $delete_attrs = 'data-id="'.$this->html_safe($c->value('id')).'" data-type="'.$this->html_safe($c->value('type')).'" data-source="'.$this->html_safe($c->value('source')).'"';
                            $res .= '<a '.$delete_attrs.' class="action-btn action-btn-delete delete_contact" title="Supprimer">';
                            $res .= '<i class="bi bi-trash-fill"></i>';
                            $res .= '</a>';
                        }
                        
                        $res .= '</div>';
                        $res .= '</td>';
                        $res .= '</tr>';
                    }
                }

                $res .= '</tbody>';
                $res .= '</table>';
                $res .= '</div>';
                
                if ($total_contacts > 0 && $total_pages > 1) {
                    $start_item = ($current_page - 1) * $per_page + 1;
                    $end_item = min($start_item + $per_page - 1, $total_contacts);
                    
                    $res .= '<div class="pagination-container mt-3">';
                    $res .= '<div class="pagination-info">';
                    $res .= sprintf($this->trans('Showing %d-%d of %d contacts'), $start_item, $end_item, $total_contacts);
                    $res .= '</div>';
                    
                    $res .= '<div class="pagination-controls">';
                    
                    $prev_disabled = $current_page <= 1 ? ' disabled=""' : '';
                    $prev_page = max(1, $current_page - 1);
                    $res .= '<button class="pagination-btn"' . $prev_disabled . ' data-page="' . $prev_page . '">';
                    $res .= '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left">';
                    $res .= '<path d="m15 18-6-6 6-6"></path>';
                    $res .= '</svg>';
                    $res .= '</button>';
                    
                    $res .= '<div class="pagination-numbers">';
                    
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        $res .= '<button class="pagination-number" data-page="1">1</button>';
                        if ($start_page > 2) {
                            $res .= '<span class="pagination-ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active_class = $i == $current_page ? ' active' : '';
                        $res .= '<button class="pagination-number' . $active_class . '" data-page="' . $i . '">' . $i . '</button>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            $res .= '<span class="pagination-ellipsis">...</span>';
                        }
                        $res .= '<button class="pagination-number" data-page="' . $total_pages . '">' . $total_pages . '</button>';
                    }
                    
                    $res .= '</div>';
                    
                    $next_disabled = $current_page >= $total_pages ? ' disabled=""' : '';
                    $next_page = min($total_pages, $current_page + 1);
                    $res .= '<button class="pagination-btn"' . $next_disabled . ' data-page="' . $next_page . '">';
                    $res .= '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">';
                    $res .= '<path d="m9 18 6-6-6-6"></path>';
                    $res .= '</svg>';
                    $res .= '</button>';
                    
                    $res .= '</div>';
                    $res .= '</div>';
                }
            } else {
                $res .= '<div class="empty-state">';
                $res .= '<p>No contacts in this category</p>';
                $res .= '</div>';
            }

            $res .= '</div>';
        }

        $res .= '</div>';
        $res .= '</div>';

        return $res;
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_save_contact extends Hm_Handler_Module
{
    public function process()
    {
        list($success, $form ) = $this->process_form(array('email_address'));
        if ($success) {
            $contacts = $this->get('contact_store');
            $contact_list = $contacts->getAll();
            $emailKeyMap = [];
            foreach ($contact_list as $key => $contact) {
                $email = strtolower($contact->value('email_address'));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emailKeyMap[$email] = $key;
                }
            }
            $existingEmails = array_keys($emailKeyMap);

            $list_mails = array_unique(
                preg_split('/[;,]+/', $form['email_address'], -1, PREG_SPLIT_NO_EMPTY)
            );
            $addedCount = 0;
            $updatedCount = 0;
            foreach ($list_mails as $addr) {
                $addresses = process_address_fld($addr);
                $newEmails = array_column($addresses, 'email');
                $validEmails = array_filter($newEmails, function($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
                if (empty($validEmails)) {
                    continue;
                }

                $newContacts = array_diff($validEmails, $existingEmails);
                $existingContacts = array_intersect($validEmails, $existingEmails);
                // add new contacts
                foreach ($newContacts as $email) {
                    $contacts->add_contact([
                        'source' => 'local',
                        'email_address' => $email,
                        'display_name' => $email,
                        'group' => 'Trusted Senders'
                    ]);
                    $addedCount++;
                }

                // Update existing contacts
                foreach ($existingContacts as $email) {
                    $contactKey = $emailKeyMap[$email];
                    $contacts->update_contact($contactKey, [
                        'source' => 'local',
                        'email_address' => $email,
                        'group' => 'Trusted Senders'
                    ]);
                    $updatedCount++;
                }
            }
            if ($addedCount > 0 || $updatedCount > 0) {
                $msgParts = [];
                if ($addedCount > 0) {
                    $msgParts[] = "$addedCount new contact" . ($addedCount > 1 ? "s" : "") . " added";
                }
                if ($updatedCount > 0) {
                    $msgParts[] = "$updatedCount contact" . ($updatedCount > 1 ? "s" : "") . " updated";
                }
                $finalMsg = implode(', ', $msgParts);

                $this->session->record_unsaved($finalMsg);
                Hm_Msgs::add($finalMsg);
            }
        }

    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_filter_autocomplete_list extends Hm_Output_Module {
    protected function output() {
        $suggestions = array();
        foreach ($this->get('contact_suggestions', array()) as $item) {
            if (is_array($item)) {
                $contact = $item[1];
                $contact_id = $item[0];

                if (trim($contact->value('display_name'))) {
                    $suggestions[] = $this->html_safe(sprintf(
                        '{"contact_id": "%s", "contact": "%s %s", "type": "%s", "source": "%s"}',
                        $contact_id,
                        $contact->value('display_name'),
                        $contact->value('email_address'),
                        $contact->value('type'),
                        $contact->value('source')
                    ));
                } else {
                    $suggestions[] = $this->html_safe(sprintf(
                        '%s',
                        $contact->value('email_address')
                    ));
                }
            }
        }
        $this->out('contact_suggestions', $suggestions);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_load_contact_mails extends Hm_Output_Module {
    protected function output() {
        if (!$this->get("enable_warn_contacts_cc_not_exist_in_list_contact")) {
            return "";
        }
        $contact_store = $this->get('contact_store');
        $emails = [];
        foreach ($contact_store->dump() as $contact) {
            $email = $contact->value('email_address');
            if ($email) {
                $emails[] = $email;
            }
        }
        $emails = json_encode($emails);        
        return "<script>var list_emails = $emails; </script>";
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_enable_warn_contacts_cc_not_exist_in_list_contact extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('enable_warn_contacts_cc_not_exist_in_list_contact', $settings) && $settings['enable_warn_contacts_cc_not_exist_in_list_contact']) {
            $checked = ' checked="checked"';
            $reset = '<span class="tooltip_restore" restore_aria_label="Restore default value"><i class="bi bi-arrow-counterclockwise fs-6 cursor-pointer refresh_list reset_default_value_checkbox"></i></span>';
        }
        else {
            $checked = '';
            $reset='';
        }
        return '<tr class="general_setting"><td><label class="form-check-label" for="enable_warn_contacts_cc_not_exist_in_list_contact">'.
            $this->trans('Enable warn if contacts Cc not exist in list contact').'</label></td>'.
            '<td><input class="form-check-input" type="checkbox" '.$checked.
            ' value="1" id="enable_warn_contacts_cc_not_exist_in_list_contact" name="enable_warn_contacts_cc_not_exist_in_list_contact" />'.$reset.'</td></tr>';
    }
}

class Hm_Handler_process_enable_warn_contacts_cc_not_exist_in_list_contact extends Hm_Handler_Module {
    public function process() {
        function enable_warn_contacts_cc_not_exist_in_list_contact_callback($val) { return $val; }
        process_site_setting('enable_warn_contacts_cc_not_exist_in_list_contact', $this, 'enable_warn_contacts_cc_not_exist_in_list_contact_callback', false, true);
    }
}


/**
 * @subpackage contacts/functions
 */
if (!hm_exists('build_contact_detail')) {
function build_contact_detail($output_mod, $contact, $id) {
    $res = '<div class="contact_detail m-3" /><table class="w-auto"><thead></thead><tbody>';
    $all_fields = false;
    $contacts = $contact->export();
    ksort($contacts);
    
    foreach ($contacts as $name => $val) {
        if ($name == 'all_fields') {
            $all_fields = $val;
            continue;
        }
        if (mb_substr($name, 0, 8) == 'carddav_') {
            continue;
        }
        if (!trim($val)) {
            continue;
        }
        $res .= '<tr><th>'.$output_mod->trans(name_map($name)).'</th>';
        $res .= '<td class="'.$output_mod->html_safe($name).'">'.$output_mod->html_safe($val).'</td></tr>';
    }
    if ($all_fields) {
        ksort($all_fields);
        foreach ($all_fields as $name => $val) {
            if (in_array($name, array(0, 'raw', 'objectclass', 'ID', 'APP:EDITED', 'UPDATED'), true)) {
                continue;
            }
            $res .= '<tr><th>'.$output_mod->trans(name_map($name)).'</th>';
            $res .= '<td>'.$output_mod->html_safe($val).'</td></tr>';
        }
    }
    $res .= '</tbody></table></div>';
    return $res;
}}


/**
 * @subpackage contacts/functions
 */
if (!hm_exists('name_map')) {
function name_map($val) {
    $names = array(
        'display_name' => 'Display Name',
        'displayname' => 'Display Name',
        'givenname' => 'Given Name',
        'GD:GIVENNAME' => 'Given Name',
        'GD:FAMILYNAME' => 'Surname',
        'sn' => 'Surname',
        'mail' => 'E-mail Address',
        'source' => 'Source',
        'email_address' => 'E-mail Address',
        'l' => 'Locality',
        'st' => 'State',
        'street' => 'Street',
        'postalcode' => 'Postal Code',
        'title' => 'Title',
        'TITLE' => 'Title',
        'phone_number' => 'Telephone Number',
        'telephonenumber' => 'Telephone Number',
        'facsimiletelephonenumber' => 'Fax Number',
        'mobile' => 'Mobile Number',
        'roomnumber' => 'Room Number',
        'carlicense' => 'Vehicle License',
        'o' => 'Organization',
        'ou' => 'Organizational Unit',
        'departmentnumber' => 'Department Number',
        'employeenumber' => 'Employee Number',
        'employeetype' => 'Employee Type',
        'preferredlanguage' => 'Preferred Language',
        'labeleduri' => 'Homepage URL',
        'home_address' => 'Home Address',
        'work_address' => 'Work Address',
        'nickname' => 'Nickname',
        'pager' => 'Pager',
        'homephone' => 'Home Phone',
        'type' => 'Type',
        'url' => 'Website',
        'org' => 'Company',
        'fn' => 'Full Name',
        'uid' => 'Uid',
        'src_url' => 'URL',
        'adr' => 'Address',
        'dn' => 'Distinguished Name'
    );
    if (array_key_exists($val, $names)) {
        return $names[$val];
    }
    return $val;
}}


/**
 * @subpackage contacts/functions
 */
if (!hm_exists('get_import_detail_modal_content')) {
function get_import_detail_modal_content($output_mod, $imported_contacts) {
    $per_page = 10;
    $page = 1;
    $total_contacts = count($imported_contacts);
    $total_pages = ceil($total_contacts / $per_page);
    $res = '<table class="table">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Display Name</th>
                <th scope="col">E-mail Address</th>
                <th scope="col">Telephone Number</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody class="import_body">';

    for ($i = 0; $i < $total_contacts; $i++) {
        $contact = $imported_contacts[$i];
        $status = $contact['status'] == "invalid email" ? "danger" : "success";
        $res .= '<tr class="page_'.ceil(($i + 1) / $per_page).'">
            <td>'.($i + 1).'</td>
            <td>'.$output_mod->html_safe($contact['display_name']).'</td>
            <td>'.$output_mod->html_safe($contact['email_address']).'</td>
            <td>'.$output_mod->html_safe($contact['phone_number']).'</td>
            <td class="text-'.$status.'">'.$output_mod->html_safe($contact['status']).'</td>
        </tr>';
    }

    $res .= '</tbody></table>';

    if ($total_pages > 1) {
        $res .= '<nav aria-label="Pagination">
            <ul class="pagination justify-content-center">
                <li class="prev_page '.($page == 1 ? "disabled" : "").'">
                    <span role="button" class="page-link" tabindex="-1" aria-disabled="true">Previous</span>
                </li>';

        for ($i = 1; $i <= $total_pages; $i++) {
            $res .= '<li class="page_item_'.$i.' '.($page == $i ? "active" : "").' page_link_selector" data-page="'. $i .'"><span role="button" class="page-link">'.$i.'</span></li>';
        }

        $res .= '<li class="next_page '.($page == $total_pages ? "disabled" : "").'">
                    <span role="button" class="page-link">Next</span>
                </li>
            </ul>
        </nav>';
    }

    $res .= '<input type="hidden" id="totalPages" value="'.$total_pages.'">';

    return '<div class="modal fade" id="importDetailModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="importDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importDetailModalLabel">'.$output_mod->trans('Import details').'</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div>
                        '.$res.'
                    </div>
                </div>
                <div class="modal-footer">
                    <input class="btn btn-secondary" data-bs-dismiss="modal" type="button" value="Cancel" />
                </div>
            </div>
        </div>
    </div>';
}}
