function applyAdvancedSearchPageHandlers() {
    globals.close_html = '<i class="bi bi-x-circle-fill cursor-pointer"></i>';

    $('.settings_subtitle').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
    $('.adv_folder_select').on("click", function() { adv_select_imap_folder(this); });
    $('.new_time').on("click", function() { add_remove_times(this); });
    $('.new_target').on("click", function() { add_remove_targets(this); });
    $('.new_term').on("click", function() { add_remove_terms(this); });
    $('.adv_expand_all').on("click", function() { adv_expand_sections(); });
    $('.adv_collapse_all').on("click", function() { adv_collapse(); });
    $('#adv_search').on("click", function() { process_advanced_search(); });
    $('.toggle_link').on("click", function() { return Hm_Message_List.toggle_rows(); });
    $('.adv_reset').on("click", function() { adv_reset_page(); });
    $('.combined_sort').on("change", function() { Hm_Message_List.sort($(this).val()); });

    $(document).off('click', '.show_save_advanced_search');
    $(document).on('click', '.show_save_advanced_search', function(e) {
        e.preventDefault();
        $('.save_advanced_search_form').show();
        $('.show_save_advanced_search').hide();
        $('.advanced_search_name').focus();
        return false;
    });

    $(document).off('click', '.cancel_save_advanced_search');
    $(document).on('click', '.cancel_save_advanced_search', function(e) {
        e.preventDefault();
        $('.save_advanced_search_form').hide();
        $('.show_save_advanced_search').show();
        $('.advanced_search_name').val('');
        return false;
    });

    $(document).off('click', '.save_advanced_search_btn');
    $(document).on('click', '.save_advanced_search_btn', function(e) {
        e.preventDefault();
        return save_advanced_search();
    });

    $(document).off('click', '.update_advanced_search_btn');
    $(document).on('click', '.update_advanced_search_btn', function(e) {
        e.preventDefault();
        return update_advanced_search();
    });

    $(document).off('click', '.delete_advanced_search_btn');
    $(document).on('click', '.delete_advanced_search_btn', function(e) {
        e.preventDefault();
        return delete_advanced_search();
    });

    apply_saved_search();
    var data = Hm_Utils.get_from_local_storage('formatted_advanced_search_data');
    if (data && data.length) {
        adv_collapse();
        Hm_Utils.tbody().html(data);
        $('.adv_controls').show();
        $('.core_msg_control').off('click');
        $('.core_msg_control').on("click", function() { return Hm_Message_List.message_action($(this).data('action')); });
        if (typeof check_select_for_imap !== 'undefined') {
            check_select_for_imap();
        }
    }
    Hm_Message_List.check_empty_list();
}