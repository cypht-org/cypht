'use strict';

var update_search = function(event) {
    event.preventDefault();
    if ($('.search_terms').val().length && $('.search_name').val().length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_update_search'},
            {'name': 'search_name', 'value': $('.search_name').val()},
            {'name': 'search_terms', 'value': $('.search_terms').val()},
            {'name': 'search_fld', 'value': $('#search_fld').val()},
            {'name': 'search_since', 'value': $('#search_since').val()}],
            search_update_results
        );
    }
    return false;
};

var delete_search = function(event) {
    if (!hm_delete_prompt()) {
        return false;
    }
    var name = $('.search_name').val();
    event.preventDefault();
    if (name.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_delete_search'},
            {'name': 'search_name', 'value': name}],
            search_delete_results
        );
    }
    return false;
};

var save_search = function(event) {
    event.preventDefault();
    if ($('.search_terms').val().length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_save_search'},
            {'name': 'search_name', 'value': $('.search_terms').val()},
            {'name': 'search_terms', 'value': $('.search_terms').val()},
            {'name': 'search_fld', 'value': $('#search_fld').val()},
            {'name': 'search_since', 'value': $('#search_since').val()}],
            search_save_results
        );
    }
    return false;
};


var update_save_search_label = function(event) {
    event.preventDefault();
    if($('.search_label_update').val().length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_update_save_search_label'},
            {'name': 'search_terms_label', 'value': $('.search_terms_label').val()},
            {'name': 'old_search_terms_label', 'value': $('.old_search_terms_label').val()},
            {'name': 'search_name', 'value': $('.search_terms').val()}],
            update_save_search_label_results
        );
    }
    return false;
}

var search_delete_results = function(res) {
    if (res.saved_search_result) {
        Hm_Folders.reload_folders(true, '.search_folders');
        Hm_Utils.reset_search_form();
    }
};

var search_update_results = function(res) {
    if (res.saved_search_result) {
        $('.update_search').remove();
        Hm_Folders.reload_folders(true, '.search_folders');
    }
};

var search_save_results = function(res) {
    if (res.saved_search_result) {
        $('.search_name').val($('.new_search_name').val());
        $('.delete_search').show();
        $('.save_search').hide();
        Hm_Folders.reload_folders(true, '.search_folders');
    }
};

var update_save_search_label_results = function(res) {
    if(res.saved_search_result) {
        $('.update_search_label_field').toggle();
        $('.search_terms_label').val("");
        $('.old_search_terms_label').val(res.new_saved_search_label);
        Hm_Folders.reload_folders(true, '.search_folders');
    }
}

if (hm_page_name() == 'search') {
    $('.save_search').on("click", save_search);
    $('.update_search').on("click", update_search);
    $('.delete_search').on("click", delete_search);
    $('.update_search_label').on("click", function() { $('.update_search_label_field').toggle(); return false; });
    $('.search_label_update').on("click", update_save_search_label);
    if ($('.search_name').val().length) {
        Hm_Utils.save_to_local_storage('formatted_search_data', '');
    }
    else if ($('.search_terms').val().length) {
        $('.save_search').show();
        $('.update_search_label').show()
    }
}
