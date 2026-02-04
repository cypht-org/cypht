/* spam reporting */
'use strict';

var spam_report_modal = null;

var spam_reporting_open_modal = function() {
    var modalEl = document.getElementById('spamReportModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        if (!modalEl) {
            console.warn('[spam_reporting] modal element not found');
        }
        if (typeof bootstrap === 'undefined') {
            console.warn('[spam_reporting] bootstrap is undefined');
        }
        return;
    }
    if (!spam_report_modal) {
        spam_report_modal = new bootstrap.Modal(modalEl);
    }
    spam_report_modal.show();
    console.info('[spam_reporting] modal opened');
};

var spam_reporting_render_targets = function(targets) {
    var select = $('.spam-report-target-select');
    var empty = $('.spam-report-targets-empty');
    var sendBtn = $('.spam-report-send');
    if (!select.length) {
        return;
    }
    if (targets && !Array.isArray(targets)) {
        targets = Object.values(targets);
    }
    select.empty();
    if (!targets || !targets.length) {
        if (empty.length) {
            empty.removeClass('d-none');
        }
        select.prop('disabled', true);
        if (sendBtn.length) {
            sendBtn.prop('disabled', true);
        }
        return;
    }
    if (empty.length) {
        empty.addClass('d-none');
    }
    var placeholder = $('<option value=""></option>').text(hm_trans('Select target'));
    select.append(placeholder);
    targets.forEach(function(target) {
        var option = $('<option></option>');
        option.val(target.id);
        option.text(target.label || target.id);
        select.append(option);
    });
    select.prop('disabled', false);
    if (sendBtn.length) {
        sendBtn.prop('disabled', false);
    }
};

var spam_reporting_apply_preview = function(preview) {
    var headers = preview && preview.headers ? preview.headers : '';
    var bodyText = preview && preview.body_text ? preview.body_text : '';
    var bodyHtml = preview && preview.body_html ? preview.body_html : '';

    $('.spam-report-headers').val(headers);
    $('.spam-report-body').val(bodyText);
    $('.spam-report-body-html').val(bodyHtml);
};

var spam_reporting_load_preview = function() {
    var uid = (typeof getMessageUidParam === 'function') ? getMessageUidParam() : $('.msg_uid').val();
    var listPath = (typeof getListPathParam === 'function') ? getListPathParam() : '';
    if (!uid || !listPath) {
        console.warn('[spam_reporting] missing uid/list_path', {uid: uid, listPath: listPath});
        return;
    }
    console.info('[spam_reporting] loading preview', {uid: uid, listPath: listPath});
    Hm_Ajax.request(
        [
            {'name': 'page', 'value': 'ajax_spam_report_preview'},
            {'name': 'hm_ajax_hook', 'value': 'ajax_spam_report_preview'},
            {'name': 'list_path', 'value': listPath},
            {'name': 'uid', 'value': uid}
        ],
        function(res) {
            console.info('[spam_reporting] preview response', res);
            if (res.spam_report_error) {
                spam_reporting_render_targets([]);
                spam_reporting_apply_preview({headers: res.spam_report_error, body_text: '', body_html: ''});
                return;
            }
            spam_reporting_render_targets(res.spam_report_targets || []);
            spam_reporting_apply_preview(res.spam_report_preview || {});
            var status = $('.spam-report-status');
            if (status.length) {
                status.text('');
            }
            if (res.spam_report_debug) {
                console.info('[spam_reporting] debug', res.spam_report_debug);
            }
        }
    );
};

var spam_reporting_toggle_html = function() {
    var target = $('.spam-report-html');
    if (!target.length) {
        return;
    }
    if ($(this).is(':checked')) {
        target.removeClass('d-none');
    } else {
        target.addClass('d-none');
    }
};

var spam_reporting_send_report = function() {
    var targetId = $('.spam-report-target-select').val();
    var notes = $('.spam-report-notes').val();
    var status = $('.spam-report-status');
    if (!targetId) {
        if (status.length) {
            status.text(hm_trans('Please select a target'));
        }
        return;
    }
    var uid = (typeof getMessageUidParam === 'function') ? getMessageUidParam() : $('.msg_uid').val();
    var listPath = (typeof getListPathParam === 'function') ? getListPathParam() : '';
    if (!uid || !listPath) {
        if (status.length) {
            status.text(hm_trans('Missing message context'));
        }
        return;
    }
    if (status.length) {
        status.text(hm_trans('Sending...'));
    }
    Hm_Ajax.request(
        [
            {'name': 'page', 'value': 'ajax_spam_report_send'},
            {'name': 'hm_ajax_hook', 'value': 'ajax_spam_report_send'},
            {'name': 'list_path', 'value': listPath},
            {'name': 'uid', 'value': uid},
            {'name': 'target_id', 'value': targetId},
            {'name': 'user_notes', 'value': notes}
        ],
        function(res) {
            if (status.length) {
                if (res.spam_report_send_ok) {
                    status.text(hm_trans('Report sent'));
                } else {
                    status.text(res.spam_report_send_message || '');
                }
            }
        }
    );
};

var spam_reporting_bound = false;
var spam_reporting_bind = function() {
    if (spam_reporting_bound) {
        return;
    }
    spam_reporting_bound = true;
    console.info('[spam_reporting] binding handlers');
    $(document).on('click', '.spam_report_action', function(e) {
        e.preventDefault();
        console.info('[spam_reporting] report button clicked');
        spam_reporting_load_preview();
        spam_reporting_open_modal();
    });
    $(document).on('change', '.spam-report-toggle-html', spam_reporting_toggle_html);
    $(document).on('click', '.spam-report-send', function(e) {
        e.preventDefault();
        spam_reporting_send_report();
    });
};

window.addEventListener('message-loaded', spam_reporting_bind);
