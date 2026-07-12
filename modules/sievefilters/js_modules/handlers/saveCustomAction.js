/**
 * Unified custom action save function (create, update, with/without apply)
 * @param {Hm_Modal} modal - Modal instance containing the action data
 * @param {Object} options - Configuration options
 *   - imapAccount: (required) IMAP account name
 *   - actionId: (optional) ID of existing action to update; if empty, creates new
 *   - applyAfterSave: (optional, default false) whether to apply after saving
 *   - applyCallback: (optional) callback after apply succeeds
 *   - saveCallback: (optional) callback after save succeeds
 */
function saveCustomAction(modal, options) {
    options = options || {};
    const imapAccount = options.imapAccount || '';
    const actionId = options.actionId || '';
    const applyAfterSave = !!options.applyAfterSave;
    const applyCallback = options.applyCallback || null;
    const saveCallback = options.saveCallback || null;

    // --- Validate name ---
    const actionName = modal.modal.find('.custom_action_name_input').val().trim();
    if (!actionName) {
        showErrorMsg(
            hm_trans('Action name is required'),
            '.sieve-filter-name-group',
            6000
        );
        return false;
    }

    // --- Collect and validate actions ---
    const actions_type = modal.modal.find('select[name^=sieve_selected_actions]').map(function (idx, elem) {
        return $(elem).val();
    }).get();

    const actions_value = modal.modal.find('[name^=sieve_selected_action_value]').map(function (idx, elem) {
        return $(elem).val();
    }).get();

    const actions_field_type = modal.modal.find('[name^=sieve_selected_action_value]').map(function (idx, elem) {
        return $(elem).attr('type');
    }).get();

    const actions_extra_value = modal.modal.find('input[name^=sieve_selected_extra_action_value]').map(function (idx, elem) {
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

    // --- Collect selected UIDs if applying ---
    const selectedUids = [];
    if (applyAfterSave) {
        $('.message_table input[type=checkbox]:checked').each(function () {
            if (this.id && this.id.indexOf('imap') === 0) {
                selectedUids.push(this.id);
            }
        });
        if (!selectedUids.length) {
            Hm_Notices.show(hm_trans('Please select at least one message to apply'), 'warning');
            return false;
        }
    }

    // --- POST to save custom action ---
    Hm_Ajax.request(
        [
            { name: 'hm_ajax_hook', value: 'ajax_save_custom_action' },
            { name: 'imap_account', value: imapAccount },
            { name: 'custom_action_name', value: actionName },
            { name: 'actions_json', value: JSON.stringify(actions_parsed) },
            { name: 'action_id', value: actionId },
        ],
        function (res) {
            if (res.custom_action_error) {
                Hm_Notices.show(res.custom_action_error, 'danger');
                return;
            }
            if (!res.custom_action_saved) { return; }

            const message = actionId ? 'updated' : 'saved';
            
            // Execute save callback if provided
            if (saveCallback) {
                saveCallback(res, actions_parsed);
                return;
            }

            if (!applyAfterSave) {
                modal.hide();
                Hm_Notices.show(hm_trans('Custom action "' + actionName + '" ' + message + ' successfully'), 'info');
                window.location = window.location;
                return;
            }

            // --- Apply to selected messages ---
            Hm_Ajax.request(
                [
                    { name: 'hm_ajax_hook', value: 'ajax_apply_custom_action' },
                    { name: 'imap_account', value: imapAccount },
                    { name: 'uids', value: JSON.stringify(selectedUids) },
                    { name: 'actions_json', value: JSON.stringify(actions_parsed) },
                ],
                function (applyRes) {
                    modal.hide();
                    if (applyRes.custom_action_error) {
                        Hm_Notices.show(applyRes.custom_action_error, 'danger');
                    } else {
                        Hm_Notices.show(
                            hm_trans('Custom action "' + actionName + '" ' + message + ' and applied to ' + selectedUids.length + ' message(s)'),
                            'info'
                        );
                    }
                    if (applyCallback) {
                        applyCallback(applyRes);
                    } else {
                        window.location = window.location;
                    }
                }
            );
        }
    );

    return true;
}
