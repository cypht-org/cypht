function applyImapMessageListPageHandlers(routeParams) {
    select_imap_folder(routeParams.list_path);
}

function applyImapMessageContentPageHandlers(routeParams) {
    imap_setup_message_view_page(routeParams.uid, null, routeParams.list_path, handleExternalResources);
}