function applyContactsPageHandlers() {
    $('.delete_contact').on("click", function() {
        delete_contact($(this).data('id'), $(this).data('source'), $(this).data('type'));
        return false;
    });
    $('.show_contact').on("click", function() {
        $('#'+$(this).data('id')).toggle();
        return false;
    });
    $('.reset_contact').on("click", function() {
        Hm_Utils.redirect('?page=contacts');
    });
    $('.server_title').on("click", function() {
        $(this).next().toggle();
    });
    $('#contact_phone').on("keyup", function() {
        let contact_phone = $('#contact_phone').val();
        const regex_number = new RegExp('^\\d+$');
        const allowed_characters = ['+','-','(',')'];
        for (let chain_counter = 0; chain_counter < contact_phone.length; chain_counter++) {
            if(!(regex_number.test(contact_phone[chain_counter])) && !(allowed_characters.indexOf(contact_phone[chain_counter]) > -1)){
                Hm_Notices.show([hm_trans("This phone number appears to contain invalid character (s).\nIf you are sure ignore this warning and continue!")]);
                $(this).off();
            }
        }

    });
    $('.source_link').on("click", function () {
        $('.list_actions').toggle(); $('#list_controls_menu').hide();
        return false;
    });
    contact_import_pagination();
}