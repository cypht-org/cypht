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

var autocomplete_contact = function(e, class_name) {
    var key_code = e.keyCode;
    if (key_code >= 37 && key_code <= 40) {
        return;
    }
    var fld_val = $(class_name).val();
    if (fld_val.length > 3) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_autocomplete_contact'},
            {'name': 'contact_value', 'value': fld_val}],
            autocomplete_contact_results
        );
    }
}

var autocomplete_contact_results = function(res) {
    var i;
    $('#to_contacts').html('');
    for (i in res.contact_suggestions) {
        $('#to_contacts').append('<option value="'+res.contact_suggestions[i]+'">');
    }
}

if (hm_page_name() == 'contacts') {
    $('.delete_contact').click(function() {
        delete_contact($(this).data('id'));
    });
}
else if (hm_page_name() == 'compose') {
    $('.compose_to').keyup(function(e) {
        autocomplete_contact(e, '.compose_to');
    });
}
