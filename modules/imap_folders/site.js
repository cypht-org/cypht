'use strict';

var folder_page_folder_list = function(container, title, link_class, target, id_dest) {
    var id = $('#imap_server_folder').val();
    var folder_location = $('.'+container);
    $('li', folder_location).not('.'+title).remove();
    var folders = $('.folder_list .imap_'+id+'_').clone(false);
    folders.find('.manage_folders_li').remove();
    $('.imap_folder_link', folders).addClass(link_class).removeClass('imap_folder_link');
    folder_location.prepend(folders);
    folder_location.show();
    $('.'+link_class, folder_location).on("click", function() { return expand_folders_page_list($(this).data('target'), container, link_class, target, id_dest); });
    $('a', folder_location).not('.'+link_class).not('.close').off('click');
    $('a', folder_location).not('.'+link_class).not('.close').on("click", function() { set_folders_page_value($(this).data('id'), container, target, id_dest); return false; });
    $('.close', folder_location).on("click", function() {
        folders.remove();
        folder_location.hide();
        $('.'+target).html('');
        $('#'+id_dest).val('');
        return false;
    });
    return false;
};


var expand_folders_page_list = function(path, container, link_class, target, id_dest) {
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
                        var folders = $(res.imap_expanded_folder_formatted);
                        folders.find('.manage_folders_li').remove();
                        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), folder_location).append(folders);
                        $('.imap_folder_link', folder_location).addClass(link_class).removeClass('imap_folder_link');
                        $('.'+link_class, folder_location).off('click');
                        $('.'+link_class, folder_location).on("click", function() { return expand_folders_page_list($(this).data('target'), container, link_class, target, id_dest); });
                        $('a', folder_location).not('.'+link_class).not('.close').off('click');
                        $('a', folder_location).not('.'+link_class).not('.close').on("click", function() { set_folders_page_value($(this).data('id'), container, target, id_dest); return false; });
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

var set_folders_page_value = function(id, container, target, id_dest) {
    var list = $('.'+container);
    var list_item = $('.'+Hm_Utils.clean_selector(id), list);
    var link = $('a', list_item).first().text();
    if (link == '+' || link == '-') {
        link = $('a', list_item).eq(1).text();
    }
    $('.'+target).html(link);
    $('#'+id_dest).val(id);
    list.hide();

};

var folder_page_delete = function() {
    var val = $('#delete_source').val();
    var id = $('#imap_server_folder').val();
    if (!id.length) {
        Hm_Notices.show({0: 'ERR'+$('#server_error').val()});
        return;
    }
    if (!val.length) {
        Hm_Notices.show({0: 'ERR'+$('#delete_folder_error').val()});
        return;
    }
    if (!confirm($('#delete_folder_confirm').val())) {
        return;
    }
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folders_delete'},
        {'name': 'imap_server_id', value: id},
        {'name': 'folder', 'value': val}],
        function(res) {
            if (res.imap_folders_success) {
                $('#delete_source').val('');
                $('.selected_delete').html('');
                Hm_Folders.reload_folders(true);
            }
        }
    );
};

var folder_page_rename = function() {
    var val = $('#rename_value').val();
    var par = $('#rename_parent_source').val().trim();
    var folder = $('#rename_source').val().trim();
    var notices = {};
    var id = $('#imap_server_folder').val();
    if (!id.length) {
        Hm_Notices.show({0: 'ERR'+$('#server_error').val()});
        return;
    }
    if (!val.length) {
        notices[0] = 'ERR'+$('#rename_folder_error').val(); 
    }
    if (!folder.length) {
        notices[1] = 'ERR'+$('#folder_name_error').val();
    }
    if (!$.isEmptyObject(notices)) {
        Hm_Notices.show(notices);
        return;
    }
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folders_rename'},
        {'name': 'imap_server_id', value: id},
        {'name': 'folder', 'value': folder},
        {'name': 'parent', 'value': par},
        {'name': 'new_folder', 'value': val}],
        function(res) {
            if (res.imap_folders_success) {
                $('#rename_value').val('');
                $('#rename_source').val('');
                $('#rename_parent_source').val('');
                $('.selected_rename').html('');
                $('.selected_rename_parent').html('');
                Hm_Folders.reload_folders(true);
            }
        }
    );
};


var folder_page_assign_trash = function() {
    var id = $('#imap_server_folder').val();
    var folder = $('#trash_source').val();
    if (id && folder) {
        assign_special_folder(id, folder, 'trash', function(res) {
            $('#trash_val').text(res.imap_special_name);
            $('.selected_trash').text('');
        });
    }
};

var folder_page_assign_sent = function() {
    var id = $('#imap_server_folder').val();
    var folder = $('#sent_source').val();
    if (id && folder) {
        assign_special_folder(id, folder, 'sent', function(res) {
            $('#sent_val').text(res.imap_special_name);
            $('.selected_sent').text('');
        });
    }
};

