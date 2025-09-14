'use strict';

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
});
