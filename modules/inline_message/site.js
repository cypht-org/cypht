'use strict';

var inline_wp_msg = function(uid, list_path, inline_msg_loaded_callback) {
    clear_open_msg(inline_msg_style());
    msg_container(inline_msg_style(), '.'+uid);
    wp_notice_view(uid, inline_msg_loaded_callback);
    $('div', $('.'+uid)).removeClass('unseen');
    return false;
};

var inline_github_msg = function(uid, list_path, inline_msg_loaded_callback) {
    clear_open_msg(inline_msg_style());
    msg_container(inline_msg_style(), '.'+uid);
    github_item_view(list_path, uid, inline_msg_loaded_callback);
    $('div', $('.'+uid)).removeClass('unseen');
    return false;
};

var inline_feed_msg = function(uid, list_path, inline_msg_loaded_callback) {
    clear_open_msg(inline_msg_style());
    msg_container(inline_msg_style(), '.'+list_path+'_'+uid);
    feed_item_view(uid, list_path, inline_msg_loaded_callback);
    $('div', $('.'+list_path+'_'+uid)).removeClass('unseen');
    return false;
};


var inline_msg_prep_imap_delete = function(path, uid, details) {
    $('#'+path).prop('checked', false);
    Hm_Message_List.remove_after_action('delete', [path]);
    return imap_delete_message(false, uid, details);
};

var inline_imap_unread_message = function(uid, details) {
    return imap_unread_message(uid, details);
};

var inline_imap_msg = function(details, uid, list_path, inline_msg_loaded_callback) {
    details['uid'] = uid;
    var path = '.'+details['type']+'_'+details['server_id']+'_'+uid+'_'+details['folder'];
    globals['inline_move_uuid'] = path.substr(1);
    clear_open_msg(inline_msg_style());
    msg_container(inline_msg_style(), path);

    imap_setup_message_view_page(uid, details, list_path, inline_msg_loaded_callback);
    $('.part_encoding').hide();
    $('.part_charset').hide();
    $('div', $(path)).removeClass('unseen');
    $(path).removeClass('unseen');
    update_imap_links(uid, details);
};

var msg_container = function(type, path) {
    if (type == 'right') {
        $('.content_title').after('<div class="inline_right msg_text"></div>');
        $('.message_table').css('width', '50%');
    }
    else {
        $(path).after('<tr class="inline_msg"><td colspan="6"><div class="msg_text"></div></td></tr>');
    }
    $(path).addClass('hl');
    $(path).removeClass('unseen');
};

var clear_open_msg = function(type) {
    if (type == 'right') {
        $('.msg_text').html('');
        $('.msg_text').remove();
        $('tr').removeClass('hl');
    }
    else {
        $('.inline_msg').html('');
        $('.inline_msg').remove();
        $('tr').removeClass('hl');
    }
};

var get_inline_msg_details = function(link) {
    var index;
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

var msg_inline_close = function() {
    $('.refresh_link').trigger('click');
    if (inline_msg_style() == 'right') {
        $('.msg_text').remove();
        $('.message_table').css('width', '100%');
    }
    else {
        $('.inline_msg').remove();
    }
    $('tr').removeClass('hl');
};

var update_imap_links = function(uid, details) {
    var path = details['type']+'_'+details['server_id']+'_'+uid+'_'+details['folder'];
    $('#unflag_msg').off('click');
    $('#flag_msg').off('click');
    $('#delete_message').off('click');
    $('#unread_message').off('click');
    $('#delete_message').on("click", function() { return inline_msg_prep_imap_delete(path, uid, details); });
    $('#flag_msg').on("click", function() { return imap_flag_message($(this).data('state'), uid, details); });
    $('#unflag_msg').on("click", function() { return imap_flag_message($(this).data('state', uid, details)); });
    $('#unread_message').on("click", function() { return inline_imap_unread_message(uid, details);});
};

var capture_subject_click = function() {
    $('.subject a').off('click');
    $('.subject a').on("click", function(e) {
        var msg_details = get_inline_msg_details(this);
        var uid = msg_details[0];
        var list_path = msg_details[1];
        var inline_msg_loaded_callback = function() {
            $('.header_subject th').append('<i class="bi bi-x-lg close_inline_msg"></i>');
            $('.close_inline_msg').on("click", function() { msg_inline_close(); });
            $('.msg_part_link').on("click", function() { return get_message_content($(this).data('messagePart'), uid, list_path, details, inline_msg_loaded_callback, false, $(this).data('allowImages')); });
            update_imap_links(uid, details);
        };

        if (list_path && uid) {
            var details = Hm_Utils.parse_folder_path(list_path);
            globals.msg_uid = uid;
            if (details['type'] == 'feeds') {
                inline_feed_msg(uid, list_path, inline_msg_loaded_callback);
                return false;
            }
            else if (details['type'] == 'imap') {
                // store the details and uid on the window object for later use when external resources are handled
                window.inline_msg_details = details;
                window.inline_msg_uid = uid;
                inline_imap_msg(details, uid, list_path, inline_msg_loaded_callback);
                return false;
            }
            else if (list_path.substr(0, 6) == 'github') {
                inline_github_msg(uid, list_path, inline_msg_loaded_callback);
                return false;
            }
            else if (list_path.substr(0, 3) == 'wp_') {
                inline_wp_msg(uid, list_path, inline_msg_loaded_callback);
                return false;
            }
            return false;
        }
        return true;
    });
};

function inlineMessageMessageListAndSearchPageHandler(routeParams) {
    if (window.inline_msg && inline_msg()) {
        setTimeout(capture_subject_click, 100);
        $('tr').removeClass('hl');
        Hm_Ajax.add_callback_hook('*', capture_subject_click);
        Hm_Ajax.add_callback_hook('ajax_imap_delete_message', msg_inline_close);
        Hm_Ajax.add_callback_hook('ajax_imap_move_copy_action', msg_inline_close);
        Hm_Ajax.add_callback_hook('ajax_imap_archive_message', msg_inline_close);
        if (routeParams.list_path?.substr(0, 4) !== 'imap') {
            Hm_Ajax.add_callback_hook('ajax_imap_unread', msg_inline_close);
        }
        if (routeParams.list_path?.substr(0, 4) === 'imap') {
            Hm_Ajax.add_callback_hook('ajax_imap_folder_display', capture_subject_click);
        }
    }
}

const messagesListMutation = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        if (mutation.addedNodes.length > 0) {
            mutation.addedNodes.forEach(function (node) {
                if (node.classList.contains('msg_text') || node.classList.contains('inline_msg')) {
                    observeMessageTextMutationAndHandleExternalResources(true);
                    if(node.querySelector('.msg_text_inner') && !document.querySelector('.external_notices')) {
                        handleExternalResources(true);
                    }
                }
            });
        }
    });
});

// for inline-right style, we observe the message_list element
const messagesList = document.querySelector('.message_list');
if (messagesList) {
    messagesListMutation.observe(messagesList, {
        childList: true
    });
}

// for inline style, we observe the message_table_body element
const messageTableBody = document.querySelector('.message_table_body');
if (messageTableBody) {
    messagesListMutation.observe(messageTableBody, {
        childList: true
    });
}
