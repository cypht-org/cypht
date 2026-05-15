/**
 * Handles clicks on saved custom action buttons in the message list dropdown.
 * Reads the actions from data-actions, applies them to the currently selected
 * messages via AJAX (one request per selected message per action).
 */
function handleApplyCustomAction() {
    $(document).on('click', '.custom_action_btn', function (e) {
        e.preventDefault();

        const actions = JSON.parse($(this).attr('data-actions') || '[]');
        const actionName = $(this).attr('data-action-name');
        const imapAccount = $(this).attr('data-imap-account');

        if (!actions.length) {
            Hm_Notices.show(hm_trans('No actions defined for "' + actionName + '"'), 'warning');
            return;
        }

        const selected = [];
        $('.message_table input[type=checkbox]:checked').each(function () {
            const $row = $(this).closest('tr');
            const uid = $row.data('uid');
            if (uid) {
                selected.push(uid);
            }
        });

        if (!selected.length) {
            Hm_Notices.show(hm_trans('Please select at least one message'), 'warning');
            return;
        }

        Hm_Ajax.request(
            [
                { name: 'hm_ajax_hook', value: 'ajax_apply_custom_action' },
                { name: 'imap_account', value: imapAccount },
                { name: 'uids', value: JSON.stringify(selected) },
                { name: 'actions_json', value: JSON.stringify(actions) },
            ],
            function (res) {
                if (res.custom_action_error) {
                    Hm_Notices.show(res.custom_action_error, 'danger');
                    return;
                }
                Hm_Notices.show(
                    hm_trans('"' + actionName + '" applied to ' + selected.length + ' message(s)'),
                    'info'
                );
                window.location = window.location;
            }
        );
    });
}
