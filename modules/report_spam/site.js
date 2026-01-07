// 'use strict';

// $(document).on('click', '#report_spam_message', function(e) {
//     e.preventDefault();
//     var modalElement = document.getElementById('reportSpamModal');
//     if (!modalElement) {
//         console.error('Report Spam modal element not found in DOM');
//         return false;
//     }
//     var modal = new bootstrap.Modal(modalElement);
//     modal.show();
//     return false;
// });

// $(document).on('change', '#spam_reason_select', function() {
//     var selectedOptions = $(this).val() || [];
//     if (selectedOptions.includes('other')) {
//         $('#spam_reason_other_input').show();
//         $('#spam_reason_other_text').prop('required', true);
//     } else {
//         $('#spam_reason_other_input').hide();
//         $('#spam_reason_other_text').prop('required', false).val('');
//     }
// });

// $(document).on('click', '#confirm_report_spam', function(e) {
//     e.preventDefault();
//     var selectedReasons = $('#spam_reason_select').val() || [];
//     if (selectedReasons.length === 0) {
//         alert(hm_trans('Please select at least one reason for reporting this email as spam.'));
//         return false;
//     }

//     if (selectedReasons.includes('other')) {
//         var otherText = $('#spam_reason_other_text').val().trim();
//         if (!otherText) {
//             alert(hm_trans('Please specify the reason.'));
//             return false;
//         }
//     }

//     var uid = getMessageUidParam();
//     var detail = Hm_Utils.parse_folder_path(getListPathParam(), 'imap');

//     var reasons = selectedReasons.map(function(reason) {
//         return reason === 'other' ? $('#spam_reason_other_text').val().trim() : reason;
//     });

//     var selectedMessages = $('#reportSpamModal').data('selected-messages');
//     var isBulkAction = selectedMessages && selectedMessages.length > 0;
//     var messageIds = '';

//     if (isBulkAction) {
//         messageIds = selectedMessages.join(',');
//     } else {
//         if (uid && detail && detail.server_id && detail.folder) {
//             messageIds = 'imap_' + detail.server_id + '_' + uid + '_' + detail.folder;
//         } else {
//             alert(hm_trans('Unable to determine message details.'));
//             return false;
//         }
//     }

//     // AJAX call to report spam
//     Hm_Ajax.request(
//         [
//             {'name': 'hm_ajax_hook', 'value': 'ajax_report_spam'},
//             {'name': 'message_ids', 'value': messageIds},
//             {'name': 'spam_reasons', 'value': reasons}
//         ],
//         function(res) {
//             var modal = bootstrap.Modal.getInstance(document.getElementById('reportSpamModal'));
//             modal.hide();
//             $('#reportSpamForm')[0].reset();
//             $('#spam_reason_other_input').hide();
//             $('#spam_reason_other_text').prop('required', false).val('');
//             $('#reportSpamModal').removeData('selected-messages');

//             // Display messages from router_user_msgs if they exist
//             // (The global handler should do this, but we do it here as well to ensure they're shown)
//             if (res && res.router_user_msgs && typeof res.router_user_msgs === 'object') {
//                 var msgKeys = Object.keys(res.router_user_msgs);
//                 if (msgKeys.length > 0) {
//                     // router_user_msgs exists and has content - display each message
//                     Object.values(res.router_user_msgs).forEach(function(msg) {
//                         if (msg && msg.text && msg.type) {
//                             Hm_Notices.show(msg.text, msg.type);
//                         }
//                     });
//                     return; // Exit early - messages have been shown
//                 }
//             }

//             // Fallback: If router_user_msgs is missing or empty, use the spam_report_message
//             if (res && res.spam_report_message) {
//                 var messageType = res.spam_report_error ? 'danger' : 'success';
//                 Hm_Notices.show(res.spam_report_message, messageType);
//             } else if (res && res.spam_report_error) {
//                 Hm_Notices.show(hm_trans('Failed to report spam.'), 'danger');
//             } else if (res) {
//                 Hm_Notices.show(hm_trans('Report submitted successfully'), 'success');
//             }
//         },
//         [],
//         false,
//         false,
//         true
//     );

//     return false;
// });

// // Reset form when modal is closed
// $(document).on('hidden.bs.modal', '#reportSpamModal', function() {
//     $('#reportSpamForm')[0].reset();
//     $('#spam_reason_other_input').hide();
//     $('#spam_reason_other_text').prop('required', false).val('');
// });
