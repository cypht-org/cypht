/**
 * Unified custom action save function (create, update, with/without apply)
 * @param {Hm_Modal} modal - Modal instance containing the action data
 * @param {Object} options - Configuration options
 *   - imapAccount: (required) IMAP account name
 *   - actionId: (optional) ID of existing action to update; if empty, creates new
 *   - applyAfterSave: (optional, default false) whether to apply after saving
 *   - applyCallback: (optional) callback after apply succeeds
 *   - saveCallback: (optional) callback after save succeeds
 *   - triggerBtn: (optional) jQuery button element to disable/spin for the duration,
 *     and re-enable if validation fails or the request errors (see applyCustomAction.js)
 */
function saveCustomAction(modal, options) {
    options = options || {};
    const imapAccount = options.imapAccount || '';
    const actionId = options.actionId || '';
    const applyAfterSave = !!options.applyAfterSave;
    const applyCallback = options.applyCallback || null;
    const saveCallback = options.saveCallback || null;
    const triggerBtn = options.triggerBtn || null;

    // --- Validate name ---
    const actionName = modal.modal.find('.custom_action_name_input').val().trim();
    if (!actionName) {
        showErrorMsg(
            hm_trans('Action name is required'),
            '.sieve-filter-name-group',
            6000
        );
        restoreButtonState(triggerBtn);
        return false;
    }

    // --- Collect and validate actions ---
    // Shared with applyToSelected() (applyCustomAction.js) so every custom-action modal
    // button — Save, Update & Apply, Save & Apply, Apply to Selected — validates the same
    // way (at least one action; a value for anything but "keep"; a dedicated message when
    // a "move"/"copy to folder" action has no folder selected).
    const actions_parsed = collectActionsFromModal(modal);
    if (!actions_parsed) {
        restoreButtonState(triggerBtn);
        return false;
    }

    // --- Collect selected UIDs if applying ---
    // singleTarget (message-page context, e.g. "Create for message like this") applies
    // directly to the open message; otherwise fall back to the message-list checkbox selection.
    const selectedUids = [];
    if (applyAfterSave) {
        if (options.singleTarget) {
            selectedUids.push(options.singleTarget);
        } else {
            $('.message_table input[type=checkbox]:checked').each(function () {
                if (this.id && this.id.indexOf('imap') === 0) {
                    selectedUids.push(this.id);
                }
            });
            if (!selectedUids.length) {
                Hm_Notices.show(hm_trans('Please select at least one message to apply'), 'warning');
                restoreButtonState(triggerBtn);
                return false;
            }
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
                restoreButtonState(triggerBtn);
                return;
            }
            if (!res.custom_action_saved) {
                restoreButtonState(triggerBtn);
                return;
            }

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
                    restoreButtonState(triggerBtn);
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
