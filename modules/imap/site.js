Hm_Folders = {
    show: function(folders) {
        var folder_html = '';
        for (folder in folders) {
            folder_html += '<div>'+folders[folder]+'</div>';
        }
        $('.imap_folder_data').html(folder_html);
    }
};

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
            Hm_Folders.show(res.imap_folders);
            $('.imap_debug_data').html(res.imap_debug);
        },
        {'imap_connect': 1}
    );
});

var update_summary_display = function(res) {
    var context;
    for (id in res.imap_summary) {
        context = $('.imap_summary_'+id);
        $('.total', context).html(res.imap_summary[id].messages);
        $('.unseen', context).html(res.imap_summary[id].unseen);
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
