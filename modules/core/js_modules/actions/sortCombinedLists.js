async function sortCombinedLists(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    
    history.pushState(history.state, null, url.toString());
    location.next = url.search;
    const messagesStore = new Hm_MessagesStore(getListPathParam(), Hm_Utils.get_url_page_number(), `${getParam('keyword')}_${getParam('filter')}`, sortValue);
    try {
        Hm_Utils.tbody().attr('id', messagesStore.list);
        await messagesStore.load(true, false, false, store => {
            display_imap_mailbox(store.rows, store.list, store);
        });
    } catch (error) {
        Hm_Notices.show('Failed to load messages', 'danger');
    }
}
