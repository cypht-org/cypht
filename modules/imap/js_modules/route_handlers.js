function applyImapMessageListPageHandlers(routeParams) {
    const setupPageResult = setup_imap_folder_page(routeParams.list_path);

    sortHandlerForMessageListAndSearchPage();

    imap_setup_snooze();
    imap_setup_tags();

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

    if (window.feedMessageContentPageHandler) feedMessageContentPageHandler(routeParams);
    if (window.githubMessageContentPageHandler) githubMessageContentPageHandler(routeParams);
    if (window.pgpMessageContentPageHandler) pgpMessageContentPageHandler();
    if (window.wpMessageContentPageHandler) wpMessageContentPageHandler(routeParams);
}