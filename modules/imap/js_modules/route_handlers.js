function applyImapMessageListPageHandlers(routeParams) {
    const setupPageResult = setup_imap_folder_page(routeParams.list_path, routeParams.list_page);

    imap_setup_snooze();
    imap_setup_tags();

    processNextActionDate();

    if (window.inlineMessageMessageListAndSearchPageHandler) inlineMessageMessageListAndSearchPageHandler(routeParams);
    if (window.wpMessageListPageHandler) wpMessageListPageHandler(routeParams);

    return async function() {
        const [refreshIntervalId, abortController] = await setupPageResult;
        abortController.abort();
        clearInterval(refreshIntervalId);
    }
}

function applyImapMessageContentPageHandlers(routeParams) {
    imap_setup_message_view_page(routeParams.uid, null, routeParams.list_path, routeParams.list_parent, imap_setup_tags);
    imap_setup_snooze();

    const messages = new Hm_MessagesStore(routeParams.list_path, routeParams.list_page);
    messages.load(false);
    const next = messages.getNextRowForMessage(routeParams.uid);
    const prev = messages.getPreviousRowForMessage(routeParams.uid);
    if (next) {
        const nextMessageUid = $(next['0']).data('uid');
        preFetchMessageContent(false, nextMessageUid, routeParams.list_path);
    }
    if (prev) {
        const prevMessageUid = $(prev['0']).data('uid');
        preFetchMessageContent(false, prevMessageUid, routeParams.list_path);
    }

    if (window.pgpMessageContentPageHandler) pgpMessageContentPageHandler();
    if (window.wpMessageContentPageHandler) wpMessageContentPageHandler(routeParams);
}