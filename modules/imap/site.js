var imap_delete_action = function() {
    $('.imap_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
            }
        },
        {'imap_delete': 1}
    );
};
$('.imap_delete').on('click', imap_delete_action);

$('.save_connection').on('click', function() {
    $('.imap_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('.save_connection').hide();
                form.find('span').hide();
                form.append('<input type="submit" value="Delete" class="imap_delete" />');
                $('.imap_delete').on('click', imap_delete_action);
            }
        },
        {'imap_save': 1}
    );
});

$('.forget_connection').on('click', function() {
    $('.imap_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
            }
        },
        {'imap_forget': 1}
    );
});

$('.test_connect').on('click', function() {
    $(this).attr('disabled', true);
    $('.imap_debug_data').empty();
    $('.imap_folder_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            $('.test_connect').attr('disabled', false);
        },
        {'imap_connect': 1}
    );
});

var update_summary_display = function(res) {
    var context;
    var unseen;
    var messages;
    for (id in res.imap_summary) {
        context = $('.imap_summary_'+id);
        messages = res.imap_summary[id].messages;
        unseen = res.imap_summary[id].unseen;
        if (!unseen) {
            unseen = 0;
        }
        if (!messages) {
            messages = 0;
        }
        $('.total', context).html(messages);
        $('.unseen', context).html(unseen);
    }
};

var update_unread_message_display = function(res) {
    if (res.imap_unread_unchanged) {
        console.log("Nothing to update");
        return;
    }
    var count = 0;
    var empty = $('.empty_table', $(res.formatted_unread_data));
    if (empty.length == 0) {
        count = $('tr', $(res.formatted_unread_data)).length - 1;
    }
    var title = document.title;
    if (title.search(/\(\d+\)/) != -1) {
        title = title.replace(/\(\d+\)/, ' ('+count+')');
    }
    else {
        title = title + ' ('+count+')';
    }
    document.title = title;
    $('h1').text('HM3 - '+count);
    $('.unread_messages').html(res.formatted_unread_data);
    if (count > 1) {
        $('table', $('.unread_messages')).tablesorter();
    }
};

var imap_unread_update = function(loading) {
    var ids = $('#imap_unread_ids').val();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_unread'},
        {'name': 'imap_unread_ids', 'value': ids}],
        update_unread_message_display,
        [],
        loading
    );
};

var imap_summary_update = function() {
    var ids = $('#imap_summary_ids').val();
    $('.total').html('...');
    $('.unseen').html('...');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_summary'},
        {'name': 'summary_ids', 'value': ids}],
        update_summary_display
    );
};

if (hm_page_name == 'home') {
    Hm_Timer.add_job(imap_summary_update, 60);
}
else if (hm_page_name == 'unread') {
    imap_unread_update(true);
    Hm_Timer.add_job(imap_unread_update, 60, true);
    $( document ).ready(function() {
        $('.unread_messages').show(1000);
    });
}
