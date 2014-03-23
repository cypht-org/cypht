var pop3_test_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
        },
        {'pop3_connect': 1}
    );
};

var pop3_save_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('.save_pop3_connection').hide();
                form.find('span').hide();
                form.append('<input type="submit" value="Forget" class="forget_pop3_connection" />');
                $('.forget_pop3_connection').on('click', pop3_forget_action);
            }
        },
        {'po3_save': 1}
    );
};

var pop3_forget_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.just_forgot_credentials) {
                form.find('.credentials').attr('disabled', false);
                form.find('span').show();
                form.append('<input type="submit" value="Save" class="save_pop3_connection" />');
                $('.save_pop3_connection').on('click', pop3_save_action);
                $('.forget_pop3_connection', form).remove();
            }
        },
        {'pop3_forget': 1}
    );
};

var pop3_delete_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
            }
        },
        {'pop3_delete': 1}
    );
};

$('.test_pop3_connect').on('click', pop3_test_action);
$('.save_pop3_connection').on('click', pop3_save_action);
