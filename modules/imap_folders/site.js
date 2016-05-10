'use strict';

var folder_page_folder_list = function(container, title, link_class, target) {
    var id = $('#imap_server_folder').val();
    var folder_location = $('.'+container);
    $('li', folder_location).not('.'+title).remove();
    var folders = $('.imap_'+id+'_').clone(false);
    $('.imap_folder_link', folders).addClass(link_class).removeClass('imap_folder_link');
    folder_location.prepend(folders);
    folder_location.show();
    $('.'+link_class, folder_location).click(function() { return expand_folders_page_list($(this).data('target'), container, link_class, target); });
    $('a', folder_location).not('.'+link_class).not('.close').unbind('click');
    $('a', folder_location).not('.'+link_class).not('.close').click(function() { set_folders_page_value($(this).data('id'), container, target); return false; });
    $('.close', folder_location).click(function() {
        folders.remove();
        folder_location.hide();
        $('.'+target).html('');
        return false;
    });
    return false;
};


var expand_folders_page_list = function(path, container, link_class, target) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+Hm_Utils.clean_selector(detail.folder), $('.'+container));
    if ($('li', list).length === 0) {
        $('.expand_link', list).html('-');
        if (detail) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                function(res) {
                    if (res.imap_expanded_folder_path) {
                        var folder_location = $('.'+container);
                        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), folder_location).append(res.imap_expanded_folder_formatted);
                        $('.imap_folder_link', folder_location).addClass(link_class).removeClass('imap_folder_link');
                        $('.'+link_class, folder_location).unbind('click');
                        $('.'+link_class, folder_location).click(function() { return expand_folders_page_list($(this).data('target'), container, link_class, target); });
                        $('a', folder_location).not('.'+link_class).not('.close').unbind('click');
                        $('a', folder_location).not('.'+link_class).not('.close').click(function() { set_folders_page_value($(this).data('id'), container, target); return false; });
                    }
                }
            );
        }
    }
    else {
        $('.expand_link', list).html('+');
        $('ul', list).remove();
    }
    return false;
};

var set_folders_page_value = function(id, container, target) {
    var list = $('.'+container);
    var list_item = $('.'+Hm_Utils.clean_selector(id), list);
    var link = $('a', list_item).first().text();
    if (link == '+' || link == '-') {
        link = $('a', list_item).eq(1).text();
    }
    $('.'+target).html(link);
    list.hide();

};
$(function() {
    if (hm_page_name() == 'folders') {
        $('#imap_server_folder').change(function() {
            $(this).parent().submit();
        });
        $('.settings_subtitle').click(function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    }
    $('.select_parent_folder').click(function() { return folder_page_folder_list('parent_folder_select', 'parent_title', 'imap_parent_folder_link', 'selected_parent'); });
    $('.select_rename_folder').click(function() { return folder_page_folder_list('rename_folder_select', 'rename_title', 'imap_rename_folder_link', 'selected_rename'); });
    $('.select_delete_folder').click(function() { return folder_page_folder_list('delete_folder_select', 'delete_title', 'imap_delete_folder_link', 'selected_delete'); });
});
