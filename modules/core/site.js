/* Ajax multiplexer */
Hm_Ajax = {
    requests: [],

    request: function(args, callback, extra, no_icon) {
        var ajax = new Hm_Ajax_Request();
        ajax.index = Hm_Ajax.requests.length - 1;
        if (ajax.index < 0) {
            ajax.index = 0;
        }
        if (Hm_Ajax.requests.length == 0) {
            $("input[type='submit']").attr('disabled', true);
            if (!no_icon) {
                $('.loading_icon').css('visibility', 'visible');
            }
        }
        Hm_Ajax.requests.push(ajax);
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
        Hm_Ajax.requests.splice(this.index, 1);
        if (Hm_Ajax.requests.length == 0) {
            $("input[type='submit']").attr('disabled', false);
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
        Hm_Notices.hide();
    },

    hide: function() {
        if (Hm_Notices.hide_id) {
            clearTimeout(Hm_Notices.hide_id);
        }
        Hm_Notices.hide_id = setTimeout(function() {
            $('.sys_messages').fadeOut(1000, function() {
                $('.sys_messages').html('');
                $('.sys_messages').show('');
            });
        }, 20000);
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

/* start the scheduler */
Hm_Timer.fire();

/* setup hiding of any user notifications */
Hm_Notices.hide();
