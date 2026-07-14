let currentSingleTarget = null;

/**
 * Disables a modal footer button and swaps its label for a spinner while an AJAX
 * request is in flight, so a fast double-click can't fire it twice.
 * @param {jQuery} $btn
 * @param {string} loadingText
 */
function setButtonLoading($btn, loadingText) {
    if (!$btn || !$btn.length) { return; }
    $btn.data('original-html', $btn.html());
    $btn.prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + loadingText
    );

    // Lock every other footer button too, so a second action can't fire while this one
    // is in flight — they just go inert, no spinner/text change. Only siblings that were
    // actually enabled get marked/locked; a button already disabled for its own reason
    // (e.g. Save before anything is dirty) must stay disabled either way, and must NOT
    // get re-enabled by restoreButtonState() below if this request fails.
    $btn.closest('.modal-footer').find('button').not($btn).not(':disabled')
        .addClass('sibling-locked').prop('disabled', true);
}

function restoreButtonState($btn) {
    if (!$btn || !$btn.length) { return; }
    const original = $btn.data('original-html');
    if (typeof original !== 'undefined') {
        $btn.html(original);
    }
    $btn.prop('disabled', false);

    $btn.closest('.modal-footer').find('button.sibling-locked')
        .removeClass('sibling-locked').prop('disabled', false);
}

/**
 * Builds the <option> HTML for all possible sieve actions.
 * @returns {string}
 */
function buildPossibleActionsHtml() {
    let html = '';
    const possibleActions = (typeof get_account_actions === 'function')
        ? get_account_actions()
        : hm_sieve_possible_actions();

    possibleActions.forEach(function (value) {
        const selected = value.selected === true ? ' selected' : '';
        html += '<option' + selected + ' value="' + value.name + '">' + value.description + '</option>';
    });
    return html;
}

/**
 * Appends pre-filled action rows to $table from a saved actions array.
 * Mirrors the pre-fill pattern used by the sieve edit filter.
 * @param {jQuery} $table
 * @param {Array}  actions
 */
function populateActionRows($table, actions) {
    const possible_actions_html = buildPossibleActionsHtml();

    actions.forEach(function (action) {
        const $row = $(
            '<tr class="border draggable_action_row" default_value="' + (action.value || '') + '">' +
            '   <td class="col-sm-1 drag-handle" style="cursor:grab;">&#9776;</td>' +
            '   <td class="col-sm-3">' +
            '       <select class="sieve_actions_select form-control form-control-sm" name="sieve_selected_actions[]">' +
            possible_actions_html +
            '       </select>' +
            '   </td>' +
            '   <td class="col-sm-3">' +
            '       <input type="hidden" class="condition_extra_action_value form-control form-control-sm" name="sieve_selected_extra_action_value[]" />' +
            '   </td>' +
            '   <td class="col-sm-5">' +
            '       <input type="hidden" name="sieve_selected_action_value[]" value="">' +
            '   </td>' +
            '   <td class="col-sm-1 text-end align-middle">' +
            '       <a href="#" class="delete_action_modal_button btn btn-sm btn-secondary">Delete</a>' +
            '   </td>' +
            '</tr>'
        );

        $table.append($row);

        // Set action type; trigger('change') re-builds the value field
        $row.find('.sieve_actions_select').val(action.action).trigger('change');

        // Extra option value
        $row.find('[name^=sieve_selected_extra_action_value]').val(action.extra_option_value || '');

        // For non-mailbox types the value field is replaced synchronously by the change handler
        const actionDef = hm_sieve_possible_actions().find(function (a) { return a.name === action.action; });
        if (!actionDef || actionDef.type !== 'mailbox') {
            const $vf = $row.find('[name^=sieve_selected_action_value]');
            if ($vf.is('input'))    { $vf.val(action.value || ''); }
            else if ($vf.is('textarea')) { $vf.text(action.value || ''); }
        }
        // For mailbox: the AJAX callback uses the row's default_value attribute to pre-select the right option
    });
}

/**
 * Collects the current actions from inside the given modal.
 * @param {Hm_Modal} modal
 * @returns {Array|null} parsed actions, or null if validation failed (errors already shown)
 */
