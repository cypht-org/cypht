/**
 * Validates and saves a custom action from the custom action modal.
 * Reads the action name and configured actions from the modal DOM,
 * validates them using showErrorMsg, then POSTs to ajax_save_custom_action.
 *
 * @param {object} custom_action_modal - Hm_Modal instance
 * @param {object} options - { applyAfterSave: bool, imapAccount: string, actionId: string }
 */
function createCustomActionFromList(custom_action_modal, options) {
    return saveCustomAction(custom_action_modal, options);
}
