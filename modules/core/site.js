'use strict';

/**
 * NOTE: Tiki-Cypht integration dynamically removes everything from the begining of this file
 * up to swipe_event function definition as it uses jquery (over cash.js) and has bootstrap
 * framework already included. If you add code here that you wish to be included in Tiki-Cypht
 * integration, add it below swipe_event function definition.
 */

/* extend cash.js with some useful bits */
$.inArray = function(item, list) {
    for (var i in list) {
        if (list[i] === item) {
            return i;
        }
    }
    return -1;
};
$.isEmptyObject = function(obj) {
    for (var key in obj) {
        if (obj.hasOwnProperty(key)) {
            return false;
        }
    }
    return true;
};
$.fn.submit = function() { this[0].submit(); }
$.fn.focus = function() { this[0].focus(); };
$.fn.serializeArray = function() {
    var parts;
    var res = [];
    var args = this.serialize().split('&');
    for (var i in args) {
        parts = args[i].split('=');
        if (parts[0] && parts[1]) {
            res.push({'name': parts[0], 'value': parts[1]});
        }
    }
    return res.map(function(x) {return {name: x.name, value: decodeURIComponent(x.value.replace(/\+/g, " "))}});
};
$.fn.sort = function(sort_function) {
    var list = [];
    for (var i=0, len=this.length; i < len; i++) {
        list.push(this[i]);
    }
    return $(list.sort(sort_function));
};
$.fn.fadeOut = function(timeout = 600) {
    return this.css("opacity", 0)
    .css("transition", `opacity ${timeout}ms`)
};
$.fn.fadeOutAndRemove = function(timeout = 600) {
    this.fadeOut(timeout)
    var tm = setTimeout(() => {
        this.remove();
        clearTimeout(tm)
    }, timeout);
    return this;
};

$.fn.modal = function(action) {
    const modalElement = this[0];
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        if (action === 'show') {
            modal.show();
        } else if (action === 'hide') {
            modal.hide();
        }
    }
};

/* swipe event handler */
var swipe_event = function(el, callback, direction) {
    var start_x, start_y, dist_x, dist_y, threshold = 150, restraint = 100,
        allowed_time = 500, start_time;

    el.addEventListener('touchstart', function(e) {
        var touchobj = e.changedTouches[0];
        start_x = touchobj.pageX;
        start_y = touchobj.pageY;
        start_time = new Date().getTime();
    }, false);

    el.addEventListener('touchend', function(e) {
        var touchobj = e.changedTouches[0];
        dist_x = touchobj.pageX - start_x;
        dist_y = touchobj.pageY - start_y;
        if ((new Date().getTime() - start_time) <= allowed_time) {
            if (Math.abs(dist_x) >= threshold && Math.abs(dist_y) <= restraint) {
                var dir = (dist_x < 0) ? 'left' : 'right';
                if (dir == direction) {
                    callback();
                }
            }
        }
    }, false);
};

// Constants. To be used anywhere in the app via the window object.
const globalVars = {
    EMAIL_REGEX: /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/g,
}
Object.keys(globalVars).forEach(key => {
    window[key] = globalVars[key];
});


/* ajax multiplexer */
var Hm_Ajax = {
    batch_callbacks: {},
    callback_hooks: [],
    p_callbacks: [],
    aborted: false,
    err_condition: false,
    batch_callback: false,
    active_reqs: 0,
    icon_loading_id: false,

    get_ajax_hook_name: function(args) {
        var index;
        for (index in args) {
            if (args[index]['name'] == 'hm_ajax_hook') {
                return args[index]['value'];
            }
        }
        return;
    },

    request: function(args, callback, extra, no_icon, batch_callback, on_failure, signal) {
        var bcb = false;
        if (typeof batch_callback != 'undefined' && $.inArray(batch_callback, this.batch_callbacks) === -1) {
            bcb = batch_callback.toString();
            var detail = Hm_Ajax.batch_callbacks[bcb];
            if (typeof detail !== 'undefined') {
                Hm_Ajax.batch_callbacks[bcb] += 1;
            }
            else {
                Hm_Ajax.batch_callbacks[bcb] = 1;
            }
        }
        var name = Hm_Ajax.get_ajax_hook_name(args);
        var ajax = new Hm_Ajax_Request();
        if (!no_icon) {
            Hm_Ajax.show_loading_icon();
            $('body').addClass('wait');
        }
        Hm_Ajax.active_reqs++;
        return ajax.make_request(args, callback, extra, name, on_failure, batch_callback, signal);
    },

    show_loading_icon: function() {
        if (Hm_Ajax.icon_loading_id !== false) {
            return;
        }
        var hm_loading_pos = $('.loading_icon').width()/40;
        $('.loading_icon').show();
        function move_background_image() {
            hm_loading_pos = hm_loading_pos + 50;
            $('.loading_icon').css('background-position', hm_loading_pos+'px 0');
            Hm_Ajax.icon_loading_id = setTimeout(move_background_image, 100);
        }
        move_background_image();
    },

    stop_loading_icon : function(loading_id) {
        clearTimeout(loading_id);
        $('.loading_icon').hide();
        Hm_Ajax.icon_loading_id = false;
    },

    process_callback_hooks: function(name, res) {
        var hook;
        var func;
        for (var i in Hm_Ajax.callback_hooks) {
            hook = Hm_Ajax.callback_hooks[i];
            if (hook[0] == name || hook[0] == '*') {
                func = hook[1];
                func(res);
                if (hook[0] == '*') {
                    if ($.inArray(hook, Hm_Ajax.p_callbacks) === -1) {
                        Hm_Ajax.p_callbacks.push(hook);
                    }
                }
            }
        }
    },

    add_callback_hook: function(request_name, hook_function) {
        Hm_Ajax.callback_hooks.push([request_name, hook_function]);
    }
};

/* ajax request wrapper */
var Hm_Ajax_Request = function() { return {
    callback: false,
    name: false,
    batch_callback: false,
    index: 0,
    on_failure: false,
    start_time: 0,

    xhr_fetch: function(config) {
        var xhr = new XMLHttpRequest();
        var data = '';
        if (config.data) {
            data = this.format_xhr_data(config.data);
        }
        const url = window.location.next ?? window.location.href;
        xhr.open('POST', url)
        if (config.signal) {
            config.signal.addEventListener('abort', function() {
                xhr.abort();
            });
        }
        xhr.addEventListener('load', function() {
            config.callback.done(Hm_Utils.json_decode(xhr.response, true), xhr);
            config.callback.always(Hm_Utils.json_decode(xhr.response, true));
        });
        xhr.addEventListener('error', function() {
            Hm_Ajax.stop_loading_icon(Hm_Ajax.icon_loading_id);
            config.callback.fail(xhr);
            config.callback.always(Hm_Utils.json_decode(xhr.response, true));
        });
        xhr.addEventListener('abort', function() {
            Hm_Ajax.stop_loading_icon(Hm_Ajax.icon_loading_id);
            config.callback.always(Hm_Utils.json_decode(xhr.response, true));

        });
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-with', 'xmlhttprequest');
        xhr.send(data);
    },

    format_xhr_data: function(data) {
        var res = []
        for (var i in data) {
            res.push(encodeURIComponent(data[i]['name']) + '=' + encodeURIComponent(data[i]['value']));
        }
        return res.join('&');
    },

    make_request: function(args, callback, extra, request_name, on_failure, batch_callback, signal) {
        var name;
        var arg;
        this.batch_callback = batch_callback;
        this.name = request_name;
        this.callback = callback;
        if (on_failure) {
            this.on_failure = true;
        }
        if (extra) {
            for (name in extra) {
                args.push({'name': name, 'value': extra[name]});
            }
        }
        var key_found = false;
        for (arg in args) {
            if (args[arg].name == 'hm_page_key') {
                key_found = true;
                break;
            }
        }
        if (!key_found) {
            args.push({'name': 'hm_page_key', 'value': $('#hm_page_key').val()});
        }
        var dt = new Date();
        this.start_time = dt.getTime();
        this.xhr_fetch({url: '', data: args, callback: this, signal});
        return false;
    },

    done: function(res, xhr) {
        if (Hm_Ajax.aborted) {
            return;
        }
        else if (!res || typeof res == 'string' && (res == 'null' || res.indexOf('<') === 0 || res == '{}')) {
            this.fail(xhr);
            return;
        }
        else {
            $('.offline').hide();
            if (hm_encrypt_ajax_requests()) {
                res = Hm_Utils.json_decode(Hm_Crypt.decrypt(res.payload));
            }
            if ((res.state && res.state == 'not callable') || !res.router_login_state) {
                this.fail(xhr, true);
                return;
            }
            if (Hm_Ajax.err_condition) {
                Hm_Ajax.err_condition = false;
                Hm_Notices.hide(true);
            }
            if (res.router_user_msgs && !$.isEmptyObject(res.router_user_msgs)) {
                Hm_Notices.show(res.router_user_msgs);
            }
            if (res.folder_status) {
                for (const name in res.folder_status) {
                    if (name === getPageNameParam()) {
                        Hm_Folders.unread_counts[name] = res.folder_status[name]['unseen'];
                        Hm_Folders.update_unread_counts();
                        const messages = new Hm_MessagesStore(name, Hm_Utils.get_url_page_number());
                        messages.load().then(() => {
                            if (messages.count != res.folder_status[name].messages) {
                                messages.load(true).then(() => {
                                    display_imap_mailbox(messages.rows, messages.links);
                                })
                            }
                        });
                    }
                }
            }
            if (this.callback) {
                this.callback(res);
            }
            Hm_Ajax.process_callback_hooks(this.name, res);
        }
    },

    run_on_failure: function() {
        if (this.on_failure && this.callback) {
            this.callback(false);
        }
        return false;
    },

    fail: function(xhr, not_callable) {
        if (not_callable === true || (xhr.status && xhr.status == 500)) {
            Hm_Notices.show([err_msg('Server Error')]);
        }
        else {
            $('.offline').show();
        }
        Hm_Ajax.err_condition = true;
        this.run_on_failure();
    },

    always: function(res) {
        Hm_Ajax.active_reqs--;
        var batch_count = 1;
        if (this.batch_callback) {
            if (typeof Hm_Ajax.batch_callbacks[this.batch_callback.toString()] != 'undefined') {
                batch_count = --Hm_Ajax.batch_callbacks[this.batch_callback.toString()];
            }
        }
        Hm_Message_List.set_row_events();
        if (batch_count === 0) {
            Hm_Ajax.batch_callbacks[this.batch_callback.toString()] = 0;
            Hm_Ajax.aborted = false;
            Hm_Ajax.p_callbacks = [];
            this.batch_callback(res);
            this.batch_callback = false;
            Hm_Ajax.stop_loading_icon(Hm_Ajax.icon_loading_id);
            $('body').removeClass('wait');
        }
        if (Hm_Ajax.active_reqs == 0) {
            Hm_Ajax.stop_loading_icon(Hm_Ajax.icon_loading_id);
            $('body').removeClass('wait');
        }
        res = null;
    }
}};

