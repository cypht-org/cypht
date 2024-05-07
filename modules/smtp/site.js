'use strict';

var get_smtp_profile = function(profile_value) {
    if (typeof profile_value === "undefined" || profile_value == "0" || profile_value == "") {
        Hm_Notices.show([err_msg('Please create a profile for saving sent messages option')]);
    }
    else {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_profiles_status'},
            {'name': 'profile_value', 'value': profile_value}],
            function(res) {
            }
        );
    }
};

var check_attachment_dir_access = function() {
    Hm_Notices.show([err_msg('Attachment storage unavailable, please contact your site administrator')]);
};

var smtp_test_action = function(event) {
    event.preventDefault();
    var form = $(this).closest('.smtp_connect');
    Hm_Notices.hide(true);
    Hm_Ajax.request(
        form.serializeArray(),
        false,
        {'smtp_connect': 1}
    );
};

var smtp_save_action = function(event) {
    event.preventDefault();
    var form = $(this).closest('.smtp_connect');
    var btnContainer = $(this).parent();
    Hm_Notices.hide(true);
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('.save_smtp_connection').hide();
                form.find('.smtp_password').val('');
                form.find('.smtp_password').attr('placeholder', '[saved]');
                btnContainer.append('<input type="submit" value="Forget" class="forget_smtp_connection btn btn-outline-secondary btn-sm me-2" />');
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
    var form = $(this).closest('.smtp_connect');
    var btnContainer = $(this).parent();
    Hm_Notices.hide(true);
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.just_forgot_credentials) {
                form.find('.credentials').prop('disabled', false);
                form.find('.credentials').val('');
                btnContainer.append('<input type="submit" value="Save" class="save_smtp_connection btn btn-outline-secondary btn-sm me-2" />');
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
    if (!hm_delete_prompt()) {
        return false;
    }
    event.preventDefault();
    Hm_Notices.hide(true);
    var form = $(this).closest('.smtp_connect');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.deleted_server_id) {
                form.parent().fadeOutAndRemove()
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
                decrease_servers('smtp');
            }
        },
        {'smtp_delete': 1}
    );
};

var smtp_delete_draft = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_smtp_delete_draft'},
        {'name': 'draft_id', 'value': id}],
        function(res) {
            if (res.draft_id != -1) {
                $('.draft_'+id).remove();
                $('.draft_list').toggle();
            }
        }
    );
};

var send_archive = function() {
    $('.compose_post_archive').val(1);
    document.getElementsByClassName("smtp_send_placeholder")[0].click();
}

var save_compose_state = function(no_files, notice) {
    var no_icon = true;
    if (notice) {
        no_icon = false;
    }
    var uploaded_files = $("input[name='uploaded_files[]']").map(function(){return $(this).val();}).get();
    var body = $('.compose_body').val();
    var subject = $('.compose_subject').val();
    var to = $('.compose_to').val();
    var smtp = $('.compose_server').val();
    var cc = $('.compose_cc').val();
    var bcc = $('.compose_bcc').val();
    var inreplyto = $('.compose_in_reply_to').val();

    var draft_id = $('.compose_draft_id').val();
    if (globals.draft_state == body+subject+to+smtp+cc+bcc+uploaded_files) {
        return;
    }
    globals.draft_state = body+subject+to+smtp+cc+bcc+uploaded_files;

    if (!body && !subject && !to && !cc && !bcc) {
        return;
    }

    $('.smtp_send_placeholder').prop('disabled', true);
    $('.smtp_send_placeholder').addClass('disabled_input');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_smtp_save_draft'},
        {'name': 'draft_body', 'value': body},
        {'name': 'draft_id', 'value': draft_id},
        {'name': 'draft_smtp', 'value': smtp},
        {'name': 'draft_subject', 'value': subject},
        {'name': 'draft_cc', 'value': cc},
        {'name': 'draft_bcc', 'value': bcc},
        {'name': 'draft_notice', 'value': notice},
        {'name': 'draft_in_reply_to', 'value': inreplyto},
        {'name': 'delete_uploaded_files', 'value': no_files},
        {'name': 'draft_to', 'value': to},
        {'name': 'uploaded_files', 'value': uploaded_files}],
        function(res) {
            $('.smtp_send_placeholder').prop('disabled', false);
            $('.smtp_send_placeholder').removeClass('disabled_input');
            if (res.draft_id) {
                $('.compose_draft_id').val(res.draft_id);
            }
            if (res.draft_subject) {
                $('.draft_list .draft_'+draft_id+' a').text(res.draft_subject);
            }
        },
        [],
        no_icon
    );
};

