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
    var container = $('.spam-report-targets-list');
    if (!container.length) {
        return;
    }
    if (!targets || !targets.length) {
        container.text(hm_trans('No targets configured'));
        return;
    }
    var list = $('<ul class="list-unstyled mb-0"></ul>');
    targets.forEach(function(target) {
        var item = $('<li></li>');
        item.text(target.label || target.id);
        list.append(item);
    });
    container.empty().append(list);
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
};

window.addEventListener('message-loaded', spam_reporting_bind);