/**
 * Show a modal dialog with a title, content and buttons.
 */
function Hm_Modal(options) {
    var defaults = {
        title: 'Cypht',
        size: '',
        btnSize: '',
        modalId: 'myModal',
    };

    this.opts = { ...defaults, ...options };

    this.init = function () {
        if (this.modal) {
            return;
        }

        const modal = `
            <div id="${this.opts.modalId}" class="modal fade modal-${this.opts.size}" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title">${this.opts.title}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body"></div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary${this.opts.btnSize? ' btn-' + this.opts.btnSize: ''}" data-bs-dismiss="modal">${hm_trans('Close')}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modal);

        this.modal = $(`#${this.opts.modalId}`);
        this.modalContent = this.modal.find('.modal-body');
        this.modalTitle = this.modal.find('.modal-title');
        this.modalFooter = this.modal.find('.modal-footer');

        this.bsModal = new bootstrap.Modal(document.getElementById(this.opts.modalId));
    };

    this.open = () => {
        this.bsModal.show();
    };

    this.hide = () => {
        this.bsModal.hide();
    };

    this.addFooterBtn = (label, classes, callback) => {
        const btn = document.createElement('button');
        btn.innerHTML = label;

        btn.classList.add('btn', ...classes.split(' '));
        if (this.opts.btnSize) {
            btn.classList.add(`btn-${this.opts.btnSize}`);
        }

        btn.addEventListener('click', callback);

        this.modalFooter.append(btn);
    };

    this.setContent = (content) => {
        this.modalContent.html(content);
    };

    this.setTitle = (title) => {
        this.modalTitle.html(title);
    };

    this.init();
}

/* user notification manager */
var Hm_Notices = {
    hide_id: false,

    show: function(msgs) {
        var message = '';
        var type = '';
        for (var i in msgs) {
            if (msgs[i].match(/^ERR/)) {
                message = msgs[i].substring(3);
                type = 'danger';
            }
            else {
                type = 'info';
                message = msgs[i];
            }

            Hm_Utils.add_sys_message(message, type);
        }
    },

    hide: function(now) {
        if (Hm_Notices.hide_id) {
            clearTimeout(Hm_Notices.hide_id);
        }
        if (now) {
            $('.sys_messages').addClass('d-none');
            Hm_Utils.clear_sys_messages();
        }
        else {
            Hm_Notices.hide_id = setTimeout(function() {
                $('.sys_messages').addClass('d-none');
                Hm_Utils.clear_sys_messages();
            }, 5000);
        }
    }
};

/* job scheduler */
var Hm_Timer = {
    jobs: [],
    interval: 1000,

    add_job: function(job, interval, defer, custom_defer) {
        if (custom_defer) {
            Hm_Timer.jobs.push([job, interval, custom_defer]);
        }
        else if (interval) {
            Hm_Timer.jobs.push([job, interval, interval]);
        }
        if (!defer) {
            try { job(); } catch(e) { console.log(e); }
        }
    },

    cancel: function(job) {
        for (var index in Hm_Timer.jobs) {
            if (Hm_Timer.jobs[index][0] == job) {
                Hm_Timer.jobs.splice(index, 1);
                return true;
            }
        }
        return false;
    },

    fire: function() {
        var job;
        var index;
        for (index in Hm_Timer.jobs) {
            job = Hm_Timer.jobs[index];
            job[2]--;
            if (job[2] === 0) {
                job[2] = job[1];
                Hm_Timer.jobs[index] = job;
                try { job[0](); } catch(e) { console.log(e); }
            }
        }
        setTimeout(Hm_Timer.fire, Hm_Timer.interval);
    }
};

