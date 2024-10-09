function applyFoldersPageHandlers() {
    $('#imap_server_folder').on("change", function() {
        $(this).parent().parent().submit();
    });
    $('.settings_subtitle').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    
    bindFoldersEventHandlers();
}

function applyFoldersSubscriptionPageHandlers() {
    $('#imap_server_folder').on("change", function() {
        $(this).parent().parent().submit();
    });

    $('.subscribe_parent_folder').on("click", function() { return folder_page_folder_list('subscribe_parent_folder_select', 'subscribe_title', 'imap_parent_folder_link', '', 'subscribe_parent', true); });
    $('.subscribe_parent_folder').trigger('click');
    $($('.subscribe_parent_folder_select .imap_parent_folder_link')[0]).trigger('click');
    const selected_imap_server = $('#imap_server_folder').val();
    const email_folder_server = $(`.email_folders .imap_${selected_imap_server}_ .inner_list`);
    if (email_folder_server && $(email_folder_server[0]).children().length) {
        $($('.subscribe_parent_folder_select .imap_parent_folder_link')[0]).trigger('click');
    }

    bindFoldersEventHandlers();
}