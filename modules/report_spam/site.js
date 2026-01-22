'use strict';

$(document).on('click', '#report_spam_message', function(e) {
    e.preventDefault();
    var modal = new bootstrap.Modal(document.getElementById('reportSpamModal'));
    modal.show();
    return false;
});

$(document).on('change', '#spam_reason_select', function() {
    var selectedOptions = $(this).val() || [];
    if (selectedOptions.includes('other')) {
        $('#spam_reason_other_input').show();
        $('#spam_reason_other_text').prop('required', true);
    } else {
        $('#spam_reason_other_input').hide();
        $('#spam_reason_other_text').prop('required', false).val('');
    }
});

$(document).on('click', '#confirm_report_spam', function(e) {
    e.preventDefault();
    var selectedReasons = $('#spam_reason_select').val() || [];
    if (selectedReasons.length === 0) {
        alert(hm_trans('Please select at least one reason for reporting this email as spam.'));
        return false;
    }

    if (selectedReasons.includes('other')) {
        var otherText = $('#spam_reason_other_text').val().trim();
        if (!otherText) {
            alert(hm_trans('Please specify the reason.'));
            return false;
        }
    }

    var uid = getMessageUidParam();
    var detail = Hm_Utils.parse_folder_path(getListPathParam(), 'imap');

    var reasons = selectedReasons.map(function(reason) {
        return reason === 'other' ? $('#spam_reason_other_text').val().trim() : reason;
    });

    var selectedMessages = $('#reportSpamModal').data('selected-messages');
    var isBulkAction = selectedMessages && selectedMessages.length > 0;
    var messageIds = '';

    if (isBulkAction) {
        messageIds = selectedMessages.join(',');
    } else {
        if (uid && detail && detail.server_id && detail.folder) {
            messageIds = 'imap_' + detail.server_id + '_' + uid + '_' + detail.folder;
        } else {
            alert(hm_trans('Unable to determine message details.'));
            return false;
        }
    }

    var modal = bootstrap.Modal.getInstance(document.getElementById('reportSpamModal'));
    
    Hm_Ajax.request(
        [
            {'name': 'hm_ajax_hook', 'value': 'ajax_report_spam'},
            {'name': 'message_ids', 'value': messageIds},
            {'name': 'spam_reasons', 'value': reasons}
        ],
        function(res) {
            // Cleanup form (modal already hidden)
            $('#reportSpamForm')[0].reset();
            $('#spam_reason_other_input').hide();
            $('#spam_reason_other_text').prop('required', false).val('');
            $('#reportSpamModal').removeData('selected-messages');

            if (res && res.spam_report_error && (!res.router_user_msgs || Object.keys(res.router_user_msgs).length === 0)) {
                Hm_Notices.show(res.spam_report_message || hm_trans('Failed to report spam.'), 'danger');
            } else if (res && !res.spam_report_error && (!res.router_user_msgs || Object.keys(res.router_user_msgs).length === 0)) {
                var message = res && res.spam_report_message ? res.spam_report_message : hm_trans('Report submitted successfully');
                Hm_Notices.show(message, 'success');
            }
        },
        [],
        false,
        false,
        true
    );
    
    if (modal) {
        modal.hide();
    }

    return false;
});

$(document).on('hidden.bs.modal', '#reportSpamModal', function() {
    $('#reportSpamForm')[0].reset();
    $('#spam_reason_other_input').hide();
    $('#spam_reason_other_text').prop('required', false).val('');
});
