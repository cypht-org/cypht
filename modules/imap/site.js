$('.imap_delete').on('click', function() {
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
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('span').hide();
            }
            if (res.just_forgot_credentials) {
                form.find('.credentials').attr('disabled', false);
                form.find('span').show();
            }
            $('.test_connect').attr('disabled', false);
            $('.imap_debug_data').html(res.imap_debug);
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

var imap_summary_update = function() {
    var ids = $('#imap_summary_ids').val();
    $('.total').html('...');
    $('.unseen').html('...');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_summary'},
        {'name': 'summary_ids', 'value': ids}],
        update_summary_display);
};

if (hm_page_name == 'home') {
    Hm_Timer.add_job(imap_summary_update, 60);
}
