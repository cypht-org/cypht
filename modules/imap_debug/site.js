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
            Hm_Folders.show(res.imap_folders);
            $('.imap_debug_data').html(res.imap_debug);
        },
        {'imap_connect': 1}
    );
});
