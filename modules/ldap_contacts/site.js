'use strict';

$(function() {
    $('.ldap_password_change').click(function() {
        $(this).prev().prop('disabled', false);
        $(this).prev().attr('placeholder', '');
    });
});
