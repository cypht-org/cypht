Hm_Ajax = {

    callback: false,

    request: function(args, callback, extra, no_icon) {
        $("input[type='submit']").attr('disabled', true);
        Hm_Ajax.callback = callback;
        if (extra) {
            for (name in extra) {
                args.push({'name': name, 'value': extra[name]});
            }
        }
        if (!no_icon) {
            $('.loading_icon').css('visibility', 'visible');
        }
        $.post('', args )
        .done(Hm_Ajax.done)
        .fail(Hm_Ajax.fail)
        .always(Hm_Ajax.always);

        return false;
    },

    done: function(res) {
        if (typeof res == 'string' && (res == 'null' || res.indexOf('<') == 0)) {
            Hm_Ajax.fail(res);
            return;
        }
        else if (!res) {
            Hm_Ajax.fail(res);
            return;
        }
        else {
            res = jQuery.parseJSON(res);
            if (res.date) {
                $('.date').html(res.date);
            }
            if (Hm_Ajax.callback) {
                Hm_Ajax.callback(res);
            }
        }
    },

    fail: function(res) {
        Hm_Notices.show({0: 'An error occured communicating with the server'});
    },

    always: function(res) {
        $('.loading_icon').css('visibility', 'hidden');
        $("input[type='submit']").attr('disabled', false);
    }
};

Hm_Notices = {

    show: function(msgs) {
        var msg_list = $.map(msgs, function(v) { return v; });
        $('.sys_messages').html(msg_list.join('<br />'));
    }
};

Hm_Timer = {

    jobs: [],
    interval: 1000,

    add_job: function(job, interval, defer) {
        Hm_Timer.jobs.push([job, interval, interval]);
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

Hm_Timer.fire();

setTimeout(
    function() { $('.sys_messages').empty(); },
    20000
);

