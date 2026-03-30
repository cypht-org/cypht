function processNextActionDate(e) {
    let reload_and_redirect = async function () {
        Hm_Folders.reload_folders(true);
        let path = getListPathParam();
        await navigate(`?page=message_list&list_path=${path}`);
    };

    let collectCheckedIds = function () {
        const ids = [];
        if (getPageNameParam() == 'message') {
            const list_path = getListPathParam().split('_');
            ids.push(list_path[1]+'_'+getMessageUidParam()+'_'+list_path[2]);
        } else {
            $('input[type=checkbox]').each(function () {
                if (this.checked && this.id.search('imap') !== -1) {
                    let parts = this.id.split('_');
                    ids.push(parts[1] + '_' + parts[2] + '_' + parts[3]);
                }
            });
            if (ids.length === 0) {
                return;
            }
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
            async function (res) {
                const snoozedMessages = Object.values(res['snoozed_messages']);
                if (snoozedMessages.length) {
                    const path = getParam("list_parent") || getListPathParam();
                    const store = new Hm_MessagesStore(path, Hm_Utils.get_url_page_number(), `${getParam('keyword')}_${getParam('filter')}`, getParam('sort'));
                    await store.load(false, true, true);

                    snoozedMessages.forEach((msg) => {
                        store.removeRow(msg);
                    });
                    if (getPageNameParam() == 'message_list') {
                        display_imap_mailbox(store.rows, store.list, store);
                    } else {
                        const nextLink = $('.nlink').attr('href');
                        if (nextLink) {
                            navigate(nextLink);
                        } else {
                            navigate(`?page=message_list&list_path=${getParam('list_parent')}&list_page=${getParam('list_page')}&sort=${getParam('sort')}`);
                        }
                    }

                    Hm_Folders.reload_folders(true);
                }
            }
        );
    });
}