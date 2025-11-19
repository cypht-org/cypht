'use strict';
var initLocalContactModal = function() {
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
        
        if ($('#manual-entry-btn').hasClass('active')) {
            var name = $('#contact_name').val();
            var email = $('#contact_email').val();
            var phone = $('#contact_phone').val();
            var category = $('#contact_group').val();
            
            if (!name || !email) {
                //TODO: Use better error display
                alert('Please fill in the required fields (Name and Email)');
                return;
            }
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Adding...');
            Hm_Ajax.request(
                [
                    {'name': 'hm_ajax_hook', 'value': 'ajax_add_contact'},
                    {'name': 'contact_name', 'value': name},
                    {'name': 'contact_email', 'value': email},
                    {'name': 'contact_phone', 'value': phone},
                    {'name': 'contact_category', 'value': category},
                    {'name': 'contact_source', 'value': 'local:local'}
                ],
                function(res) {
                    $('#submit-local-contact-btn').prop('disabled', false).text('Add Contact');
                    if (res.contact_added) {
                        const modalElement = document.getElementById('localContactModal');
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }
                        window.location.reload();
                    }
                },
                [],
                false,
                function() {
                    alert("HELLO")
                }
            );
        } else {
            var fileInput = $('#contact_csv')[0];
            if (!fileInput.files.length) {
                alert('Please select a CSV file');
                return;
            }
            //TODO: Implement CSV import functionality
            console.log('CSV import not implemented yet');
        }
    });

    $('#localContactModal').on('hidden.bs.modal', function() {
        $('#manual-contact-form')[0].reset();
        $('#manual-entry-btn').addClass('active');
        $('#csv-import-btn').removeClass('active');
        $('.contact-manual-form').show();
        $('.csv-import-section').hide();
        $('#submit-local-contact-btn').text('Add Contact');
    });
};

$(document).ready(function() {
    initLocalContactModal();
});
//TODO: Remove this block when form submission is implemented with ajax
// $(document).on('click', '#submit-local-contact-btn', function(e) {
//     e.preventDefault();
//
//     const isCSVMode = $('#csv-import-btn').hasClass('active');
//     const form = isCSVMode ? $('#csv-import-form') : $('#manual-contact-form');
//
//     if (form[0].checkValidity()) {
//         const formData = new FormData(form[0]);
//
//         if (isCSVMode) {
//             formData.append('import_contact', '1');
//         } else {
//             const isEdit = form.find('input[name="contact_id"]').length > 0;
//             formData.append(isEdit ? 'edit_contact' : 'add_contact', '1');
//         }
//         form.submit();
//         $('#localContactModal').modal('hide');
//     } else {
//         form[0].reportValidity();
//     }
// });

$('#localContactModal').on('hidden.bs.modal', function () {
    $('#manual-contact-form')[0].reset();
    if ($('#csv-import-form').length) {
        $('#csv-import-form')[0].reset();
    }
    $('.method-btn').removeClass('active');
    $('#manual-entry-btn').addClass('active');
    $('.contact-manual-form').show();
    $('.csv-import-section').hide();
});

$(document).on('change', '#contact_csv', function(e) {
    const fileName = e.target.files[0]?.name;
    if (fileName) {
        const label = $(this).siblings('.csv-upload-label');
        label.find('.csv-upload-text strong').text(fileName);
        label.find('.csv-upload-hint').text(Hm_Trans('Click to change file'));
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