/* message list */
function Message_List() {
    var self = this;
    this.sources = [];
    this.deleted = [];
    this.background = false;
    this.completed_count = 0;
    this.last_click = '';
    this.callbacks = [];
    this.sort_fld = 4;
    this.past_total = 0;
    this.just_inserted = [];

    this.page_caches = {
        'feeds': 'formatted_feed_data',
        'combined_inbox': 'formatted_combined_inbox',
        'email': 'formatted_all_mail',
        'unread': 'formatted_unread_data',
        'flagged': 'formatted_flagged_data',
        'junk': 'formatted_junk_data',
        'trash': 'formatted_trash_data',
        'sent': 'formatted_sent_data',
        'drafts': 'formatted_drafts_data',
        'tag': 'formatted_tag_data'
    };

    this.run_callbacks = function (completed) {
        var func;
        var index;
        if (completed) {
            for (index in this.callbacks) {
                func = this.callbacks[index];
                try { func(); } catch(e) { console.log(e); }
            }
        }
        fixLtrInRtl();
    };

    this.update = function(ids, msgs, type, cache) {
        var completed = false;
        this.completed_count++;
        if (this.completed_count == this.sources.length) {
            this.completed_count = 0;
            completed = true;
        }
        if ($('input[type=checkbox]', $('.message_table')).filter(function() {return this.checked; }).length > 0) {
            this.run_callbacks(completed);
            return 0;
        }
        if (msgs[0] === "") {
            this.run_callbacks(completed);
            return 0;
        }
        var msg_rows;
        if (!cache) {
            msg_rows = Hm_Utils.tbody();
        }
        else {
            msg_rows = cache;
        }
        if (!this.background && !$.isEmptyObject(msgs)) {
            $('.empty_list').remove();
        }
        var msg_ids = this.add_rows(msgs, msg_rows);
        var count = this.remove_rows(ids, msg_ids, type, msg_rows);
        this.run_callbacks(completed);
        if (!cache) {
            this.set_tab_index();
        }
        return count;
    };

    this.set_tab_index = function() {
        var msg_rows = Hm_Utils.rows();
        var count = 1;
        msg_rows.each(function() {
            $(this).attr('tabindex', count);
            count++;
        });
    };

    this.remove_rows = function(ids, msg_ids, type, msg_rows) {
        var count = $('tr', msg_rows).length;
        var parts;
        var re;
        var i;
        var id;
        for (i=0;i<ids.length;i++) {
            id = ids[i];
            if ((id+'').search('_') != -1) {
                parts = id.split('_', 2);
                re = new RegExp(parts[1]+'$');
                parts[1] = re;
            }
            else {
                parts = [id, false];
            }
            $('tr[class^='+type+'_'+parts[0]+'_]', msg_rows).filter(function() {
                var id = this.className;
                if (id.indexOf(' ') != -1) {
                    id = id.split(' ')[0];
                }
                if (!parts[1] || parts[1].exec(id)) {
                    if ($.inArray(id, msg_ids) == -1) {
                        count--;
                        $(this).remove();
                    }
                }
            });
        }
        return count;
    };

    this.sort = function(fld) {
        var listitems = Hm_Utils.rows();
        var aval;
        var bval;
        var sort_result = listitems.sort(function(a, b) {
            switch (Math.abs(fld)) {
                case 1:
                case 2:
                case 3:
                    aval = $($('td', a)[Math.abs(fld)]).text().replace(/^\s+/g, '');
                    bval = $($('td', b)[Math.abs(fld)]).text().replace(/^\s+/g, '');
                    break;
                case 4:
                default:
                    aval = $('input', $($('td', a)[Math.abs(fld)])).val();
                    bval = $('input', $($('td', b)[Math.abs(fld)])).val();
                    break;
            }
            if (fld == 4 || fld == -4 || !fld) {
                if (fld == -4) {
                    return aval - bval;
                }
                return bval - aval;
            }
            else {
                if (fld && fld < 0) {
                    return bval.toUpperCase().localeCompare(aval.toUpperCase());
                }
                return aval.toUpperCase().localeCompare(bval.toUpperCase());
            }
        });
        this.sort_fld = fld;
        Hm_Utils.tbody().html('');
        for (var i = 0, len=sort_result.length; i < len; i++) {
            Hm_Utils.tbody().append(sort_result[i]);
        }
        this.save_updated_list();
    };

    this.add_rows = function(msgs, msg_rows) {
        var msg_ids = [];
        var row;
        var id;
        var index;
        for (index in msgs) {
            row = msgs[index][0];
            id = msgs[index][1];
            if (this.deleted.indexOf(Hm_Utils.clean_selector(id)) != -1) {
                continue;
            }
            id = id.replace(/ /, '-');
            if (!$('.'+Hm_Utils.clean_selector(id), msg_rows).length) {
                this.insert_into_message_list(row, msg_rows);
                $('.'+Hm_Utils.clean_selector(id), msg_rows).show();
            }
            else {
                $('.'+Hm_Utils.clean_selector(id), msg_rows).replaceWith(row)
            }
            msg_ids.push(id);
        }
        return msg_ids;
    };

    this.insert_into_message_list = function(row, msg_rows) {
        var sort_fld = this.sort_fld;
        if (typeof sort_fld == 'undefined' || sort_fld == null) {
            sort_fld = 4;
        }
        var element = false;
        if (sort_fld == 4 || sort_fld == -4) {
            var timestr2;
            var timestr = $('.msg_timestamp', $(row)).val();
            $('tr', msg_rows).each(function() {
                timestr2 = $('.msg_timestamp', $(this)).val();
                if ((sort_fld == -4 && (timestr2*1) >= (timestr*1)) ||
                    (sort_fld == 4 && (timestr*1) >= (timestr2*1))) {
                    element = $(this);
                    return false;
                }
            });
        }
        else {
            var bval;
            var aval = $($('td', $(row))[Math.abs(sort_fld)]).text().replace(/^\s+/g, '');
            $('tr', msg_rows).each(function() {
                bval = $($('td', $(this))[Math.abs(sort_fld)]).text().replace(/^\s+/g, '');
                if ((sort_fld < 0 && aval.toUpperCase().localeCompare(bval.toUpperCase()) > 0) ||
                   (sort_fld > 0 && bval.toUpperCase().localeCompare(aval.toUpperCase()) > 0)) {
                    element = $(this);
                    return false;
                }
            });
        }
        // apply JS pagination only on aggregate folders; imap ones already have the messages sorted
        if (getListPathParam().substring(0, 5) != 'imap_' && element) {
            $(row, msg_rows).insertBefore(element);
        }
        else {
            msg_rows.append(row);
        }
        self.just_inserted.push($('.from', $(row)).text()+' - '+$('.subject', $(row)).text());
    };

    this.reset_checkboxes = function() {
        this.toggle_msg_controls();
        this.set_row_events();
    };

    this.toggle_msg_controls = function() {
        if ($('input[type=checkbox]', $('.message_table')).filter(function() {return this.checked; }).length > 0) {
            $('.msg_controls').addClass('d-flex');
            $('.msg_controls').removeClass('d-none');
            $('.mailbox_list_title').addClass('hide');
        }
        else {
            $('.msg_controls').removeClass('d-flex');
            $('.msg_controls').addClass('d-none');
            $('.mailbox_list_title').removeClass('hide');
        }
    };

    this.update_after_action = function(action_type, selected) {
        var remove = false;
        if (action_type == 'read' && getListPathParam() == 'unread') {
            remove = true;
        }
        if (action_type == 'unflag' && getListPathParam() == 'flagged') {
            remove = true;
        }
        else if (action_type == 'delete' || action_type == 'archive') {
            remove = true;
        }
        if (remove) {
            this.remove_after_action(action_type, selected);
        }
        else {
            if (action_type == 'read' || action_type == 'unread') {
                this.read_after_action(action_type, selected);
            }
            else if (action_type == 'flag' || action_type == 'unflag') {
                this.flag_after_action(action_type, selected);
            }
        }
        this.save_updated_list();
        this.reset_checkboxes();
    };

    this.save_updated_list = function() {
        if (this.page_caches.hasOwnProperty(getListPathParam())) {
            this.set_message_list_state(this.page_caches[getListPathParam()]);
            Hm_Utils.save_to_local_storage('sort_'+getListPathParam(), this.sort_fld);
        }
    };

    this.remove_after_action = function(action_type, selected) {
        var removed = 0;
        var class_name = false;
        var index;
        for (index in selected) {
            class_name = selected[index];
            $('.'+Hm_Utils.clean_selector(class_name)).remove();
            if (action_type == 'delete') {
                this.deleted.push(class_name);
            }
            removed++;
        }
        return removed;
    };

    this.read_after_action = function(action_type, selected) {
        var read = 0;
        var row;
        var index;
        var class_name = false;
        for (index in selected) {
            class_name = selected[index];
            row = $('.'+Hm_Utils.clean_selector(class_name));
            if (action_type == 'read') {
                $('.subject > div', row).removeClass('unseen');
                row.removeClass('unseen');
            }
            else {
                $('.subject > div', row).addClass('unseen');
                row.addClass('unseen');
            }
            read++;
        }
        return read;
    };

    this.flag_after_action = function(action_type, selected) {
        var flagged = 0;
        var class_name;
        var row;
        var index;
        for (index in selected) {
            class_name = selected[index];
            row = $('.'+Hm_Utils.clean_selector(class_name));
            if (action_type == 'flag') {
                $('.icon', row).html(hm_flag_image_src());
            }
            else {
                $('.icon', row).empty();
            }
            flagged++;
        }
        return flagged;
    };

    this.load_sources = function() {
        var index;
        var source;
        if (!self.background) {
            $('.src_count').text(self.sources.length);
            $('.total').text(Hm_Utils.rows().length);
        }
        for (index in self.sources) {
            source = self.sources[index];
        }
        return false;
    };

    this.select_combined_view = function() {
        if (self.page_caches.hasOwnProperty(getListPathParam())) {
            self.setup_combined_view(self.page_caches[getListPathParam()]);
        }
        else {
            if (getPageNameParam() == 'search') {
                self.setup_combined_view('formatted_search_data');
            }
            else {
                self.setup_combined_view(false);
            }
        }
        var sort_type = Hm_Utils.get_from_local_storage('sort_'+getListPathParam());
        if (sort_type != null) {
            this.sort_fld = sort_type;
            $('.combined_sort').val(sort_type);
        }
        $('.core_msg_control').on("click", function(e) {
            e.preventDefault();
            return self.message_action($(this).data('action')); });
        $('.toggle_link').on("click", function() { return self.toggle_rows(); });
        $('.refresh_link').on("click", function() { return self.load_sources(); });
    };

    this.add_sources = function(sources) {
        self.sources = sources;
    };

    this.setup_combined_view = function(cache_name) {
        self.add_sources(hm_data_sources());
        var data = Hm_Utils.get_from_local_storage(cache_name);
        var interval = Hm_Utils.get_from_global('combined_view_refresh_interval', 60);
        if (data && data.length) {
            Hm_Utils.tbody().html(data);
            if (cache_name == 'formatted_unread_data') {
                self.clear_read_messages();
            }
            self.set_row_events();
            $('.combined_sort').show();
        }
        if (getPageNameParam() == 'search' && hm_run_search() == "0") {
            Hm_Timer.add_job(self.load_sources, interval, true);
        }
        else {
            Hm_Timer.add_job(this.load_sources, interval);
        }
    };

    this.clear_read_messages = function() {
        var class_name;
        var list = Hm_Utils.get_from_local_storage('read_message_list');
        if (list && list.length) {
            list = Hm_Utils.json_decode(list);
            for (class_name in list) {
                $('.'+Hm_Utils.clean_selector(class_name)).remove();
            }
            Hm_Utils.save_to_local_storage('read_message_list', '');
        }
    };

    /* TODO: remove module specific refs */
    this.update_title = function() {
        var count = 0;
        var rows = Hm_Utils.rows();
        var tbody = Hm_Utils.tbody();
        if (getListPathParam() == 'unread') {
            count = rows.length;
            document.title = count+' '+hm_trans('Unread');
        }
        else if (getListPathParam() == 'flagged') {
            count = rows.length;
            document.title = count+' '+hm_trans('Flagged');
        }
        else if (getListPathParam() == 'combined_inbox') {
            count = $('tr .unseen', tbody).length;
            document.title = count+' '+hm_trans('Unread in Everything');
        }
        else if (getListPathParam() == 'email') {
            count = $('tr .unseen', tbody).length;
            document.title = count+' '+hm_trans('Unread in Email');
        }
        else if (getListPathParam() == 'feeds') {
            count = $('tr .unseen', tbody).length;
            document.title = count+' '+hm_trans('Unread in Feeds');
        }
    };

    this.message_action = function(action_type) {
        if (action_type == 'delete' && !hm_delete_prompt()) {
            return false;
        }
        var msg_list = $('.message_table');
        var selected = [];
        var current_list = self.filter_list();
        $('input[type=checkbox]', msg_list).each(function() {
            if (this.checked) {
                selected.push($(this).val());
            }
        });
        if (selected.length > 0) {
            var updated = false;
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_message_action'},
                {'name': 'action_type', 'value': action_type},
                {'name': 'message_ids', 'value': selected}],
                function(res) {
                    if (!res) {
                        $('.message_table_body').replaceWith(current_list);
                        self.save_updated_list();
                        self.toggle_msg_controls();
                    }
                    else {
                        if (res.hasOwnProperty('move_count')) {
                            selected = Object.values(res.move_count);
                        }
                        self.update_after_action(action_type, selected);
                        updated = true;
                    }
                },
                [],
                false,
                false,
                true
            );
        }
        if (!updated) {
            self.update_after_action(action_type, selected);
        }
    };

    this.prev_next_links = function() {
        let phref;
        let nhref;
        const target = $('.msg_headers tr').last();
        const messages = new Hm_MessagesStore(getListPathParam(), Hm_Utils.get_url_page_number());
        messages.load(false, true);
        const next = messages.getNextRowForMessage(getMessageUidParam());
        const prev = messages.getPreviousRowForMessage(getMessageUidParam());
        if (prev) {
            const prevSubject = $(prev['0']).find('.subject');
            phref = prevSubject.find('a').prop('href');
            const subject = new Option(prevSubject.text()).innerHTML;
            const plink = '<a class="plink" href="'+phref+'"><i class="prevnext bi bi-arrow-left-square-fill"></i> '+subject+'</a>';
            $('<tr class="prev"><th colspan="2">'+plink+'</th></tr>').insertBefore(target);
        }
        if (next) {
            const nextSubject = $(next['0']).find('.subject');
            nhref = nextSubject.find('a').prop('href');
            const subject = new Option(nextSubject.text()).innerHTML;
            const nlink = '<a class="nlink" href="'+nhref+'"><i class="prevnext bi bi-arrow-right-square-fill"></i> '+subject+'</a>';
            $('<tr class="next"><th colspan="2">'+nlink+'</th></tr>').insertBefore(target);
        }

        return [phref, nhref];
    };

    this.check_empty_list = function() {
        var count = Hm_Utils.rows().length;
        if (!count) {
            if (!$('.empty_list').length) {
                if (getPageNameParam() == 'search') {
                    $('.search_content').append('<div class="empty_list">'+hm_empty_folder()+'</div>');
                }
                else {
                    $('.message_list').append('<div class="empty_list">'+hm_empty_folder()+'</div>');
                }
                $(".page_links").css("display", "none");// Hide page links as message list is empty
            }
        }
        else {
            $('.empty_list').remove();
            $('.combined_sort').show();
        }
        return count === 0;
    };

    this.track_read_messages = function(class_name) {
        var read_messages = Hm_Utils.get_from_local_storage('read_message_list');
        if (read_messages && read_messages.length) {
            read_messages = Hm_Utils.json_decode(read_messages);
        }
        else {
            read_messages = {};
        }
        var added = false;
        if (!(class_name in read_messages)) {
            added = true;
        }
        read_messages[class_name] = 1;
        Hm_Utils.save_to_local_storage('read_message_list', Hm_Utils.json_encode(read_messages));
        return added;
    };

    this.adjust_unread_total = function(amount, replace) {
        var missing = $('.total_unread_count').text() === '' ? true : false;
        var current = $('.total_unread_count').text()*1;
        var new_total;
        if (replace && amount == current && amount != 0) {
            return;
        }
        if (!replace && amount == 0) {
            return;
        }
        if (replace) {
            new_total = amount;
        }
        else {
            new_total = current + amount;
        }
        if (new_total < 0) {
            new_total = 0;
        }
        if (new_total != current || missing) {
            $('.total_unread_count').html('&#160;'+new_total+'&#160;');
        }
        if (new_total > current && getPageNameParam() != 'message_list' && getListPathParam() != 'unread') {
            $('.menu_unread > a').css('font-weight', 'bold');
        }
        if (amount == -1 || new_total < current) {
            $('.menu_unread > a').css('font-weight', 'normal');
        }
        Hm_Folders.save_folder_list();
        self.past_total = current;
    };

    this.toggle_rows = function() {
        $('input[type=checkbox]', $('.message_table')).each(function () { this.checked = !this.checked; });
        self.toggle_msg_controls();
        return false;
    };

    this.filter_list = function() {
        var data = Hm_Utils.rows().clone().filter(function() {
            if (this.className == 'inline_msg') {
                return false;
            }
            return true;
        });
        var res = $('<tbody class="message_table_body"></tbody>');
        data.appendTo(res);
        return res;
    };

    this.set_message_list_state = function(list_type) {
        var data = this.filter_list();
        data.find('*[style]').attr('style', '');
        data.find('input[type=checkbox]').removeAttr('checked');
        Hm_Utils.save_to_local_storage(list_type, data.html());
        var empty = self.check_empty_list();
        if (!empty) {
            self.set_row_events();
        }
        $('.total').text(Hm_Utils.rows().length);
        self.update_title();
        if (list_type == 'formatted_unread_data') {
            self.adjust_unread_total(Hm_Utils.rows().length, true);
        }
    };

    this.select_range = function(a, b) {
        var start = false;
        var end = false;
        $('input[type=checkbox]', $('.message_table')).each(function() {
            if (end) {
                return false;
            }
            if (!start && ($(this).prop('id') == a || $(this).prop('id') == b)) {
                this.checked = 'checked';
                start = true;
                return true;
            }
            if (start && !end) {
                this.checked = 'checked';
            }
            if (start && ($(this).prop('id') == b || $(this).prop('id') == a)) {
                end = true;
                return true;
            }
        });
    };

    this.process_shift_click = function(el) {
        var id = el.prop('id');
        if (id == self.last_click) {
            return;
        }
        self.select_range(id, self.last_click);
    };

    this.set_row_events = function() {
        Hm_Utils.rows().off('click');
        Hm_Utils.rows().on('click', function(e) { self.process_row_click(e); });
    }

    this.process_row_click = function(e) {
        document.getSelection().removeAllRanges();
        var target = $(e.target);
        var class_name = target[0].className;
        var shift = e.shiftKey;
        var ctrl = e.ctrlKey;
        if (class_name == 'checkbox_label' || class_name == 'checkbox_cell') {
            ctrl = true
        }
        while (target[0].tagName != 'TR') { target = target.parent(); }
        var el = $('input[type=checkbox]', target);
        if (!shift && !ctrl) {
            navigate($('.subject a', target).prop('href'));
            return false;
        }
        else {
            self.select_on_row_click(shift, ctrl, el, target);
        }
        self.toggle_msg_controls();
        e.preventDefault();
        return false;
    }

    this.select_on_row_click = function(shift, ctrl, el, target) {
        if (shift) {
            if (self.last_click) {
                self.process_shift_click(el);
            }
            $('#'+el.prop('id')).prop('checked', 'checked');
            self.last_click = el.prop('id');
        }
        else if (ctrl) {
            if (el.prop('checked')) {
                $('#'+el.prop('id')).prop('checked', false);
            }
            else {
                $('#'+el.prop('id')).prop('checked', 'checked');
                self.last_click = el.prop('id');
            }
        }
    }

    this.set_all_mail_state = function() { self.set_message_list_state('formatted_all_mail'); };
    this.set_combined_inbox_state = function() { self.set_message_list_state('formatted_combined_inbox'); };
    this.set_flagged_state = function() { self.set_message_list_state('formatted_flagged_data'); };
    this.set_unread_state = function() { self.set_message_list_state('formatted_unread_data'); };
    this.set_search_state = function() { self.set_message_list_state('formatted_search_data'); };
    this.set_junk_state = function() { self.set_message_list_state('formatted_junk_data'); };
    this.set_trash_state = function() { self.set_message_list_state('formatted_trash_data'); };
    this.set_draft_state = function() { self.set_message_list_state('formatted_drafts_data'); };
    this.set_tag_state = function() { self.set_message_list_state('formatted_tag_data'); };
};

