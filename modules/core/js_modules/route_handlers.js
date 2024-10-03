function applyServersPageHandlers() {
    $('.server_section').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
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
}

function applySettingsPageHandlers() {
    Hm_Utils.expand_core_settings();
    $('.settings_subtitle').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
}