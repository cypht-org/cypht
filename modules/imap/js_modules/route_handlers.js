function applyImapMessageListPageHandlers(routeParams) {
    select_imap_folder(routeParams.list_path); // Imap is OK, but other types don't work with this handler yet
    // TODO: - Support folders from other sources (Combined, Feeds, and JMAP as well maybe.)
}

function applyImapMessageContentPageHandlers(routeParams) {
    imap_setup_message_view_page(routeParams.uid, null, routeParams.list_path, handleExternalResources);
}