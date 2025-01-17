function applyServersPageHandlers() {
    $('.server_section').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    $('.edit_server_connection').on('click', imap_smtp_edit_action);
    // NUX
    expand_server_settings();
    $('.nux_next_button').on("click", nux_service_select);
    $('#service_select').on("change", function() {
        if ($(this).val() == 'all-inkl') {
            add_extra_fields(this, 'all_inkl_login', 'Login', hm_trans('Your All-inkl Login'));
        } else {
            $('.nux_extra_fields_container').remove();
        }
    });

    // Optional modules
    if (window.feedServersPageHandler) feedServersPageHandler();
    if (window.githubServersPageHandler) githubServersPageHandler();
    if (window.nasaServersPageHandler) nasaServersPageHandler();
    if (window.smtpServersPageHandler) smtpServersPageHandler();
    if (window.imapServersPageHandler) imapServersPageHandler();
    if (window.wpServersPageHandler) wpServersPageHandler();
}

function applySettingsPageHandlers(routeParams, hash) {
    if (hash) {
        Hm_Utils.toggle_page_section(`.${hash}`);
    }
    
    $('.settings_subtitle').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    $('.reset_default_value_checkbox').on("click", reset_default_value_checkbox);
    $('.reset_default_value_select').on("click", reset_default_value_select);
    $('.reset_default_value_input').on("click", reset_default_value_input);
    $('.reset_default_timezone').on("click", reset_default_timezone);
    if (window.smtpSettingsPageHandler) smtpSettingsPageHandler();
}

function applySearchPageHandlers(routeParams) {
    Hm_Message_List.select_combined_view();
    sortHandlerForMessageListAndSearchPage();
    $('.search_reset').on("click", Hm_Utils.reset_search_form);

    performSearch(routeParams);

    if (window.inlineMessageMessageListAndSearchPageHandler) inlineMessageMessageListAndSearchPageHandler(routeParams);
    if (window.savedSearchesSearchPageHandler) savedSearchesSearchPageHandler();
}

function applyHomePageHandlers() {
    $('.pw_update').on("click", function() { update_password($(this).data('id')); });
}

function applyInfoPageHandlers() {
    const timer = setTimeout(() => {
        imap_status_update();
        if (window.feed_status_update) feed_status_update();
        if (window.github_repo_update) github_repo_update();
    }, 100);

    $('.config_map_page').on("click", function() {
        var target = $(this).data('target');
        $('.'+target).toggle();
    });

    return () => {
        clearTimeout(timer);
    }
}

function applyMessaleListPageHandlers(routeParams) {
    sortHandlerForMessageListAndSearchPage();
    Hm_Message_List.set_row_events();
    const messagesStore = new Hm_MessagesStore(routeParams.list_path, routeParams.list_page);
    Hm_Utils.tbody().attr('id', messagesStore.list);

    $('.core_msg_control').on("click", function(e) {
        e.preventDefault();
        Hm_Message_List.message_action($(this).data('action')); 
    });
    $('.toggle_link').on("click", function(e) {
        e.preventDefault();
        Hm_Message_List.toggle_rows();
    });

    get_list_block_sieve();

    if (routeParams.list_path === 'github_all') {
        return applyGithubMessageListPageHandler(routeParams);
    }

    // TODO: Refactor this handler to be more modular(applicable only for the imap list type)
    return applyImapMessageListPageHandlers(routeParams);
}

function applyMessagePageHandlers(routeParams) {
    const path = routeParams.list_path.substr(0, 4);
    
    switch (path) {
        case 'imap':
            return applyImapMessageContentPageHandlers(routeParams);
        case 'feed':
            return applyFeedMessageContentPageHandlers(routeParams);
        case 'gith':
            return applyGithubMessageContentPageHandlers(routeParams);
    
        default:
            break;
    }
}

function applyComposePageHandlers(routeParams) {
    applySmtpComposePageHandlers(routeParams);
    if (hm_module_is_supported('contacts')) {
        applyContactsAutocompleteComposePageHandlers(routeParams);
    }
}
