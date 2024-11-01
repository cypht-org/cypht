function applyImapMessageListPageHandlers(routeParams) {
    const setupPageResult = setup_imap_folder_page(routeParams.list_path);

    sortHandlerForMessageListAndSearchPage();

    imap_setup_snooze();
    imap_setup_tags();
    handleMessagesDragAndDrop();

    Hm_Message_List.set_row_events();

    $('.core_msg_control').on("click", function(e) {
        e.preventDefault();
        Hm_Message_List.message_action($(this).data('action')); 
    });
    $('.toggle_link').on("click", function(e) {
        e.preventDefault();
        Hm_Message_List.toggle_rows();
    });

    if (window.githubMessageListPageHandler) githubMessageListPageHandler(routeParams);
    if (window.inlineMessageMessageListAndSearchPageHandler) inlineMessageMessageListAndSearchPageHandler(routeParams);
    if (window.wpMessageListPageHandler) wpMessageListPageHandler(routeParams);

    return async function() {
        const [refreshIntervalId, abortController] = await setupPageResult;
        abortController.abort();
        clearInterval(refreshIntervalId);
    }
}

function applyImapMessageContentPageHandlers(routeParams) {
    imap_setup_message_view_page(routeParams.uid, null, routeParams.list_path, handleExternalResources);
    imap_setup_snooze();
    imap_setup_tags();

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

    if (window.feedMessageContentPageHandler) feedMessageContentPageHandler(routeParams);
    if (window.githubMessageContentPageHandler) githubMessageContentPageHandler(routeParams);
    if (window.pgpMessageContentPageHandler) pgpMessageContentPageHandler();
    if (window.wpMessageContentPageHandler) wpMessageContentPageHandler(routeParams);
}