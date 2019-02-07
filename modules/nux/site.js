'use strict';

var display_next_nux_step = function(res) {
    $('.nux_step_two').html(res.nux_service_step_two);
    $('.nux_step_one').hide();
    $('.nux_submit').on("click", nux_add_account);
    $('.reset_nux_form').on("click", function() {
        $('.nux_step_one').show();
        $('.nux_step_two').html('');
        document.getElementById('service_select').getElementsByTagName('option')[0].selected = 'selected';
        $('.nux_username').val('');
        return false;
    });
};

var nux_add_account = function() {
    var nux_border = $('.nux_username').css('border');
    $('.nux_password').css('border', nux_border);
    var service = $('#nux_service').val();
    var name = $('.nux_name').val();
    var email = $('#nux_email').val();
    var pass = $('.nux_password').val();
    if (name.length && service.length && email.length && pass.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_nux_add_service'},
            {'name': 'nux_service', 'value': service},
            {'name': 'nux_email', 'value': email},
            {'name': 'nux_name', 'value': name},
            {'name': 'nux_pass', 'value': pass}],
            display_final_nux_step,
            [],
            false
        );
    }
    else {
        if (!pass.length) {
            $('.nux_password').css('border', 'solid red 1px');
        }
    }
    return false;
};

var display_final_nux_step = function(res) {
    if (res.nux_account_added) {
        window.location.href = "?page=servers";
    }
};

var nux_service_select = function() {
    var nux_border = $('.nux_username').css('border');
    var el = document.getElementById('service_select');
    var service = el.options[el.selectedIndex].value;
    var email = $('.nux_username').val();
    var account = $('.nux_account_name').val();
    if (email.length && service.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_nux_service_select'},
            {'name': 'nux_service', 'value': service},
            {'name': 'nux_account_name', 'value': account},
            {'name': 'nux_email', 'value': email}],
            display_next_nux_step,
            [],
            false
        );
    }
    else {
        if (!email.length) {
            $('.nux_username').css('border', 'solid 1px red');
        }
        else {
            $('.nux_username').css('border', nux_border);
        }
        if (!service.length) {
            $('#service_select').css('border', 'solid 1px red');
        }
        else {
            $('#service_select').css('border', nux_border);
        }
    }
};

var expand_server_settings = function() {
    var dsp;
    var i;
    var hash = window.location.hash;
    var sections = ['.feeds_section', '.quick_add_section', '.smtp_section', '.imap_section', '.pop3_section'];
    for (i=0;i<sections.length;i++) {
        dsp = Hm_Utils.get_from_local_storage(sections[i]);
        if (hash) {
            if (hash.replace('#', '.') != sections[i]) {
                dsp = 'none';
            }
            else {
                dsp = 'block';
            }
        }
        if (dsp === 'block' || dsp === 'none') {
            $(sections[i]).css('display', dsp);
            Hm_Utils.save_to_local_storage(sections[i], dsp);
        }
    }
};

$(function() {
    if (hm_page_name() === 'servers') {
        expand_server_settings();
        $('.nux_next_button').on("click", nux_service_select);
    }
    else if (hm_page_name() === 'message_list') {
        var list_path = hm_list_path();
        if (list_path === 'unread' || list_path === 'combined_inbox' || list_path === 'flagged') {
            var data_sources = hm_data_sources();
            if (data_sources.length === 0) {
                $('.nux_empty_combined_view').show();
            }
        }
    }
});
