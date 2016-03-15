var inline_wp_msg = function(uid, list_path) {
    $('.msg_text').html('');
    $('.msg_text').remove();
    $('tr').removeClass('hl');
    $('.content_title').after('<div class="msg_text"></div>');
    $('.message_table').css('width', '50%');
    $('.'+uid).addClass('hl');
    wp_notice_view(uid, inline_msg_loaded_callback);
    $('.'+uid).removeClass('unseen');
    $('div', $('.'+uid)).removeClass('unseen');
    return false;
};

var inline_github_msg = function(uid, list_path) {
    $('.msg_text').html('');
    $('.msg_text').remove();
    $('tr').removeClass('hl');
    $('.content_title').after('<div class="msg_text"></div>');
    $('.message_table').css('width', '50%');
    $('.'+uid).addClass('hl');
    github_item_view(list_path, uid, inline_msg_loaded_callback);
    $('.'+uid).removeClass('unseen');
    $('div', $('.'+uid)).removeClass('unseen');
    return false;
};

var inline_feed_msg = function(uid, list_path) {
    $('.msg_text').html('');
    $('.msg_text').remove();
    $('tr').removeClass('hl');
    $('.content_title').after('<div class="msg_text"></div>');
    $('.message_table').css('width', '50%');
    $('.'+list_path+'_'+uid).addClass('hl');
    feed_item_view(uid, list_path, inline_msg_loaded_callback);
    $('div', $('.'+list_path+'_'+uid)).removeClass('unseen');
    return false;
};

var inline_imap_msg = function(details, uid, list_path) {
    details['uid'] = uid;
    path = '.'+details['type']+'_'+details['server_id']+'_'+uid+'_'+details['folder'];
    $('.msg_text').html('');
    $('.msg_text').remove();
    $('tr').removeClass('hl');
    $('.content_title').after('<div class="msg_text"></div>');
    $('.message_table').css('width', '50%');
    $(path).addClass('hl');
    imap_setup_message_view_page(uid, details, list_path, inline_msg_loaded_callback);
    $('div', $(path)).removeClass('unseen');
    $(path).removeClass('unseen');
};

var get_inline_msg_details = function(link) {
    var pair;
    var uid = false;
    var list_path = false;
    var pairs = $(link).attr('href').split('&');
    for (index in pairs) {
        pair = pairs[index].split('=');
        if (pair[0] == 'uid') {
            uid = pair[1];
        }
        if (pair[0] == 'list_path') {
            list_path = pair[1];
        }
    }
    return [uid, list_path];
};

var inline_msg_loaded_callback = function() {
    $('.header_subject th').append('<span class="close_inline_msg">X</span>');
    $('.close_inline_msg').click(function() {
        Hm_Message_List.load_sources();
        Hm_Folders.open_folder_list();
        $('.msg_text').remove();
        $('.message_table').css('width', '100%');
        $('tr').removeClass('hl');
    });
    $('.msg_part_link').click(function() { return get_message_content($(this).data('messagePart'), uid, list_path, details, inline_msg_loaded_callback); });
};

var capture_subject_click = function() {
    $('a', $('.subject')).click(function(e) {
        var msg_details = get_inline_msg_details(this); 
        var uid = msg_details[0];
        var list_path = msg_details[1];

        if (list_path && uid) {
            Hm_Folders.hide_folder_list(true);
            var details = Hm_Utils.parse_folder_path(list_path);
            if (details['type'] == 'feeds') {
                inline_feed_msg(uid, list_path);
                return false;
            }
            else if (details['type'] == 'imap') {
                inline_imap_msg(details, uid, list_path);
                return false;
            }
            else if (list_path.substr(0, 6) == 'github') {
                inline_github_msg(uid, list_path);
                return false;
            }
            else if (list_path.substr(0, 3) == 'wp_') {
                inline_wp_msg(uid, list_path);
                return false;
            }
        }
        return true;
    });
};

$(function() {
    if (hm_page_name() == 'message_list' || hm_page_name() == 'search') {
        if (inline_msg()) {
            capture_subject_click();
            $('tr').removeClass('hl');
            Hm_Message_List.callbacks.push(capture_subject_click);
            if (hm_list_path().substr(0, 4) === 'imap') {
                Hm_Ajax.add_callback_hook(select_imap_folder, capture_subject_click);
                $('.refresh_list').click(function() { select_imap_folder(hm_list_path(), capture_subject_click); });
            }
        }
    }
});
