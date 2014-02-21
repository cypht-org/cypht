Hm_Ajax = {

    callback: false,

    request: function(args, callback, extra) {
        Hm_Ajax.callback = callback;
        if (extra) {
            for (name in extra) {
                args.push({'name': name, 'value': extra[name]});
            }
        }
        $.post('', args )
        .done(Hm_Ajax.done)
        .fail(Hm_Ajax.fail)
        .always(Hm_Ajax.always);
        return false;
    },

    done: function(res) {
        res = jQuery.parseJSON(res);
        if (Hm_Ajax.callback) {
            Hm_Ajax.callback(res);
        }
    },

    fail: function(res) {
        Hm_Notices.show({0: 'An error occured communicating with the server'});
    },

    always: function(res) {
    }
}
Hm_Notices = {

    show: function(msgs) {
        var msg_list = $.map(msgs, function(v) { return v; });
        $('.sys_messages').html(msg_list.join('<br />'));
    }
}
