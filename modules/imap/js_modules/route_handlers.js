function applyImapMessageListPageHandlers(routeParams) {
    const setupPageResult = setup_imap_folder_page(routeParams.list_path, routeParams.list_page);

    imap_setup_snooze();
    imap_setup_tags();

    setupScreening();

    processNextActionDate();

    if (window.inlineMessageMessageListAndSearchPageHandler) inlineMessageMessageListAndSearchPageHandler(routeParams);
    if (window.wpMessageListPageHandler) wpMessageListPageHandler(routeParams);

    return async function() {
        const cleanupFunction = await setupPageResult;
        if (cleanupFunction) {
            cleanupFunction();
        }
    }
}

function applyImapMessageContentPageHandlers(routeParams) {
    imap_setup_message_view_page(routeParams.uid, null, routeParams.list_path, routeParams.list_parent, () => {
        imap_setup_tags();
        imap_setup_snooze();
        window.dispatchEvent(new CustomEvent('message-loaded'));
    });

    const listPath = routeParams.list_parent || routeParams.list_path;
    const messages = new Hm_MessagesStore(listPath, routeParams.list_page, `${routeParams.keyword}_${routeParams.filter}`, getParam('sort'));
    messages.load(false);
    const next = messages.getNextRowForMessage(routeParams.uid);
    const prev = messages.getPreviousRowForMessage(routeParams.uid);
    if (next) {
        const nextHref = $(next['0']).find(".subject a").prop('href');
        const nextListPath = new URLSearchParams(nextHref.split('?')[1]).get('list_path');
        const nextMessageUid = $(next['0']).data('uid');
        preFetchMessageContent(false, nextMessageUid, nextListPath);
    }
    if (prev) {
        const prevHref = $(prev['0']).find(".subject a").prop('href');
        const prevListPath = new URLSearchParams(prevHref.split('?')[1]).get('list_path');
        const prevMessageUid = $(prev['0']).data('uid');
        preFetchMessageContent(false, prevMessageUid, prevListPath);
    }

    if (window.pgpMessageContentPageHandler) pgpMessageContentPageHandler();
    if (window.wpMessageContentPageHandler) wpMessageContentPageHandler(routeParams);
}