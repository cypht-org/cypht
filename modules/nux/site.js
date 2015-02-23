var display_next_nux_step = function(res) {
    $('.nux_step_two').html(res.nux_service_step_two);
    $('.nux_step_one').hide();
    $('.nux_submit').click(nux_add_account);
    $('.reset_nux_form').click(function() {
        $('.nux_step_one').show();
        $('.nux_step_two').html('');
        document.getElementById('service_select').getElementsByTagName('option')[0].selected = 'selected';
        $('.nux_username').val('');
        return false;
    });
}
var nux_add_account = function() {
    var service = $('#nux_service').val();
    var email = $('#nux_email').val();
    var pass = $('.nux_password').val();
    if (service.length && email.length && pass.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_nux_add_service'},
            {'name': 'nux_service', 'value': service},
            {'name': 'nux_email', 'value': email},
            {'name': 'nux_pass', 'value': pass}],
            display_final_nux_step,
            [],
            false
        );
    }
    else {
        Hm_Msgs.show({0: 'An error occurred'});
    }
    return false;
};
var display_final_nux_step = function(res) {
    console.log(res);
}
var nux_service_select = function() {
    var el = document.getElementById('service_select');
    var service = el.options[el.selectedIndex].value;
    var email = $('.nux_username').val();
    if (email.length && service.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_nux_service_select'},
            {'name': 'nux_service', 'value': service},
            {'name': 'nux_email', 'value': email}],
            display_next_nux_step,
            [],
            false
        );
    }
};

if (hm_page_name() == 'servers') {
    $('.nux_next_button').click(nux_service_select);
}