/* folder list */
var Hm_Folders = {
    expand_after_update: false,
    unread_counts: {},
    observer : false,

    save_folder_list: function() {
        Hm_Utils.save_to_local_storage('formatted_folder_list', $('.folder_list').html());
    },

    load_unread_counts: function() {
        var res = Hm_Utils.json_decode(Hm_Utils.get_from_local_storage('unread_counts'));
        if (!res) {
            Hm_Folders.unread_counts = {};
        }
        else {
            Hm_Folders.unread_counts = res;
        }
    },

    update_unread_counts: function(folder) {
        if (folder) {
            $('.unread_'+folder).html('&#160;'+Hm_Folders.unread_counts[folder]+'&#160;');
        }
        else {
            var name;
            for (name in Hm_Folders.unread_counts) {
                if (!Hm_Folders.unread_counts[name]) {
                    Hm_Folders.unread_counts[name] = 0;
                }
                if (getListPathParam() == name && getPageNameParam() == 'message_list') {
                    var title = document.title.replace(/^\[\d+\]/, '');
                    document.title = '['+Hm_Folders.unread_counts[name]+'] '+title;
                    /* HERE */
                }
                $('.unread_'+name).html('&#160;'+Hm_Folders.unread_counts[name]+'&#160;');
            }
        }
        Hm_Utils.save_to_local_storage('unread_counts', Hm_Utils.json_encode(Hm_Folders.unread_counts));
    },

    open_folder_list: function() {
        $('.folder_list').show();
        $('.folder_toggle').toggle();
        if (hm_mobile()) {
            $('main').hide();
        }
        else {
            $('main').css('display', 'table-cell');
        }
        Hm_Utils.save_to_local_storage('hide_folder_list', '');
        return false;
    },

    toggle_folder_list: function() {
        if ($('.folder_list').css('display') == 'none') {
            Hm_Folders.open_folder_list();
        }
        else {
            Hm_Folders.hide_folder_list();
        }
    },

    hide_folder_list: function(forget) {
        $('.folder_list').hide();
        $('.folder_toggle').show();
        if (!forget) {
            Hm_Utils.save_to_local_storage('formatted_folder_list', $('.folder_list').html());
            Hm_Utils.save_to_local_storage('hide_folder_list', '1');
            $('main').css('display', 'block');
        }
        return false;
    },

    reload_folders: function(force, expand_after_update) {
        if (document.cookie.indexOf('hm_reload_folders=1') > -1 || force) {
            Hm_Folders.expand_after_update = expand_after_update;
            var ui_state = Hm_Utils.preserve_local_settings();
            Hm_Folders.update_folder_list();
            sessionStorage.clear();
            Hm_Utils.restore_local_settings(ui_state);
            Hm_Utils.expand_core_settings();
            return true;
        }
        return false;
    },

    sort_list: function(class_name, exclude_name, last_name) {
        var folder = $('.'+class_name+' ul');
        var listitems;
        if (exclude_name) {
            listitems = $('li:not(.'+exclude_name+')', folder);
        }
        else {
            listitems = $('li', folder);
        }
        listitems = listitems.sort(function(a, b) {
            if (last_name && ($(a).attr('class') == last_name || $(b).attr('class') == last_name)) {
                return false;
            }
            if ($(b).text().toUpperCase() == 'ALL') {
                return true;
            }
           return $(a).text().toUpperCase().localeCompare($(b).text().toUpperCase());
        });
        $.each(listitems, function(_, itm) { folder.append(itm); });
    },

    update_folder_list_display: function(res) {
        $('.folder_list').html(res.formatted_folder_list);
        Hm_Folders.sort_list('email_folders', 'menu_email');
        Hm_Folders.sort_list('feeds_folders', 'menu_feeds', 'feeds_add_new');
        Hm_Folders.sort_list('main', 'menu_search', 'menu_logout');
        Hm_Utils.save_to_local_storage('formatted_folder_list', $('.folder_list').html());
        Hm_Folders.hl_selected_menu();
        Hm_Folders.folder_list_events();
        if (Hm_Folders.expand_after_update) {
            Hm_Utils.toggle_section(Hm_Folders.expand_after_update);
        }
        Hm_Folders.expand_after_update = false;
        Hm_Folders.listen_for_new_messages();
        hl_save_link();
    },

    update_folder_list: function() {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_hm_folders'}],
            Hm_Folders.update_folder_list_display,
            [],
            true
        );
        return false;
    },

    folder_list_events: function() {
        $('.imap_folder_link').on("click", function() { return expand_imap_folders($(this)); });
        $('.src_name').on("click", function() {
            var class_name = $(this).data('source');
            var icon_element = $(this).find('.bi');
            Hm_Utils.toggle_section(class_name);
            setTimeout(() => {
                var target_element = document.querySelector(class_name);
                var is_visible = Hm_Utils.is_element_visible(target_element);
                if (is_visible) {
                    icon_element.removeClass('bi-chevron-down').addClass('bi-chevron-up');
                } else {
                    icon_element.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                }
            }, 0);
        });
        $('.update_message_list').on("click", function(e) {
            var text = e.target.innerHTML;
            e.target.innerHTML = '<div class="spinner-border spinner-border-sm text-dark role="status"><span class="visually-hidden">Loading...</span></div>';
            Hm_Folders.update_folder_list();
            Hm_Ajax.add_callback_hook('hm_reload_folders', function() {
                e.target.innerHTML = text;
            });
            return false;
        });
        $('.hide_folders').on("click", function() { return Hm_Folders.hide_folder_list(); });
        $('.logout_link').on("click", function(e) { return Hm_Utils.confirm_logout(); });
        if (hm_search_terms()) {
            $('.search_terms').val(hm_search_terms());
        }
        $('.search_terms').on('search', function() {
            Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_reset_search'}]);
        });
    },

    hl_selected_menu: function() {
        const page = getPageNameParam();
        const path = getListPathParam();
        
        $('.folder_list').find('*').removeClass('selected_menu');
        if (path) {
            if (page == 'message_list' || page == 'message') {
                $("[data-id='"+Hm_Utils.clean_selector(path)+"']").addClass('selected_menu');
                $('.menu_'+Hm_Utils.clean_selector(path)).addClass('selected_menu');
            }
            else {
                $('.menu_'+path).addClass('selected_menu');
            }
        }
        else {
            $('.menu_'+page).addClass('selected_menu');
        }
    },

    listen_for_new_messages: function() {
        var target = $('.total_unread_count').get(0);
        if (!Hm_Folders.observer) {
            Hm_Folders.observer = new MutationObserver(function(mutations) {
                $('body').trigger('new_message');
            });
        }
        else {
            Hm_Folders.observer.disconnect();
        }
        Hm_Folders.observer.observe(target, {attributes: true, childList: true, characterData: true});
    },

    load_from_local_storage: function() {
        var folder_list = Hm_Utils.get_from_local_storage('formatted_folder_list');
        if (folder_list) {
            $('.folder_list').html(folder_list);
            if (Hm_Utils.get_from_local_storage('hide_folder_list') == '1') {
                $('.folder_list').hide();
                $('.folder_toggle').show();
                $('main').css('display', 'block');
            }
            Hm_Folders.hl_selected_menu();
            Hm_Folders.folder_list_events();
            Hm_Folders.load_unread_counts();
            Hm_Folders.update_unread_counts();
            Hm_Folders.listen_for_new_messages();
            return true;
        }
        return false;
    },

    toggle_folders_event: function() {
        $('.folder_toggle').on("click", function() { return Hm_Folders.open_folder_list(); });
    }
};

