var delete_contact = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_delete_contact'},
        {'name': 'contact_id', 'value': id}],
        function(res) { $('.contact_row_'+id).remove(); }
    );
};

var add_contact_from_message_view = function() {
    var contact = $('#add_contact').val();
    if (contact) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_add_contact'},
            {'name': 'contact_value', 'value': contact}],
            function(res) { $('.add_contact_controls').toggle(); }
        );
    }
};

var autocomplete_contact = function(e, class_name, list_div) {
    var key_code = e.keyCode;
    if (key_code >= 37 && key_code <= 40) {
        return;
    }
    var div = $('<div></div>');
    var fld_val = $(class_name).val();
    var addresses = fld_val.split(' ');
    var first = '';
    if (addresses.length > 1) {
        fld_val = addresses.pop();
    }
    if (fld_val.length > 3) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_autocomplete_contact'},
            {'name': 'contact_value', 'value': fld_val}],
            function(res) {
                if (res.contact_suggestions) {
                    var i;
                    var count = 0;
                    $(list_div).html('');
                    for (i in res.contact_suggestions) {
                        div.html(res.contact_suggestions[i]);
                        if ($(class_name).val().match(div.text())) {
                            continue;
                        }
                        if (count == 0) {
                            first = 'first ';
                        }
                        else {
                            first = '';
                        }
                        count++;
                        $(list_div).append('<a tabindex="1" href="#" class="'+first+'contact_suggestion unread_link">'+res.contact_suggestions[i]+'</a>');
                    }
                    if (count > 0) {
                        $(list_div).show();
                        setup_autocomplete_events(class_name, list_div, fld_val);
                    }
                }
            }
        );
    }
};

var autocomplete_keyboard_nav = function(event, list_div, class_name, fld_val) {
    var in_list = false;
    if (event.keyCode == 40) {
        if ($(event.target).prop('nodeName') == 'INPUT') {
            $('.first').addClass('selected_menu');
            $('.first').focus();
            in_list = true;
        }
        else {
            $(event.target).removeClass('selected_menu');
            $(event.target).next().addClass('selected_menu');
            $(event.target).next().focus();
            in_list = true;
        }
        return false;
    }
    else if (event.keyCode == 38) {
        if ($(event.target).prev().length) {
            $(event.target).removeClass('selected_menu');
            $(event.target).prev().addClass('selected_menu');
            $(event.target).prev().focus();
            in_list = true;
        }
        else {
            $(class_name).focus();
            $(event.target).removeClass('selected_menu');
        }
        return false;
    }
    else if (event.keyCode == 13) {
        $(class_name).focus();
        $(list_div).hide();
        add_autocomplete(event, class_name, list_div, fld_val);
        return false;
    }
    else if (event.keyCode == 27) {
        $(list_div).html('');
        $(list_div).hide();
        $(class_name).focus();
        return false;
    }
    if (in_list) {
        return false;
    }
    return true;
};

var setup_autocomplete_events = function(class_name, list_div, fld_val) {
    $('.contact_suggestion').click(function() { return add_autocomplete(event, class_name, list_div, fld_val); });
    $(class_name).keydown(function(event) { return autocomplete_keyboard_nav(event, list_div, class_name, fld_val); });
    $('.contact_suggestion').keydown(function(event) { return autocomplete_keyboard_nav(event, list_div, class_name, fld_val); });
    $(document).click(function() { $(list_div).hide(); });
};

var add_autocomplete = function(event, class_name, list_div, fld_val) {
    var new_address = $(event.target).text()
    var existing = $(class_name).val();
    var re = new RegExp(fld_val+'$');
    existing = existing.replace(re, '');
    if (existing.length) {
        existing = existing.replace(/[\s,]+$/, '')+', ';
    }
    $(list_div).html('');
    $(list_div).hide();
    $(class_name).val(existing+new_address);
    $(class_name).focus();
    return false;
};

if (hm_page_name() == 'contacts') {
    $('.delete_contact').click(function() {
        delete_contact($(this).data('id'));
    });
    $('.reset_contact').click(function() {
        window.location.href = '?page=contacts';
    });
}
else if (hm_page_name() == 'compose') {
    $('.compose_to').keyup(function(e) { autocomplete_contact(e, '.compose_to', '#to_contacts'); });
    $('.compose_cc').keyup(function(e) { autocomplete_contact(e, '.compose_cc', '#cc_contacts'); });
    $('.compose_bcc').keyup(function(e) { autocomplete_contact(e, '.compose_bcc', '#bcc_contacts'); });
    $('.compose_to').focus();
}
