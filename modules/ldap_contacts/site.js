'use strict';

$(function() {
    $('.ldap_password_change').on("click", function() {
        $(this).prev().prop('disabled', false);
        $(this).prev().attr('placeholder', '');
    });
});
