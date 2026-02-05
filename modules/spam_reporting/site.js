/* spam reporting */
'use strict';

var spam_report_modal = null;
var spam_reporting_targets = [];
var spam_reporting_platforms = [];
var spam_reporting_suggestion = {};
var spam_reporting_current_uid = null;
var spam_reporting_current_list_path = null;

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

var spam_reporting_resolve_target_label = function(target, platforms) {
    var pid = target.platform_id || '';
    if (pid && platforms && platforms.length) {
        for (var i = 0; i < platforms.length; i++) {
            if ((platforms[i].platform_id || platforms[i].id) === pid) {
                return platforms[i].name || target.label || target.id;
            }
        }
    }
    return target.label || target.id;
};

var spam_reporting_to_array = function(val) {
    if (Array.isArray(val)) return val;
    if (val && typeof val === 'object') return Object.values(val);
    return [];
};

var spam_reporting_platform_data_summary = function(platform) {
    if (!platform) return '';
    var req = spam_reporting_to_array(platform.required_data);
    var allowed = spam_reporting_to_array(platform.allowed_data);
    var never = spam_reporting_to_array(platform.never_send);
    var parts = [];
    if (allowed.indexOf('ip') >= 0) parts.push(hm_trans('IP address') + ' \u2713');
    if (allowed.indexOf('headers') >= 0) parts.push(hm_trans('Headers') + ' \u2713');
    if (allowed.indexOf('body') >= 0) parts.push(hm_trans('Body') + (req.indexOf('body') >= 0 ? ' \u2713' : ' (' + hm_trans('optional') + ')'));
    if (allowed.indexOf('user_notes') >= 0) parts.push(hm_trans('User notes') + (req.indexOf('user_notes') >= 0 ? ' \u2713' : ' (' + hm_trans('optional') + ')'));
    if (parts.length === 0) return '';
    return parts.join(', ');
};

var spam_reporting_render_targets = function(targets, suggestion, platforms) {
    var select = $('.spam-report-target-select');
    var empty = $('.spam-report-targets-empty');
    var sendBtn = $('.spam-report-send');
    if (!select.length) return;
    if (targets && !Array.isArray(targets)) targets = Object.values(targets);
    platforms = platforms || spam_reporting_platforms;
    suggestion = suggestion || {};
    var suggestedIds = spam_reporting_to_array(suggestion.suggested_target_ids);
    select.empty();
    if (!targets || !targets.length) {
        if (empty.length) empty.removeClass('d-none');
        select.prop('disabled', true);
        if (sendBtn.length) sendBtn.prop('disabled', true);
        return;
    }
    if (empty.length) empty.addClass('d-none');
    var placeholder = $('<option value=""></option>').text(hm_trans('Select target'));
    select.append(placeholder);
    var suggested = [];
    var other = [];
    targets.forEach(function(target) {
        var label = spam_reporting_resolve_target_label(target, platforms);
        var opt = { target: target, id: target.id, label: label };
        if (suggestedIds.indexOf(target.id) >= 0) suggested.push(opt);
        else other.push(opt);
    });
    if (suggested.length > 0 && other.length > 0) {
        var matchHint = suggested.length === 1 ? hm_trans('Strong match') : hm_trans('Possible match');
        var grpSuggested = $('<optgroup label="' + hm_trans('Recommended platforms') + ' (' + matchHint + ')"></optgroup>');
        suggested.forEach(function(o) {
            grpSuggested.append($('<option></option>').val(o.id).text(o.label));
        });
        select.append(grpSuggested);
        var grpOther = $('<optgroup label="' + hm_trans('Other platforms') + '"></optgroup>');
        other.forEach(function(o) {
            grpOther.append($('<option></option>').val(o.id).text(o.label));
        });
        select.append(grpOther);
    } else {
        targets.forEach(function(target) {
            var label = spam_reporting_resolve_target_label(target, platforms);
            select.append($('<option></option>').val(target.id).text(label));
        });
    }
    select.prop('disabled', false);
    if (sendBtn.length) sendBtn.prop('disabled', false);
};

var spam_reporting_render_suggestion = function(suggestion) {
    var explanationEl = $('.spam-report-suggestion-text');
    var selfNoteEl = $('.spam-report-self-report-note');
    if (!explanationEl.length && !selfNoteEl.length) {
        return;
    }
    suggestion = suggestion || {};
    var explanation = suggestion.explanation || '';
    var selfNote = suggestion.self_report_note || '';
    if (explanationEl.length) {
        explanationEl.text(explanation).toggle(explanation.length > 0);
    }
    if (selfNoteEl.length) {
        selfNoteEl.text(selfNote).toggle(selfNote.length > 0);
    }
};

