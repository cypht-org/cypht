'use strict';


function submitSmtpImapServer() {
    $('#nux_config_form_loader').removeClass('hide');
    $('.step_config-actions').addClass('hide');

    var requestData = [
        { name: 'hm_ajax_hook', value: 'ajax_quick_server_setup_nux' },
        { name: 'nux_config_profile_name', value: $('#nux_config_profile_name').val() },
        { name: 'nux_config_email', value: $('#nux_config_email').val() },
        { name: 'nux_config_password', value: $('#nux_config_password').val() },
        { name: 'nux_config_provider', value: $('#nux_config_provider').val() },
        { name: 'nux_config_is_sender', value: $('#nux_config_is_sender').prop('checked') },
        { name: 'nux_config_is_receiver', value: $('#nux_config_is_receiver').prop('checked') },
        { name: 'nux_config_smtp_address', value: $('#nux_config_smtp_address').val() },
        { name: 'nux_config_smtp_port', value: $('#nux_config_smtp_port').val() },
        { name: 'nux_config_smtp_tls', value: $('input[name="nux_config_smtp_tls"]:checked').val() },
        { name: 'nux_config_imap_address', value: $('#nux_config_imap_address').val() },
        { name: 'nux_config_imap_port', value: $('#nux_config_imap_port').val() },
        { name: 'nux_config_imap_tls', value: $('input[name="nux_config_imap_tls"]:checked').val() },
        { name: 'nux_enable_sieve', value: $('#nux_enable_sieve').prop('checked') },
        { name: 'nux_create_profile', value: $('#nux_create_profile').prop('checked') },
        { name: 'nux_profile_is_default', value: $('#nux_profile_is_default').prop('checked') },
        { name: 'nux_profile_signature', value: $('#nux_profile_signature').val() },
        { name: 'nux_profile_reply_to', value: $('#nux_profile_reply_to').val() },
        { name: 'nux_imap_sieve_host', value: $('#nux_imap_sieve_host').val() },
        { name: 'nux_config_only_jmap', value: $('input[name="nux_config_only_jmap"]:checked').val() },
        { name: 'nux_config_jmap_hide_from_c_page', value: $('input[name="nux_config_jmap_hide_from_c_page"]:checked').val() },
        { name: 'nux_config_jmap_address', value: $('#nux_config_jmap_address').val() },
    ];

    Hm_Ajax.request(requestData, function(res) {
        $('#nux_config_form_loader').addClass('hide');
        $('.step_config-actions').removeClass('hide');

        if (res.just_saved_credentials) {
            $('#nux_config_stepper').find('form').trigger('reset');
            display_config_step(0);

            //Initialize the form
            $("#nux_profile_reply_to").val('');
            $("#nux_profile_signature").val('');
            $("#nux_config_profile_name").val('');
            $("#nux_config_email").val('');
            $("#nux_config_password").val('');
            $("#nux_profile_is_default").prop('checked', true);
            $("#nux_config_is_sender").prop('checked', true);
            $("#nux_config_is_receiver").prop('checked', true);
            $("#nux_enable_sieve").prop('checked', false);
            $("#nux_config_only_jmap").prop('checked', false);
            $('#step_config-imap_bloc').show();
            $('#step_config-smtp_bloc').show();
            $('#nux_profile_bloc').show();

            Hm_Utils.set_unsaved_changes(1);
            Hm_Folders.reload_folders(true);
            location.reload();
        }
    });
}

function handleCreateProfileCheckboxChange(checkbox) {
    if(checkbox.checked) {
        $('#nux_profile_bloc').show();
    }else{
        $('#nux_profile_bloc').hide();
    }
}

function handleSieveStatusChange (checkbox) {
    if(checkbox.checked) {
        $('#nux_imap_sieve_host_bloc').show();
    }else{
        $('#nux_imap_sieve_host_bloc').hide();
    }
}
function handleSmtpImapCheckboxChange(checkbox) {
    $(".step_config-smtp_imap_bloc").show();
    
    if (checkbox.id === 'nux_config_is_receiver') {
        if(checkbox.checked) $('#step_config-imap_bloc').show();
        else $('#step_config-imap_bloc').hide();
    }

    if (checkbox.id === 'nux_config_is_sender') {
        if(checkbox.checked) $('#step_config-smtp_bloc').show();
        else $('#step_config-smtp_bloc').hide();
    }

    if($('#nux_config_is_sender').prop('checked') &&
        $('#nux_config_is_receiver').prop('checked')){
        $('#nux_profile_bloc').show();
        $('#nux_profile_checkbox_bloc').show();
        $('#nux_config_jmap_select_box').show();
        $("#nux_config_only_jmap").show();
    }else{
        $("#nux_config_only_jmap").prop('checked', false);
        
        $('#nux_profile_bloc').hide();
        $('#nux_profile_checkbox_bloc').hide();
        $('#nux_config_jmap_select_box').hide();
        $("#nux_config_only_jmap").hide();

        if(!$('#nux_config_is_sender').prop('checked') &&
            !$('#nux_config_is_receiver').prop('checked')){
            $(".step_config-smtp_imap_bloc").hide();
        }
    }
}

