function applyImapMessageListPageHandlers(routeParams) {
    const refreshInterval = setup_imap_folder_page(routeParams.list_path);
    return async function() {
        const refreshIntervalId = await refreshInterval;
        clearInterval(refreshIntervalId);
    }
}

function applyImapMessageContentPageHandlers(routeParams) {
    imap_setup_message_view_page(routeParams.uid, null, routeParams.list_path, handleExternalResources);
}