var spam_reporting_render_platforms = function(platforms) {
    var list = $('.spam-report-platforms-list');
    var empty = $('.spam-report-platforms-empty');
    if (!list.length) return;
    if (platforms && !Array.isArray(platforms)) platforms = Object.values(platforms);
    list.empty();
    if (!platforms || !platforms.length) {
        if (empty.length) empty.removeClass('d-none');
        return;
    }
    if (empty.length) empty.addClass('d-none');
    platforms.forEach(function(platform) {
        var name = platform.name || platform.id || '';
        var description = platform.description || '';
        var dataSummary = spam_reporting_platform_data_summary(platform);
        var item = $('<li class="list-group-item"></li>');
        item.append($('<div class="fw-semibold"></div>').text(name));
        if (description) {
            item.append($('<div class="text-muted small"></div>').text(description));
        }
        if (dataSummary) {
            item.append($('<div class="text-muted small mt-1"></div>').text(dataSummary));
        }
        list.append(item);
    });
};

var spam_reporting_update_data_summary = function(targetId) {
    var summaryEl = $('.spam-report-data-summary');
    var checklistEl = $('.spam-report-data-checklist');
    if (!summaryEl.length || !checklistEl.length) return;
    if (!targetId) {
        summaryEl.addClass('d-none');
        return;
    }
    var target = null;
    for (var i = 0; i < spam_reporting_targets.length; i++) {
        if (spam_reporting_targets[i].id === targetId) {
            target = spam_reporting_targets[i];
            break;
        }
    }
    if (!target) {
        summaryEl.addClass('d-none');
        return;
    }
    var platform = null;
    var pid = target.platform_id || '';
    if (pid && spam_reporting_platforms.length) {
        for (var j = 0; j < spam_reporting_platforms.length; j++) {
            if ((spam_reporting_platforms[j].platform_id || spam_reporting_platforms[j].id) === pid) {
                platform = spam_reporting_platforms[j];
                break;
            }
        }
    }
    checklistEl.empty();
    if (platform) {
        var req = spam_reporting_to_array(platform.required_data);
        var allowed = spam_reporting_to_array(platform.allowed_data);
        var never = spam_reporting_to_array(platform.never_send);
        var hasItems = false;
        if (allowed.indexOf('ip') >= 0) {
            checklistEl.append($('<li></li>').html(hm_trans('IP address') + ' &#x2713;'));
            hasItems = true;
        }
        if (allowed.indexOf('headers') >= 0) {
            checklistEl.append($('<li></li>').html(hm_trans('Headers') + ' &#x2713;'));
            hasItems = true;
        }
        if (allowed.indexOf('body') >= 0) {
            var bodyLabel = hm_trans('Body') + (req.indexOf('body') >= 0 ? ' &#x2713;' : ' (' + hm_trans('optional') + ')');
            checklistEl.append($('<li></li>').html(bodyLabel));
            hasItems = true;
        }
        if (allowed.indexOf('user_notes') >= 0) {
            var notesLabel = hm_trans('User notes') + (req.indexOf('user_notes') >= 0 ? ' &#x2713;' : ' (' + hm_trans('optional') + ')');
            checklistEl.append($('<li></li>').html(notesLabel));
            hasItems = true;
        }
        if (never.length) {
            checklistEl.append($('<li></li>').html(hm_trans('Never sent') + ': ' + never.join(', ')));
            hasItems = true;
        }
        if (!hasItems) {
            checklistEl.append($('<li></li>').text(hm_trans('Full message (headers + body). User identity is never sent.')));
        }
    } else {
        checklistEl.append($('<li></li>').text(hm_trans('Full message (headers + body). User identity is never sent.')));
    }
    summaryEl.removeClass('d-none');
};

var spam_reporting_apply_preview = function(preview) {
    var headers = preview && preview.headers ? preview.headers : '';
    var bodyText = preview && preview.body_text ? preview.body_text : '';
    var bodyHtml = preview && preview.body_html ? preview.body_html : '';

    var headersEl = $('.spam-report-headers');
    var bodyEl = $('.spam-report-body');
    var bodyHtmlEl = $('.spam-report-body-html');
    console.info('[spam_reporting] DEBUG apply_preview', {
        previewKeys: preview ? Object.keys(preview) : [],
        headersLen: headers.length,
        bodyTextLen: bodyText.length,
        bodyHtmlLen: bodyHtml.length,
        headersElCount: headersEl.length,
        bodyElCount: bodyEl.length,
        bodyHtmlElCount: bodyHtmlEl.length,
        modalInDom: document.getElementById('spamReportModal') ? 'yes' : 'no'
    });
    headersEl.val(headers);
    bodyEl.val(bodyText);
    bodyHtmlEl.val(bodyHtml);
};

