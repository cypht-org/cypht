'use strict';

var isLdapSubmitting = false;

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
        $('#submit-ldap-contact-btn').off('click');
        $('#ldapContactModal').off('hidden.bs.modal');

        $('#submit-ldap-contact-btn').on('click', function(e) {
            e.preventDefault();

            if (isLdapSubmitting) {
                return;
            }

            var isEdit = $('#ldap-contact-form input[name="update_ldap_contact"]').length > 0;
            var firstName = $('#ldap_first_name').val();
            var lastName = $('#ldap_last_name').val();
            var email = $('#ldap_mail').val();

            if (!firstName || !lastName || !email) {
                alert('Please fill in the required fields (First Name, Last Name, and Email)');
                return;
            }

            isLdapSubmitting = true;
            var buttonText = isEdit ? 'Updating...' : 'Adding...';
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' + buttonText);

            var formData = $('#ldap-contact-form').serializeArray();
            formData.push({'name': 'hm_ajax_hook', 'value': isEdit ? 'ajax_update_contact' : 'ajax_add_contact'});

            Hm_Ajax.request(
                formData,
                function(res) {
                    var isSuccess = false;
                    if (res.router_user_msgs) {
                        for (var key in res.router_user_msgs) {
                            if (res.router_user_msgs[key].type === 'success') {
                                isSuccess = true;
                                break;
                            }
                        }
                    }

                    $('#submit-ldap-contact-btn').prop('disabled', false).text(isEdit ? 'Update' : 'Add');
                    isLdapSubmitting = false;

                    if (isSuccess) {
                        var modalElement = document.getElementById('ldapContactModal');
                        var modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }

                        var activeTab = $('.category-tab.active').data('target');
                        var redirectUrl = '?page=contacts';
                        if (activeTab) {
                            redirectUrl += '&active_tab=' + activeTab;
                        }
                        window.location.href = redirectUrl;
                    }
                },
                [],
                false,
                function() {
                    $('#submit-ldap-contact-btn').prop('disabled', false).text(isEdit ? 'Update' : 'Add');
                    isLdapSubmitting = false;
                }
            );
        });

        $('#ldapContactModal').on('hidden.bs.modal', function() {
            isLdapSubmitting = false;
            var form = document.getElementById('ldap-contact-form');
            if (form) {
                form.reset();
            }

            var search = window.location.search;
            if (search.indexOf('contact_type=ldap') !== -1) {
                var currentUrl = new URL(window.location.href);
                currentUrl.searchParams.delete('contact_id');
                currentUrl.searchParams.delete('contact_source');
                currentUrl.searchParams.delete('contact_type');
                currentUrl.searchParams.delete('contact_page');
                currentUrl.searchParams.delete('dn');
                window.history.replaceState({}, '', currentUrl.toString());
            }
        });
    };

    $(document).ready(function() {
        enhanceLdapContacts();
        initLdapContactModal();
    });
});
