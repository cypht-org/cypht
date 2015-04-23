var load_github_data = function() {
    Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_github_data'}], display_github_data);
};

var display_github_data = function(res) {
    Hm_Message_List.update([0], res.formatted_message_list, 'github_all');
    Hm_Message_List.set_message_list_state('github_all')
};

if (hm_page_name() == 'servers') {
    var dsp = Hm_Utils.get_from_local_storage('.github_connect_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.github_connect_section').css('display', dsp);
    }
}