var spam_reporting_load_preview = function(uid, listPath) {
    if (uid === undefined || uid === null) {
        uid = (typeof getMessageUidParam === 'function') ? getMessageUidParam() : $('.msg_uid').val();
    }
    if (listPath === undefined || listPath === null || listPath === '') {
        listPath = (typeof getListPathParam === 'function') ? getListPathParam() : '';
    }
    if (!uid || !listPath) {
        console.warn('[spam_reporting] missing uid/list_path', {uid: uid, listPath: listPath});
        return;
    }
    spam_reporting_current_uid = uid;
    spam_reporting_current_list_path = listPath;
    var ajaxPayload = [
        {'name': 'page', 'value': 'ajax_spam_report_preview'},
        {'name': 'hm_ajax_hook', 'value': 'ajax_spam_report_preview'},
        {'name': 'list_path', 'value': listPath},
        {'name': 'uid', 'value': uid}
    ];
    console.info('[spam_reporting] loading preview, sending AJAX', {uid: uid, listPath: listPath, payload: ajaxPayload});
    Hm_Ajax.request(
        ajaxPayload,
        function(res) {
            if (!res) {
                console.warn('[spam_reporting] DEBUG AJAX callback received null/false - request may have failed');
                return;
            }
            var preview = res.spam_report_preview || {};
            console.info('[spam_reporting] DEBUG preview response', {
                hasError: !!res.spam_report_error,
                error: res.spam_report_error,
                hasPreview: !!res.spam_report_preview,
                previewKeys: preview ? Object.keys(preview) : [],
                headersLen: preview && preview.headers ? preview.headers.length : 0,
                bodyTextLen: preview && preview.body_text ? preview.body_text.length : 0,
                bodyHtmlLen: preview && preview.body_html ? preview.body_html.length : 0,
                targetsCount: (res.spam_report_targets || []).length,
                fullRes: res
            });
            if (res.spam_report_error) {
                spam_reporting_targets = [];
                spam_reporting_platforms = [];
                spam_reporting_suggestion = {};
                spam_reporting_render_targets([], {}, []);
                spam_reporting_render_suggestion({});
                spam_reporting_render_platforms([]);
                spam_reporting_update_data_summary('');
                spam_reporting_apply_preview({headers: res.spam_report_error, body_text: '', body_html: ''});
                return;
            }
            spam_reporting_targets = res.spam_report_targets || [];
            spam_reporting_platforms = res.spam_report_platforms || [];
            spam_reporting_suggestion = res.spam_report_suggestion || {};
            spam_reporting_render_targets(spam_reporting_targets, spam_reporting_suggestion, spam_reporting_platforms);
            spam_reporting_render_suggestion(spam_reporting_suggestion);
            spam_reporting_render_platforms(spam_reporting_platforms);
            spam_reporting_update_data_summary('');
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
    var target = null;
    for (var i = 0; i < spam_reporting_targets.length; i++) {
        if (spam_reporting_targets[i].id === targetId) {
            target = spam_reporting_targets[i];
            break;
        }
    }
    if (target && target.is_api_target && target.api_service_name) {
        var consentMsg = hm_trans('This report will be sent to an external service via an API operated by %s.');
        consentMsg = (consentMsg && consentMsg.indexOf('%s') >= 0)
            ? consentMsg.replace('%s', target.api_service_name)
            : 'This report will be sent to an external service via an API operated by ' + target.api_service_name + '.';
        if (!confirm(consentMsg)) {
            return;
        }
    }
    var uid = spam_reporting_current_uid;
    var listPath = spam_reporting_current_list_path;
    if (!uid || !listPath) {
        uid = (typeof getMessageUidParam === 'function') ? getMessageUidParam() : $('.msg_uid').val();
        listPath = (typeof getListPathParam === 'function') ? getListPathParam() : '';
    }
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
        var btn = $(e.currentTarget);
        var uid = btn.data('uid');
        var listPath = btn.data('list-path');
        var urlUid = (typeof getMessageUidParam === 'function') ? getMessageUidParam() : undefined;
        var urlListPath = (typeof getListPathParam === 'function') ? getListPathParam() : undefined;
        var msgUidVal = $('.msg_uid').val();
        console.info('[spam_reporting] DEBUG click', {
            fromButton: { uid: uid, listPath: listPath },
            fromUrl: { uid: urlUid, listPath: urlListPath },
            fromMsgUid: msgUidVal,
            windowLocationNext: window.location.next || '(not set)',
            windowLocationSearch: window.location.search
        });
        if (!uid || !listPath) {
            uid = urlUid || msgUidVal;
            listPath = urlListPath || '';
        }
        console.info('[spam_reporting] report button clicked', {uid: uid, listPath: listPath});
        spam_reporting_load_preview(uid, listPath);
        spam_reporting_open_modal();
    });
    $(document).on('change', '.spam-report-toggle-html', spam_reporting_toggle_html);
    $(document).on('change', '.spam-report-target-select', function() {
        spam_reporting_update_data_summary($(this).val());
    });
    $(document).on('click', '.spam-report-send', function(e) {
        e.preventDefault();
        spam_reporting_send_report();
    });
};

window.addEventListener('message-loaded', spam_reporting_bind);
