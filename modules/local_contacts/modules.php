<?php

/**
 * Local contact modules
 * @package modules
 * @subpackage local_contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_process_add_contact_from_message extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_source', 'contact_value'));
        if (!$success) {
            return;
        }
        list($type, $source) = explode(':', $form['contact_source']);
        if ($type == 'local' && $source == 'local') {
            $addresses = Hm_Address_Field::parse($form['contact_value']);
            if (!empty($addresses)) {
                $contacts = $this->get('contact_store');
                foreach ($addresses as $vals) {
                    $contacts->add_contact(array('source' => 'local', 'email_address' => $vals['email'], 'display_name' => $vals['name'], 'group' => isset($vals['contact_group']) ? $vals['contact_group'] : 'Personal Addresses'));
                }
                Hm_Msgs::add('Contact Added');
            }
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_process_delete_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($success, $form) = $this->process_form(array('contact_type', 'contact_source', 'contact_id'));
        if ($success && $form['contact_type'] == 'local' && $form['contact_source'] == 'local') {
            if ($contacts->delete($form['contact_id'])) {
                $this->out('contact_deleted', 1);
                Hm_Msgs::add('Contact Deleted');
            }
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_process_add_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($success, $form) = $this->process_form(array('contact_source', 'contact_email', 'contact_name'));
        if ($success && $form['contact_source'] == 'local:local') {
            $details = array('source' => 'local', 'email_address' => $form['contact_email'], 'display_name' => $form['contact_name']);
            if (array_key_exists('contact_phone', $this->request->post) && $this->request->post['contact_phone']) {
                $details['phone_number'] = $this->request->post['contact_phone'];
            }
            if (array_key_exists('contact_group', $this->request->post) && $this->request->post['contact_group']) {
                $details['group'] = $this->request->post['contact_group'];
            }
            else {
                $details['group'] = 'Personal Addresses';
            }
            $contacts->add_contact($details);
            Hm_Msgs::add('Contact Added');
            $this->out('contact_added', 1);
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_process_import_contact extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_source'));
        if ($success && $form['contact_source'] == 'csv') {
            $file = $this->request->files['contact_csv'];
            $csv = fopen($file['tmp_name'], 'r');
            if ($csv) {
                $contacts = $this->get('contact_store');
                $header = fgetcsv($csv);
                $expectedHeader = array('display_name', 'email_address', 'phone_number');

                if ($header !== $expectedHeader) {
                    fclose($csv);
                    Hm_Msgs::add('Invalid CSV file, please use a valid header: '.implode(', ', $expectedHeader), 'danger');
                    return;
                }

                $contact_list = $contacts->getAll();
                $message = '';
                $update_count = 0;
                $create_count = 0;
                $invalid_mail_count = 0;
                $import_result = [];


                while (($data = fgetcsv($csv)) !== FALSE) {
                    $single_contact = [
                        'display_name' => $data[0],
                        'email_address' => $data[1],
                        'phone_number' => $data[2] ?? ''
                    ];
                    $email = $data[1];
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $single_contact['status'] = 'invalid email';
                        array_push($import_result, $single_contact);
                        $invalid_mail_count++;
                        continue;
                    }

                    $details = array('source' => 'local', 'display_name' => $data[0], 'email_address' => $email);
                    if (array_key_exists(2, $data) && $data[2]) {
                        $details['phone_number'] = $data[2];
                    }

                    $contactUpdated = false;
                    foreach ($contact_list as $key => $contact) {
                        if ($contact->value('email_address') == $email) {
                            $contacts->update_contact($key, $details);
                            $single_contact['status'] = 'update';
                            array_push($import_result, $single_contact);
                            $update_count++;
                            $contactUpdated = true;
                            continue 2;
                        }
                    }

                    if (!$contactUpdated) {
                        $contacts->add_contact($details);
                        $single_contact['status'] = 'new';
                        array_push($import_result, $single_contact);
                        $create_count++;
                    }
                }
                fclose($csv);
                $contacts->save();
                $this->session->record_unsaved('Contact Created');
                $type = 'danger';
                if (isset($import_result) && (!$create_count && !$update_count)) {
                    $message = $create_count.' contacts created, '.$update_count.' contacts updated, '.$invalid_mail_count.' Invalid email address';
                    $type = 'warning';
                } elseif (isset($import_result) && ($create_count || $update_count)) {
                    $message = $create_count.' contacts created, '.$update_count.' contacts updated, '.$invalid_mail_count.' Invalid email address';
                    $type = 'success';
                } else {
                    $message = 'An error occurred';
                }

                $this->session->set('imported_contact', $import_result);
                Hm_Msgs::add($message, $type);
                $this->out('contacts_imported', $create_count + $update_count);
            }
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_process_edit_contact extends Hm_Handler_Module {
    public function process() {
        $contacts = $this->get('contact_store');
        list($success, $form) = $this->process_form(array('contact_source', 'contact_id', 'contact_email', 'contact_name'));
        if ($success && $form['contact_source'] == 'local:local') {
            $details = array('email_address' => $form['contact_email'], 'display_name' => $form['contact_name']);
            if (array_key_exists('contact_phone', $this->request->post)) {
                $details['phone_number'] = $this->request->post['contact_phone'];
            }
            if (array_key_exists('contact_group', $this->request->post)) {
                $details['group'] = $this->request->post['contact_group'];
            }
            else {
                $details['group'] = 'Personal Addresses';
            }
            if ($contacts->update_contact($form['contact_id'], $details)) {
                Hm_Msgs::add('Contact Updated');
                $this->out('contact_updated', 1);
            }
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_load_edit_contact extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('contact_source', $this->request->get) && $this->request->get['contact_source'] == 'local'
            && array_key_exists('contact_type', $this->request->get) && $this->request->get['contact_type'] == 'local' &&
            array_key_exists('contact_id', $this->request->get)) {
            $contacts = $this->get('contact_store');
            $contact = $contacts->get($this->request->get['contact_id']);
            if (is_object($contact)) {
                $current = $contact->export();
                $current['id'] = $this->request->get['contact_id'];
                $this->out('current_contact', $current);
            }
        }
    }
}

/**
 * @subpackage local_contacts/handler
 */