/* misc */
var Hm_Utils = {
    get_url_page_number: function() {
        var index;
        var match_result;
        var page_number = 1;
        var params = location.search.substr(1).split('&');
        var param_len = params.length;

        for (index=0; index < param_len; index++) {
            match_result = params[index].match(/list_page=(\d+)/);
            if (match_result) {
                page_number = match_result[1];
                break;
            }
        }
        return page_number;
    },

    get_from_global: function(name, def) {
        if (globals[name]) {
            return globals[name];
        }
        return def;
    },

    preserve_local_settings: function() {
        var i;
        var result = {};
        var prefix = window.location.pathname.length;
        for (i in sessionStorage) {
            i = i.substr(prefix);
            if (i.match(/\..+(_setting|_section)/)) {
                result[i] = Hm_Utils.get_from_local_storage(i);
            }
        }
        return result;
    },

    restore_local_settings: function(settings) {
        var i;
        for (i in settings) {
            Hm_Utils.save_to_local_storage(i, settings[i]);
        }
    },

    reset_search_form: function() {
        Hm_Utils.save_to_local_storage('formatted_search_data', '');
        Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_reset_search'}],
            function(res) { window.location = '?page=search'; }, false, true);
        return false;
    },

    confirm_logout: function() {
        if (! $('#unsaved_changes').length || $('#unsaved_changes').val() == 0) {
            document.getElementById('logout_without_saving').click();
        }
        else {
            var confirmLogoutModal = new bootstrap.Modal(document.getElementById('confirmLogoutModal'), {keyboard: true})
            confirmLogoutModal.show();
            $('.confirm_logout').show();
        }
        return false;
    },

    get_path_type: function(path) {
        if (path.indexOf('_') != -1) {
            var path_parts = path.split('_');
            return path_parts[0];
        }
        return false;
    },

    parse_folder_path: function(path, path_type) {
        if (!path_type) {
            path_type = Hm_Utils.get_path_type(path);
        }
        if (path && path.indexOf(' ') != -1) {
            path = path.split(' ')[0];
        }
        var type = false;
        var server_id = false;
        var uid = false;
        var folder = '';
        var parts;

        if (path_type == 'imap') {
            parts = path.split('_', 4);
            if (parts.length == 2) {
                type = parts[0];
                server_id = parts[1];
            }
            else if (parts.length == 3) {
                type = parts[0];
                server_id = parts[1];
                folder = parts[2];
            }
            else if (parts.length == 4) {
                type = parts[0];
                server_id = parts[1];
                uid = parts[2];
                folder = parts[3];
            }
            if (type && server_id) {
                return {'type': type, 'server_id' : server_id, 'folder' : folder, 'uid': uid};
            }
        }
        else if (path_type == 'feeds') {
            parts = path.split('_', 3);
            if (parts.length > 1) {
                type = parts[0];
                server_id = parts[1];
            }
            if (parts.length == 3) {
                uid = parts[2];
            }
            if (type && server_id) {
                return {'type': type, 'server_id' : server_id, 'uid': uid};
            }
        }
        return false;
    },

    toggle_section: function(class_name, force_on, force_off) {
        if ($(class_name).length) {
            if (force_off) {
                $(class_name).css('display', 'block');
            }
            if (force_on) {
                $(class_name).css('display', 'none');
            }
            $(class_name).toggle();
            Hm_Utils.save_to_local_storage('formatted_folder_list', $('.folder_list').html());
        }
        return false;
    },

    toggle_page_section: function(class_name) {
        if ($(class_name).length) {
            $(class_name).toggle();
            Hm_Utils.save_to_local_storage(class_name, $(class_name).css('display'));
        }
        return false;
    },

    expand_core_settings: function() {
        var sections = Hm_Utils.get_core_settings();
        var key;
        var dsp;
        for (key in sections) {
            dsp = sections[key];
            if (!dsp) {
                dsp = 'none';
            }
            $(key).css('display', dsp);
            Hm_Utils.save_to_local_storage(key, dsp);
        }
    },

    get_core_settings: function() {
        var dsp;
        var results = {}
        var i;
        var hash = window.location.hash;
        var sections = ['.wp_notifications_setting', '.github_all_setting', '.tfa_setting', '.sent_setting', '.general_setting', '.unread_setting', '.flagged_setting', '.all_setting', '.email_setting', '.junk_setting', '.trash_setting', '.drafts_setting','.tag_setting'];
        for (i=0;i<sections.length;i++) {
            dsp = Hm_Utils.get_from_local_storage(sections[i]);
            if (hash) {
                if (hash.replace('#', '.') != sections[i]) {
                    dsp = 'none';
                }
                else {
                    dsp = 'table-row';
                }
            }
            results[sections[i]] = dsp;
        }
        return results;
    },

    get_from_local_storage: function(key) {
        var prefix = window.location.pathname;
        key = prefix+key;
        var res = false;
        if (hm_encrypt_local_storage()) {
             res = Hm_Crypt.decrypt(sessionStorage.getItem(key));
        }
        else {
            res = sessionStorage.getItem(key);
        }
        return res;
    },

    search_from_local_storage: function(pattern) {
        const results = [];
        const key_pattern = new RegExp(pattern);
        for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key_pattern.test(key)) {
                const value = get_from_local_storage(key);
                results.push({ key: key, value: value });
            }
        }
        return results;
    },

    save_to_local_storage: function(key, val) {
        var prefix = window.location.pathname;
        key = prefix+key;
        if (hm_encrypt_local_storage()) {
            val = Hm_Crypt.encrypt(val);
        }
        if (Storage !== void(0)) {
            try { sessionStorage.setItem(key, val); } catch(e) {
                sessionStorage.clear();
                sessionStorage.setItem(key, val);
            }
            if (sessionStorage.getItem(key) === null) {
                sessionStorage.clear();
                sessionStorage.setItem(key, val);
            }
        }
        return false;
    },

    clean_selector: function(str) {
        return str.replace(/(:|\.|\[|\]|\/)/g, "\\$1");
    },

    toggle_long_headers: function() {
        $('.long_header').toggle();
        $('.all_headers').toggle();
        $('.small_headers').toggle();
        return false;
    },

    set_unsaved_changes: function(state) {
        $('#unsaved_changes').val(state);
    },

    /**
     * Shows pending messages added with the add_sys_message method
     */
    show_sys_messages: function() {
        $('.sys_messages').removeClass('d-none');
    },

    /**
     *
     * @param {*} msg : The alert message to display
     * @param {*} type : The type of message to display, depending on the type of boostrap5 alert (primary, secondary, success, danger, warning, info, light, dark )
     */
    add_sys_message: function(msg, type = 'info') {
        if (!msg) {
            return;
        }
        const icon = type == 'success' ? 'bi-check-circle' : 'bi-exclamation-circle';
        $('.sys_messages').append('<div class="alert alert-'+type+' alert-dismissible fade show" role="alert"><i class="bi '+icon+' me-2"></i><span class="' + type + '">'+msg+'</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
        this.show_sys_messages();
    },

    clear_sys_messages: function () {
        $('.sys_messages').html('');
    },

    cancel_logout_event: function() {
        $('.cancel_logout').on("click", function() { $('.confirm_logout').hide(); return false; });
    },

    json_encode: function(val) {
        try {
            return JSON.stringify(val);
        }
        catch (e) {
            return false;
        }
    },

    json_decode: function(val, original) {
        try {
            return JSON.parse(val);
        }
        catch (e) {
            if (original === true) {
                return val;
            }
            return false;
        }
    },

    rows: function() {
        return $('.message_table_body > tr').not('.inline_msg');
    },

    tbody: function() {
        return $('.message_table_body');
    },

    html_entities: function(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    test_connection: function() {
        $('.offline').hide();
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_test'}],
            false, [], false, false, false);
    },

    is_element_visible: function (elem) {
        if (!elem) return false;
        var style = window.getComputedStyle(elem);
        return style.display !== 'none' && style.visibility !== 'hidden' && elem.offsetWidth > 0 && elem.offsetHeight > 0;
    },

    redirect: function (path) {
        if (! path) {
            path = window.location.href;
        }
        window.location.href = path;
    },

    is_valid_email: function (val) {
        return /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|.(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(val)
    },
};

var Hm_Crypt = {
    decrypt: function(ciphertext) {
        try {
            ciphertext = atob(ciphertext);
            if (!ciphertext || ciphertext.length < 200) {
                return false;
            }
            var secret = $('#hm_page_key').val();
            var payload = ciphertext.substr(192);
            var hmac_sig = ciphertext.substr(128, 64);
            var salt = ciphertext.substr(0, 128);
            var digest = forge.md.sha512.create();
            var hmac = forge.hmac.create();
            var key = forge.pkcs5.pbkdf2(secret, salt, 100, 32, digest);
            var hmac_key = forge.pkcs5.pbkdf2(secret, salt, 101, 32, digest);

            hmac.start(digest, hmac_key);
            hmac.update(payload);
            if (hmac.digest().data != hmac_sig) {
                return false;
            }
            var iv = forge.pkcs5.pbkdf2(secret, salt, 100, 16, digest);
            var decipher = forge.cipher.createDecipher('AES-CBC', key);
            decipher.start({iv: iv});
            decipher.update(forge.util.createBuffer(payload, 'raw'));
            decipher.finish();
            return forge.util.decodeUtf8(decipher.output.data);
        } catch(e) {
            return false;
        }
    },

    encrypt: function(plaintext) {
        try {
            var secret = $('#hm_page_key').val();
            var salt = forge.random.getBytesSync(128);
            var digest = forge.md.sha512.create();
            var key = forge.pkcs5.pbkdf2(secret, salt, 100, 32, digest);
            var hmac_key = forge.pkcs5.pbkdf2(secret, salt, 101, 32, digest);
            var iv = forge.pkcs5.pbkdf2(secret, salt, 100, 16, digest);
            var hmac = forge.hmac.create();
            var cipher = forge.cipher.createCipher('AES-CBC', key);
            cipher.start({iv: iv});
            cipher.update(forge.util.createBuffer(plaintext, 'utf8'));
            cipher.finish();
            hmac.start(digest, hmac_key);
            hmac.update(cipher.output.data);
            return btoa(salt+hmac.digest().data+cipher.output.data);
        } catch(e) {
            return false;
        }
    },
}

var update_password = function(id) {
    var pass = $('#update_pw_'+id).val();
    if (pass && pass.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_update_server_pw'},
            {'name': 'password', 'value': pass},
            {'name': 'server_pw_id', 'value': id}],
            function(res) {
                if (res.connect_status) {
                    $('.div_'+id).remove();
                    if ($('.home_password_dialogs div').length == 1) {
                        $('.home_password_dialogs').remove();
                    }
                }
            }
        );
    }
}

