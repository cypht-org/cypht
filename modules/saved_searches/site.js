var update_search = function(event) {
    var name = $('.search_name').val();
    event.preventDefault();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_update_search'},
        {'name': 'search_name', 'value': name}],
        function() { Hm_Folders.reload_folders(true, '.search_folders'); }
    );
    return false;
}
var delete_search = function(event) {
    var name = $('.search_name').val();
    event.preventDefault();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_delete_search'},
        {'name': 'search_name', 'value': name}],
        function() { Hm_Folders.reload_folders(true, '.search_folders'); }
    );
    return false;
}
var save_search = function(event) {
    var name = $('.search_name').val();
    event.preventDefault();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_save_search'},
        {'name': 'search_name', 'value': name}],
        function() { Hm_Folders.reload_folders(true, '.search_folders'); }
    );
    return false;
}
if (hm_page_name() == 'search') {
    $('.save_search').click(save_search);
    $('.update_search').click(update_search);
    $('.delete_search').click(delete_search);
}
