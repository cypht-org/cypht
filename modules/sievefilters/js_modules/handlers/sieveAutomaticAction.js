function handleSieveAutomaticAction() {
    const automatic_action_modal = new Hm_Modal({
        size: 'xl',
        modalId: 'myAutomaticActionModal',
    });

    automatic_action_modal.setTitle(hm_trans('Setup Filter from selected messages'));

    automatic_action_modal.addFooterBtn(
        hm_trans('Build Filter'),
        'btn-primary ms-auto',
        async function () {
            await createFilterFromList(automatic_action_modal);
        },
    );

    $('#add_automatic_action_button').on('click', function (e) {
        e.preventDefault();

        const mailbox = $(this).attr('account');
        current_mailbox_for_filter = mailbox;

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

        function extractKeywords(subject) {
            return subject
                .toLowerCase()
                .replace(/[^\w\s]/g, '')
                .split(/\s+/)
                .filter((w) => w.length > 3); // ignore small words
        }

        const fromEmails = [
            ...new Set(selected.map((m) => m.from_email).filter(Boolean)),
        ];

        const subjectKeywords = [
            ...new Set(
                selected.flatMap((m) => extractKeywords(m.subject || '')),
            ),
        ];

        automatic_action_modal.setContent(sieveAutomaticActionMarkup(mailbox));
        automatic_action_modal.open();
        
        renderChips('#filter-from-list', fromEmails);
        renderChips('#filter-subject-list', subjectKeywords);

        handleAutomaticActionSubjectFilter();
    });

}
