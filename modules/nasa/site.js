'use strict';

var nasa_disconnect = function(event) {
    if (!hm_delete_prompt()) {
        return false;
    }
    event.preventDefault();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_nasa_disconnect'},
        {'name': 'nasa_disconnect', 'value': true}],
        nasa_disconnect_result
    );
    return false;
};

var nasa_connect = function(event) {
    event.preventDefault();
    var key = $('.nasa_api_key').val();
    if (key.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_nasa_connect'},
            {'name': 'api_key', 'value': key}],
            nasa_connect_result
        );
    }
    return false;
};

var nasa_connect_result = function(res) {
    if (res.nasa_action_status) {
        $('.nasa_connect_inner_1').hide();
        $('.nasa_connect_inner_2').show();
        Hm_Folders.reload_folders(true);
    }
};

var nasa_disconnect_result = function(res) {
    $('.nasa_api_key').val('');
    $('.nasa_connect_inner_1').show();
    $('.nasa_connect_inner_2').hide();
    Hm_Folders.reload_folders(true);
};

if (hm_page_name() == 'servers') {
    $('.nasa_api_connect').on("click", nasa_connect);
    $('.nasa_api_disconnect').on("click", nasa_disconnect);
}
