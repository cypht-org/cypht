var record_message = function(res) {
    var history = Hm_Utils.json_decode(Hm_Utils.get_from_local_storage('message_history'));
    if (!history) {
        history = {};
    }
    history[window.location.href] = [
        $('th', $('.header_subject')).html(),
        $('td', $('.header_from')).html(),
        $('td', $('.header_date')).html(),
        $('a', $('.content_title')).text()
    ];
    Hm_Utils.save_to_local_storage('message_history', Hm_Utils.json_encode(history));
};

var display_history_page_links = function() {
    var msg;
    var history = Hm_Utils.json_decode(Hm_Utils.get_from_local_storage('message_history'));
    for (msg in history) {
        if (history[msg][0] == null) {
            continue;
        }
        if (history[msg][1] == null) {
            history[msg][1] = '';
        }
        if (history[msg][2] == null) {
            history[msg][2] = '';
        }
        $('.history_links').append('<tr><td>'+history[msg][2]+'</td><td><a href="'+msg+'">'+history[msg][0]+'</a></td><td>'+history[msg][3]+'</td><td>'+history[msg][1]+'</td></tr>');
    }
};

$(function() {
    if (hm_page_name() == 'message') {
        Hm_Ajax.add_callback_hook(get_message_content, record_message);
    }
    else if (hm_page_name() == 'history') {
        display_history_page_links();
    }
});