var folder_page_assign_archive = function() {
    var id = $('#imap_server_folder').val();
    var folder = $('#archive_source').val();
    if (id && folder) {
        assign_special_folder(id, folder, 'archive', function(res) {
            $('#archive_val').text(res.imap_special_name);
            $('.selected_archive').text('');
        });
    }
};

var folder_page_assign_draft = function() {
    var id = $('#imap_server_folder').val();
    var folder = $('#draft_source').val();
    if (id && folder) {
        assign_special_folder(id, folder, 'draft', function(res) {
            $('#draft_val').text(res.imap_special_name);
            $('.selected_draft').text('');
        });
    }
};

var clear_special_folder = function(type) {
    var id = $('#imap_server_folder').val();
    if (id) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_clear_special_folder'},
            {'name': 'imap_server_id', 'value': id},
            {'name': 'special_folder_type', 'value': type}],
            function(res) { $('#'+type+'_val').text($('#not_set_string').val()); }
        );
    }
};

var assign_special_folder = function(id, folder, type, callback) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_special_folder'},
        {'name': 'imap_server_id', 'value': id},
        {'name': 'special_folder_type', 'value': type},
        {'name': 'folder', 'value': folder}],
        callback
    );
};

var folder_page_create = function() {
    var par = $('#create_parent').val();
    var folder = $('#create_value').val().trim();
    var id = $('#imap_server_folder').val();
    if (!id.length) {
        Hm_Notices.show({0: 'ERR'+$('#server_error').val()});
        return;
    }
    if (!folder.length) {
        Hm_Notices.show({0: 'ERR'+$('#folder_name_error').val()});
        return;
    }
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folders_create'},
        {'name': 'imap_server_id', value: id},
        {'name': 'folder', 'value': folder},
        {'name': 'parent', 'value': par}],
        function(res) {
            if (res.imap_folders_success) {
                $('#create_value').val('');
                $('#create_parent').val('');
                $('.selected_parent').html('');
                Hm_Folders.reload_folders(true);
            }
        }
    );

};

$(function() {
    if (hm_page_name() == 'folders') {
        $('#imap_server_folder').on("change", function() {
            $(this).parent().submit();
        });
        $('.settings_subtitle').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    }
    $('.select_parent_folder').on("click", function() { return folder_page_folder_list('parent_folder_select', 'parent_title', 'imap_parent_folder_link', 'selected_parent', 'create_parent'); });
    $('.select_rename_folder').on("click", function() { return folder_page_folder_list('rename_folder_select', 'rename_title', 'imap_rename_folder_link', 'selected_rename', 'rename_source'); });
    $('.select_delete_folder').on("click", function() { return folder_page_folder_list('delete_folder_select', 'delete_title', 'imap_delete_folder_link', 'selected_delete', 'delete_source'); });
    $('.select_trash_folder').on("click", function() { return folder_page_folder_list('trash_folder_select', 'trash_title', 'imap_trash_folder_link', 'selected_trash', 'trash_source'); });
    $('.select_sent_folder').on("click", function() { return folder_page_folder_list('sent_folder_select', 'sent_title', 'imap_sent_folder_link', 'selected_sent', 'sent_source'); });
    $('.select_archive_folder').on("click", function() { return folder_page_folder_list('archive_folder_select', 'archive_title', 'imap_archive_folder_link', 'selected_archive', 'archive_source'); });
    $('.select_draft_folder').on("click", function() { return folder_page_folder_list('draft_folder_select', 'draft_title', 'imap_draft_folder_link', 'selected_draft', 'draft_source'); });
    $('.select_rename_parent_folder').on("click", function() { return folder_page_folder_list('rename_parent_folder_select', 'rename_parent_title', 'imap_rename_parent_folder_link', 'selected_rename_parent', 'rename_parent_source'); });
    $('#create_folder').on("click", function() { folder_page_create(); return false; });
    $('#delete_folder').on("click", function() { folder_page_delete(); return false; });
    $('#rename_folder').on("click", function() { folder_page_rename(); return false; });

    $('#set_trash_folder').on("click", function() { folder_page_assign_trash(); return false; });
    $('#set_sent_folder').on("click", function() { folder_page_assign_sent(); return false; });
    $('#set_archive_folder').on("click", function() { folder_page_assign_archive(); return false; });
    $('#set_draft_folder').on("click", function() { folder_page_assign_draft(); return false; });

    $('#clear_trash_folder').on("click", function() { clear_special_folder('trash'); return false; });
    $('#clear_sent_folder').on("click", function() { clear_special_folder('sent'); return false; });
    $('#clear_archive_folder').on("click", function() { clear_special_folder('archive'); return false; });
    $('#clear_draft_folder').on("click", function() { clear_special_folder("draft"); return false; });
});