var elog = function(val) {
    if (hm_debug()) {
        console.log(val);
    }
};

var hl_save_link = function() {
    if ($('.save_reminder').length) {
        $('.menu_save a').css('font-weight', 'bold');
    }
    else {
        $('.menu_save a').css('font-weight', 'normal');
    }
};

var reset_default_value_checkbox = function() {
    var checkbox = $(this).closest('.tooltip_restore').prev('input[type="checkbox"]');
    var default_value = checkbox.data('default-value');
    default_value = (default_value === 'true');
    checkbox.prop('checked', default_value);
    checkbox.prop('disabled', true);
};

var reset_default_timezone = function() {
    var hm_default_timezone = window.hm_default_timezone;
    $('#timezone').val(hm_default_timezone);
}
var reset_default_value_select = function() {
    var dropdown = $(this).closest('.tooltip_restore').prev('select');
    var default_value = dropdown.data('default-value');
    dropdown.val(default_value);
}

var reset_default_value_input = function() {
    var inputField = $(this).closest('.tooltip_restore').prev('input');
    var default_value = inputField.data('default-value');
    inputField.val(default_value);
}

var decrease_servers = function(section) {
    const element = document.querySelector(`.server_count .${section}_server_count`);
    const value = parseInt(element.textContent);
    if (value > 0) {
        element.innerHTML  = value - 1;
    }

    if (value === 1) {
        if ($(`.${section}_server`)) {
            $(`.${section}_server`).prev().fadeOutAndRemove();
        }
    }
};

var err_msg = function(msg) {
    return "ERR"+hm_trans(msg);
};

var hm_spinner = function(type = 'border', size = '') {
    return `<div class="d-flex justify-content-center spinner">
        <div class="spinner-${type} text-dark${size ? ` spinner-${type}-${size}` : ''}" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>`
};

var fillImapData = function(details) {
    $('#srv_setup_stepper_imap_address').val(details.server);
    $('#srv_setup_stepper_imap_port').val(details.port);
    $('#srv_setup_stepper_imap_server_id').val(details.id);
    $('#srv_setup_stepper_imap_hide_from_c_page').prop("checked", details.hide);
    if (details.sieve_config_host) {
        $('#srv_setup_stepper_imap_sieve_host').val(details.sieve_config_host);
        $("#srv_setup_stepper_enable_sieve").trigger("click", false);
    }

    if(details.tls) {
        $("input[name='srv_setup_stepper_imap_tls'][value='true']").prop("checked", true);
    } else {
        $("input[name='srv_setup_stepper_imap_tls'][value='false']").prop("checked", true);
    }
};

var fillSmtpData = function(details) {
    $('#srv_setup_stepper_smtp_server_id').val(details.id);
    $('#srv_setup_stepper_smtp_address').val(details.server);
    $('#srv_setup_stepper_smtp_port').val(details.port);
};

var fillJmapData = function(details) {
    $('#srv_setup_stepper_imap_server_id').val(details.id);
    $('#srv_setup_stepper_only_jmap').trigger('click');
    $('#srv_setup_stepper_jmap_address').val(details.server);
    $('#srv_setup_stepper_imap_hide_from_c_page').prop("checked", details.hide);
};

var imap_smtp_edit_action = function(event) {
    resetQuickSetupForm();
    event.preventDefault();
    Hm_Notices.hide(true);
    var details = $(this).data('server-details');

    $('.imap-jmap-smtp-btn').trigger('click');
    $('#srv_setup_stepper_profile_name').trigger('focus');
    $('#srv_setup_stepper_profile_name').val(details.name);
    $('#srv_setup_stepper_email').val(details.user);
    $('#srv_setup_stepper_password').val('');
    $('#srv_setup_stepper_profile_reply_to').val('');
    $('#srv_setup_stepper_create_profile').trigger("click", true);

    if ($(this).data('type') == 'jmap') {
        fillJmapData(details);
    } else if ($(this).data('type') == 'imap') {
        fillImapData(details);
        var smtpDetails = $('[data-type="smtp"][data-id="'+details.name+'"]');
        if (smtpDetails.length) {
            fillSmtpData(smtpDetails.data('server-details'));
        } else {
            $('#srv_setup_stepper_is_sender').trigger("click", true);
        }
    } else {
        fillSmtpData(details);
        var imapDetails = $('[data-type="imap"][data-id="'+details.name+'"]');
        if (imapDetails.length) {
            fillImapData(imapDetails.data('server-details'));
        } else {
            $('#srv_setup_stepper_is_receiver').trigger("click", true);
        }
    }
};

var hasLeadingOrTrailingSpaces = function(str) {
    return str !== str.trim();
};

/* create a default message list object */
var Hm_Message_List = new Message_List();

function sortHandlerForMessageListAndSearchPage() {
    $('.combined_sort').on("change", function() { Hm_Message_List.sort($(this).val()); });
    $('.source_link').on("click", function() { $('.list_sources').toggle(); $('#list_controls_menu').hide(); return false; });
    if (getListPathParam() == 'unread' && $('.menu_unread > a').css('font-weight') == 'bold') {
        $('.menu_unread > a').css('font-weight', 'normal');
        Hm_Folders.save_folder_list();
    }
}

/* executes on onload, has access to other module code */
$(function() {
    /* Remove disabled attribute to send checkbox */
    $('.save_settings').on("click", function (e) {
        $('.general_setting input[type=checkbox]').each(function () {
            if (this.hasAttribute('disabled') && this.checked) {
                this.removeAttr('disabled');
            }
        });
    })
    $('.reset_factory_button').on('click', function() { return hm_delete_prompt(); });

    /* check for folder reload */
    var reloaded = Hm_Folders.reload_folders();

    /* setup a few page wide event handlers */
    Hm_Utils.cancel_logout_event();
    Hm_Folders.toggle_folders_event();

    /* fire up the job scheduler */
    Hm_Timer.fire();
    
    /* show any pending notices */
    Hm_Utils.show_sys_messages();

    /* load folder list */
    if (hm_is_logged() && (!reloaded && !Hm_Folders.load_from_local_storage())) {
        Hm_Folders.update_folder_list();
    }

    hl_save_link();
    if (hm_mailto()) {
        try { navigator.registerProtocolHandler("mailto", "?page=compose&compose_to=%s", "Cypht"); } catch(e) {}
    }

    if (hm_mobile()) {
        swipe_event(document.body, function() { Hm_Folders.open_folder_list(); }, 'right');
        swipe_event(document.body, function() { Hm_Folders.hide_folder_list(); }, 'left');
        $('.list_controls.on_mobile').show();
        $('.list_controls.no_mobile').hide();
    } else {
        $('.list_controls.on_mobile').hide();
    }
    $('.offline').on("click", function() { Hm_Utils.test_connection(); });

    if (hm_check_dirty_flag()) {
        $('form:not(.search_terms)').areYouSure();
    }

    $(document).on('paste', '.warn_on_paste', function (e) {
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        if (hasLeadingOrTrailingSpaces(paste)) {
            Hm_Utils.add_sys_message(hm_trans('Pasted text has leading or trailing spaces'), 'danger');
        }
    });

    fixLtrInRtl()
});

/*
   check if language is rtl, it checks some elements based on the page and
   if those contain non-Arabic letters, the ltr class will be added and it
   will fix the direction and font.
*/
function fixLtrInRtl() {
    if (hm_language_direction() != "rtl") {
        return
    }

    function isTextEnglish(text) {
        if (text === "") {
            return false
        }
        var RTL = ['ا', 'ب', 'پ', 'ت', 'س', 'ج', 'چ', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی'];
        for (var char of RTL) {
            if (text.indexOf(char) > -1) {
                return false;
            }
        }
        return true;
    };

    function getElements() {
        var pageName = getPageNameParam();
        if (pageName == "message") {
            return [...$(".msg_text_inner").find('*'), ...$(".header_subject").find("*")];
        }
        if (pageName == "message_list" || pageName == "?page=history") {
            return [...$('*')];
        }
        return []
    }

    setTimeout(function(){
        var elements = getElements()
        for (var index = 0; index < elements.length; index++) {
            if (isTextEnglish(elements[index].textContent)) {
                if ((elements[index].className).indexOf("ltr") > -1) {
                    continue
                }
                elements[index].className = elements[index].className + ' ltr';
            };
        }
    }, 0)
}

function listControlsMenu() {
    $('#list_controls_menu').toggleClass('show')
    $('.list_sources').hide();
}


// Sortablejs
const tableBody = document.querySelector('.message_table_body');
if(tableBody && !hm_mobile()) {
    const allFoldersClassNames = [];
    let targetFolder;
    let movingElement;
    let movingNumber;
    Sortable.create(tableBody, {
        sort: false,
        group: 'messages',
        ghostClass: 'drag_target',
        draggable: ':not(.inline_msg)',

        onMove: (sortableEvent) => {
            movingElement = sortableEvent.dragged;
            targetFolder = sortableEvent.related?.className.split(' ')[0];
            return false;
        },

        onEnd: () => {
            // Remove the highlight class from the tr
            document.querySelectorAll('.message_table_body > tr.drag_target').forEach((row) => {
                row.classList.remove('drag_target');
            });
            return false;
        }
    });

    const isValidFolderReference = (className='') => {
        return className.startsWith('imap_') && allFoldersClassNames.includes(className)
    }

    Sortable.utils.on(tableBody, 'dragstart', (evt) => {
        let movingElements = [];
        // Is the target element checked
        const isChecked = evt.target.querySelector('.checkbox_cell input[type=checkbox]:checked');
        if (isChecked) {
            movingElements = document.querySelectorAll('.message_table_body > tr > .checkbox_cell input[type=checkbox]:checked');
            // Add a highlight class to the tr
            movingElements.forEach((checkbox) => {
                checkbox.parentElement.parentElement.classList.add('drag_target');
            });
        } else {
            // If not, uncheck all other checked elements so that they don't get moved
            document.querySelectorAll('.message_table_body > tr > .checkbox_cell input[type=checkbox]:checked').forEach((checkbox) => {
                checkbox.checked = false;
            });
        }

        movingNumber = movingElements.length || 1;

        const element = document.createElement('div');
        element.textContent = `Move ${movingNumber} conversation${movingNumber > 1 ? 's' : ''}`;
        element.style.position = 'absolute';
        element.className = 'dragged_element';
        document.body.appendChild(element);

        function moveElement() {
            element.style.display = 'none';
        }

        function removeElement() {
            element.remove();
        }

        document.addEventListener('drag', moveElement);
        document.addEventListener('mouseover', removeElement);

        evt.dataTransfer.setDragImage(element, 0, 0);
    });

    Sortable.utils.on(tableBody, 'dragend', () => {
        // If the target is not a folder, do nothing
        if (!isValidFolderReference(targetFolder ?? '')) {
            return;
        }

        const page = getPageNameParam();
        const selectedRows = [];

        if(movingNumber > 1) {
            document.querySelectorAll('.message_table_body > tr').forEach(row => {
                if (row.querySelector('.checkbox_cell input[type=checkbox]:checked')) {
                    selectedRows.push(row);
                }
            });
        }

        if (selectedRows.length == 0) {
            selectedRows.push(movingElement);
        }

        const movingIds = selectedRows.map(row => row.className.split(' ')[0]);

        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_move_copy_action'},
            {'name': 'imap_move_ids', 'value': movingIds.join(',')},
            {'name': 'imap_move_to', 'value': targetFolder},
            {'name': 'imap_move_page', 'value': page},
            {'name': 'imap_move_action', 'value': 'move'}],
            (res) =>{
                for (const index in res.move_count) {
                    $('.'+Hm_Utils.clean_selector(res.move_count[index])).remove();
                    select_imap_folder(getListPathParam());
                }
            }
        );

        // Reset the target folder
        targetFolder = null;
    });

    const folderList = document.querySelector('.folder_list');

    const observer = new MutationObserver((mutations) => {
        const emailFoldersGroups = document.querySelectorAll('.email_folders .inner_list');
        const emailFoldersElements = document.querySelectorAll('.email_folders .inner_list > li');

        // Keep track of all folders class names
        allFoldersClassNames.push(...[...emailFoldersElements].map(folder => folder.className.split(' ')[0]));

        emailFoldersGroups.forEach((emailFolders) => {
            Sortable.create(emailFolders, {
                sort: false,
                group: {
                    put: 'messages'
                }
            });
        });

        emailFoldersElements.forEach((emailFolder) => {
            emailFolder.addEventListener('dragenter', () => {
                emailFolder.classList.add('drop_target');
            });
            emailFolder.addEventListener('dragleave', () => {
                emailFolder.classList.remove('drop_target');
            });
            emailFolder.addEventListener('drop', () => {
                emailFolder.classList.remove('drop_target');
            });
        });
    });

    const config = {
        childList: true
    };

    observer.observe(folderList, config);
}

