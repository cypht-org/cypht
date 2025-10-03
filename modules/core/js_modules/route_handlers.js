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

    $('#settingsSearch').on('input', function () {
        const query = $(this).val().trim().toLowerCase();
        let anyMatch = false;

        $('td.settings_subtitle').each(function () {
            const $subtitleRow = $(this).closest('tr:not(:first-child)');
            const targetClass = $(this).data('target'); // e.g. ".general_setting"
            const $detailRows = $(targetClass);
            const $matchedDetails = [];

            let sectionMatches = false;
            let detailMatches = [];

            // Check if section title matches
            if ($(this).text().toLowerCase().includes(query)) {
                sectionMatches = true;
                detailMatches = $matchedDetails.push(...$detailRows.get()); // show all detail rows
            } else {
                // Check individual detail rows
                $detailRows.each(function () {
                    const $row = $(this);
                    if ($row.text().toLowerCase().includes(query)) {
                        sectionMatches = true;
                        detailMatches.push($row[0]); // add matching row
                    }
                });
            }

            if (sectionMatches || query === '') {
                anyMatch = true;
                $subtitleRow.show();

                // Show only matching detail rows (if searching), or all (if empty)
                if (query) {
                    $detailRows.hide();
                    $(detailMatches).show();
                } else {
                    $detailRows.show();
                }
            } else {
                $subtitleRow.hide();
                $detailRows.hide();
            }
        });

        // Show "No settings found" if needed
        if (! anyMatch && query !== '') {
            $('#noSettingsFound').removeClass('d-none');
        } else {
            $('#noSettingsFound').addClass('d-none');
        }
    });
}

function applySearchPageHandlers(routeParams) {
    Hm_Message_List.select_combined_view();
    sortHandlerForMessageListAndSearchPage();
    $('.search_reset').on("click", Hm_Utils.reset_search_form);
    $('.combined_sort').on("change", function() { Hm_Message_List.sort($(this).val()); });

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

function applyMessageListPageHandlers(routeParams) {
    sortHandlerForMessageListAndSearchPage();
    Hm_Message_List.set_row_events();
    const messagesStore = new Hm_MessagesStore(routeParams.list_path, routeParams.list_page, `${routeParams.keyword}_${routeParams.filter}`, routeParams.sort);
    Hm_Utils.tbody().attr('id', messagesStore.list);

    $('.core_msg_control').on("click", function(e) {
        e.preventDefault();
        Hm_Message_List.message_action($(this).data('action')); 
    });
    $('.toggle_link').on("click", function(e) {
        e.preventDefault();
        Hm_Message_List.toggle_rows();
    });

    if (window.get_list_block_sieve) get_list_block_sieve();

    if (routeParams.list_path === 'github_all') {
        return applyGithubMessageListPageHandler(routeParams);
    }
    
    if (routeParams.sort) {
        $('.combined_sort').val(routeParams.sort);
    }
    $('.combined_sort').on("change", function() {
        Hm_Message_List.sort($(this).val());
    });

    // TODO: Refactor this handler to be more modular(applicable only for the imap list type)
    return applyImapMessageListPageHandlers(routeParams);
}

function applyMessagePageHandlers(routeParams) {
    const path = routeParams.list_path.substr(0, 4);
    
    switch (path) {
        case 'imap':
        case 'trac':
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
    if (hm_is_logged()) {
        applySmtpComposePageHandlers(routeParams);
    }
    if (hm_module_is_supported('contacts')) {
        applyContactsAutocompleteComposePageHandlers(routeParams);
    }
}
