/* Ajax multiplexer */
var Hm_Ajax = {
    request_count: 0,
    batch_callback: false,

    request: function(args, callback, extra, no_icon, batch_callback) {
        var ajax = new Hm_Ajax_Request();
        if (Hm_Ajax.request_count == 0) {
            if (!no_icon) {
                show_loading_icon();
                $('body').addClass('wait');
            }
        }
        Hm_Ajax.request_count++;
        Hm_Ajax.batch_callback = batch_callback;
        return ajax.make_request(args, callback, extra);
    }
};

var hm_loading_id = 0;
var hm_loading_pos = 0;

var show_loading_icon = function() {
    $('.loading_icon').show();
    hm_loading_pos = hm_loading_pos + 5;
    $('.loading_icon').css('background-position', hm_loading_pos+'px 0');
    hm_loading_id = setTimeout(show_loading_icon, 100);
};

var stop_loading_icon = function() {
    $('.loading_icon').hide();
    clearTimeout(hm_loading_id);
}

/* Ajax request wrapper */
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
        if (typeof res == 'string' && (res == 'null' || res.indexOf('<') == 0 || res == '{}')) {
            this.fail(res);
            return;
        }
        else if (!res) {
            this.fail(res);
            return;
        }
        else {
            if (!res.router_login_state) {
                window.location.href = "?page=notfound";
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
        if (Hm_Ajax.request_count == 0) {
            if (Hm_Ajax.batch_callback) {
                Hm_Ajax.batch_callback(res);
                Hm_Ajax.batch_callback = false;
            }
            stop_loading_icon();
            $('body').removeClass('wait');
        }
    }
}; };

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
        var index;
        for (index in Hm_Timer.jobs) {
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
            if (job[2] == 0) {
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
    sorts: {'source': 'asc', 'from': 'asc', 'subject': 'asc', 'msg_date': 'asc'},
    sort_type: 'numericasc',
    sort_fld: 'msg_date',

    update: function(ids, msgs, type) {
        var msg_ids = Hm_Message_List.add_rows(msgs);
        var count = Hm_Message_List.remove_rows(ids, msg_ids, type);
        return count;
    },

    remove_rows: function(ids, msg_ids, type) {
        var count = $('.message_table tbody tr').length;
        var i;
        for (i=0;i<ids.length;i++) {
            $('.message_table tbody tr[class^='+type+'_'+ids[i]+'_]').filter(function() {
                var id = this.className;
                if ($.inArray(id, msg_ids) == -1) {
                    count--;
                    $(this).remove();
                }
            });
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
            if (!$('.'+clean_selector(id)).length) {
                Hm_Message_List.insert_into_message_list(row);
                $('.'+clean_selector(id)).show();
            }
            else {
                timestr = $('.msg_date', $(row)).html();
                subject = $('.subject', $(row)).html();
                timeint = $('.msg_timestamp', $(row)).val();
                $('.msg_date', $('.'+clean_selector(id))).html(timestr);
                $('.subject', $('.'+clean_selector(id))).html(subject);
                $('.msg_timestamp', $('.'+clean_selector(id))).val(timeint);
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
                if (Hm_Message_List.range_start != '') {
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
        if (action_type == 'read' && hm_list_path == 'unread') {
            remove = true;
        }
        else if (action_type == 'delete') {
            remove = true;
        }
        if (remove) {
            for (index in selected) {
                class_name = selected[index];
                count--;
                $('.'+clean_selector(class_name)).remove();
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

    setup_combined_view: function(cache_name) {
        var data = get_from_local_storage(cache_name);
        if (data && data.length) {
            $('.message_table tbody').html(data);
            if (cache_name == 'formatted_unread_data') {
                Hm_Message_List.clear_read_messages();
            }
        }
        Hm_Timer.add_job(Hm_Message_List.load_sources, 60);
    },

    clear_read_messages: function() {
        var class_name;
        var list = get_from_local_storage('read_message_list');
        if (list && list.length) {
            list = JSON.parse(list);
            for (class_name in list) {
                $('.'+class_name).remove();
            }
            save_to_local_storage('read_message_list', '');
        }
    },

    update_title: function() {
        var count = 0;
        if (hm_list_path == 'unread') {
            count = $('.message_table tbody tr').length;
            document.title = count+' Unread';
        }
        else if (hm_list_path == 'flagged') {
            count = $('.message_table tbody tr').length;
            document.title = count+' Flagged';
        }
        else if (hm_list_path == 'combined_inbox') {
            count = $('.unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Everything';
        }
        else if (hm_list_path == 'email') {
            count = $('.unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Email';
        }
        else if (hm_list_path == 'feeds') {
            count = $('.unseen', $('.message_table tbody')).length;
            document.title = count+' Unread in Feeds';
        }
    }
};

var save_folder_list = function() {
    save_to_local_storage('formatted_folder_list', $('.folder_list').html());
};

var message_action = function(action_type) {
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
            reload_after_message_action,
            []
        );
        Hm_Message_List.update_after_action(action_type, selected);
    }
    return false;
};

var reload_after_message_action = function() {
    Hm_Message_List.load_sources();
};

var confirm_logout = function() {
    if ($('#unsaved_changes').val() == 0) {
        $('#logout_without_saving').click();
    }
    else {
        $('.confirm_logout').show();
    }
    return false;
};

var parse_folder_path = function(path, path_type) {
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
            return {'type': type, 'server_id' : server_id, 'folder' : folder, 'uid': uid}
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
            return {'type': type, 'server_id' : server_id, 'uid': uid}
        }
    }
    return false;
};

var prev_next_links = function(cache, class_name) {
    var href;
    var target;
    var plink = false;
    var nlink = false;
    var list = get_from_local_storage(cache);
    var current = $('<div></div>').append(list).find('.'+clean_selector(class_name));
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
};

var open_folder_list = function() {
    $('.folder_list').show();
    $('.folder_toggle').toggle();
    save_to_local_storage('hide_folder_list', '');
    return false;
};

var hide_folder_list = function() {
    if ($('.folder_list').css('display') == 'none') {
        $('.folder_list').show();
        $('.folder_toggle').hide();
    }
    else {
        $('.folder_list').hide();
        $('.folder_toggle').show();
    }
    save_to_local_storage('formatted_folder_list', $('.folder_list').html());
    save_to_local_storage('hide_folder_list', '1');
    return false;
};

var toggle_section = function(class_name, force_on) {
    if ($(class_name).length) {
        if (force_on) {
            $(class_name).css('display', 'none');
        }
        $(class_name).toggle();
        save_to_local_storage('formatted_folder_list', $('.folder_list').html());
    }
    return false;
};

var toggle_page_section = function(class_name) {
    if ($(class_name).length) {
        $(class_name).toggle();
        save_to_local_storage(class_name, $(class_name).css('display'));
    }
    return false;
};

var get_from_local_storage = function(key) {
    return sessionStorage.getItem(key);
};

var reload_folders = function(force) {
    if (document.cookie.indexOf('hm_reload_folders=1') > -1 || force) {
        update_folder_list();
        sessionStorage.clear();
        document.cookie = 'hm_reload_folders=; expires=' + new Date(0).toUTCString();
    }
};

var save_to_local_storage = function(key, val) {
    if (Storage !== void(0)) {
        sessionStorage.setItem(key, val);
    }
    return false;
};

var sort_list = function(class_name, exclude_name) {
	var folder = $('.'+class_name+' ul');
	var listitems = $('li:not(.'+exclude_name+')', folder);
	listitems.sort(function(a, b) {
		if ($(b).text().toUpperCase() == 'ALL') {
			return true;
		}
	   return $(a).text().toUpperCase().localeCompare($(b).text().toUpperCase());
	});
	$.each(listitems, function(_, itm) { folder.append(itm); });
};

var update_folder_list_display = function(res) {
    $('.folder_list').html(res.formatted_folder_list);
	sort_list('email_folders', 'menu_email');
	sort_list('feeds_folders', 'menu_feeds');
    save_to_local_storage('formatted_folder_list', res.formatted_folder_list);
    hl_selected_menu();
};

var update_folder_list = function() {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_hm_folders'}],
        update_folder_list_display,
        [],
        false
    );
    return false;
};

var clean_selector = function(str) {
    return str.replace(/(:|\.|\[|\]|\/)/g, "\\$1");
};

var hl_selected_menu = function() {
    $('.folder_list').find('*').removeClass('selected_menu');
    if (hm_list_path.length) {
        var path = hm_list_path.replace(/ /, '-');
        if (hm_page_name == 'message_list') {
            $('a', $('.'+clean_selector(path))).addClass('selected_menu');
            $('.menu_'+clean_selector(path)).addClass('selected_menu');
        }
        else if (hm_list_parent) {
            $('a', $('.'+clean_selector(hm_list_parent))).addClass('selected_menu');
            $('.menu_'+clean_selector(hm_list_parent)).addClass('selected_menu');
        }
        else {
            $('.menu_'+hm_page_name).addClass('selected_menu');
        }
    }
    else {
        $('.menu_'+hm_page_name).addClass('selected_menu');
    }
};

var set_all_mail_state = function() {
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_all_mail', data.html());
    var empty = check_empty_list();
    if (!empty) {
        $('input[type=checkbox]').click(function(e) {
            Hm_Message_List.toggle_msg_controls();
            Hm_Message_List.check_select_range(e);
        });
    }
    $('.total').text($('.message_table tbody tr').length);
    Hm_Message_List.update_title();
};

var set_combined_inbox_state = function() {
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_combined_inbox', data.html());
    var empty = check_empty_list();
    if (!empty) {
        $('input[type=checkbox]').click(function(e) {
            Hm_Message_List.toggle_msg_controls();
            Hm_Message_List.check_select_range(e);
        });
    }
    $('.total').text($('.message_table tbody tr').length);
    Hm_Message_List.update_title();
};

var set_flagged_state = function() {
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_flagged_data', data.html());
    var empty = check_empty_list();
    if (!empty) {
        $('input[type=checkbox]').click(function(e) {
            Hm_Message_List.toggle_msg_controls();
            Hm_Message_List.check_select_range(e);
        });
    }
    $('.total').text($('.message_table tbody tr').length);
    Hm_Message_List.update_title();
};

var set_unread_state = function() {
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_unread_data', data.html());
    var empty = check_empty_list();
    if (!empty) {
        $('input[type=checkbox]').click(function(e) {
            Hm_Message_List.toggle_msg_controls();
            Hm_Message_List.check_select_range(e);
        });
    }
    $('.total').text($('.message_table tbody tr').length);
    Hm_Message_List.update_title();
};

var check_empty_list = function() {
    var count = $('.message_table tbody tr').length;
    if (!count) {
        if (!$('.empty_list').length) {
            $('.message_list').append('<div class="empty_list">So alone</div>');
        }
    }
    return count == 0;
};

var track_read_messages = function(class_name) {
    var read_messages = get_from_local_storage('read_message_list');
    if (read_messages && read_messages.length) {
        read_messages = JSON.parse(read_messages);
    }
    else {
        read_messages = {};
    }
    read_messages[class_name] = 1;
    save_to_local_storage('read_message_list', JSON.stringify(read_messages));
};

var toggle_rows = function() {
    $('input[type=checkbox]').each(function () { this.checked = !this.checked; });
    Hm_Message_List.toggle_msg_controls();
    return false;
};

var toggle_long_headers = function() {
    $('.long_header').toggle();
    $('.header_toggle').toggle();
    return false;
};

var expand_core_settings = function() {
    var dsp;
    var i;
    var hash = window.location.hash;
    var sections = ['.general_setting', '.unread_setting', '.flagged_setting', '.all_setting'];
    for (i=0;i<sections.length;i++) {
        dsp = get_from_local_storage(sections[i]);
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
            save_to_local_storage(sections[i], dsp);
        }
    }
};

var set_unsaved_changes = function(state) {
    $('#unsaved_changes').val(state);
};

var folder_list = get_from_local_storage('formatted_folder_list');

if (folder_list) {
    $('.folder_list').html(folder_list);
    if (get_from_local_storage('hide_folder_list') == '1') {
        $('.folder_list').hide();
        $('.folder_toggle').show();
    }
    hl_selected_menu();
}
else {
    update_folder_list();
}

if (hm_page_name == 'settings' || hm_page_name == 'servers') {
    if (hm_page_name == 'settings') {
        expand_core_settings();
    }
    reload_folders();
}

if ($('.sys_messages').text().length) {
    $('.sys_messages').show();
    $('.sys_messages').on('click', function() {
        $('.sys_messages').hide();
        $('.sys_messages').html('');
    });
}

Hm_Timer.fire();

$(function() {
    if (hm_page_name == 'message_list') {
        if (hm_list_path == 'feeds') {
            Hm_Message_List.setup_combined_view('formatted_feed_data');
        }
        else if (hm_list_path == 'combined_inbox') {
            Hm_Message_List.setup_combined_view('formatted_combined_inbox');
        }
        else if (hm_list_path == 'email') {
            Hm_Message_List.setup_combined_view('formatted_all_mail');
        }
        else if (hm_list_path == 'unread') {
            Hm_Message_List.setup_combined_view('formatted_unread_data');
        }
        else if (hm_list_path == 'flagged') {
            Hm_Message_List.setup_combined_view('formatted_flagged_data');
        }
    }
});