class Hm_Handler_load_local_contacts extends Hm_Handler_Module {
    public function process() {
        $this->append('contact_sources', 'local');
        $this->append('contact_edit', 'local:local');
    }
}

/**
 * @subpackage local_contacts/output
 */
class Hm_Output_contacts_form extends Hm_Output_Module {
    protected function output() {
        $current = $this->get('current_contact', array());
        $is_edit = !empty($current);
        
        // Build the modal
        $res = '<div class="modal fade" id="localContactModal" tabindex="-1" aria-labelledby="localContactModalLabel" aria-hidden="true">';
        $res .= '<div class="modal-dialog modal-dialog-centered modal-lg">';
        $res .= '<div class="modal-content custom-modal-content">';
        
        // Modal Header
        $res .= '<div class="modal-header custom-modal-header">';
        $res .= '<h5 class="modal-title d-flex align-items-center" id="localContactModalLabel">';
        $res .= '<div class="modal-icon-wrapper me-2">';
        $res .= '<i class="bi bi-person-plus" style="font-size: 24px;"></i>';
        $res .= '</div>';
        $res .= $is_edit ? $this->trans('Edit Local Contact') : $this->trans('Add Local Contact');
        $res .= '</h5>';
        $res .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'; 
        $res .= '</div>';

        // Modal Body
        $res .= '<div class="modal-body custom-modal-body">';
        
        // Toggle buttons for Manual vs CSV (only show for new contacts, not edit)
        if (!$is_edit) {
            $res .= '<div class="contact-method-toggle">';
            $res .= '<button type="button" class="method-btn active" id="manual-entry-btn">';
            $res .= '<i class="bi bi-person-plus" style="width: 18px; height: 18px;"></i>';
            $res .= '<span>' . $this->trans('Manual Entry') . '</span>';
            $res .= '</button>';
            $res .= '<button type="button" class="method-btn" id="csv-import-btn">';
            $res .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $res .= '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>';
            $res .= '<polyline points="14 2 14 8 20 8"/>';
            $res .= '<line x1="12" y1="18" x2="12" y2="12"/>';
            $res .= '<line x1="9" y1="15" x2="15" y2="15"/>';
            $res .= '</svg>';
            $res .= '<span>' . $this->trans('Import CSV') . '</span>';
            $res .= '</button>';
            $res .= '</div>';
        }

        // Get current values for edit mode
        $email = isset($current['email_address']) ? $this->html_safe($current['email_address']) : '';
        $name = isset($current['display_name']) ? $this->html_safe($current['display_name']) : '';
        $phone = isset($current['phone_number']) ? $this->html_safe($current['phone_number']) : '';
        $group = isset($current['group']) ? $this->html_safe($current['group']) : 'Personal Addresses';

        // Manual Entry Form
        $res .= '<form class="contact-manual-form" id="manual-contact-form" method="POST">';
        $res .= '<input type="hidden" name="contact_source" value="local" />';
        $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
        
        if ($is_edit) {
            $res .= '<input type="hidden" name="contact_id" value="'.$this->html_safe($current['id']).'" />';
        }
        
        $res .= '<div class="row">';
        $res .= '<div class="col-md-6 mb-3">';
        $res .= '<label for="contact_name" class="form-label">';
        $res .= $this->trans('Display Name') . ' <span class="text-danger">*</span>';
        $res .= '</label>';
        $res .= '<input type="text" class="form-control custom-input" id="contact_name" name="contact_name" placeholder="John Doe" value="'.$name.'" required>';
        $res .= '</div>';
        $res .= '<div class="col-md-6 mb-3">';
        $res .= '<label for="contact_email" class="form-label">';
        $res .= $this->trans('Email Address') . ' <span class="text-danger">*</span>';
        $res .= '</label>';
        $res .= '<input type="email" class="form-control custom-input" id="contact_email" name="contact_email" placeholder="john.doe@example.com" value="'.$email.'" required>';
        $res .= '</div>';
        $res .= '</div>';
        $res .= '<div class="mb-3">';
        $res .= '<label for="contact_phone" class="form-label">';
        $res .= $this->trans('Phone Number');
        $res .= '</label>';
        $res .= '<input type="tel" class="form-control custom-input" id="contact_phone" name="contact_phone" placeholder="+1 234 567 8900" value="'.$phone.'">';
        $res .= '</div>';
        $res .= '<div class="mb-3">';
        $res .= '<label for="contact_group" class="form-label">';
        $res .= $this->trans('Category');
        $res .= '</label>';
        $res .= '<select class="form-select custom-input" id="contact_group" name="contact_group">';
        $res .= '<option value="Collected Recipients"'.($group == 'Collected Recipients' ? ' selected' : '').'>' . $this->trans('Collected Recipients') . '</option>';
        $res .= '<option value="Trusted Senders"'.($group == 'Trusted Senders' ? ' selected' : '').'>' . $this->trans('Trusted Senders') . '</option>';
        $res .= '<option value="Personal Addresses"'.($group == 'Personal Addresses' ? ' selected' : '').'>' . $this->trans('Personal Addresses') . '</option>';
        $res .= '</select>';
        $res .= '</div>';
        $res .= '</form>';

        // CSV Import Section (hidden by default, only for new contacts)
        if (!$is_edit) {
            $csv_sample_path = WEB_ROOT.'modules/local_contacts/assets/data/contact_sample.csv';
            
            $res .= '<div class="csv-import-section" style="display: none;">';
            $res .= '<form id="csv-import-form" method="POST" enctype="multipart/form-data">';
            $res .= '<input type="hidden" name="contact_source" value="csv" />';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            
            $res .= '<div class="csv-info-card">';
            $res .= '<div class="csv-info-icon">';
            $res .= '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $res .= '<circle cx="12" cy="12" r="10"/>';
            $res .= '<line x1="12" y1="16" x2="12" y2="12"/>';
            $res .= '<line x1="12" y1="8" x2="12.01" y2="8"/>';
            $res .= '</svg>';
            $res .= '</div>';
            $res .= '<div>';
            $res .= '<h6 class="csv-info-title">' . $this->trans('CSV Format Requirements') . '</h6>';
            $res .= '<p class="csv-info-text">';
            $res .= $this->trans('Your CSV file must include headers') . ': <strong>display_name</strong>, <strong>email_address</strong>, <strong>phone_number</strong>';
            $res .= '</p>';
            $res .= '<a href="'.$csv_sample_path.'" class="csv-download-link" download data-external="true">';
            $res .= $this->trans('Download sample CSV file');
            $res .= '</a>';
            $res .= '</div>';
            $res .= '</div>';
            $res .= '<div class="csv-upload-area">';
            $res .= '<input type="file" id="contact_csv" name="contact_csv" accept=".csv" class="csv-file-input" required>';
            $res .= '<label for="contact_csv" class="csv-upload-label">';
            $res .= '<div class="csv-upload-icon">';
            $res .= '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $res .= '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>';
            $res .= '<polyline points="17 8 12 3 7 8"/>';
            $res .= '<line x1="12" y1="3" x2="12" y2="15"/>';
            $res .= '</svg>';
            $res .= '</div>';
            $res .= '<p class="csv-upload-text">';
            $res .= '<strong>' . $this->trans('Click to upload') . '</strong> ' . $this->trans('or drag and drop');
            $res .= '</p>';
            $res .= '<p class="csv-upload-hint">' . $this->trans('CSV files only') . '</p>';
            $res .= '</label>';
            $res .= '</div>';
            $res .= '</form>';
            $res .= '</div>';
        }
        
        $res .= '</div>';

        // Modal Footer
        $res .= '<div class="modal-footer custom-modal-footer">';
        $res .= '<button type="button" class="btn btn-secondary custom-btn-secondary" data-bs-dismiss="modal">';
        $res .= $this->trans('Cancel');
        $res .= '</button>';
        $res .= '<button type="submit" class="btn btn-primary custom-btn-primary" id="submit-local-contact-btn">';
        $res .= $is_edit ? $this->trans('Update Contact') : $this->trans('Add Contact');
        $res .= '</button>';
        $res .= '</div>';
        
        $res .= '</div>';
        $res .= '</div>';
        $res .= '</div>';
        
        return $res;
    }
}

/**
 * @subpackage import_local_contacts/output
 * This class is now deprecated as CSV import is integrated into Hm_Output_contacts_form
 */
class Hm_Output_import_contacts_form extends Hm_Output_Module {
    protected function output() {
        // This output module is no longer needed as CSV import is now part of the main contact modal
        // Keeping it for backward compatibility but it outputs nothing
        return '';
    }
}
