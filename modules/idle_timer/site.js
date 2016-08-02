'use strict';

var Hm_No_Op = {
    'interval': 300,
    'idle_time': 0,
    'reset': function() {
        Hm_No_Op.idle_time = 0;
        Hm_Timer.cancel(Hm_No_Op.update);
        Hm_Timer.add_job(Hm_No_Op.update, Hm_No_Op.interval, true);
    },
    'update': function() {
        Hm_No_Op.idle_time += Hm_No_Op.interval;
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_no_op'},
            {'name': 'idle_time', 'value': Hm_No_Op.idle_time}],
            function() { },
            [],
            false
        );
        return false;
    }
};

$(function() {
    Hm_Timer.add_job(Hm_No_Op.update, Hm_No_Op.interval, true);
    $('*').on('click', function() { Hm_No_Op.reset(); });
});
