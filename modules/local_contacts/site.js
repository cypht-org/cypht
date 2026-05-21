'use strict';

var isSubmitting = false;

var validateLocalForm = function() {
    hm_clear_form_errors('#manual-contact-form');
    var valid = true;

    var name  = ($('#contact_name').val() || '').trim();
    var email = ($('#contact_email').val() || '').trim();
    var phone = ($('#contact_phone').val() || '').trim();

    if (!name) {
        hm_show_field_error('contact_name', 'Name is required.');
        valid = false;
    } else if (!Hm_Utils.is_valid_name(name, 2, 100)) {
        hm_show_field_error('contact_name', 'Name must be 2–100 characters and contain only letters, numbers, spaces, hyphens, or apostrophes.');
        valid = false;
    }

    if (!email) {
        hm_show_field_error('contact_email', 'Email address is required.');
        valid = false;
    } else if (!Hm_Utils.is_valid_email(email)) {
        hm_show_field_error('contact_email', 'Please enter a valid email address (e.g. user@example.com).');
        valid = false;
    }

    if (phone && !Hm_Utils.is_valid_phone(phone)) {
        hm_show_field_error('contact_phone', 'Please enter a valid phone number (e.g. +1 555 123 4567).');
        valid = false;
    }

    return valid;
};

var initLocalContactModal = function() {
    // Remove existing event handlers to avoid duplicates
    $('#manual-entry-btn').off('click');
    $('#csv-import-btn').off('click');
    $('#submit-local-contact-btn').off('click');
    $('#localContactModal').off('hidden.bs.modal');
    
    $('#manual-entry-btn').on('click', function() {
        $(this).addClass('active');
        $('#csv-import-btn').removeClass('active');
        $('.contact-manual-form').show();
        $('.csv-import-section').hide();
        $('#submit-local-contact-btn').text('Add Contact');
    });

    $('#csv-import-btn').on('click', function() {
        $(this).addClass('active');
        $('#manual-entry-btn').removeClass('active');
        $('.contact-manual-form').hide();
        $('.csv-import-section').show();
        $('#submit-local-contact-btn').text('Import Contacts');
    });

    $('#submit-local-contact-btn').on('click', function(e) {
        e.preventDefault();
        
        // Prevent multiple submissions
        if (isSubmitting) {
            return;
        }
        
        var contactId = $('input[name="contact_id"]').val();
        var isEdit = contactId && contactId.length > 0;
        
        if ($('#manual-entry-btn').hasClass('active') || isEdit) {
            if (!validateLocalForm()) {
                return;
            }

            var name = ($('#contact_name').val() || '').trim();
            var email = ($('#contact_email').val() || '').trim();
            var phone = ($('#contact_phone').val() || '').trim();
            var category = $('#contact_group').val();

            isSubmitting = true;
            var buttonText = isEdit ? 'Updating...' : 'Adding...';
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' + buttonText);
            
            var ajaxData = [
                {'name': 'hm_ajax_hook', 'value': isEdit ? 'ajax_update_contact' : 'ajax_add_contact'},
                {'name': 'contact_name', 'value': name},
                {'name': 'contact_email', 'value': email},
                {'name': 'contact_phone', 'value': phone},
                {'name': 'contact_group', 'value': category},
                {'name': 'contact_source', 'value': 'local:local'}
            ];
            
            if (isEdit) {
                ajaxData.push({'name': 'contact_id', 'value': contactId});
            }
            
            Hm_Ajax.request(
                ajaxData,
                function(res) {
                    var isSuccess = Hm_Ajax.has_success(res);

                    $('#submit-local-contact-btn').prop('disabled', false).text(isEdit ? 'Update Contact' : 'Add Contact');
                    isSubmitting = false;

                    if (isSuccess) {
                        var modalEl = document.getElementById('localContactModal');
                        if (modalEl) {
                            modalEl.addEventListener('hidden.bs.modal', hm_redirect_to_contacts, { once: true });
                            Hm_Modal.hide('localContactModal');
                        } else {
                            hm_redirect_to_contacts();
                        }
                    }
                },
                [],
                false,
                function() {
                    $('#submit-local-contact-btn').prop('disabled', false).text(isEdit ? 'Update Contact' : 'Add Contact');
                    isSubmitting = false;
                }
            );
        } else {
            // CSV import - submit form normally (no AJAX)
            var form = $('#csv-import-form')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Importing...');
            $('#csv-upload-progress').show();
            form.submit();
        }
    });

    hm_init_field_error_clearing('#manual-contact-form');

    $('#localContactModal').on('hidden.bs.modal', function() {
        hm_clear_form_errors('#manual-contact-form');
        isSubmitting = false;
        $('#csv-upload-progress').hide();
        $('#manual-contact-form')[0].reset();
        $('#manual-entry-btn').addClass('active');
        $('#csv-import-btn').removeClass('active');
        $('.contact-manual-form').show();
        $('.csv-import-section').hide();
        $('#submit-local-contact-btn').text('Add Contact');

        if (window.location.search.indexOf('open_modal=') !== -1) {
            hm_remove_url_params(['open_modal', 'contact_id', 'contact_source', 'contact_type']);
        }
    });
};

$(document).ready(function() {
    initLocalContactModal();
});

$(document).on('change', '#contact_csv', function(e) {
    const fileName = e.target.files[0]?.name;
    if (fileName) {
        const label = $(this).siblings('.csv-upload-label');
        label.find('.csv-upload-text strong').text(fileName);
        label.find('.csv-upload-hint').text(hm_trans('Click to change file'));
        label.addClass('file-selected');
    }
});

$(document).on('dragover', '.csv-upload-label', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass('drag-over');
});

$(document).on('dragleave', '.csv-upload-label', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('drag-over');
});

$(document).on('drop', '.csv-upload-label', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('drag-over');
    
    const files = e.originalEvent.dataTransfer.files;
    if (files.length > 0 && files[0].name.endsWith('.csv')) {
        $('#contact_csv')[0].files = files;
        $('#contact_csv').trigger('change');
    }
});
