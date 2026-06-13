function autoSavePasswordModal(title, bodyText, onSubmit, securityNote) {
    const modal = new Hm_Modal({
        title: title,
        modalId: 'autoSavePasswordModal',
    });
    let content = '<p>' + bodyText + '</p>';
    if (securityNote) {
        content += '<p class="text-muted small">' + securityNote + '</p>';
    }
    modal.setContent(content +
        '<div class="form-floating mt-3">' +
        '<input type="password" class="form-control auto_save_password_input warn_on_paste" id="auto_save_password_input" autocomplete="current-password" placeholder="' + hm_trans('Password') + '">' +
        '<label for="auto_save_password_input">' + hm_trans('Password') + '</label>' +
        '</div>');
    modal.addFooterBtn(hm_trans('Save'), 'btn-primary', function() {
        const password = $('.auto_save_password_input').val();
        if (!password) {
            Hm_Notices.show(hm_trans('Password is required'), 'warning');
            return;
        }
        onSubmit(password, modal);
    });
    modal.open();
    return modal;
}

function autoSaveToggleRequest(enabled, password, onSuccess, onError) {
    const data = [
        { name: 'hm_ajax_hook', value: enabled ? 'ajax_auto_save_settings' : 'ajax_auto_save_settings' },
        { name: 'auto_save', value: enabled ? '1' : '0' },
    ];
    if (password) {
        data.push({ name: 'password', value: password });
    }
    Hm_Ajax.request(data, function(res) {
        if (res.auto_save_error) {
            if (onError) {
                onError(res);
            }
            return;
        }
        if (onSuccess) {
            onSuccess(res);
        }
    }, [], false, undefined, function() {
        Hm_Notices.show(hm_trans('An error occurred while updating auto-save'), 'danger');
        if (onError) {
            onError();
        }
    });
}

function updateAutoSaveStatus(enabled, keyReady) {
    const $status = $('.auto_save_status');
    if (!$status.length) {
        return;
    }
    if (!enabled) {
        $status.remove();
        return;
    }
    if (keyReady) {
        $status.removeClass('text-warning').addClass('text-muted').text(hm_trans('Active'));
    } else {
        $status.removeClass('text-muted').addClass('text-warning').text(hm_trans('Password required'));
    }
}

function bindAutoSaveSettingToggle() {
    const $toggle = $('#auto_save_setting');
    if (!$toggle.length) {
        return;
    }
    $toggle.on('change', function() {
        const enabled = $(this).is(':checked');
        const previous = !enabled;
        $(this).prop('checked', previous);

        if (!enabled) {
            autoSaveToggleRequest(false, null, function() {
                $toggle.prop('checked', false);
                updateAutoSaveStatus(false, false);
                $('.auto_save_resume_notice').remove();
            }, function() {
                $toggle.prop('checked', previous);
            });
            return;
        }

        autoSavePasswordModal(
            hm_trans('Enable auto-save'),
            hm_trans('Enter your password to enable auto-save.'),
            function(password, modal) {
                autoSaveToggleRequest(true, password, function(res) {
                    $toggle.prop('checked', true);
                    updateAutoSaveStatus(true, !!res.auto_save_key_ready);
                    $('.auto_save_resume_notice').remove();
                    $('.save_reminder, .unsaved_icon').remove();
                    modal.hide();
                }, function() {
                    $toggle.prop('checked', previous);
                });
            },
            hm_trans('Your password stays in this session until logout. Do not enable on shared or public computers.')
        );
    });
}

function bindAutoSaveResumeButton() {
    $(document).on('click', '.auto_save_resume_btn', function() {
        autoSavePasswordModal(
            hm_trans('Resume auto-save'),
            hm_trans('Auto-save needs your password again. This is often after a password change. Enter it once to resume.'),
            function(password, modal) {
                Hm_Ajax.request([
                    { name: 'hm_ajax_hook', value: 'ajax_resume_auto_save' },
                    { name: 'password', value: password },
                ], function(res) {
                    if (res.auto_save_error) {
                        return;
                    }
                    $('.auto_save_resume_notice').remove();
                    updateAutoSaveStatus(true, !!res.auto_save_key_ready);
                    $('.save_reminder, .unsaved_icon').remove();
                    modal.hide();
                }, [], false, undefined, function() {
                    Hm_Notices.show(hm_trans('An error occurred while resuming auto-save'), 'danger');
                });
            }
        );
    });
}

function autoSaveSettingsPageHandler() {
    bindAutoSaveSettingToggle();
}

bindAutoSaveResumeButton();