var resetStepperButtons = function() {
    $('.step_config-actions button').removeAttr('disabled');
    $('#stepper-action-finish').text($('#stepper-action-finish').text().slice(0, -3));
};

function submitSmtpImapServer() {
    $('.step_config-actions button').attr('disabled', true);
    $('#stepper-action-finish').text($('#stepper-action-finish').text() + '...');

    var requestData = [
        { name: 'hm_ajax_hook', value: 'ajax_quick_servers_setup' },
        { name: 'srv_setup_stepper_profile_name', value: $('#srv_setup_stepper_profile_name').val() },
        { name: 'srv_setup_stepper_email', value: $('#srv_setup_stepper_email').val() },
        { name: 'srv_setup_stepper_password', value: $('#srv_setup_stepper_password').val() },
        { name: 'srv_setup_stepper_provider', value: $('#srv_setup_stepper_provider').val() },
        { name: 'srv_setup_stepper_is_sender', value: $('#srv_setup_stepper_is_sender').prop('checked') },
        { name: 'srv_setup_stepper_is_receiver', value: $('#srv_setup_stepper_is_receiver').prop('checked') },
        { name: 'srv_setup_stepper_smtp_address', value: $('#srv_setup_stepper_smtp_address').val() },
        { name: 'srv_setup_stepper_smtp_port', value: $('#srv_setup_stepper_smtp_port').val() },
        { name: 'srv_setup_stepper_smtp_tls', value: $('input[name="srv_setup_stepper_smtp_tls"]:checked').val() },
        { name: 'srv_setup_stepper_imap_address', value: $('#srv_setup_stepper_imap_address').val() },
        { name: 'srv_setup_stepper_imap_port', value: $('#srv_setup_stepper_imap_port').val() },
        { name: 'srv_setup_stepper_imap_tls', value: $('input[name="srv_setup_stepper_imap_tls"]:checked').val() },
        { name: 'srv_setup_stepper_enable_sieve', value: $('#srv_setup_stepper_enable_sieve').prop('checked') },
        { name: 'srv_setup_stepper_create_profile', value: $('#srv_setup_stepper_create_profile').prop('checked') },
        { name: 'srv_setup_stepper_profile_is_default', value: $('#srv_setup_stepper_profile_is_default').prop('checked') },
        { name: 'srv_setup_stepper_profile_signature', value: $('#srv_setup_stepper_profile_signature').val() },
        { name: 'srv_setup_stepper_profile_reply_to', value: $('#srv_setup_stepper_profile_reply_to').val() },
        { name: 'srv_setup_stepper_imap_sieve_host', value: $('#srv_setup_stepper_imap_sieve_host').val() },
        { name: 'srv_setup_stepper_only_jmap', value: $('input[name="srv_setup_stepper_only_jmap"]:checked').val() },
        { name: 'srv_setup_stepper_imap_hide_from_c_page', value: $('input[name="srv_setup_stepper_imap_hide_from_c_page"]:checked').val() },
        { name: 'srv_setup_stepper_jmap_address', value: $('#srv_setup_stepper_jmap_address').val() },
        { name: 'srv_setup_stepper_imap_server_id', value: $('#srv_setup_stepper_imap_server_id').val() },
        { name: 'srv_setup_stepper_smtp_server_id', value: $('#srv_setup_stepper_smtp_server_id').val() }
    ];

    Hm_Ajax.request(requestData, function(res) {
        resetStepperButtons();
        if (res.just_saved_credentials) {
            if (res.imap_server_id) {
                Hm_Ajax.request(
                    [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_accept_special_folders'},
                    {'name': 'imap_server_id', value: res.imap_server_id},
                    {'name': 'imap_service_name', value: res.imap_service_name}],
                    function () {
                        resetQuickSetupForm();
                        Hm_Utils.redirect();
                    }
                );
            } else {
                resetQuickSetupForm();
                Hm_Utils.redirect();
            }
        }
    }, null, null, undefined, function (res) {
        resetStepperButtons();
    });
}

function resetQuickSetupForm() {
    $('#srv_setup_stepper_stepper').find('form').trigger('reset');
    display_config_step(0);

    //Initialize the form
    $("#srv_setup_stepper_profile_reply_to").val('');
    $("#srv_setup_stepper_profile_signature").val('');
    $("#srv_setup_stepper_profile_name").val('');
    $("#srv_setup_stepper_email").val('');
    $("#srv_setup_stepper_password").val('');
    $("#srv_setup_stepper_jmap_address").val('');
    $("#srv_setup_stepper_profile_is_default").prop('checked', true);
    $("#srv_setup_stepper_create_profile").prop('checked', true);
    $("#srv_setup_stepper_imap_server_id").val('');
    $("#srv_setup_stepper_smtp_server_id").val('');
    $("#srv_setup_stepper_is_sender").prop('checked', true);
    $("#srv_setup_stepper_is_receiver").prop('checked', true);
    $("#srv_setup_stepper_enable_sieve").prop('checked', false);
    $("#srv_setup_stepper_only_jmap").prop('checked', false);
    $('#step_config-imap_bloc').show();
    $('#step_config-smtp_bloc').show();
    $('#srv_setup_stepper_profile_bloc').show();

    Hm_Utils.set_unsaved_changes(1);
    Hm_Folders.reload_folders(true);
}

function handleCreateProfileCheckboxChange(checkbox) {
    if(checkbox.checked) {
        $('#srv_setup_stepper_profile_bloc').show();
    }else{
        $('#srv_setup_stepper_profile_bloc').hide();
    }
}

function handleSieveStatusChange (checkbox) {
    if(checkbox.checked) {
        $('#srv_setup_stepper_imap_sieve_host_bloc').show();
    }else{
        $('#srv_setup_stepper_imap_sieve_host_bloc').hide();
    }
}
function handleSmtpImapCheckboxChange(checkbox) {
    if (checkbox.id === 'srv_setup_stepper_is_receiver') {
        if(checkbox.checked) {
            $('#step_config-imap_bloc').show();
            $('#step_config_combined_view').show();
            $('#srv_setup_stepper_jmap_select_box').show();
            $('#srv_setup_stepper_only_jmap').prop('checked', false);
        } else {
            $('#step_config-imap_bloc').hide();
            $('#step_config-jmap_bloc').hide();
            $('#step_config_combined_view').hide();
            $('#srv_setup_stepper_jmap_select_box').hide();
        }
    }

    if (checkbox.id === 'srv_setup_stepper_is_sender') {
        console.log("checkbox.checked", checkbox.checked)
        if(checkbox.checked) $('#step_config-smtp_bloc').show();
        else $('#step_config-smtp_bloc').hide();
    }

    if ($('#srv_setup_stepper_is_sender').prop('checked') && $('#srv_setup_stepper_is_receiver').prop('checked')) {
        $('#srv_setup_stepper_profile_bloc').show();
        $('#srv_setup_stepper_profile_checkbox_bloc').show();
        
    } else if(! $('#srv_setup_stepper_is_sender').prop('checked') || ! $('#srv_setup_stepper_is_receiver').prop('checked')) {
        $('#srv_setup_stepper_profile_bloc').hide();
        $('#srv_setup_stepper_profile_checkbox_bloc').hide();
    }
}

function handleJmapCheckboxChange(checkbox) {
    if (checkbox.checked) {
        $('#step_config-jmap_bloc').show();
        $('#step_config-imap_bloc').hide();
        if (! $('#srv_setup_stepper_enable_sieve').prop('checked')) {
            $('#srv_setup_stepper_imap_sieve_host_bloc').hide();
        }
    } else {
        $('#step_config-jmap_bloc').hide();
        $('#step_config-imap_bloc').show();
    }
}

function handleProviderChange(select) {
    let providerKey = select.value;
    if(providerKey) {
        getServiceDetails(providerKey);
    }else{
        $("#srv_setup_stepper_smtp_address").val('');
        $("#srv_setup_stepper_smtp_port").val(465);
        $("#srv_setup_stepper_imap_address").val('');
        $("#srv_setup_stepper_imap_port").val(993);
    }
}

