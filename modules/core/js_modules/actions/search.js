async function performSearch(routeParams) {
    if (routeParams.search_terms) {
        const messages = new Hm_MessagesStore('search', Hm_Utils.get_url_page_number(), `${routeParams.search_terms}_${routeParams.search_fld}_${routeParams.search_since}`, routeParams.sort);
        await messages.load(true, false, false, function() {
            display_imap_mailbox(messages.rows, messages.list, messages, false);
        });
        Hm_Message_List.check_empty_list();
    }
}