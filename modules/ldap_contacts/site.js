'use strict';

var delete_ldap_contact = function(id, source, type, ldap_dn) {
    if (!hm_delete_prompt()) {
        return false;
    }
    var request_data = [
        {'name': 'hm_ajax_hook', 'value': 'ajax_delete_contact'},
        {'name': 'contact_id', 'value': id},
        {'name': 'contact_type', 'value': type},
        {'name': 'contact_source', 'value': source},
        {'name': 'ldap_dn', 'value': ldap_dn}
    ];
    
    Hm_Ajax.request(
        request_data,
        function(res) {
            if (res.contact_deleted && res.contact_deleted === 1) {
                $('.contact_row_'+id).remove();
            }
        }
    );
};

$(function() {
    $('.ldap_password_change').on("click", function() {
        $(this).prev().prop('disabled', false);
        $(this).prev().attr('placeholder', '');
    });

    function toggleUsernameField() {
        var uidattrSelect = $('#ldap_uidattr');
        var usernameFieldWrapper = $('#ldap_uid_field_wrapper');
        var usernameField = $('#ldap_uid');
        var uidattr = uidattrSelect.val();
        
        if (uidattr === 'uid') {
            usernameFieldWrapper.removeClass('d-none');
            usernameField.prop('required', true);
        } else {
            usernameFieldWrapper.addClass('d-none');
            usernameField.prop('required', false);
            usernameField.val('');
        }
    }

    $(document).ready(function() {
        toggleUsernameField();
    });
    
    $(document).on('change', '#ldap_uidattr', toggleUsernameField);

    $(document).on('click', '.delete_contact[data-ldap-dn]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var contact = $(this);
        var contactId = contact.data('id');
        var contactType = contact.data('type');
        var contactSource = contact.data('source');
        var ldapDn = contact.data('ldap-dn');
        
        if (!contactId || !contactType || !contactSource || !ldapDn) {
            console.error('Missing contact data for LDAP deletion');
            return false;
        }
        
        delete_ldap_contact(contactId, contactSource, contactType, ldapDn);
        return false;
    });

    function enhanceLdapContacts() {
        if (typeof window.cypht_ldap_contacts === 'undefined') {
            return;
        }

        Object.keys(window.cypht_ldap_contacts).forEach(function(contactId) {
            var data = window.cypht_ldap_contacts[contactId];
            var row = document.querySelector('.contact_row_' + contactId);
            
            if (!row) return;

            var editUrl = '?page=contacts&contact_id=' + encodeURIComponent(contactId) +
                         '&contact_source=' + encodeURIComponent(data.source) +
                         '&contact_type=' + encodeURIComponent(data.type) +
                         '&contact_page=' + data.current_page +
                         '&dn=' + data.encoded_dn;

            var sendToUrl = '?page=compose&contact_id=' + encodeURIComponent(contactId) +
                           '&contact_source=' + encodeURIComponent(data.source) +
                           '&contact_type=' + encodeURIComponent(data.type) +
                           '&dn=' + data.encoded_dn;

            var editLink = row.querySelector('a.edit_contact');
            if (editLink) {
                editLink.href = editUrl;
            }

            var sendLink = row.querySelector('a.send_to_contact');
            if (sendLink) {
                sendLink.href = sendToUrl;
            }

            var deleteBtn = row.querySelector('a.delete_contact');
            if (deleteBtn) {
                deleteBtn.setAttribute('data-ldap-dn', data.dn);
            }
        });
    }

    var initLdapContactModal = function() {
        $('#submit-ldap-contact-btn').on('click', function(e) {
            e.preventDefault();
            
            var firstName = $('#ldap_first_name').val();
            var lastName = $('#ldap_last_name').val();
            var email = $('#ldap_mail').val();
            
            if (!firstName || !lastName || !email) {
                //TODO: Use better error display
                alert('Please fill in the required fields (First Name, Last Name, and Email)');
                return;
            }
            //TODO: implement: validation for other fields as needed and ajax_add_ldap_contact
            var formData = [
                {'name': 'hm_ajax_hook', 'value': 'ajax_add_ldap_contact'},
                {'name': 'contact_source', 'value': 'ldap'},
                {'name': 'ldap_source', 'value': $('#ldap_source').val()},
                {'name': 'ldap_first_name', 'value': firstName},
                {'name': 'ldap_last_name', 'value': lastName},
                {'name': 'ldap_mail', 'value': email},
                {'name': 'ldap_displayname', 'value': $('#ldap_displayname').val()},
                {'name': 'ldap_uidattr', 'value': $('#ldap_uidattr').val()},
                {'name': 'ldap_uid', 'value': $('#ldap_uid').val()},
                {'name': 'ldap_locality', 'value': $('#ldap_locality').val()},
                {'name': 'ldap_state', 'value': $('#ldap_state').val()},
                {'name': 'ldap_street', 'value': $('#ldap_street').val()},
                {'name': 'ldap_postalcode', 'value': $('#ldap_postalcode').val()},
                {'name': 'ldap_title', 'value': $('#ldap_title').val()},
                {'name': 'ldap_phone', 'value': $('#ldap_phone').val()},
                {'name': 'ldap_fax', 'value': $('#ldap_fax').val()},
                {'name': 'ldap_mobile', 'value': $('#ldap_mobile').val()},
                {'name': 'ldap_room', 'value': $('#ldap_room').val()},
                {'name': 'ldap_car', 'value': $('#ldap_car').val()},
                {'name': 'ldap_org', 'value': $('#ldap_org').val()},
                {'name': 'ldap_org_unit', 'value': $('#ldap_org_unit').val()},
                {'name': 'ldap_org_dpt', 'value': $('#ldap_org_dpt').val()},
                {'name': 'ldap_emp_num', 'value': $('#ldap_emp_num').val()},
                {'name': 'ldap_emp_type', 'value': $('#ldap_emp_type').val()},
                {'name': 'ldap_lang', 'value': $('#ldap_lang').val()},
                {'name': 'ldap_uri', 'value': $('#ldap_uri').val()}
            ];
            
            Hm_Ajax.request(
                formData,
                function(res) {
                    if (res.contact_added) {
                        const modalElement = document.getElementById('ldapContactModal');
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }
                        window.location.reload();
                    } else {
                        if (res.error_message) {
                            //TODO: Use better error display
                            alert(res.error_message);
                        }
                    }
                }
            );
        });

        $('#ldapContactModal').on('hidden.bs.modal', function() {
            $('#ldap-contact-form')[0].reset();
        });

        $('#ldap_uidattr').on('change', function() {
            if ($(this).val() === 'uid') {
                $('#ldap_uid_field_wrapper').removeClass('d-none');
            } else {
                $('#ldap_uid_field_wrapper').addClass('d-none');
            }
        });
    };

    $(document).ready(function() {
        enhanceLdapContacts();
        initLdapContactModal();
    });
});
