/* ajax multiplexer */
var Hm_Ajax = {
    request_count: 0,
    batch_callback: false,
    icon_loading_id: 0,

    request: function(args, callback, extra, no_icon, batch_callback) {
        var ajax = new Hm_Ajax_Request();
        if (Hm_Ajax.request_count === 0) {
            if (!no_icon) {
                Hm_Ajax.show_loading_icon();
                $('body').addClass('wait');
            }
        }
        Hm_Ajax.request_count++;
        Hm_Ajax.batch_callback = batch_callback;
        return ajax.make_request(args, callback, extra);
    },

    show_loading_icon: function() {
        var hm_loading_pos = 0;
        $('.loading_icon').show();
        function move_background_image() {
            hm_loading_pos = hm_loading_pos + 5;
            $('.loading_icon').css('background-position', hm_loading_pos+'px 0');
            Hm_Ajax.icon_loading_id = setTimeout(move_background_image, 100);
        }
        move_background_image();
    },

    stop_loading_icon : function(loading_id) {
        clearTimeout(loading_id);
        $('.loading_icon').hide();
    }
};

/* ajax request wrapper */
var Hm_Ajax_Request = function() { return { 
    callback: false,
    index: 0,
    start_time: 0,

    make_request: function(args, callback, extra) {
        var name;
        this.callback = callback;
        if (extra) {
            for (name in extra) {
                args.push({'name': name, 'value': extra[name]});
            }
        }
        args.push({'name': 'hm_nonce', 'value': $('#hm_nonce').val()});

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
        if (typeof res == 'string' && (res == 'null' || res.indexOf('<') === 0 || res == '{}')) {
            this.fail(res);
            return;
        }
        else if (!res) {
            this.fail(res);
            return;
        }
        else {
            if (!res.router_login_state) {
                window.location.href = "?page=home";
            }
            if (res.date) {
                $('.date').html(res.date);
            }
            if (res.router_user_msgs && !$.isEmptyObject(res.router_user_msgs)) {
                Hm_Notices.show(res.router_user_msgs);
            }
            if (this.callback) {
                this.callback(res);
            }
        }
    },

    fail: function() {
        setTimeout(function() { Hm_Notices.show({0: 'An error occured communicating with the server'}); }, 1000);
    },

    always: function(res) {
        var dt = new Date();
        var elapsed = dt.getTime() - this.start_time;
        var msg = 'AJAX request finished in ' + elapsed + ' millis';
        Hm_Debug.add(msg);
        Hm_Ajax.request_count--;
        if (Hm_Ajax.request_count === 0) {
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

/* debug output */
var Hm_Debug = {
    max: 5,
    count: 0,
    add: function(msg) {
        Hm_Debug.count++;
        $('.debug').prepend('<div>'+msg+'</div>');
        if (Hm_Debug.check()) {
            Hm_Debug.prune();
        }
    },

    check: function() {
        return Hm_Debug.count > Hm_Debug.max;
    },

    prune: function() {
        $('.debug > div:last-child').remove();
        Hm_Debug.count--;
    }
};

/* user notification manager */
var Hm_Notices = {
    hide_id: false,

    show: function(msgs) {
        window.scrollTo(0,0);
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
            $('.sys_messages').show();
        }
        else {
            Hm_Notices.hide_id = setTimeout(function() {
                $('.sys_messages').hide();
                $('.sys_messages').html('');
                $('.sys_messages').show();
            }, 5000);
        }
    }
};

/* job scheduler */
var Hm_Timer = {
    jobs: [],
    interval: 1000,

    add_job: function(job, interval, defer) {
        if (interval) {
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
var Hm_Message_List = {
    range_start: '',
    sources: [],
    page_caches: {
        'feeds': 'formatted_feed_data',
        'combined_inbox': 'formatted_combined_inbox',
        'email': 'formatted_all_mail',
        'unread': 'formatted_unread_data',
        'flagged': 'formatted_flagged_data'
    },

    update: function(ids, msgs, type) {
        var msg_ids = Hm_Message_List.add_rows(msgs);
        var count = Hm_Message_List.remove_rows(ids, msg_ids, type);
        return count;
    },

    add_page_source: function() {
        var details;
        $('.folders a').each(function() {
            if ($(this).data('id')) {
                details = Hm_Utils.parse_folder_path($(this).data('id'));
                if (!Hm_Message_List.is_source_active(details.type, details.server_id)) {
                    /* TODO */
                }
            }
        });
        return false;
    },

    remove_page_source: function(link) {
        var details = Hm_Utils.parse_folder_path($(link).data('id'));
        if (details) {
            if (details.type == 'feeds') {
                details.type = 'feed';
            }
            Hm_Message_List.remove_source(details.type, details.server_id);
            $(".message_list tbody tr").remove();
            Hm_Message_List.load_sources();
            $(link).parent().remove();
        }
        return false;
    },

    is_source_active: function(type, id) {
        var src;
        for (var index in Hm_Message_List.sources) {
            src = Hm_Message_List.sources[index];
            if (src.type == type && src.id == id) {
                return true;
            }
        }
        return false;
    },

    remove_source: function(type, id) {
        var new_sources = [];
        var src;
        for (var index in Hm_Message_List.sources) {
            src = Hm_Message_List.sources[index];
            if (src.type != type && src.id != id) {
                new_sources.push(src);
            }
        }
        Hm_Message_List.sources = new_sources;
    },

    remove_rows: function(ids, msg_ids, type) {
        var count = $('.message_table tbody tr').length;
        var i;
        var filter_function = function() {
            var id = this.className;
            if ($.inArray(id, msg_ids) == -1) {
                count--;
                $(this).remove();
            }
        };
        for (i=0;i<ids.length;i++) {
            $('.message_table tbody tr[class^='+type+'_'+ids[i]+'_]').filter(filter_function);
        }
        return count;
    },

    add_rows: function(msgs) {
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
            if (!$('.'+Hm_Utils.clean_selector(id)).length) {
                Hm_Message_List.insert_into_message_list(row);
                $('.'+Hm_Utils.clean_selector(id)).show();
            }
            else {
                timestr = $('.msg_date', $(row)).html();
                subject = $('.subject', $(row)).html();
                timeint = $('.msg_timestamp', $(row)).val();
                $('.msg_date', $('.'+Hm_Utils.clean_selector(id))).html(timestr);
                $('.subject', $('.'+Hm_Utils.clean_selector(id))).html(subject);
                $('.msg_timestamp', $('.'+Hm_Utils.clean_selector(id))).val(timeint);
            }
            msg_ids.push(id);
        }
        return msg_ids;
    },

    insert_into_message_list: function(row) {
        var timestr = $('.msg_timestamp', $(row)).val();
        var element = false;
        var timestr2;
        $('.message_table tbody tr').each(function() {
            timestr2 = $('.msg_timestamp', $(this)).val();
            if ((timestr*1) >= (timestr2*1)) {
                element = $(this);
                return false;
            }
        });
        if (element) {
            $(row).insertBefore(element);
        }
        else {
            $('.message_table tbody').append(row);
        }
    },

    reset_checkboxes: function() {
        $('input[type=checkbox]').each(function () { this.checked = false; });
        Hm_Message_List.toggle_msg_controls();
        $('input[type=checkbox]').click(function(e) {
            Hm_Message_List.toggle_msg_controls();
            Hm_Message_List.check_select_range(e);
        });
    },

    select_range: function(start, end) {
        var found = false;
        var other = false;
        $('.message_table tbody tr').each(function() {
            if (found) {
                $('input[type=checkbox]', $(this)).prop('checked', true);
                if ($(this).prop('class') == other) {
                    return false;
                }
            }
            if ($(this).prop('class') == start) {
                found = true;
                other = end;
            }
            if ($(this).prop('class') == end) {
                found = true;
                other = start;
            }
        });
        
    },

    check_select_range: function(event_object) {
        var start;
        var end;
        if (event_object && event_object.shiftKey) {
            if (event_object.target.checked) {
                if (Hm_Message_List.range_start !== '') {
                    start = Hm_Message_List.range_start;
                    end = event_object.target.value;
                    Hm_Message_List.select_range(start, end);
                    Hm_Message_List.range_start = '';
                }
                else {
                   Hm_Message_List.range_start = event_object.target.value; 
                }
            }
        }

    },

    toggle_msg_controls: function() {
        if ($('input[type=checkbox]').filter(function() {return this.checked; }).length > 0) {
            $('.msg_controls').addClass('msg_controls_visible');
        }
        else {
            $('.msg_controls').removeClass('msg_controls_visible');
        }
    },

    update_after_action: function(action_type, selected) {
        var index;
        var remove = false;
        var class_name = false;
        var count = $(".message_list tbody tr").length;
        if (action_type == 'read' && hm_list_path() == 'unread') {
            remove = true;
        }
        else if (action_type == 'delete') {
            remove = true;
        }
        if (remove) {
            for (index in selected) {
                class_name = selected[index];
                count--;
                $('.'+Hm_Utils.clean_selector(class_name)).remove();
            }
        }
        Hm_Message_List.reset_checkboxes();
    },

    load_sources: function() {
        var index;
        var source;
        $('.empty_list').remove();
        $('.src_count').text(Hm_Message_List.sources.length);
        $('.total').text($('.message_table tbody tr').length);
        for (index in Hm_Message_List.sources) {
            source = Hm_Message_List.sources[index];
            source.callback(source.id);
        }
        return false;
    },

    select_combined_view: function() {
        if (Hm_Message_List.page_caches.hasOwnProperty(hm_list_path())) {
            Hm_Message_List.setup_combined_view(Hm_Message_List.page_caches[hm_list_path()]);
        }
        else {
            if (hm_page_name() == 'search') {
                Hm_Message_List.setup_combined_view('formatted_search_data');
            }
            else {
                Hm_Message_List.setup_combined_view(false);
            }
        }
        $('.msg_controls > a').click(function() { return Hm_Message_List.message_action($(this).data('action')); });
        $('.toggle_link').click(function() { return Hm_Message_List.toggle_rows(); });
        $('.refresh_link').click(function() { return Hm_Message_List.load_sources(); });
    },

    add_sources: function() {
        Hm_Message_List.sources = hm_data_sources();
    },

    setup_combined_view: function(cache_name) {
        Hm_Message_List.add_sources();
        var data = Hm_Utils.get_from_local_storage(cache_name);
        if (data && data.length) {
            $('.message_table tbody').html(data);
            if (cache_name == 'formatted_unread_data') {
                Hm_Message_List.clear_read_messages();
            }
        }
        if (hm_page_name() == 'search' && hm_run_search() == "0") {
            Hm_Timer.add_job(Hm_Message_List.load_sources, 60, true);
        }
        else {
            Hm_Timer.add_job(Hm_Message_List.load_sources, 60);
        }
    },

    clear_read_messages: function() {
        var class_name;
        var list = Hm_Utils.get_from_local_storage('read_message_list');
        if (list && list.length) {
            list = JSON.parse(list);
            for (class_name in list) {
                $('.'+class_name).remove();
            }
            Hm_Utils.save_to_local_storage('read_message_list', '');
        }
    },

    /* TODO: remove module specific refs */
    update_title: function() {
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
            count = $('.unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Everything';
        }
        else if (hm_list_path() == 'email') {
            count = $('.unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Email';
        }
        else if (hm_list_path() == 'feeds') {
            count = $('.unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Feeds';
        }
    },

    message_action: function(action_type) {
        var msg_list = $('.message_list');
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
                Hm_Message_List.load_sources,
                []
            );
            Hm_Message_List.update_after_action(action_type, selected);
        }
        return false;
    },

    prev_next_links: function(cache, class_name) {
        var href;
        var target;
        var plink = false;
        var nlink = false;
        var list = Hm_Utils.get_from_local_storage(cache);
        var current = $('<div></div>').append(list).find('.'+Hm_Utils.clean_selector(class_name));
        var prev = current.prev();
        var next = current.next();
        var header_links = $('.header_links');
        if (header_links.length) {
            target = header_links.parent();
        }
        else {
            target = $('.msg_headers tr').last();
        }
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
    },

    check_empty_list: function() {
        var count = $('.message_table tbody tr').length;
        if (!count) {
            if (!$('.empty_list').length) {
                $('.message_list').append('<div class="empty_list">So alone</div>');
            }
        }
        return count === 0;
    },

    track_read_messages: function(class_name) {
        var read_messages = Hm_Utils.get_from_local_storage('read_message_list');
        if (read_messages && read_messages.length) {
            read_messages = JSON.parse(read_messages);
        }
        else {
            read_messages = {};
        }
        read_messages[class_name] = 1;
        Hm_Utils.save_to_local_storage('read_message_list', JSON.stringify(read_messages));
    },

    toggle_rows: function() {
        $('input[type=checkbox]').each(function () { this.checked = !this.checked; });
        Hm_Message_List.toggle_msg_controls();
        return false;
    },

    set_message_list_state: function(list_type) {
        var data = $('.message_table tbody');
        data.find('*[style]').attr('style', '');
        Hm_Utils.save_to_local_storage(list_type, data.html());
        var empty = Hm_Message_List.check_empty_list();
        if (!empty) {
            $('input[type=checkbox]').click(function(e) {
                Hm_Message_List.toggle_msg_controls();
                Hm_Message_List.check_select_range(e);
            });
        }
        $('.total').text($('.message_table tbody tr').length);
        Hm_Message_List.update_title();
    },

    set_all_mail_state: function() { Hm_Message_List.set_message_list_state('formatted_all_mail'); },
    set_combined_inbox_state: function() { Hm_Message_List.set_message_list_state('formatted_combined_inbox'); },
    set_flagged_state: function() { Hm_Message_List.set_message_list_state('formatted_flagged_data'); },
    set_unread_state: function() { Hm_Message_List.set_message_list_state('formatted_unread_data'); },
    set_search_state: function() { Hm_Message_List.set_message_list_state('formatted_search_data'); }
};

/* folder list */
var Hm_Folders = {
    save_folder_list: function() {
        Hm_Utils.save_to_local_storage('formatted_folder_list', $('.folder_list').html());
    },
    open_folder_list: function() {
        $('.folder_list').show();
        $('.folder_toggle').toggle();
        Hm_Utils.save_to_local_storage('hide_folder_list', '');
        return false;
    },
    hide_folder_list: function() {
        if ($('.folder_list').css('display') == 'none') {
            $('.folder_list').show();
            $('.folder_toggle').hide();
        }
        else {
            $('.folder_list').hide();
            $('.folder_toggle').show();
        }
        Hm_Utils.save_to_local_storage('formatted_folder_list', $('.folder_list').html());
        Hm_Utils.save_to_local_storage('hide_folder_list', '1');
        return false;
    },
    reload_folders: function(force) {
        if (document.cookie.indexOf('hm_reload_folders=1') > -1 || force) {
            Hm_Folders.update_folder_list();
            sessionStorage.clear();
            document.cookie = 'hm_reload_folders=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        }
    },
    sort_list: function(class_name, exclude_name) {
        var folder = $('.'+class_name+' ul');
        var listitems = $('li:not(.'+exclude_name+')', folder);
        listitems.sort(function(a, b) {
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
        Hm_Utils.save_to_local_storage('formatted_folder_list', res.formatted_folder_list);
        Hm_Folders.hl_selected_menu();
        Hm_Folders.folder_list_events();
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
    },
    hl_selected_menu: function() {
        var page = hm_page_name();
        var path = hm_list_path();
        $('.folder_list').find('*').removeClass('selected_menu');
        if (path.length) {
            path = path.replace(/ /, '-');
            if (page == 'message_list') {
                $("[data-id='"+Hm_Utils.clean_selector(path)+"']").addClass('selected_menu');
                $('.menu_'+Hm_Utils.clean_selector(path)).addClass('selected_menu');
            }
            else if (hm_list_parent()) {
                $('a', $('.'+Hm_Utils.clean_selector(hm_list_parent()))).addClass('selected_menu');
                $('.menu_'+Hm_Utils.clean_selector(hm_list_parent())).addClass('selected_menu');
            }
            else {
                $('.menu_'+page).addClass('selected_menu');
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
            }
            Hm_Folders.hl_selected_menu();
            Hm_Folders.folder_list_events();
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
    toggle_section: function(class_name, force_on) {
        if ($(class_name).length) {
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
        var dsp;
        var i;
        var hash = window.location.hash;
        var sections = ['.general_setting', '.unread_setting', '.flagged_setting', '.all_setting'];
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
            if (dsp == 'table-row' || dsp == 'none') {
                $(sections[i]).css('display', dsp);
                Hm_Utils.save_to_local_storage(sections[i], dsp);
            }
        }
    },
    get_from_local_storage: function(key) {
        return sessionStorage.getItem(key);
    },
    save_to_local_storage: function(key, val) {
        if (Storage !== void(0)) {
            sessionStorage.setItem(key, val);
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
    }
};

var elog = function(val) {
    if (hm_debug()) {
        console.log(val);
    }
};

/* executes before onload, but after the DOM (just before the closing body tag) */

/* load folder list */
if (!Hm_Folders.load_from_local_storage()) {
    Hm_Folders.update_folder_list();
}

/* setup settings and server pages */
if (hm_page_name() == 'settings') {
    Hm_Utils.expand_core_settings();
    $('.settings_subtitle').click(function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    Hm_Folders.reload_folders();
}
else if (hm_page_name() == 'servers') {
    $('.server_section').click(function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    Hm_Folders.reload_folders();
}

/* show any pending notices */
Hm_Utils.show_sys_messages();

/* setup a few page wide event handlers */
Hm_Utils.cancel_logout_event();
Hm_Folders.toggle_folders_event();

/* fire up the job scheduler */
Hm_Timer.fire();

/* executes on real onload, has access to other module code */
$(function() {
    if (hm_page_name() == 'message_list' || hm_page_name() == 'search') {
        Hm_Message_List.select_combined_view();
        $('.source_link').click(function() { $('.list_sources').toggle(); return false; });
        $('.del_src_link').click(function() { return Hm_Message_List.remove_page_source(this); });
        $('.add_src_link').click(function() { return Hm_Message_List.add_page_source(); });
    }
});
