function applyImapMessageListPageHandlers(routeParams) {
    const refreshInterval = setup_imap_folder_page(routeParams.list_path);

    sortHandlerForMessageListAndSearchPage();

    imap_setup_snooze();
    imap_setup_tags();

    if (window.githubMessageListPageHandler) githubMessageListPageHandler(routeParams);

    return async function() {
        const refreshIntervalId = await refreshInterval;
        clearInterval(refreshIntervalId);
    }
}

function applyImapMessageContentPageHandlers(routeParams) {
    imap_setup_message_view_page(routeParams.uid, null, routeParams.list_path, handleExternalResources);
    imap_setup_snooze();
    imap_setup_tags();

    if (window.feedMessageContentPageHandler) feedMessageContentPageHandler(routeParams);
    if (window.githubMessageContentPageHandler) githubMessageContentPageHandler(routeParams);
}