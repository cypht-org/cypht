'use strict';

var isSubmitting = false;

var validateLocalForm = function() {
    hm_clear_form_errors('#manual-contact-form');
    var valid = true;

    var name  = ($('#contact_name').val() || '').trim();
    var email = ($('#contact_email').val() || '').trim();
    var phone = ($('#contact_phone').val() || '').trim();

    var namePattern  = /^[A-Za-zÀ-ÖØ-öø-ÿ'\- ]{2,100}$/;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    var phonePattern = /^\+?[\d\s\-().]{7,20}$/;

    if (!name) {
        hm_show_field_error('contact_name', 'Name is required.');
        valid = false;
    } else if (!namePattern.test(name)) {
        hm_show_field_error('contact_name', 'Name must be 2–100 characters and contain only letters, spaces, hyphens, or apostrophes.');
        valid = false;
    }

    if (!email) {
        hm_show_field_error('contact_email', 'Email address is required.');
        valid = false;
    } else if (!emailPattern.test(email)) {
        hm_show_field_error('contact_email', 'Please enter a valid email address (e.g. user@example.com).');
        valid = false;
    }

    if (phone && !phonePattern.test(phone)) {
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
                    console.log(res);
                    
                    var isSuccess = false;
                    if (res.router_user_msgs) {
                        for (var key in res.router_user_msgs) {
                            if (res.router_user_msgs[key].type === 'success') {
                                isSuccess = true;
                                break;
                            }
                        }
                    }
                    
                    $('#submit-local-contact-btn').prop('disabled', false).text(isEdit ? 'Update Contact' : 'Add Contact');
                    isSubmitting = false;
                    
                    if (isSuccess) {
                        const modalElement = document.getElementById('localContactModal');
                        const modal = bootstrap.Modal.getInstance(modalElement);
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
            
            // Submit the form normally
            form.submit();
        }
    });

    $('#manual-contact-form').on('input change', '.is-invalid', function() {
        $(this).removeClass('is-invalid');
        $(this).next('.invalid-feedback').remove();
    });

    $('#localContactModal').on('hidden.bs.modal', function() {
        hm_clear_form_errors('#manual-contact-form');
        isSubmitting = false;
        $('#manual-contact-form')[0].reset();
        $('#manual-entry-btn').addClass('active');
        $('#csv-import-btn').removeClass('active');
        $('.contact-manual-form').show();
        $('.csv-import-section').hide();
        $('#submit-local-contact-btn').text('Add Contact');
        
        // Clean URL parameters related to modal if present
        if (window.location.search.indexOf('open_modal=') !== -1) {
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('open_modal');
            currentUrl.searchParams.delete('contact_id');
            currentUrl.searchParams.delete('contact_source');
            currentUrl.searchParams.delete('contact_type');
            window.history.replaceState({}, '', currentUrl.toString());
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