function collectActionsFromModal(modal) {
    const types       = modal.modal.find('select[name^=sieve_selected_actions]').map(function (i, el) { return $(el).val(); }).get();
    const values      = modal.modal.find('[name^=sieve_selected_action_value]').map(function (i, el) { return $(el).val(); }).get();
    const extraValues = modal.modal.find('input[name^=sieve_selected_extra_action_value]').map(function (i, el) { return $(el).val(); }).get();

    if (types.length === 0) {
        showErrorMsg(hm_trans('You must provide at least one action'), '.sieve-filter-actions-block', 6000);
        return null;
    }

    let validation_failed = false;
    const actions_parsed = [];

    const possible_actions = (typeof get_account_actions === 'function') ? get_account_actions() : hm_sieve_possible_actions();
    types.forEach(function (action, idx) {
        const actionDef = possible_actions.find(function (a) { return a.name === action; });
        const requiresValue = !actionDef || actionDef.type !== 'none';
        if (requiresValue && !values[idx]) {
            const order = ordinal_number(idx + 1);
            const actionLabel = actionDef ? actionDef.description : action;
            const message = (actionDef && actionDef.type === 'mailbox')
                ? hm_trans('The ' + order + ' action (' + actionLabel + ') requires a folder to be selected')
                : hm_trans('The ' + order + ' action (' + actionLabel + ') value must be provided');
            showErrorMsg(message, '.sieve-filter-actions-block', 6000);
            validation_failed = true;
        }
        actions_parsed.push({ action: action, value: values[idx] || '', extra_option: '', extra_option_value: extraValues[idx] || '' });
    });

    return validation_failed ? null : actions_parsed;
}

/**
 * Fires the AJAX apply request. Uses modal's current actions unless overridden.
 * @param {Hm_Modal} modal
 * @param {string}   imapAccount
 * @param {Array}    [actions]  optional override (used by Update & Apply after save)
 * @param {jQuery}   [triggerBtn] the clicked button, disabled/spun for the duration
 */
function applyToSelected(modal, imapAccount, actions, triggerBtn) {
    let selectedUids = [];
    const resolvedImapAccount = (imapAccount || current_account || '').toString();

    if (currentSingleTarget) {
        // Message-page context: apply directly to the open message, no checkboxes involved.
        selectedUids = [currentSingleTarget];
    } else {
        $('.message_table input[type=checkbox]:checked').each(function () {
            // Use the full checkbox id (imap_{server_id}_{uid}_{hex_folder}) so the
            // server can extract server, UID and folder without extra lookups.
            if (this.id && this.id.indexOf('imap') === 0) {
                selectedUids.push(this.id);
            }
        });
    }

    if (!selectedUids.length) {
        Hm_Notices.show(hm_trans('Please select at least one message'), 'warning');
        restoreButtonState(triggerBtn);
        return;
    }

    const actions_parsed = actions || collectActionsFromModal(modal);
    if (!actions_parsed) {
        // Validation failed inside collectActionsFromModal(); it already showed the error.
        restoreButtonState(triggerBtn);
        return;
    }

    Hm_Ajax.request(
        [
            { name: 'hm_ajax_hook', value: 'ajax_apply_custom_action' },
            { name: 'imap_account', value: resolvedImapAccount },
            { name: 'uids',         value: JSON.stringify(selectedUids) },
            { name: 'actions_json', value: JSON.stringify(actions_parsed) },
        ],
        function (res) {
            if (res.custom_action_error) {
                Hm_Notices.show(res.custom_action_error, 'danger');
                restoreButtonState(triggerBtn);
                return;
            }
            modal.hide();
            Hm_Notices.show(hm_trans('Applied to ' + selectedUids.length + ' message(s)'), 'info');
            window.location = window.location;
        }
    );
}

/**
 * Opens the edit modal for a saved custom action and handles Save / Apply.
 */
