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
}

function applySettingsPageHandlers() {
    Hm_Utils.expand_core_settings();
    $('.settings_subtitle').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    $('.reset_default_value_checkbox').on("click", reset_default_value_checkbox);
    $('.reset_default_value_select').on("click", reset_default_value_select);
    $('.reset_default_value_input').on("click", reset_default_value_input);
    $('.reset_default_timezone').on("click", reset_default_timezone);

    if (window.expand_feed_settings) expand_feed_settings();
}

function applySearchPageHandlers() {
    Hm_Message_List.select_combined_view();
    sortHandlerForMessageListAndSearchPage();
    $('.search_reset').on("click", Hm_Utils.reset_search_form);
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

    return () => {
        clearTimeout(timer);
    }
}