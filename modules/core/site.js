/* Ajax multiplexer */
Hm_Ajax = {
    request_count: 0,
    batch_callback: false,

    request: function(args, callback, extra, no_icon, batch_callback) {
        var ajax = new Hm_Ajax_Request();
        if (Hm_Ajax.request_count == 0) {
            if (!no_icon) {
                $('.loading_icon').css('visibility', 'visible');
            }
        }
        Hm_Ajax.request_count++;
        Hm_Ajax.batch_callback = batch_callback;
        return ajax.make_request(args, callback, extra);
    }
};

/* Ajax request wrapper */
Hm_Ajax_Request = function() { return { 

    callback: false,
    index: 0,
    start_time: 0,

    make_request: function(args, callback, extra) {
        this.callback = callback;
        if (extra) {
            for (name in extra) {
                args.push({'name': name, 'value': extra[name]});
            }
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
        if (typeof res == 'string' && (res == 'null' || res.indexOf('<') == 0 || res == '{}')) {
            this.fail(res);
            return;
        }
        else if (!res) {
            this.fail(res);
            return;
        }
        else {
            res = jQuery.parseJSON(res);
            if (res.date) {
                $('.date').html(res.date);
            }
            if (res.router_user_msgs && !jQuery.isEmptyObject(res.router_user_msgs)) {
                Hm_Notices.show(res.router_user_msgs);
            }
            if (this.callback) {
                this.callback(res);
            }
        }
    },

    fail: function(res) {
        Hm_Notices.show({0: 'An error occured communicating with the server'});
    },

    always: function(res) {
        var dt = new Date();
        var elapsed = dt.getTime() - this.start_time;
        var msg = 'AJAX request finished in ' + elapsed + ' millis';
        if (elapsed > 2000) {
            msg += '. Ouch!';
        }
        $('.elapsed').html(msg);
        Hm_Ajax.request_count--;
        if (Hm_Ajax.request_count == 0) {
            if (Hm_Ajax.batch_callback) {
                Hm_Ajax.batch_callback(res);
                Hm_Ajax.batch_callback = false;
            }
            $('.loading_icon').css('visibility', 'hidden');
        }
    }
}; };

/* user notification manager */
Hm_Notices = {

    hide_id: false,

    show: function(msgs) {
        var msg_list = $.map(msgs, function(v) {
            if (v.match(/^ERR/)) {
                return '<span class="err">'+v.substring(3)+'</span>';
            }
            return v;
        });
        $('.sys_messages').html(msg_list.join(', '));
    },

    hide: function(now) {
        if (Hm_Notices.hide_id) {
            clearTimeout(Hm_Notices.hide_id);
        }
        if (now) {
            $('.sys_messages').fadeOut(300, function() {
                $('.sys_messages').html('');
                $('.sys_messages').show('');
            });
        }
        else {
            Hm_Notices.hide_id = setTimeout(function() {
                $('.sys_messages').fadeOut(1000, function() {
                    $('.sys_messages').html('');
                    $('.sys_messages').show('');
                });
            }, 5000);
        }
    }
};

/* job scheduler */
Hm_Timer = {

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

    update: function(ids, msgs, type) {
        if (msgs && !jQuery.isEmptyObject(msgs)) {
            $('.empty_list').remove();
        }
        var msg_ids = Hm_Message_List.add_rows(msgs, type);
        var count = Hm_Message_List.remove_rows(ids, msg_ids);
        return count;
    },

    remove_rows: function(ids, msg_ids, type) {
        var count = $('.message_table tbody tr').length;
        for (i=0;i<ids.length;i++) {
            $('.message_table tbody tr[class^='+type+'_'+ids[i]+'_]').filter(function() {
                var id = this.className;
                if (jQuery.inArray(id, msg_ids) == -1) {
                    count--;
                    $(this).remove();
                }
            });
        }
        return count;
    },

    add_rows: function(msgs) {
        var msg_ids = [];
        for (index in msgs) {
            row = msgs[index][0];
            id = msgs[index][1];
            if (!$('.'+clean_selector(id)).length) {
                insert_into_message_list(row);
                $('.'+clean_selector(id)).fadeIn(300);
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

    reset_checkboxes: function() {
        $(':checkbox').each(function () { this.checked = false; });
        Hm_Message_List.toggle_msg_controls();
        $(':checkbox').click(function(e) {
            Hm_Message_List.toggle_msg_controls();
            Hm_Message_List.check_select_range(e);
        });
    },

    select_range: function(start, end) {
        var found = false;
        var other = false;
        $('.message_table tbody tr').each(function() {
            if (found) {
                console.log($(':checkbox', $(this)));
                $(':checkbox', $(this)).prop('checked', true);
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
                if (Hm_Message_List.range_start) {
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

    remove_from_cache: function(cached_list_name, row_class) {
        var count = 0;
        var cache_data = get_from_local_storage(cached_list_name);
        if (cache_data) {
            var adjusted_data = $('<div></div>').append(cache_data).find('tr').remove('.'+clean_selector(row_class)).end().html();
            save_to_local_storage(cached_list_name, adjusted_data);
            count = $(adjusted_data).length;
        }
        return count;
    },

    toggle_msg_controls: function() {
        if ($('input:checked').length > 0) {
            $('.msg_controls a').removeClass('disabled_link');
        }
        else {
            $('.msg_controls a').addClass('disabled_link');
        }
    },

    update_after_action: function(action_type, selected) {
        var remove = false;
        var row = false;
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
                $('.'+clean_selector(class_name)).fadeOut(200, function() { $(this).remove(); });
            }
        }
        if (hm_list_path == 'unread') {
            set_unread_count(count);
        }
        for (index in selected) {
            class_name = selected[index];
            Hm_Message_List.remove_from_cache('formatted_unread_data', class_name);
        }
        Hm_Message_List.reset_checkboxes();
    }
};

var confirm_logout = function() {
    $('.confirm_logout').fadeIn(200);
    return false;
};

var parse_folder_path = function(path, path_type) {
    var type = false;
    var server_id = false;
    var folder = '';

    if (path_type == 'imap') {
        parts = path.split('_', 3);
        if (parts.length == 2) {
            type = parts[0];
            server_id = parts[1];
        }
        else if (parts.length == 3) {
            type = parts[0];
            server_id = parts[1];
            folder = parts[2];
        }
        if (type && server_id) {
            return {'type': type, 'server_id' : server_id, 'folder' : folder}
        }
    }
    else if (path_type == 'pop3') {
        parts = path.split('_', 2);
        if (parts.length == 2) {
            type = parts[0];
            server_id = parts[1];
        }
        if (type && server_id) {
            return {'type': type, 'server_id' : server_id}
        }
    }
    return false;
};

var toggle_section = function(class_name) {
    if ($(class_name).length) {
        $(class_name).toggle(200, function() {
            save_to_local_storage('formatted_folder_list', $('.folder_list').html());
        });
    }
    return false;
};

var get_from_local_storage = function(key) {
    return sessionStorage.getItem(key);
};

var save_to_local_storage = function(key, val) {
    if (typeof(Storage) !== "undefined") {
        sessionStorage.setItem(key, val);
    }
    return false;
};

var update_folder_list_display = function(res) {
    $('.folder_list').html(res.formatted_folder_list);
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
    if (hm_page_name == 'message_list') {
        if (hm_list_path.substring(0, 4) == 'imap') {
            $('a:eq(0)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
            $('a:eq(1)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
        }
        else {
            $('.menu_'+clean_selector(hm_list_path)).addClass('selected_menu');
        }
    }
    else if (hm_list_parent) {
        $('.menu_'+clean_selector(hm_list_parent)).addClass('selected_menu');
    }
    else {
        $('.menu_'+hm_page_name).addClass('selected_menu');
    }
};

var folder_list = get_from_local_storage('formatted_folder_list');


if (folder_list) {
    $('.folder_list').html(folder_list);
    hl_selected_menu();
}
else {
    update_folder_list();
}

$('body').fadeIn(300);
Hm_Timer.fire();
