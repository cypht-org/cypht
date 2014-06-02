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
                Hm_Notices.hide(true);
            }
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
            var section_state = $(class_name).css('display').toLowerCase();
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_save_section_state'},
                {'name': 'section_state', 'value': section_state},
                {'name': 'section_class', 'value': class_name}],
                false,
                [],
                true
            );
        });
    }
    return false;
};

var clean_selector = function(str) {
    return str.replace(/(:|\.|\[|\]|\/)/g, "\\$1");
};

/* start the scheduler */
Hm_Timer.fire();
$('.folder_list').find('*').removeClass('selected_menu');
$('.menu_'+hm_page_name).addClass('selected_menu');
