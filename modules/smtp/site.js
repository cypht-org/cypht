var smtp_test_action = function(event) {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
        },
        {'smtp_connect': 1}
    );
};

var smtp_save_action = function(event) {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('.save_smtp_connection').hide();
                form.find('.smtp_password').val('');
                form.find('.smtp_password').attr('placeholder', '[saved]');
                form.append('<input type="submit" value="Forget" class="forget_smtp_connection" />');
                $('.forget_smtp_connection').on('click', smtp_forget_action);
                Hm_Utils.set_unsaved_changes(1);
                reload_folders(true);
            }
        },
        {'smtp_save': 1}
    );
};

var smtp_forget_action = function(event) {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.just_forgot_credentials) {
                form.find('.credentials').attr('disabled', false);
                form.find('.smtp_password').val('');
                form.find('.smtp_password').attr('placeholder', 'Password');
                form.append('<input type="submit" value="Save" class="save_smtp_connection" />');
                $('.save_smtp_connection').on('click', smtp_save_action);
                $('.forget_smtp_connection', form).remove();
                Hm_Utils.set_unsaved_changes(1);
                reload_folders(true);
            }
        },
        {'smtp_forget': 1}
    );
};

var smtp_delete_action = function(event) {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
            }
        },
        {'smtp_delete': 1}
    );
};

var save_compose_state = function() {
    var body = $('.compose_body').val();
    var subject = $('.compose_subject').val();
    var to = $('.compose_to').val();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_smtp_save_draft'},
        {'name': 'draft_body', 'value': body},
        {'name': 'draft_subject', 'value': subject},
        {'name': 'draft_to', 'value': to}],
        function() { },
        [],
        true
    );
};

if (hm_page_name() === 'servers') {
    $('.test_smtp_connect').on('click', smtp_test_action);
    $('.save_smtp_connection').on('click', smtp_save_action);
    $('.forget_smtp_connection').on('click', smtp_forget_action);
    $('.delete_smtp_connection').on('click', smtp_delete_action);
    var dsp = Hm_Utils.get_from_local_storage('.smtp_section');
    if (dsp === 'block' || dsp === 'none') {
        $('.smtp_section').css('display', dsp);
    }
}

if (hm_page_name() === 'compose') {
    Hm_Timer.add_job(save_compose_state, 30, true);
    $('.smtp_reset').click(function() {
        $('.compose_body').val('');
        $('.compose_subject').val('');
        $('.compose_to').val('');
        $('.ke-content', $('iframe').contents()).html('');
        save_compose_state();
    });
}