function setDefaultReplyTo(val) {
    if (Hm_Utils.is_valid_email(val)) {
        $("#srv_setup_stepper_profile_reply_to").val(val);
    }
}
function display_config_step(stepNumber) {
    if(stepNumber === 2) {

        var isValid = true;

        [   {key: 'srv_setup_stepper_profile_name', value: $('#srv_setup_stepper_profile_name').val()},
            {key: 'srv_setup_stepper_email', value: $('#srv_setup_stepper_email').val()},
            {key: 'srv_setup_stepper_password', value: $('#srv_setup_stepper_password').val()}].forEach((item) => {
            if (!item.value) {
                if (item.key == 'srv_setup_stepper_password' && ($('#srv_setup_stepper_imap_server_id').val() || $('#srv_setup_stepper_smtp_server_id').val())) {
                    $(`#${item.key}-error`).text('');
                } else {
                    $(`#${item.key}-error`).text('Required');
                    isValid = false;
                }
                
            } else {
                $(`#${item.key}-error`).text('');
            }
        })

        if (!isValid) {
            return
        }

        let providerKey = getEmailProviderKey($('#srv_setup_stepper_email').val());
        getServiceDetails(providerKey);
        setDefaultReplyTo($('#srv_setup_stepper_email').val());
    }

    if(stepNumber === 3) {
        var requiredFields = [];
        var isValid = true;

        if(!$('#srv_setup_stepper_is_sender').is(':checked') && !$('#srv_setup_stepper_is_receiver').is(':checked')){
            $('#srv_setup_stepper_serve_type-error').text('Required');
            return;
        }

        if($('#srv_setup_stepper_is_sender').is(':checked') &&
            $('#srv_setup_stepper_is_receiver').is(':checked') &&
            $('#srv_setup_stepper_only_jmap').is(':checked')){
            requiredFields.push(
                {key: 'srv_setup_stepper_jmap_address', value: $('#srv_setup_stepper_jmap_address').val()},
            )
        }else {
            if($('#srv_setup_stepper_is_sender').is(':checked')){
                requiredFields.push(
                    {key: 'srv_setup_stepper_smtp_address', value: $('#srv_setup_stepper_smtp_address').val()},
                    {key: 'srv_setup_stepper_smtp_port', value: $('#srv_setup_stepper_smtp_port').val()},
                )
            }

            if($('#srv_setup_stepper_is_receiver').is(':checked')) {
                requiredFields.push(
                    {key: 'srv_setup_stepper_imap_address', value: $('#srv_setup_stepper_imap_address').val()},
                    {key: 'srv_setup_stepper_imap_port', value: $('#srv_setup_stepper_imap_port').val()},
                )
            }
        }

        if($('#srv_setup_stepper_enable_sieve').is(':checked')) {
            requiredFields.push(
                {key: 'srv_setup_stepper_imap_sieve_host', value: $('#srv_setup_stepper_imap_sieve_host').val()},
            )
        }

        requiredFields.forEach((item) => {
            if(!item.value) {
                $(`#${item.key}-error`).text('Required');
                isValid = false;
            }
            else $(`#${item.key}-error`).text('');
        })


        if(!isValid) return

        submitSmtpImapServer();
        return
    }
    // Hide all step elements
    var steps = document.querySelectorAll('.step_config');
    for (var i = 0; i < steps.length; i++) {
        steps[i].style.display = 'none';
    }

    // Show the selected step
    var selectedStep = document.getElementById('step_config_' + stepNumber);

    if (selectedStep) {
        selectedStep.style.display = 'block';
        if(stepNumber === 0) $('.srv_setup_stepper_btn').show();
    }
}

function getServiceDetails(providerKey){
    if(providerKey) {
        $("#srv_setup_stepper_provider").val(providerKey);

        Hm_Ajax.request(
            [
                {'name': 'hm_ajax_hook', 'value': 'ajax_get_nux_service_details'},
                {'name': 'nux_service', 'value': providerKey},],
            function(res) {
                if(res.service_details){
                    let serverConfig = JSON.parse(res.service_details)

                    $("#srv_setup_stepper_smtp_address").val(serverConfig.smtp.server);
                    $("#srv_setup_stepper_smtp_port").val(serverConfig.smtp.port);

                    if(serverConfig.smtp.tls)$("input[name='srv_setup_stepper_smtp_tls'][value='true']").prop("checked", true);
                    else $("input[name='srv_setup_stepper_smtp_tls'][value='false']").prop("checked", true);

                    $("#srv_setup_stepper_imap_address").val(serverConfig.server);
                    $("#srv_setup_stepper_imap_port").val(serverConfig.port);

                    if(serverConfig.tls)$("input[name='srv_setup_stepper_imap_tls'][value='true']").prop("checked", true);
                    else $("input[name='srv_setup_stepper_imap_tls'][value='false']").prop("checked", true);

                    if (serverConfig.hasOwnProperty('sieve')) {
                        $('#srv_setup_stepper_enable_sieve')
                            .prop('checked', true)
                            .trigger('change');
                        $('#srv_setup_stepper_imap_sieve_host').val(serverConfig.sieve.host + ':' + serverConfig.sieve.port);
                    } else {
                        $('#srv_setup_stepper_enable_sieve')
                            .prop('checked', false)
                            .trigger('change');;
                        $('#srv_setup_stepper_imap_sieve_host').val('');
                    }
                }
            },
            [],
            false
        );
    }
}

function getEmailProviderKey(email) {
    const emailProviderMap = {
        "all-inkl": ["all-inkl.de", "all-inkl.com"],
        "aol": ["aol.com"],
        "fastmail": ["fastmail.com"],
        "gandi": ["gandi.net"],
        "gmail": ["gmail.com"],
        "gmx": ["gmx.com", "gmx.de"],
        "icloud": ["icloud.com"],
        "inbox": ["inbox.com"],
        "kolabnow": ["kolabnow.com"],
        "mailcom": ["mail.com"],
        "mailbox": ["mailbox.org"],
        "migadu": ["migadu.com"],
        "office365": ["office365.com"],
        "outlook": ["outlook.com", "outlook.fr"],
        "postale": ["postale.io"],
        "yahoo": ["yahoo.com", "yahoo.fr"],
        "yandex": ["yandex.com", "yandex.ru"],
        "zoho": ["zoho.com"]
    };

    const emailParts = email.split("@");

    if(emailParts.length !== 2) return "";

    const provider = emailParts[1].toLowerCase();

    for (const providerKey in emailProviderMap) {
        if (emailProviderMap[providerKey].some(p => p.includes(provider))) {
            return providerKey;
        }
    }

    return "";
}

/**
 * Allow external resources for the provided element.
 *
 * @param {HTMLElement} element - The element containing the allow button.
 * @param {string} messagePart - The message part associated with the resource.
 * * @param {Boolean} inline - true if the message is displayed in inline mode, false otherwise.
 * @returns {void}
 */
function handleAllowResource(element, messagePart, inline = false) {
    element.querySelector('a').addEventListener('click', function (e) {
        e.preventDefault();
        $('.msg_text_inner').remove();
        const externalSources = $(this).data('src').split(',');
        externalSources?.forEach((source) => Hm_Utils.save_to_local_storage(source, 1));
        if (inline) {
            return inline_imap_msg(window.inline_msg_details, window.inline_msg_uid);
        }
        return get_message_content(messagePart, false, false, false, false, false);
    });
}

/**
 * Create and insert in the DOM an element containing a message and a button to allow the resource.
 *
 * @param {HTMLElement} element - The element having the blocked resource.
 * @param {Boolean} inline - true if the message is displayed in inline mode, false otherwise.
 * @returns {void}
 */
function handleInvisibleResource(element, inline = false) {
    const dataSrc = element.dataset.src;

    const allowResource = document.createElement('div');
    allowResource.classList.add('alert', 'alert-warning', 'p-1');

    const source = dataSrc.substring(0, 40) + (dataSrc.length > 40 ? '...' : '');
    allowResource.innerHTML = `Source blocked: ${element.alt ? element.alt : source}
    <a href="#" data-src="${dataSrc}" class="btn btn-light btn-sm">
    Allow</a></div>
    `;

    document.querySelector('.external_notices').insertAdjacentElement('beforeend', allowResource);
    handleAllowResource(allowResource, element.dataset.messagePart, inline);
}

const handleExternalResources = (inline) => {
    const messageContainer = document.querySelector('.msg_text_inner');
    messageContainer.insertAdjacentHTML('afterbegin', '<div class="external_notices"></div>');

    const sender = document.querySelector('#contact_info').textContent.match(EMAIL_REGEX)[0] + '_external_resources_allowed';
    const elements = messageContainer.querySelectorAll('[data-src]');
    const blockedResources = [];
    elements.forEach(function (element) {

        const dataSrc = element.dataset.src;
        const senderAllowed = Hm_Utils.get_from_local_storage(sender);
        const allowed = Hm_Utils.get_from_local_storage(dataSrc);

        switch (Number(allowed) || Number(senderAllowed)) {
            case 1:
                element.src = dataSrc;
                break;
            default:
                if ((allowed || senderAllowed) === null) {
                    Hm_Utils.save_to_local_storage(dataSrc, 0);
                }
                handleInvisibleResource(element, inline);
                blockedResources.push(dataSrc);
                break;
        }
    });

    const noticesElement = document.createElement('div');
    noticesElement.classList.add('notices');

    if (blockedResources.length) {
        const allowAll = document.createElement('div');
        allowAll.classList.add('allow_image_link', 'all', 'fw-bold');
        allowAll.textContent = 'For security reasons, external resources have been blocked.';
        if (blockedResources.length > 1) {
            const allowAllLink = document.createElement('a');
            allowAllLink.classList.add('btn', 'btn-light', 'btn-sm');
            allowAllLink.href = '#';
            allowAllLink.dataset.src = blockedResources.join(',');
            allowAllLink.textContent = 'Allow all';
            allowAll.appendChild(allowAllLink);
            handleAllowResource(allowAll, elements[0].dataset.messagePart, inline);
        }
        noticesElement.appendChild(allowAll);

        const button = document.createElement('a');
        button.classList.add('always_allow_image', 'btn', 'btn-light', 'btn-sm');
        button.textContent = 'Always allow from this sender';
        noticesElement.appendChild(button);

        button.addEventListener('click', function (e) {
            e.preventDefault();
            Hm_Utils.save_to_local_storage(sender, 1);
            $('.msg_text_inner').remove();
            if (inline) {
                inline_imap_msg(window.inline_msg_details, window.inline_msg_uid);
            } else {
                get_message_content(elements[0].dataset.messagePart, false, false, false, false, false)
            }
        });
    }

    document.querySelector('.external_notices').insertAdjacentElement('beforebegin', noticesElement);
};

const observeMessageTextMutationAndHandleExternalResources = (inline) => {
    const message = document.querySelector('.msg_text');    
    if (message) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.classList.contains('msg_text_inner')) {
                            handleExternalResources(inline);                    
                        }
                    });
                }
            });
        }).observe(message, {
            childList: true
        });
    }
};