function handleJmapCheckboxChange(checkbox) {
    if(checkbox.checked){
        $('#step_config-jmap_bloc').show();
        $('#step_config-smtp_bloc').hide();
        $('#step_config-imap_bloc').hide();
        $('#nux_profile_bloc').hide();
        $('#nux_profile_checkbox_bloc').hide();
    }else {
        $('#step_config-jmap_bloc').hide();
        $('#step_config-smtp_bloc').show();
        $('#step_config-imap_bloc').show();
        $('#nux_profile_bloc').show();
        $('#nux_profile_checkbox_bloc').show();
    }
}

function handleProviderChange(select) {
    let providerKey = select.value;
    if(providerKey) {
        Hm_Ajax.request(
            [
                {'name': 'hm_ajax_hook', 'value': 'ajax_get_nux_service_details'},
                {'name': 'nux_service', 'value': providerKey},],
            function(res) {
                if(res.service_details){
                    let serverConfig = JSON.parse(res.service_details)

                    $("#nux_config_smtp_address").val(serverConfig.smtp.server);
                    $("#nux_config_smtp_port").val(serverConfig.smtp.port);

                    if(serverConfig.smtp.tls)$("input[name='nux_config_smtp_tls'][value='true']").prop("checked", true);
                    else $("input[name='nux_config_smtp_tls'][value='false']").prop("checked", true);

                    $("#nux_config_imap_address").val(serverConfig.server);
                    $("#nux_config_imap_port").val(serverConfig.port);

                    if(serverConfig.tls)$("input[name='nux_config_imap_tls'][value='true']").prop("checked", true);
                    else $("input[name='nux_config_imap_tls'][value='false']").prop("checked", true);
                }
            },
            [],
            false
        );
    }else{
        $("#nux_config_smtp_address").val('');
        $("#nux_config_smtp_port").val(465);
        $("#nux_config_imap_address").val('');
        $("#nux_config_imap_port").val(993);
    }
}
function display_config_step(stepNumber) {
    if(stepNumber == 2) {

        var isValid = true;

        [   {key: 'nux_config_profile_name', value: $('#nux_config_profile_name').val()},
            {key: 'nux_config_email', value: $('#nux_config_email').val()},
            {key: 'nux_config_password', value: $('#nux_config_password').val()}].forEach((item) => {
                if(!item.value) {
                    $(`#${item.key}-error`).text('Required');
                    isValid = false;
                }
                else $(`#${item.key}-error`).text('');
        })

        if (!isValid) {
            return
        }

        let providerKey = getEmailProviderKey($('#nux_config_email').val());
        if(providerKey) {
            $("#nux_config_provider").val(providerKey);

            Hm_Ajax.request(
                [
                    {'name': 'hm_ajax_hook', 'value': 'ajax_get_nux_service_details'},
                    {'name': 'nux_service', 'value': providerKey},],
                function(res) {
                    if(res.service_details){
                        let serverConfig = JSON.parse(res.service_details)

                        $("#nux_config_smtp_address").val(serverConfig.smtp.server);
                        $("#nux_config_smtp_port").val(serverConfig.smtp.port);

                        if(serverConfig.smtp.tls)$("input[name='nux_config_smtp_tls'][value='true']").prop("checked", true);
                        else $("input[name='nux_config_smtp_tls'][value='false']").prop("checked", true);

                        $("#nux_config_imap_address").val(serverConfig.server);
                        $("#nux_config_imap_port").val(serverConfig.port);

                        if(serverConfig.tls)$("input[name='nux_config_imap_tls'][value='true']").prop("checked", true);
                        else $("input[name='nux_config_imap_tls'][value='false']").prop("checked", true);
                    }
                },
                [],
                false
            );
        }
    }

    if(stepNumber == 3) {
        var requiredFields = [];
        var isValid = true;

        if(!$('#nux_config_is_sender').is(':checked') && !$('#nux_config_is_receiver').is(':checked')){
            $('#nux_config_serve_type-error').text('Required');
            return;
        }
        
        if($('#nux_config_is_sender').is(':checked') && 
            $('#nux_config_is_receiver').is(':checked') && 
            $('#nux_config_only_jmap').is(':checked')){
            requiredFields.push(
                {key: 'nux_config_jmap_address', value: $('#nux_config_jmap_address').val()},
            )
        }else {
            if($('#nux_config_is_sender').is(':checked')){
                requiredFields.push(
                    {key: 'nux_config_smtp_address', value: $('#nux_config_smtp_address').val()},
                    {key: 'nux_config_smtp_port', value: $('#nux_config_smtp_port').val()},
                )
            }

            if($('#nux_config_is_receiver').is(':checked')) {
                requiredFields.push(
                    {key: 'nux_config_imap_address', value: $('#nux_config_imap_address').val()},
                    {key: 'nux_config_imap_port', value: $('#nux_config_imap_port').val()},
                )
            }
        }

        if($('#nux_enable_sieve').is(':checked')) {
            requiredFields.push(
                {key: 'nux_imap_sieve_host', value: $('#nux_imap_sieve_host').val()},
            )
        }

        requiredFields.forEach((item) => {
            if(!item.value) {
                $(`#${item.key}-error`).text('Required');
                isValid = false;
            }
            else $(`#${item.key}-error`).text('');
        })



        if(!isValid) return

        submitSmtpImapServer();
        return
    }
    // Hide all step elements
    var steps = document.querySelectorAll('.step_config');
    for (var i = 0; i < steps.length; i++) {
        steps[i].style.display = 'none';
    }

    // Show the selected step
    var selectedStep = document.getElementById('step_config_' + stepNumber);
    if (selectedStep) {
        selectedStep.style.display = 'block';
    }
}

