/**
 * Validates and saves a custom action from the custom action modal.
 * Reads the action name and configured actions from the modal DOM,
 * validates them using showErrorMsg, then POSTs to ajax_save_custom_action.
 *
 * @param {object} custom_action_modal - Hm_Modal instance for the custom action modal
 * @param {object} options - { applyAfterSave: bool, imapAccount: string }
 */
function createCustomActionFromList(custom_action_modal, options) {
    options = options || {};
    const applyAfterSave = !!options.applyAfterSave;
    const imapAccount = options.imapAccount || '';
    // --- Validate name ---
    const actionName = custom_action_modal.modal.find('.custom_action_name_input').val().trim();
    if (!actionName) {
        showErrorMsg(
            hm_trans('Action name is required'),
            '.sieve-filter-name-group',
            6000
        );
        return false;
    }

    // --- Collect and validate actions ---
    const actions_type = custom_action_modal.modal.find('select[name^=sieve_selected_actions]').map(function (idx, elem) {
        return $(elem).val();
    }).get();

    const actions_value = custom_action_modal.modal.find('[name^=sieve_selected_action_value]').map(function (idx, elem) {
        return $(elem).val();
    }).get();

    const actions_field_type = custom_action_modal.modal.find('[name^=sieve_selected_action_value]').map(function (idx, elem) {
        return $(elem).attr('type');
    }).get();

    const actions_extra_value = custom_action_modal.modal.find('input[name^=sieve_selected_extra_action_value]').map(function (idx, elem) {
        return $(elem).val();
    }).get();

    if (actions_type.length === 0) {
        showErrorMsg(
            hm_trans('You must provide at least one action'),
            '.sieve-filter-actions-block',
            6000
        );
        return false;
    }

    let validation_failed = false;
    const actions_parsed = [];

    actions_type.forEach(function (action, idx) {
        if (actions_value[idx] === '' && actions_field_type[idx] !== 'hidden') {
            showErrorMsg(
                hm_trans('The ' + ordinal_number(idx + 1) + ' action (' + action + ') value must be provided'),
                '.sieve-filter-actions-block',
                6000
            );
            validation_failed = true;
        }
        actions_parsed.push({
            action: action,
            value: actions_value[idx],
            extra_option: '',
            extra_option_value: actions_extra_value[idx] || '',
        });
    });

    if (validation_failed) {
        return false;
    }

    // --- Collect selected UIDs (needed for apply) ---
    const selectedUids = [];
    if (applyAfterSave) {
        $('.message_table input[type=checkbox]:checked').each(function () {
            // Full checkbox id (imap_{server_id}_{uid}_{hex_folder}) carries all
            // the info the server needs to perform IMAP operations.
            if (this.id && this.id.indexOf('imap') === 0) {
                selectedUids.push(this.id);
            }
        });
        if (!selectedUids.length) {
            Hm_Notices.show(hm_trans('Please select at least one message to apply'), 'warning');
            return false;
        }
    }

    // --- POST to backend ---
    Hm_Ajax.request(
        [
            { name: 'hm_ajax_hook', value: 'ajax_save_custom_action' },
            { name: 'imap_account', value: imapAccount },
            { name: 'custom_action_name', value: actionName },
            { name: 'actions_json', value: JSON.stringify(actions_parsed) },
        ],
        function (res) {
            if (res.custom_action_error) {
                Hm_Notices.show(res.custom_action_error, 'danger');
                return;
            }
            if (!res.custom_action_saved) { return; }

            if (!applyAfterSave) {
                custom_action_modal.hide();
                Hm_Notices.show(hm_trans('Custom action "' + actionName + '" saved successfully'), 'info');
                window.location = window.location;
                return;
            }

            // Save succeeded — now apply
            Hm_Ajax.request(
                [
                    { name: 'hm_ajax_hook', value: 'ajax_apply_custom_action' },
                    { name: 'imap_account', value: imapAccount },
                    { name: 'uids', value: JSON.stringify(selectedUids) },
                    { name: 'actions_json', value: JSON.stringify(actions_parsed) },
                ],
                function (applyRes) {
                    custom_action_modal.hide();
                    if (applyRes.custom_action_error) {
                        Hm_Notices.show(applyRes.custom_action_error, 'danger');
                    } else {
                        Hm_Notices.show(
                            hm_trans('Custom action "' + actionName + '" saved and applied to ' + selectedUids.length + ' message(s)'),
                            'info'
                        );
                    }
                    window.location = window.location;
                }
            );
        }
    );

    return true;
}
