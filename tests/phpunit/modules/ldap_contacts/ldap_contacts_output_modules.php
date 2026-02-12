<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Ldap_Contacts_Output_Modules extends TestCase {
    public function setUp(): void {
        require __DIR__.'/../../helpers.php';
        require APP_PATH.'modules/contacts/hm-contacts.php';
        require APP_PATH.'modules/ldap_contacts/modules.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_contact_form_end() {
        $test = new Output_Test('ldap_contact_form_end', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array("</div></div></div>"), $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_first_name() {
        $test = new Output_Test('ldap_form_first_name', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_first_name" class="form-label">First Name <span class="text-danger">*</span></label><input required placeholder="First Name" id="ldap_first_name" type="text" name="ldap_first_name" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_submit() {
        $test = new Output_Test('ldap_form_submit', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('</div></div><input name="add_ldap_contact" type="hidden" value="Add" class="btn btn-primary custom-btn-primary me-1" /></form></div><div class="modal-footer custom-modal-footer"><button type="button" class="reset_contact btn btn-secondary custom-btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary custom-btn-primary" id="submit-ldap-contact-btn">Add</button></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_last_name() {
        $test = new Output_Test('ldap_form_last_name', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_last_name" class="form-label">Last Name <span class="text-danger">*</span></label><input required placeholder="Last Name" id="ldap_last_name" type="text" name="ldap_last_name" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_title() {
        $test = new Output_Test('ldap_form_title', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-4 mb-3"><label for="ldap_title" class="form-label">Title</label><input placeholder="Title" id="ldap_title" type="text" name="ldap_title" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_contact_form_start() {
        $test = new Output_Test('ldap_contact_form_start', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true,'ldap_sources' => array('Personal'));
        $res = $test->run();
        $this->assertEquals(array('<div class="modal fade" id="ldapContactModal" tabindex="-1" aria-labelledby="ldapContactModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content custom-modal-content"><div class="modal-header custom-modal-header"><h5 class="modal-title d-flex align-items-center" id="ldapContactModalLabel"><div class="modal-icon-wrapper me-2"><i class="bi bi-person-plus-fill" style="font-size: 24px;"></i></div>Add LDAP</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body custom-modal-body"><form class="ldap-contact-form" id="ldap-contact-form" method="POST" action=""><input type="hidden" name="hm_page_key" value="" /><input type="hidden" name="contact_source" value="ldap" /><div class="form-section mb-4"><h6 class="form-section-title">Basic Information</h6><div class="row"><div class="col-md-6 mb-3"><label for="ldap_source" class="form-label">Source</label><select id="ldap_source" name="ldap_source" class="form-select custom-input"><option value="Personal">Personal</option></select></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_displayname() {
        $test = new Output_Test('ldap_form_displayname', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_displayname" class="form-label">Display Name</label><input placeholder="Display Name" id="ldap_displayname" type="text" name="ldap_displayname" value="" class="form-control custom-input"></div></div></div><div class="form-section mb-4"><h6 class="form-section-title">Contact Information</h6><div class="row">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_uidattr() {
        $test = new Output_Test('ldap_form_uidattr', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_uidattr" class="form-label">UID Attribute</label><select id="ldap_uidattr" name="ldap_uidattr" class="form-select custom-input"><option value="cn">cn</option><option value="uid">uid</option></select></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_dn_display() {
        $test = new Output_Test('ldap_form_dn_display', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('ldap_edit' => true), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_mail() {
        $test = new Output_Test('ldap_form_mail', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_mail" class="form-label">E-mail Address <span class="text-danger">*</span></label><input required placeholder="E-mail Address" id="ldap_mail" type="email" name="ldap_mail" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_phone() {
        $test = new Output_Test('ldap_form_phone', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-4 mb-3"><label for="ldap_phone" class="form-label">Telephone Number</label><input placeholder="Telephone Number" id="ldap_phone" type="text" name="ldap_phone" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_fax() {
        $test = new Output_Test('ldap_form_fax', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-4 mb-3"><label for="ldap_fax" class="form-label">Fax Number</label><input placeholder="Fax Number" id="ldap_fax" type="text" name="ldap_fax" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_mobile() {
        $test = new Output_Test('ldap_form_mobile', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-4 mb-3"><label for="ldap_mobile" class="form-label">Mobile Number</label><input placeholder="Mobile Number" id="ldap_mobile" type="text" name="ldap_mobile" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_room() {
        $test = new Output_Test('ldap_form_room', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-4 mb-3"><label for="ldap_room" class="form-label">Room Number</label><input placeholder="Room Number" id="ldap_room" type="text" name="ldap_room" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_car() {
        $test = new Output_Test('ldap_form_car', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_car" class="form-label">License Plate Number</label><input placeholder="License Plate Number" id="ldap_car" type="text" name="ldap_car" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_org() {
        $test = new Output_Test('ldap_form_org', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_org" class="form-label">Organization</label><input placeholder="Organization" id="ldap_org" type="text" name="ldap_org" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_org_unit() {
        $test = new Output_Test('ldap_form_org_unit', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_org_unit" class="form-label">Organization Unit</label><input placeholder="Organization Unit" id="ldap_org_unit" type="text" name="ldap_org_unit" value="" class="form-control custom-input"></div>'), $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_org_dpt() {
        $test = new Output_Test('ldap_form_org_dpt', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-4 mb-3"><label for="ldap_org_dpt" class="form-label">Department Number</label><input placeholder="Department Number" id="ldap_org_dpt" type="text" name="ldap_org_dpt" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_emp_num() {
        $test = new Output_Test('ldap_form_emp_num', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_emp_num" class="form-label">Employee Number</label><input placeholder="Employee Number" id="ldap_emp_num" type="text" name="ldap_emp_num" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_emp_type() {
        $test = new Output_Test('ldap_form_emp_type', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_emp_type" class="form-label">Employment Type</label><input placeholder="Employment Type" id="ldap_emp_type" type="text" name="ldap_emp_type" value="" class="form-control custom-input"></div></div></div><div class="form-section mb-4"><h6 class="form-section-title">Additional Information</h6><div class="row">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_lang() {
        $test = new Output_Test('ldap_form_lang', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_lang" class="form-label">Language</label><input placeholder="Language" id="ldap_lang" type="text" name="ldap_lang" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_uri() {
        $test = new Output_Test('ldap_form_uri', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-12 mb-3"><label for="ldap_uri" class="form-label">Website</label><input placeholder="Website" id="ldap_uri" type="text" name="ldap_uri" value="" class="form-control custom-input"></div></div></div><div class="form-section mb-4"><h6 class="form-section-title">Address Information</h6><div class="row">'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_locality() {
        $test = new Output_Test('ldap_form_locality', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_locality" class="form-label">Locality</label><input placeholder="Locality" id="ldap_locality" type="text" name="ldap_locality" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_street() {
        $test = new Output_Test('ldap_form_street', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_street" class="form-label">Street</label><input placeholder="Street" id="ldap_street" type="text" name="ldap_street" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_state() {
        $test = new Output_Test('ldap_form_state', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        var_dump($res->output_response);
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_state" class="form-label">State</label><input placeholder="State" id="ldap_state" type="text" name="ldap_state" value="" class="form-control custom-input"></div>'), $res->output_response);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_ldap_form_postalcode() {
        $test = new Output_Test('ldap_form_postalcode', 'ldap_contacts');
        $test->handler_response = array('ldap_edit' => true);
        $res = $test->run();
        $this->assertEquals(array('<div class="col-md-6 mb-3"><label for="ldap_postalcode" class="form-label">Postal Code</label><input placeholder="Postal Code" id="ldap_postalcode" type="text" name="ldap_postalcode" value="" class="form-control custom-input"></div></div></div><div class="form-section mb-4"><h6 class="form-section-title">Organization Information</h6><div class="row">'), $res->output_response);
    }
}