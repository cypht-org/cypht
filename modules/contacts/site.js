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

if (hm_page_name() == 'contacts') {
    $('.delete_contact').click(function() {
        delete_contact($(this).data('id'));
    });
}
