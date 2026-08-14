function handleSieveCustomAction() {
    const custom_action_modal = new Hm_Modal({
        size: 'xl',
        modalId: 'myCustomActionModal',
    });

    custom_action_modal.addFooterBtn(
        hm_trans('Save Custom Action'),
        'btn-secondary ms-auto',
        function () {
            const $btn = $(this);
            setButtonLoading($btn, hm_trans('Saving...'));
            createCustomActionFromList(custom_action_modal, { applyAfterSave: false, triggerBtn: $btn });
        },
    );

    custom_action_modal.addFooterBtn(
        hm_trans('Save & Apply to Selected'),
        'btn-primary save_and_apply_btn',
        function () {
            const $btn = $(this);
            setButtonLoading($btn, hm_trans('Applying...'));
            createCustomActionFromList(custom_action_modal, {
                applyAfterSave: true,
                imapAccount: custom_action_modal._imapAccount || '',
                singleTarget: custom_action_modal._singleTarget || null,
                triggerBtn: $btn,
            });
        },
    );

    $(document).off('click', '#add_custom_action_button').on('click', '#add_custom_action_button', function (e) {
        e.preventDefault();

        const mailbox = $(this).attr('account');
        current_mailbox_for_filter = mailbox;
        custom_action_modal._imapAccount = mailbox;
        current_account = mailbox;
        current_account_element = find_account_element(mailbox);

        const singleTarget = $(this).hasClass('add_custom_action_message')
            ? 'imap_' + $(this).attr('data-msg-server-id') + '_' + $(this).attr('data-msg-uid') + '_' + $(this).attr('data-msg-folder')
            : null;
        custom_action_modal._singleTarget = singleTarget;

        custom_action_modal.setTitle(singleTarget
            ? hm_trans('Setup Custom Action for message like this')
            : hm_trans('Setup Custom Action from selected messages'));

        const $applyBtn = custom_action_modal.modalFooter.find('.save_and_apply_btn');

        if (singleTarget) {
            $applyBtn.text(hm_trans('Save & Apply to this message'));
            $applyBtn.prop('disabled', false);
        } else {
            const selected = [];

            $('.message_table input[type=checkbox]:checked').each(function () {
                const $row = $(this).closest('tr');

                selected.push({
                    // imap_id: this.value,
                    uid: $row.data('uid'),
                    message_id: $row.data('msg-id'),
                    from_email: ($row.find('td.from').data('title') || '').trim(),
                    subject: $row.find('td.subject a').attr('title') || '',
                });
            });

            // Update apply button label with selected count
            const count = selected.length;
            $applyBtn.text(count > 0
                ? hm_trans('Save & Apply to') + ' ' + count + ' ' + hm_trans('Selected')
                : hm_trans('Save & Apply to Selected')
            );
            $applyBtn.prop('disabled', count === 0);
        }

        const templateEl = document.querySelector('#custom_action_template');
        if (templateEl) {
            custom_action_modal.setContent(templateEl.innerHTML);
        }
        custom_action_modal.open();
    });
}