function getEmailProviderKey(email) {
    const emailProviderMap = {
        "all-inkl": ["all-inkl.de", "all-inkl.com"],
        "aol": ["aol.com"],
        "fastmail": ["fastmail.com"],
        "gandi": ["gandi.net"],
        "gmail": ["gmail.com"],
        "gmx": ["gmx.com", "gmx.de"],
        "icloud": ["icloud.com"],
        "inbox": ["inbox.com"],
        "kolabnow": ["kolabnow.com"],
        "mailcom": ["mail.com"],
        "mailbox": ["mailbox.org"],
        "migadu": ["migadu.com"],
        "office365": ["office365.com"],
        "outlook": ["outlook.com", "outlook.fr"],
        "postale": ["postale.io"],
        "yahoo": ["yahoo.com", "yahoo.fr"],
        "yandex": ["yandex.com", "yandex.ru"],
        "zoho": ["zoho.com"]
    };

    const emailParts = email.split("@");

    if(emailParts.length !== 2) return "";

    const provider = emailParts[1].toLowerCase();

    for (const providerKey in emailProviderMap) {
        if (emailProviderMap[providerKey].some(p => p.includes(provider))) {
            return providerKey;
        }
    }

    return "";
}


var display_next_nux_step = function(res) {
    $('.nux_step_two').html(res.nux_service_step_two);
    $('.nux_step_one').hide();
    $('.nux_submit').on("click", nux_add_account);
    $('.reset_nux_form').on("click", function() {
        $('.nux_step_one').show();
        $('.nux_step_two').html('');
        document.getElementById('service_select').getElementsByTagName('option')[0].selected = 'selected';
        $('.nux_username').val('');
        $('.nux_extra_fields').remove();
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
    var extra_fields = [];
    $('input.nux_extra_fields').each(function () {
        extra_fields.push({ 'name': $(this).attr('id'), 'value': $(this).val() });
    });
    if (name.length && service.length && email.length && pass.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_nux_add_service'},
            {'name': 'nux_service', 'value': service},
            {'name': 'nux_email', 'value': email},
            {'name': 'nux_name', 'value': name},
            {'name': 'nux_pass', 'value': pass}, ...extra_fields],
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
        if (confirm('Do you accept special folders?')) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_accept_special_folders'},
                {'name': 'imap_server_id', value: res.nux_server_id},
                {'name': 'imap_service_name', value: res.nux_service_name}],
                function(res) {
                    window.location.href = "?page=servers";
                }
            );
        }

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
    var sections = ['.feeds_section', '.quick_add_section', '.smtp_section', '.imap_section'];
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

var add_extra_fields = function(select, id, label, placeholder) {
    $(select).next().next().after('<input type="text" id="nux_'+id+'" class="nux_extra_fields" placeholder="'+placeholder+'"><label class="screen_reader nux_extra_fields" for="nux_'+id+'">'+label+'</label><br class="nux_extra_fields">');
};

$(function() {
    if (hm_page_name() === 'servers') {
        expand_server_settings();
        $('.nux_next_button').on("click", nux_service_select);
        $('#service_select').on("change", function() {
            if ($(this).val() == 'all-inkl') {
                add_extra_fields(this, 'all_inkl_login', 'Login', 'Your All-inkl Login');
            } else {
                $('.nux_extra_fields').remove();
            }
        });
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
