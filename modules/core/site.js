'use strict';

/* ajax multiplexer */
var Hm_Ajax = {
    request_count: 0,
    callback_hooks: [],
    p_callbacks: [],
    aborted: false,
    err_condition: false,
    batch_callback: false,
    icon_loading_id: 0,

    get_ajax_hook_name: function(args) {
        var index;
        for (index in args) {
            if (args[index]['name'] == 'hm_ajax_hook') {
                return args[index]['value'];
            }
        }
        return;
    },

    request: function(args, callback, extra, no_icon, batch_callback) {
        var name = Hm_Ajax.get_ajax_hook_name(args);
        var ajax = new Hm_Ajax_Request();
        if (Hm_Ajax.request_count === 0) {
            if (!no_icon) {
                Hm_Ajax.show_loading_icon();
                $('body').addClass('wait');
            }
        }
        Hm_Ajax.request_count++;
        if (batch_callback) {
            Hm_Ajax.batch_callback = batch_callback;
        }
        return ajax.make_request(args, callback, extra, name);
    },

    show_loading_icon: function() {
        var hm_loading_pos = 0;
        $('.loading_icon').show();
        function move_background_image() {
            hm_loading_pos = hm_loading_pos + ($('.loading_icon').width()/20);
            $('.loading_icon').css('background-position', hm_loading_pos+'px 0');
            Hm_Ajax.icon_loading_id = setTimeout(move_background_image, 100);
        }
        move_background_image();
    },

    process_callback_hooks: function(name, res) {
        var hook;
        var func;
        var i;
        for (i in Hm_Ajax.callback_hooks) {
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
    },

    stop_loading_icon : function(loading_id) {
        clearTimeout(loading_id);
        $('.loading_icon').hide();
    }
};

/* ajax request wrapper */
var Hm_Ajax_Request = function() { return { 
    callback: false,
    name: false,
    index: 0,
    start_time: 0,

    make_request: function(args, callback, extra, request_name) {
        var name;
        var arg;
        this.name = request_name;
        this.callback = callback;
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
        $.ajax({
            type: "POST",
            url: '',
            data: args,
            context: this, 
            success: this.done,
            complete: this.always,
            error: this.fail
        });

        return false;
    },

    done: function(res) {
        if (Hm_Ajax.aborted) {
            return;
        }
        else if (typeof res == 'string' && (res == 'null' || res.indexOf('<') === 0 || res == '{}')) {
            this.fail(res);
            return;
        }
        else if (!res) {
            this.fail(res);
            return;
        }
        else {
            if (hm_encrypt_ajax_requests()) {
                res = Hm_Utils.json_decode(Hm_Crypt.decrypt(res.payload));
            }
            if (!res || (res.state && res.state == 'not callable') || !res.router_login_state) {
                window.location.href = "?page=home";
                return;
            }
            if (Hm_Ajax.err_condition) {
                Hm_Ajax.err_condition = false;
                Hm_Notices.hide(true);
            }
            if (res.date) {
                $('.date').html(res.date);
            }
            if (res.router_user_msgs && !$.isEmptyObject(res.router_user_msgs)) {
                Hm_Notices.show(res.router_user_msgs);
            }
            if (res.folder_status) {
                for (var name in res.folder_status) {
                    Hm_Folders.unread_counts[name] = res.folder_status[name]['unseen'];
                    Hm_Folders.update_unread_counts();
                }
            }
            if (this.callback) {
                this.callback(res);
            }
            Hm_Ajax.process_callback_hooks(this.name, res);
        }
    },

    fail: function() {
        Hm_Ajax.err_condition = true;
        setTimeout(function() { Hm_Notices.show({0: 'ERRAn error occurred communicating with the server'}); }, 1000);
    },

    always: function(res) {
        if (hm_debug()) {
            var dt = new Date();
            var elapsed = dt.getTime() - this.start_time;
            var msg = 'AJAX request finished in ' + elapsed + ' millis';
        }
        Hm_Ajax.request_count--;
        Hm_Message_List.set_checkbox_callback();
        if (Hm_Ajax.request_count === 0) {
            Hm_Ajax.aborted = false;
            Hm_Ajax.p_callbacks = [];
            if (Hm_Ajax.batch_callback) {
                Hm_Ajax.batch_callback(res);
                Hm_Ajax.batch_callback = false;
            }
            Hm_Ajax.stop_loading_icon(Hm_Ajax.icon_loading_id);
            $('body').removeClass('wait');
        }
        res = null;
    }
}; };

/* user notification manager */
var Hm_Notices = {
    hide_id: false,

    show: function(msgs) {
        var msg_list = $.map(msgs, function(v) {
            if (v.match(/^ERR/)) {
                return '<span class="err">'+v.substring(3)+'</span>';
            }
            return v;
        });
        $('.sys_messages').html(msg_list.join(', '));
        $('.sys_messages').show();
        $('.sys_messages').on('click', function() {
            $('.sys_messages').hide();
            $('.sys_messages').html('');
        });
    },

    hide: function(now) {
        if (Hm_Notices.hide_id) {
            clearTimeout(Hm_Notices.hide_id);
        }
        if (now) {
            $('.sys_messages').hide();
            $('.sys_messages').html('');
        }
        else {
            Hm_Notices.hide_id = setTimeout(function() {
                $('.sys_messages').hide();
                $('.sys_messages').html('');
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
    this.callbacks = [];

    this.page_caches = {
        'feeds': 'formatted_feed_data',
        'combined_inbox': 'formatted_combined_inbox',
        'email': 'formatted_all_mail',
        'unread': 'formatted_unread_data',
        'flagged': 'formatted_flagged_data'
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
    };

    this.update = function(ids, msgs, type, cache) {
        var completed = false;
        this.completed_count++;
        if (this.completed_count == this.sources.length) {
            this.completed_count = 0;
            completed = true;
        }
        if ($('input[type=checkbox]').filter(function() {return this.checked; }).length > 0) {
            this.run_callbacks(completed);
            Hm_Ajax.aborted = true;
            return 0;
        }
        if (msgs[0] === "") {
            this.run_callbacks(completed);
            return 0;
        }
        var msg_rows;
        if (!cache) {
            msg_rows = $('.message_table tbody');
        }
        else {
            msg_rows = cache;
        }
        if (!$.isEmptyObject(msgs)) {
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
        var row;
        var msg_rows = $('.message_table tbody');
        var count = 1;
        var key;
        $('tr', msg_rows).each(function() {
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
                parts[0] -= 0;
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

    this.add_rows = function(msgs, msg_rows) {
        var msg_ids = [];
        var row;
        var id;
        var index;
        var timestr;
        var subject;
        var timeint;
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
        var timestr = $('.msg_timestamp', $(row)).val();
        var element = false;
        var timestr2;
        $('tr', msg_rows).each(function() {
            timestr2 = $('.msg_timestamp', $(this)).val();
            if ((timestr*1) >= (timestr2*1)) {
                element = $(this);
                return false;
            }
        });
        if (element) {
            $(row, msg_rows).insertBefore(element);
        }
        else {
            msg_rows.append(row);
        }
    };

    this.reset_checkboxes = function() {
        $('input[type=checkbox]').each(function () { this.checked = false; });
        this.toggle_msg_controls();
        this.set_checkbox_callback();
    };

    this.toggle_msg_controls = function() {
        if ($('input[type=checkbox]').filter(function() {return this.checked; }).length > 0) {
            $('.msg_controls').addClass('msg_controls_visible');
        }
        else {
            $('.msg_controls').removeClass('msg_controls_visible');
        }
    };

    this.update_after_action = function(action_type, selected) {
        var remove = false;
        if (action_type == 'read' && hm_list_path() == 'unread') {
            remove = true;
        }
        if (action_type == 'unflag' && hm_list_path() == 'flagged') {
            remove = true;
        }
        else if (action_type == 'delete') {
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
        if (this.page_caches.hasOwnProperty(hm_list_path())) {
            this.set_message_list_state(this.page_caches[hm_list_path()]);
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
                $('.icon', row).html('<img width="16" height="16" src="'+hm_flag_image_src()+'" />');
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
            $('.total').text($('.message_table tbody tr').length);
        }
        for (index in self.sources) {
            source = self.sources[index];
            source.callback(source.id, source.folder);
        }
        return false;
    };

    this.select_combined_view = function() {
        if (self.page_caches.hasOwnProperty(hm_list_path())) {
            self.setup_combined_view(self.page_caches[hm_list_path()]);
        }
        else {
            if (hm_page_name() == 'search') {
                self.setup_combined_view('formatted_search_data');
            }
            else {
                self.setup_combined_view(false);
            }
        }
        $('.core_msg_control').click(function() { return self.message_action($(this).data('action')); });
        $('.toggle_link').click(function() { return self.toggle_rows(); });
        $('.refresh_link').click(function() { return self.load_sources(); });
    };

    this.add_sources = function(sources) {
        self.sources = sources;
    };

    this.setup_combined_view = function(cache_name) {
        self.add_sources(hm_data_sources());
        var data = Hm_Utils.get_from_local_storage(cache_name);
        if (data && data.length) {
            $('.message_table tbody').html(data);
            if (cache_name == 'formatted_unread_data') {
                self.clear_read_messages();
            }
            self.set_checkbox_callback();
        }
        if (hm_page_name() == 'search' && hm_run_search() == "0") {
            Hm_Timer.add_job(self.load_sources, 60, true);
        }
        else {
            Hm_Timer.add_job(this.load_sources, 60);
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
        if (hm_list_path() == 'unread') {
            count = $('.message_table tbody tr').length;
            document.title = count+' Unread';
        }
        else if (hm_list_path() == 'flagged') {
            count = $('.message_table tbody tr').length;
            document.title = count+' Flagged';
        }
        else if (hm_list_path() == 'combined_inbox') {
            count = $('tr .unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Everything';
        }
        else if (hm_list_path() == 'email') {
            count = $('tr .unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Email';
        }
        else if (hm_list_path() == 'feeds') {
            count = $('tr .unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Feeds';
        }
    };

    this.message_action = function(action_type) {
        if (action_type == 'delete' && !hm_delete_prompt()) {
            return false;
        }
        var msg_list = $('.message_table');
        var selected = [];
        $('input[type=checkbox]', msg_list).each(function() {
            if (this.checked) {
                selected.push($(this).val());
            }
        });
        if (selected.length > 0) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_message_action'},
                {'name': 'action_type', 'value': action_type},
                {'name': 'message_ids', 'value': selected}],
                false,
                []
            );
            self.update_after_action(action_type, selected);
        }
        return false;
    };

    this.prev_next_links = function(cache, class_name) {
        var href;
        var target;
        var plink = false;
        var nlink = false;
        var list = Hm_Utils.get_from_local_storage(cache);
        var current = $('<div></div>').append(list).find('.'+Hm_Utils.clean_selector(class_name));
        var prev = current.prev();
        var next = current.next();
        target = $('.msg_headers tr').last();
        if (prev.length) {
            href = prev.find('.subject').find('a').prop('href');
            plink = '<a class="plink" href="'+href+'"><div class="prevnext prev_img"></div> '+prev.find('.subject').text()+'</a>';
            $('<tr class="prev"><th colspan="2">'+plink+'</th></tr>').insertBefore(target);
        }
        if (next.length) {
            href = next.find('.subject').find('a').prop('href');
            nlink = '<a class="nlink" href="'+href+'"><div class="prevnext next_img"></div> '+next.find('.subject').text()+'</a>';
            $('<tr class="next"><th colspan="2">'+nlink+'</th></tr>').insertBefore(target);
        }
    };

    this.check_empty_list = function() {
        var count = $('.message_table tbody tr').length;
        if (!count) {
            if (!$('.empty_list').length) {
                if (hm_page_name() == 'search') {
                    $('.search_content').append('<div class="empty_list">'+hm_empty_folder()+'</div>');
                }
                else {
                    $('.message_list').append('<div class="empty_list">'+hm_empty_folder()+'</div>');
                }
            }
        }
        else {
            $('.empty_list').remove();
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

    this.adjust_unread_total = function(amount) {
        var total = $('.total_unread_count').text()*1;
        if (amount < 0 && total == 0) {
            return;
        }
        total += amount;
        $('.total_unread_count').html('&#160;'+total+'&#160;');
        Hm_Folders.save_folder_list();
    };

    this.toggle_rows = function() {
        $('input[type=checkbox]').each(function () { this.checked = !this.checked; });
        self.toggle_msg_controls();
        return false;
    };

    this.set_message_list_state = function(list_type) {
        var data = $('.message_table tbody');
        data.find('*[style]').attr('style', '');
        Hm_Utils.save_to_local_storage(list_type, data.html());
        var empty = self.check_empty_list();
        if (!empty) {
            self.set_checkbox_callback();
        }
        $('.total').text($('.message_table tbody tr').length);
        self.update_title();
        if (list_type == 'formatted_unread_data') {
            $('.total_unread_count').html('&#160;'+$('.message_table tbody tr').length+'&#160;');
            Hm_Folders.save_folder_list();
        }
    };

    this.set_checkbox_callback = function() {
        $('input[type=checkbox]').unbind('click');
        $('input[type=checkbox]').click(function(e) {
            self.toggle_msg_controls();
        });
    };

    this.set_all_mail_state = function() { self.set_message_list_state('formatted_all_mail'); };
    this.set_combined_inbox_state = function() { self.set_message_list_state('formatted_combined_inbox'); };
    this.set_flagged_state = function() { self.set_message_list_state('formatted_flagged_data'); };
    this.set_unread_state = function() { self.set_message_list_state('formatted_unread_data'); };
    this.set_search_state = function() { self.set_message_list_state('formatted_search_data'); };
};

/* folder list */
var Hm_Folders = {
    expand_after_update: false,
    unread_counts: {},

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
                if (hm_list_path() == name && hm_page_name() == 'message_list') {
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
        Hm_Utils.save_to_local_storage('hide_folder_list', '');
        $('main').css('display', 'table-cell');
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
            document.cookie = 'hm_reload_folders=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            Hm_Utils.expand_core_settings();
        }
    },
    sort_list: function(class_name, exclude_name) {
        var folder = $('.'+class_name+' ul');
        var listitems;
        if (exclude_name) {
            listitems = $('li:not(.'+exclude_name+')', folder);
        }
        else {
            listitems = $('li', folder);
        }
        listitems.sort(function(a, b) {
            if ($(b).attr('class') == 'menu_logout') {
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
        Hm_Folders.sort_list('feeds_folders', 'menu_feeds');
        Hm_Folders.sort_list('main', 'menu_search');
        Hm_Utils.save_to_local_storage('formatted_folder_list', $('.folder_list').html());
        Hm_Folders.hl_selected_menu();
        Hm_Folders.folder_list_events();
        if (Hm_Folders.expand_after_update) {
            Hm_Utils.toggle_section(Hm_Folders.expand_after_update);
        }
        Hm_Folders.expand_after_update = false;
        hl_save_link();
    },
    update_folder_list: function() {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_hm_folders'}],
            Hm_Folders.update_folder_list_display,
            [],
            false
        );
        return false;
    },
    folder_list_events: function() {
        $('.imap_folder_link').click(function() { return expand_imap_folders($(this).data('target')); });
        $('.src_name').click(function() { return Hm_Utils.toggle_section($(this).data('source')); });
        $('.update_message_list').click(function() { return Hm_Folders.update_folder_list(); });
        $('.hide_folders').click(function() { return Hm_Folders.hide_folder_list(); });
        $('.logout_link').click(function() { return Hm_Utils.confirm_logout(); });
        if (hm_search_terms()) {
            $('.search_terms').val(hm_search_terms());
        }
        $('.search_terms').on('search', function() {
            Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_reset_search'}]);
        });
    },
    hl_selected_menu: function() {
        var page = hm_page_name();
        var path = hm_list_path();
        $('.folder_list').find('*').removeClass('selected_menu');
        if (path.length) {
            if (hm_list_parent()) {
                $('a', $('.'+Hm_Utils.clean_selector(hm_list_parent()))).addClass('selected_menu');
                $('.menu_'+Hm_Utils.clean_selector(hm_list_parent())).addClass('selected_menu');
            }
            else if (page == 'message_list' || page == 'message') {
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
            return true;
        }
        return false;
    },
    toggle_folders_event: function() {
        $('.folder_toggle').click(function() { return Hm_Folders.open_folder_list(); });
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
    preserve_local_settings: function() {
        var i;
        var result = {};
        for (i in sessionStorage) {
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
        Hm_Utils.save_to_local_storage('formatted_sent_data', '');
        Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_reset_search'}],
            function(res) { window.location = '?page=search'; }, false, true);
        return false;
    },
    confirm_logout: function() {
        if ($('#unsaved_changes').val() === "0") {
            $('#logout_without_saving').click();
        }
        else {
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
        if (path.indexOf(' ') != -1) {
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
        else if (path_type == 'pop3' || path_type == 'feeds') {
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
        var sections = ['.wp_notifications_setting', '.github_all_setting', '.tfa_setting', '.sent_setting', '.general_setting', '.unread_setting', '.flagged_setting', '.all_setting', '.email_setting'];
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
        $('.header_toggle').toggle();
        return false;
    },
    set_unsaved_changes: function(state) {
        $('#unsaved_changes').val(state);
    },
    show_sys_messages: function() {
        if ($('.sys_messages').text().length) {
            $('.sys_messages').show();
            $('.sys_messages').on('click', function() {
                $('.sys_messages').hide();
                $('.sys_messages').html('');
            });
        }
    },
    prune_local_storage: function() {
        var i;
        var key;
        var value_size;
        var size = sessionStorage.length;
        if (size > 1) {
            for (i = 0; i < size; i++) {
                key = sessionStorage.key(i);
                value_size = sessionStorage.getItem(key).length;
                if (value_size > 0 && key != 'formatted_folder_list') {
                    /* candidate for pruning */
                }
            }
        }
    },
    cancel_logout_event: function() {
        $('.cancel_logout').click(function() { $('.confirm_logout').hide(); return false; });
    },
    json_encode: function(val) {
        try {
            return JSON.stringify(val);
        }
        catch (e) {
            return false;
        }
    },
    json_decode: function(val) {
        try {
            return JSON.parse(val);
        }
        catch (e) {
            return false;
        }
    }
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

/* create a default message list object */
var Hm_Message_List = new Message_List();

/* executes on onload, has access to other module code */
$(function() {

    /* setup settings and server pages */
    if (hm_page_name() == 'settings') {
        Hm_Utils.expand_core_settings();
        $('.settings_subtitle').click(function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    }
    else if (hm_page_name() == 'servers') {
        $('.server_section').click(function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    }

    /* check for folder reload */
    Hm_Folders.reload_folders();

    /* show any pending notices */
    Hm_Utils.show_sys_messages();

    /* setup a few page wide event handlers */
    Hm_Utils.cancel_logout_event();
    Hm_Folders.toggle_folders_event();

    /* fire up the job scheduler */
    Hm_Timer.fire();

    /* load folder list */
    if (!Hm_Folders.load_from_local_storage()) {
        Hm_Folders.update_folder_list();
    }
    if (hm_page_name() == 'message_list' || hm_page_name() == 'search') {
        Hm_Message_List.select_combined_view();
        $('.content_cell').swipeDown(function() { Hm_Message_List.load_sources(); });
        $('.source_link').click(function() { $('.list_sources').toggle(); return false; });
        if (hm_list_path() == 'unread' && $('.menu_unread > a').css('font-weight') == 'bold') {
            $('.menu_unread > a').css('font-weight', 'normal');
            Hm_Folders.save_folder_list();
        }
    }
    hl_save_link();
    if (hm_page_name() == 'search') {
        $('.search_reset').click(Hm_Utils.reset_search_form);
    }
    try { navigator.registerProtocolHandler("mailto", "?page=compose&compose_to=%s", "Cypht"); } catch(e) {}

    if (hm_page_name() == 'home') {
        $('.pw_update').click(function() { update_password($(this).data('id')); });
    }
    $('.content_cell').swipeRight(function() { Hm_Folders.open_folder_list(); });

});