var toggle_recip_flds = function() {
    var symbol = '<i class="bi bi-plus-square-fill fs-3"></i>';
    if ($('.toggle_recipients').html() == '<i class="bi bi-plus-square-fill fs-3"></i>') {
        symbol = '<i class="bi bi-dash-square fs-3"></i>';
    }
    $('.toggle_recipients').html(symbol);
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

var replace_cursor_positon = function (txtElement) {
    txtElement.val('\r\n\r\n\r\n'+txtElement.val());
    txtElement.prop('selectionEnd',0);
    txtElement.focus();
}

var init_resumable_upload = function () {
    var r = new Resumable({
        target:'?page=compose&hm_ajax_hook=ajax_upload_chunk&draft_smtp=' + $(".compose_server").val(),
        testTarget:'?page=compose&hm_ajax_hook=ajax_get_test_chunk&draft_smtp=' + $(".compose_server").val(),
        testMethod: 'POST',
        headers: {
            'X-Requested-with': 'xmlhttprequest'
        }
    });
    r.assignBrowse(document.getElementsByClassName('compose_attach_button'));
    r.on('fileAdded', function(file, event){
        $('.uploaded_files').append('<tr id="tr-'+file.uniqueIdentifier+'"><td>'
                +file.fileName+'</td><td>'+file.file.type+' ' + (Math.round((file.file.size/1024) * 100)/100) + 'KB '
                +'</td><td><a class="remove_attachment text-danger" id="remove-'+file.uniqueIdentifier+'" style="display:none" href="#">Remove</a><a  id="pause-'+file.uniqueIdentifier+'" class="pause_upload" href="#">Pause</a><a style="display:none" id="resume-'+file.uniqueIdentifier+'" class="resume_upload" href="#">Resume</a></td></tr><tr><td colspan="2">'
                +'<div class="meter" style="width:100%"><span id="progress-'
                +file.uniqueIdentifier+'" style="width:0%;"><span class="progress" id="progress-bar-'
                +file.uniqueIdentifier+'"></span></span></div></td></tr>');
        r.upload()
        $('.pause_upload').on('click', function (e) {
            e.preventDefault();
            r.pause();
        });
        $('.resume_upload').on('click', function(e) {
            e.preventDefault();
            $('.remove_attachment').css('display', 'none');
            $('.pause_upload').css('display', '');
            $('.resume_upload').css('display', 'none');
            r.upload();
        });
        $('.remove_attachment').on('click', function(e) {
            e.preventDefault();
            var fileUniqueId = $(this).attr('id').replace('remove-', '');
            file = r.getFromUniqueIdentifier(fileUniqueId);
            if (file) {
                r.removeFile(file);
            }
            $(this).parent().parent().next('tr').remove();
            $(this).parent().parent().remove();
        });
    });
    r.on('fileProgress', function(file) {
        var progress = Math.floor(file.progress() * 100);
        $('#progress-' + file.uniqueIdentifier).css('width', progress+'%');
    });
    r.on('fileSuccess', function(file) {
        $('.remove_attachment').css('display', '');
        $('.pause_upload').css('display', 'none');
        $('.resume_upload').css('display', 'none');
        $('#tr-'+file.uniqueIdentifier).append('<td style="display:none"><input name="uploaded_files[]" type="text" value="'+file.fileName+'" /></td>');
        $('#progress-bar-' + file.uniqueIdentifier).css('background-color', 'green');
        $('#progress-' + file.uniqueIdentifier).parent().css('opacity', '0');
    });
    r.on('fileError', function(file, message) {
        $('#progress-bar-' + file.uniqueIdentifier).css('background-color', 'red');
    });
    r.on('pause', function() {
        $('.remove_attachment').css('display', 'none');
        $('.pause_upload').css('display', 'none');
        $('.resume_upload').css('display', '');
    });
    $('.remove_attachment').on('click', function(e) {
        e.preventDefault();
        var fileUniqueId = $(this).attr('id').replace('remove-', '');
        $(this).parent().parent().next('tr').remove();
        $(this).parent().parent().remove();
        file = r.getFromUniqueIdentifier(fileUniqueId);
        r.removeFile(file);
    });
}

var move_recipient_to_section = function(e) {
    e.preventDefault();
    var id = e.dataTransfer.getData("text");
    var target = $(e.target);
    if (!target.hasClass('compose_container')) {
        target = target.closest('.compose_container');
    }
    target.find('.bubbles').append($('#'+id));
    var input = target.find('input');
    input.focus();
    resize_input(input[0]);
};

var allow_drop = function(e) {
    e.preventDefault();
};

var drag = function(e) {
    e.dataTransfer.setData('text', e.target.id);
};

var bubbles_to_text = function(input) {
    var value = '';
    $(input).prev().children().each(function() {
        if (value) {
            value = value + ', ';
        }
        value = value + $(this).attr('data-value');
        $(this).remove();
    });
    if (value) {
        if ($(input).val()) {
            value = value + ', ' + $(input).val();
        }
        $(input).val(value);
    }
    $(input).css('width', '95%');
};

var resize_input = function(input) {
    $(input).css('width', 'auto');
    var input_width = $(input).parent().outerWidth() - $(input).position().left;
    $(input).css('width', input_width);
};

var text_to_bubbles = function(input) {
    var contact_id = input.getAttribute("data-id");
    var contact_type = input.getAttribute("data-type");
    var contact_source = input.getAttribute("data-source");

    if ($(input).val()) {
        var recipients = $(input).val().split(/,|;/);
        var invalid_recipients = '';

        for (var i = 0; i < recipients.length; i++) {
            if (is_valid_recipient(recipients[i])) {
                const value = recipients[i].trim().replace(/</g, '&lt;').replace(/>/g, '&gt;');
                append_bubble(value, input, contact_id, contact_type, contact_source);
            } else {
                if (invalid_recipients) {
                    invalid_recipients = invalid_recipients + ', ';
                }
                invalid_recipients = invalid_recipients + recipients[i];
            }
        }
        $(input).val(invalid_recipients);
    }
    resize_input(input);
};

var bubble_index = 0;
var append_bubble = function(value, to, id, type, source) {
    var bubble = '<div id="bubble_'+bubble_index+'" class="bubble bubble_dropdown-toggle" onclick="toggle_bubble_dropdown(this)" draggable="true"  ondragstart="drag(event)" data-id="'+id+'"  data-type="'+type+'"  data-source="'+source+'" data-value="'+value+'">'+value+'<span class="bubble_close">&times;</span></div>';
    $(to).prev().append(bubble);
    bubble_index++;
};

var toggle_bubble_dropdown = function (element) {
    var dropdownContent = element.nextElementSibling;

    if (!dropdownContent) {
        var textValue = element.dataset.value;
        var contact_id = element.getAttribute('data-id');
        var contact_type = element.getAttribute('data-type');
        var contact_source = element.getAttribute('data-source');
        dropdownContent = document.createElement('div');
        dropdownContent.classList.add('bubble_dropdown-content');
        let html = '<ul><li><span data-value="' + textValue + '" onclick="copy_text_to_clipboard(this)"><i class="bi bi-copy"></i> Copy</span></li>';
        if (contact_id !== "null") {
            html += '<li><a href="?page=contacts&contact_id=' + contact_id + '&contact_source=' + contact_source + '&contact_type=' + contact_type + '"><i class="bi bi-pencil-fill"></i> Edit</a></li>';
        }
        html += '</ul>';
        dropdownContent.innerHTML = html;
        element.parentNode.appendChild(dropdownContent);
    }

    dropdownContent.classList.toggle('show');
}

var copy_text_to_clipboard = function(e) {
    navigator.clipboard.writeText(e.dataset.value);
    $(".bubble_dropdown-content").remove();
}

var is_valid_recipient = function(recipient) {
    var valid_regex = /^[\p{L}|\d' ]*(<)?[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*(>)?$/u;
    return recipient.match(valid_regex);
};

var process_compose_form = function(){
    var msg_uid = hm_msg_uid();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    var class_name = 'imap_' + detail.server_id + '_' + msg_uid + '_' + detail.folder;
    var key = 'imap_' + Hm_Utils.get_url_page_number() + '_' + hm_list_path();
    var next_message = Hm_Message_List.prev_next_links(key, class_name)[1];

    if (next_message) {
        $('.compose_next_email_data').val(next_message);
    }

    var uploaded_files = $("input[name='uploaded_files[]']").map(function () { return $(this).val(); }).get();
    $('#send_uploaded_files').val(uploaded_files);
    Hm_Ajax.show_loading_icon();
    $('.smtp_send_placeholder').addClass('disabled_input');
    $('.smtp_send_archive').addClass('disabled_input');
    $('.smtp_send').on("click", function () { return false; });
}
var force_send_message = function() {
    // Check if the force_send input already exists
    var forceSendInput = document.getElementById('force_send');
    if (! forceSendInput) {
        // Create a hidden input element
        var hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'force_send';
        hiddenInput.value = '1';
        hiddenInput.id = 'force_send';
        hiddenInput.classList.add('force_send');
        // Append the hidden input to the form
        var form = document.querySelector('.compose_form');
        form.appendChild(hiddenInput);
    }
}

$(function () {
    if (!hm_is_logged()) {
        return;
    }
    if (hm_page_name() === 'settings') {
        $('#clear_chunks_button').on('click', function(e) {
            e.preventDefault();
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_clear_attachment_chunks'}],
                function(res) {

                },
                []
            );
        });
    }
    if (hm_page_name() === 'compose') {
        init_resumable_upload()

        var interval = Hm_Utils.get_from_global('compose_save_interval', 30);
        Hm_Timer.add_job(function() { save_compose_state(); }, interval, true);
        $('.draft_title').on("click", function() { $('.draft_list').toggle(); });
        $('.toggle_recipients').on("click", function() { return toggle_recip_flds(); });
        $('.smtp_reset').on("click", reset_smtp_form);
        $('.delete_draft').on("click", function() { smtp_delete_draft($(this).data('id')); });
        $('.smtp_save').on("click", function() { save_compose_state(false, true); });
        $('.smtp_send_archive').on("click", function() { send_archive(false, true); });

        const modal = new Hm_Modal({
            modalId: 'emptySubjectBodyModal',
            title: 'Warning',
            btnSize: 'sm'
        });

        $('.smtp_send_placeholder').on("click", function (e) {
            if (window.kindEditor) {
                kindEditor.sync();
            }

            if (window.mdEditor) {
                mdEditor.codemirror.save();
            }

            const body = $('.compose_body').val().trim();
            const subject = $('.compose_subject').val().trim();

            let modalContentHeadline = '';
            let dontWanValueInStorage = '';

            // If the subject is empty, we should warn the user
            if (!subject) {
                dontWanValueInStorage = 'dont_warn_empty_subject';
                modalContentHeadline = "Your subject is empty!";
            }

            // If the body is empty, we should warn the user
            if (!body) {
                dontWanValueInStorage = 'dont_warn_empty_body';
                modalContentHeadline = "Your body is empty!";
            }

            // if both the subject and the body are empty, we should warn the user
            if (!body && !subject) {
                dontWanValueInStorage = 'dont_warn_empty_subject_body';
                modalContentHeadline = "Your subject and body are empty!";
            }

            // If the user has disabled the warning, we should send the message
            if (Boolean(Hm_Utils.get_from_local_storage(dontWanValueInStorage))) {
                handleSendAnyway();
            }
            // Otherwise, we should show the modal if we have a headline
            else if (modalContentHeadline) {
                modalContentHeadline = `<p>${hm_trans(modalContentHeadline)}</p>`;
                return showModal(modalContentHeadline);
            }
            // Subject and body are not empty, we can send the message
            else {
                handleSendAnyway();
            }

            /*
            ========================================
            Functions declarations
            ========================================
            */
            function showModal() {
                if (! modal.modalContent.html()) {
                    modal.addFooterBtn(hm_trans('Send anyway'), 'btn-warning', handleSendAnyway);
                    modal.addFooterBtn(hm_trans("Send anyway and don't warn in the future"), 'btn-warning', handleSendAnywayAndDontWarnMe);
                }
                modal.setContent(modalContentHeadline + `<p>${hm_trans('Are you sure you want to send this message?')}</p>`);
                modal.open();
            }

            function handleSendAnyway() {
                if (handleMissingAttachment()) {
                    document.getElementsByClassName("smtp_send")[0].click();
                } else {
                    e.preventDefault();
                }
            };

            function handleSendAnywayAndDontWarnMe() {
                Hm_Utils.save_to_local_storage(dontWanValueInStorage, true);
                handleSendAnyway();
            };

            function handleMissingAttachment() {
                var uploaded_files = $("input[name='uploaded_files[]']").map(function () { return $(this).val(); }).get();
                const compose_body_value = document.getElementById('compose_body').value;
                const force_send = document.getElementById('force_send')?.value;
                var reminder_value = $('.compose_form').data('reminder');
                if (reminder_value === 1 && force_send !== '1') {
                    let all_translated_keywords = [];
                    for (let lang in window.hm_translations) {
                        if (window.hm_translations.hasOwnProperty(lang)) {
                            // Get translated keywords for the current language
                            const translated_keywords = hm_trans('attachment,file,attach,attached,attaching,enclosed,CV,cover letter', lang).split(',');
                            // Concatenate translated keywords with the array
                            all_translated_keywords = all_translated_keywords.concat(translated_keywords);
                        }
                    }
                    const additional_keywords = ['.doc', '.pdf'];
                    // Split the translated keywords into an array && Add additional keywords or file extensions
                    const combined_keywords = all_translated_keywords.concat(additional_keywords);
                    // Build the regex pattern
                    const pattern = new RegExp('(' + combined_keywords.map(keyword => keyword.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')).join('|') + ')', 'i');
                    // Check if the pattern is found in the message
                    if (pattern.test(compose_body_value) && uploaded_files.length === 0) {

                        if (confirm(hm_trans('We couldn\'t find the attachment you referred to. Please confirm if you attached it or provide the details again.'))) {
                            force_send_message();
                        } else {
                            return false;
                        }
                    }
                }
                return true;
            }
        });
        $('.compose_form').on('submit', function() {
            process_compose_form();
        });
        if ($('.compose_cc').val() || $('.compose_bcc').val()) {
            toggle_recip_flds();
        }
        if (window.location.href.search('&reply=1') !== -1 || window.location.href.search('&reply_all=1') !== -1) {
            replace_cursor_positon ($('textarea[name="compose_body"]'));
        }
        if (window.location.href.search('&forward=1') !== -1) {
            setTimeout(function() {
                save_compose_state();
            }, 100);
        }
        if ($('.sys_messages').text() != 'Message Sent') {
            get_smtp_profile($('.compose_server').val());
        }
        $('.compose_server').on('change', function() {
            get_smtp_profile($('.compose_server').val());
        });
        if($('.compose_attach_button').attr('disabled') == 'disabled'){
            check_attachment_dir_access();
        };

        $('.compose_container').attr('ondrop', 'move_recipient_to_section(event)').attr('ondragover', 'allow_drop(event)');
        $('.compose_to, .compose_cc, .compose_bcc').on('keypress', function(e) {
            if(e.which == 13) {
                e.preventDefault();
                text_to_bubbles(this);
            }
        });
        $('.compose_to, .compose_cc, .compose_bcc').on('blur', function(e) {
            e.preventDefault();
            text_to_bubbles(this);
        });
        $('.compose_subject, .compose_body, .compose_server, .smtp_send_placeholder, .smtp_send_archive').on('focus', function(e) {
            $('.compose_to, .compose_cc, .compose_bcc').each(function() {
                bubbles_to_text(this);
            });
        });
        $('.compose_to, .compose_cc, .compose_bcc').on('focus', function(e) {
            text_to_bubbles(this);
        });
        $('.compose_container').on('click', function() {
            $(this).find('input').focus();
        });
        $(document).on('click', '.bubble_close', function(e) {
            e.stopPropagation();
            $(".bubble_dropdown-content").remove();
            $(this).parent().remove();
        });
    }
});
