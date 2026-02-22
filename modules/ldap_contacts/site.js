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

    $(document).ready(enhanceLdapContacts);

    // Validate LDAP contact email on form submit
    $('.add_contact_form').on('submit', function(e) {
        var emailField = $('#ldap_mail');
        if (emailField.length && emailField.val()) {
            var email = emailField.val();
            if (!Hm_Utils.is_valid_email(email)) {
                e.preventDefault();
                e.stopPropagation();
                emailField.focus();
                Hm_Notices.show(hm_trans('Invalid email address. Please use a valid email address with a proper domain (e.g., user@example.com)'), 'danger');
                return false;
            }
        }
        return true;
    });

    $('#ldap_mail').on('blur', function() {
        var email = $(this).val();
        if (email) {
            if (!Hm_Utils.is_valid_email(email)) {
                $(this).addClass('is-invalid');
                if ($(this).next('.invalid-feedback').length === 0) {
                    $(this).after('<div class="invalid-feedback">' + hm_trans('Please enter a valid email address with a proper domain (e.g., user@example.com)') + '</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
            }
        }
    });

    $('#ldap_mail').on('input', function() {
        if ($(this).hasClass('is-invalid')) {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
});
