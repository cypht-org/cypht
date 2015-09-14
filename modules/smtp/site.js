/* globals Hm_Ajax,Hm_Message_List,Hm_Utils,Hm_Folders,Hm_Background_Unread,hm_list_path,hm_msg_uid,hm_search_terms,hm_list_parent,hm_page_name,Message_List,Hm_Timer: true */

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
                Hm_Folders.reload_folders(true);
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
                Hm_Folders.reload_folders(true);
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

var save_compose_state = function(no_files) {
    var body = $('.compose_body').val();
    var subject = $('.compose_subject').val();
    var to = $('.compose_to').val();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_smtp_save_draft'},
        {'name': 'draft_body', 'value': body},
        {'name': 'draft_subject', 'value': subject},
        {'name': 'delete_uploaded_files', 'value': no_files},
        {'name': 'draft_to', 'value': to}],
        function() { },
        [],
        true
    );
};

var toggle_recip_flds = function() {
    var symbol = '+';
    if ($('.toggle_recipients').text() == '+') {
        symbol = '-';
    }
    $('.toggle_recipients').text(symbol);
    $('.recipient_fields').toggle();
    return false;
}

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

var reset_smtp_form = function() {
    $('.compose_body').val('');
    $('.compose_subject').val('');
    $('.compose_to').val('');
    $('.compose_cc').val('');
    $('.compose_bcc').val('');
    $('.ke-content', $('iframe').contents()).html('');
    $('.uploaded_files').html('');
    save_compose_state(true);
};

var upload_file = function(file) {
    var res = '';
    var form = new FormData();
    var xhr = new XMLHttpRequest;
    Hm_Ajax.show_loading_icon();
    form.append('upload_file', file);
    form.append('hm_ajax_hook', 'ajax_smtp_attach_file');
    form.append('hm_page_key', $('#hm_page_key').val());
    xhr.open('POST', '', true);
    xhr.setRequestHeader('X-Requested-With', 'xmlhttprequest');
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4){ 
            if (hm_encrypt_ajax_requests()) {
                res = Hm_Utils.json_decode(xhr.responseText);
                res = Hm_Utils.json_decode(Hm_Crypt.decrypt(res.payload));
            }
            else {
                res = Hm_Utils.json_decode(xhr.responseText);
            }
            if (res.file_details) {
                $('.uploaded_files').append(res.file_details);
                $('.delete_attachment').click(function() { return delete_attachment($(this).data('id'), this); });
            }
            Hm_Ajax.stop_loading_icon();
            if (res.router_user_msgs && !$.isEmptyObject(res.router_user_msgs)) {
                Hm_Notices.show(res.router_user_msgs);
            }
        }
    }
    xhr.send(form);
};

var delete_attachment = function(file, link) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_smtp_delete_attachment'},
        {'name': 'attachment_id', 'value': file}],
        function(res) { $(link).parent().parent().remove(); }
    );
    return false;
};

if (hm_page_name() === 'compose') {
    Hm_Timer.add_job(save_compose_state, 30, true);
    $('.toggle_recipients').click(function() { return toggle_recip_flds(); });
    $('.smtp_reset').click(function() { reset_smtp_form(); });
    $('.compose_attach_button').click(function() { $('.compose_attach_file').trigger('click'); });
    $('.compose_attach_file').change(function() { upload_file(this.files[0]); });
    if ($('.compose_cc').val() || $('.compose_bcc').val()) {
        toggle_recip_flds();
    }
    $('.delete_attachment').click(function() { return delete_attachment($(this).data('id'), this); });

}
