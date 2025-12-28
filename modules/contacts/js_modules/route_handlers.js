function applyContactsPageHandlers() {
    // Validate contact form on submit
    $('.add_contact_form').on('submit', function(e) {
        var emailField = $('#contact_email');
        var email = emailField.val();
        
        if (email && Hm_Utils.is_valid_email(email)) {
            e.preventDefault();
            e.stopPropagation();
            emailField.focus();
            Hm_Notices.show(hm_trans('Invalid email address. Please use a valid email address with a proper domain (e.g., user@example.com)'), 'danger');
            return false;
        }
        return true;
    });

    // Real-time validation feedback on email field
    $('#contact_email').on('blur', function() {
        var email = $(this).val();
        if (email && !Hm_Utils.is_valid_email(email)) {
            $(this).addClass('is-invalid');
            if ($(this).next('.invalid-feedback').length === 0) {
                $(this).after('<div class="invalid-feedback">' + hm_trans('Please enter a valid email address with a proper domain (e.g., user@example.com)') + '</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });

    // Remove invalid feedback on input
    $('#contact_email').on('input', function() {
        if ($(this).hasClass('is-invalid')) {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });

    $('.delete_contact:not([data-ldap-dn])').on("click", function() {
        delete_contact($(this).data('id'), $(this).data('source'), $(this).data('type'));
        return false;
    });
    $('.show_contact').on("click", function() {
        $('#'+$(this).data('id')).toggle();
        return false;
    });
    $('.reset_contact').on("click", function() {
        Hm_Utils.redirect('?page=contacts');
    });
    $('.server_title').on("click", function() {
        $(this).next().toggle();
    });
    $('#contact_phone').on("keyup", function() {
        let contact_phone = $('#contact_phone').val();
        const regex_number = new RegExp('^\\d+$');
        const allowed_characters = ['+','-','(',')'];
        for (let chain_counter = 0; chain_counter < contact_phone.length; chain_counter++) {
            if(!(regex_number.test(contact_phone[chain_counter])) && !(allowed_characters.indexOf(contact_phone[chain_counter]) > -1)){
                Hm_Notices.show("This phone number appears to contain invalid character (s).\nIf you are sure ignore this warning and continue!", "warning");
                $(this).off();
            }
        }

    });
    $('.source_link').on("click", function () {
        $('.list_actions').toggle(); $('#list_controls_menu').hide();
        return false;
    });
    contact_import_pagination();
}

function applyContactsAutocompleteComposePageHandlers() {
    $('.compose_to').on('keyup', function(e) { autocomplete_contact(e, '.compose_to', '#to_contacts'); });
    $('.compose_cc').on('keyup', function(e) { autocomplete_contact(e, '.compose_cc', '#cc_contacts'); });
    $('.compose_bcc').on('keyup', function(e) { autocomplete_contact(e, '.compose_bcc', '#bcc_contacts'); });
}