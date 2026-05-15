function handleSieveCustomAction() {
    const custom_action_modal = new Hm_Modal({
        size: 'xl',
        modalId: 'myCustomActionModal',
    });

    custom_action_modal.setTitle(hm_trans('Setup Custom Action from selected messages'));

    custom_action_modal.addFooterBtn(
        hm_trans('Save Custom Action'),
        'btn-secondary ms-auto',
        function () {
            createCustomActionFromList(custom_action_modal, { applyAfterSave: false });
        },
    );

    custom_action_modal.addFooterBtn(
        hm_trans('Save & Apply to Selected'),
        'btn-primary save_and_apply_btn',
        function () {
            createCustomActionFromList(custom_action_modal, {
                applyAfterSave: true,
                imapAccount: custom_action_modal._imapAccount || '',
            });
        },
    );

    $('#add_custom_action_button').on('click', function (e) {
        e.preventDefault();

        const mailbox = $(this).attr('account');
        current_mailbox_for_filter = mailbox;
        custom_action_modal._imapAccount = mailbox;

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
        console.log("selected from custom action:", selected);

        // Update apply button label with selected count
        const count = selected.length;
        const $applyBtn = custom_action_modal.modalFooter.find('.save_and_apply_btn');
        $applyBtn.text(count > 0
            ? hm_trans('Save & Apply to') + ' ' + count + ' ' + hm_trans('Selected')
            : hm_trans('Save & Apply to Selected')
        );
        $applyBtn.prop('disabled', count === 0);

        const templateEl = document.querySelector('#custom_action_template');
        if (templateEl) {
            custom_action_modal.setContent(templateEl.innerHTML);
        }
        custom_action_modal.open();
    });
}
