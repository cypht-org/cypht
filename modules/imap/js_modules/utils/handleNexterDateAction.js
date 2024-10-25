function handleNexterDateAction(e) {
    let reload_and_redirect = function () {
        Hm_Folders.reload_folders(true);
        let path = hm_list_parent() ? hm_list_parent() : hm_list_path();
        window.location.replace('?page=message_list&list_path=' + path);
    };
    
    setupNexterDate(function () {
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

        if ($(this).parent().parent().is('.snooze_dropdown')) {
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
        }
        if ($(this).parent().parent().is('.schedule_dropdown')) {
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
        }
    });
}