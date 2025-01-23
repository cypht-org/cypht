// TODO: This function is too large for a route handler, decouple it into multiple functions with action scope focused.
function applySmtpComposePageHandlers() {
    init_resumable_upload()

    setupActionSchedule(function () {
        $('.smtp_send_placeholder').trigger('click');
    });

    if (window.HTMLEditor) {
        useKindEditor();
    }

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
        let showBtnSendAnywayDontWarnFuture = true;

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

        if (hm_module_is_supported('contacts')) {
            var checkInList = check_cc_exist_in_contacts_list();
            // if contact_cc not exist in contact list for user
            if (checkInList) {
                modalContentHeadline = "Adress mail not exist in your contact list";
                showBtnSendAnywayDontWarnFuture = false;
            }

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
                if (showBtnSendAnywayDontWarnFuture) {
                    modal.addFooterBtn(hm_trans("Send anyway and don't warn in the future"), 'btn-warning', handleSendAnywayAndDontWarnMe);
                }
            }
            modal.setContent(modalContentHeadline + checkInList + `<p>${hm_trans('Are you sure you want to send this message?')}</p>`);
            modal.open();
        }

        function waitForValueChange(selector, targetValue) {
            return new Promise((resolve) => {
                const checkValue = () => {
                    if ($(selector).val() !== targetValue) {
                        resolve();  
                    } else {
                        setTimeout(checkValue, 100); 
                    }
                };
                checkValue();  
            });
        }

        async function handleSendAnyway() {
            if ($('.saving_draft').val() !== '0') {
                Hm_Notices.show([hm_trans('Please wait, sending message...')]);
                await waitForValueChange('.saving_draft', '0');
            }

            if (handleMissingAttachment()) {
                if ($('.nexter_input').val()) {
                    save_compose_state(false, true, $('.nexter_input').val(), function(res) {
                        if (res.draft_id) {
                            reset_smtp_form(false);
                            Hm_Folders.reload_folders(true);
                            Hm_Utils.redirect();
                        }
                    });
                } else {
                    document.getElementsByClassName("smtp_send")[0].click();
                }
            } else {
                e.preventDefault();
            }
        }

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

    var selectedOption = $('#compose_smtp_id option[selected]');
    var selectedEmail = selectedOption.data('email');
    var selectedVal = selectedOption.val();

    var recipientsInput = $('#compose_cc');
    var excludedEmail = null;

    const excludeEmail = function () {
        var newRecipients = recipientsInput.val().split(',').filter(function(email) {
            if (email.includes(selectedEmail)) {
                excludedEmail = email;
                return false;
            }
            return true;
        }).join(', ');
        recipientsInput.val(newRecipients);
    };

    if (recipientsInput.val().includes(selectedEmail)) {
        excludeEmail();
        $(document).on('change', '#compose_smtp_id', function() {
            if ($(this).val() !== selectedVal) {
                if (!recipientsInput.val().includes(selectedEmail)) {
                    recipientsInput.val(recipientsInput.val() + ', ' + excludedEmail);
                }
            } else {
                excludeEmail();
            }
        });
    }

    if (window.pgpComposePageHandler) pgpComposePageHandler();
    if (window.profilesComposePageHandler) profilesComposePageHandler();
}