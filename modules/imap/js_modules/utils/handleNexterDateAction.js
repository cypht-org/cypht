function processNextActionDate(e) {
    let reload_and_redirect = async function () {
        Hm_Folders.reload_folders(true);
        let path = getListPathParam();
        await navigate(`?page=message_list&list_path=${path}`);
    };

    let collectCheckedIds = function () {
        let ids = [];
        $('input[type=checkbox]').each(function () {
            if (this.checked && this.id.search('imap') !== -1) {
                let parts = this.id.split('_');
                ids.push(parts[1] + '_' + parts[2] + '_' + parts[3]);
            }
        });
        if (ids.length === 0) {
            return;
        }
        return ids;
    };

    setupActionSchedule(function () {
        let ids = collectCheckedIds();

        Hm_Ajax.request(
            [
                { 'name': 'hm_ajax_hook', 'value': 'ajax_re_schedule_message_sending' },
                { 'name': 'scheduled_msg_ids', 'value': ids },
                { 'name': 'schedule_date', 'value': $(this).val() }
            ],
            function (res) {
                if (res.scheduled_msg_count > 0) {
                    reload_and_redirect();
                }
            }
        );
    });

    setupActionSnooze(function () {
        let ids = collectCheckedIds();

        Hm_Ajax.request(
            [
                { 'name': 'hm_ajax_hook', 'value': 'ajax_imap_snooze' },
                { 'name': 'imap_snooze_ids', 'value': ids },
                { 'name': 'imap_snooze_until', 'value': $(this).val() }
            ],
            function (res) {
                if (res.snoozed_messages > 0) {
                    reload_and_redirect();
                }
            }
        );
    });
}