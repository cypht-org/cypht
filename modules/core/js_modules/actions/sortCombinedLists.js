async function sortCombinedLists(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    
    history.pushState(history.state, null, url.toString());
    location.next = url.search;
    const messagesStore = new Hm_MessagesStore(getListPathParam(), getParam('page'), `${getParam('keyword')}_${getParam('filter')}`);
    try {
        await messagesStore.load(true);
        Hm_Utils.tbody().attr('id', messagesStore.list);
        display_imap_mailbox(messagesStore.rows, messagesStore.list, messagesStore);
    } catch (error) {
        Hm_Notices.show('Failed to load messages', 'danger');
    }
}
