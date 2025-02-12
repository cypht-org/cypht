async function sortCombinedLists(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    
    history.pushState(null, null, url.toString());
    location.next = url.search;
    const messagesStore = new Hm_MessagesStore(getListPathParam(), getParam('page'));
    try {
        await messagesStore.load(true);
        Hm_Utils.tbody().attr('id', messagesStore.list);
        display_imap_mailbox(messagesStore.rows, null, messagesStore.list);
    } catch (error) {
        Hm_Notices.show('Failed to load messages', 'danger');
    }
}