function handleApplyCustomAction() {
    let isDirty          = false;
    let currentActionId  = '';
    let currentImapAccount = '';

    const edit_modal = new Hm_Modal({ size: 'xl', modalId: 'editCustomActionModal' });
    edit_modal.setTitle(hm_trans('Edit Custom Action'));

    // Save (disabled until something is edited)
    edit_modal.addFooterBtn(hm_trans('Save'), 'btn-secondary ms-auto edit_ca_save_btn', function () {
        if (!isDirty) { return; }
        const $btn = $(this);
        setButtonLoading($btn, hm_trans('Saving...'));
        saveCustomAction(edit_modal, {
            imapAccount: (currentImapAccount || current_account ).toString(),
            actionId: currentActionId,
            applyAfterSave: false,
            triggerBtn: $btn,
            saveCallback: function (res) {
                isDirty = false;
                restoreButtonState($btn);
                edit_modal.modalFooter.find('.edit_ca_save_btn').prop('disabled', true);
                Hm_Notices.show(hm_trans('Custom action updated'), 'info');
            }
        });
    });

    // Apply to Selected (always visible)
    edit_modal.addFooterBtn(hm_trans('Apply to Selected'), 'btn-primary edit_ca_apply_btn', function () {
        const $btn = $(this);
        setButtonLoading($btn, hm_trans('Applying...'));
        applyToSelected(edit_modal, currentImapAccount, undefined, $btn);
    });

    // Update & Apply (hidden until dirty)
    edit_modal.addFooterBtn(hm_trans('Update & Apply'), 'btn-success edit_ca_update_apply_btn', function () {
        if (!isDirty) { return; }
        const $btn = $(this);
        setButtonLoading($btn, hm_trans('Applying...'));
        saveCustomAction(edit_modal, {
            imapAccount: (currentImapAccount || current_account || '').toString(),
            actionId: currentActionId,
            applyAfterSave: true,
            triggerBtn: $btn,
            applyCallback: function (applyRes) {
                isDirty = false;
                window.location = window.location;
            }
        });
    });

    $(document).off('click', '.custom_action_btn').on('click', '.custom_action_btn', function (e) {
        e.preventDefault();

        const actions     = JSON.parse($(this).attr('data-actions') || '[]');
        const actionName  = $(this).attr('data-action-name') || '';
        currentImapAccount = $(this).attr('data-imap-account') || '';
        currentActionId    = $(this).attr('data-action-id')   || '';
        localStorage.setItem('last_custom_action', JSON.stringify({actions: actions}));

        // Message-page button: target the open message directly, not a checkbox selection.
        currentSingleTarget = $(this).hasClass('custom_action_btn_message')
            ? 'imap_' + $(this).attr('data-msg-server-id') + '_' + $(this).attr('data-msg-uid') + '_' + $(this).attr('data-msg-folder')
            : null;

        // Set global account so get_account_actions() works for mailbox AJAX
        current_account = currentImapAccount;
        current_account_element = null;

        // Load fresh template into modal
        const templateEl = document.querySelector('#custom_action_template');
        if (templateEl) { edit_modal.setContent(templateEl.innerHTML); }

        // Pre-fill name
        edit_modal.modal.find('.custom_action_name_input').val(actionName);

        // Pre-fill action rows (scoped; avoids polluting any other open modal)
        const $table = edit_modal.modal.find('.filter_actions_modal_table');
        $table.empty();

        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_load_custom_action_by_id'},
                {'name': 'imap_account', 'value': currentImapAccount},
                {'name': 'custom_action_id', 'value': currentActionId}
            ],
            function(res) {
                if (res.custom_action_error) {
                    Hm_Notices.show(res.custom_action_error, 'danger');
                    return;
                }

                const action = res.custom_action;
                localStorage.setItem('actions', JSON.stringify(action));

                if (action.actions && typeof action.actions === 'object') {
                    populateActionRows($table, Object.values(action.actions));
                }

                const count = currentSingleTarget
                    ? 1
                    : $('.message_table input[type=checkbox]:checked').length;
                edit_modal.modalFooter.find('.edit_ca_apply_btn')
                    .text(currentSingleTarget
                        ? hm_trans('Apply to this message')
                        : (count > 0
                            ? hm_trans('Apply to') + ' ' + count + ' ' + hm_trans('Selected')
                            : hm_trans('Apply to Selected')))
                    .prop('disabled', count === 0);

                // Reset dirty state — Save is disabled, Update & Apply is hidden
                isDirty = false;
                edit_modal.modalFooter.find('.edit_ca_save_btn').prop('disabled', true);
                edit_modal.modalFooter.find('.edit_ca_update_apply_btn').addClass('d-none');

                edit_modal.open();
                // Dirty tracking: first change enables Save and reveals Update & Apply
                edit_modal.modal.off('.dirty').on('change.dirty input.dirty', 'select, input:not([type=hidden]), textarea', function () {
                if (!isDirty) {
                    isDirty = true;
                    edit_modal.modalFooter.find('.edit_ca_save_btn').prop('disabled', false);
                    edit_modal.modalFooter.find('.edit_ca_update_apply_btn').removeClass('d-none');
                }
            });
            }
        );
    });
}
