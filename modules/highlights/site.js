'use strict';

$(function() {
    $('.rule_del').on('click', function() {
        return hm_delete_prompt();
    });
    $('.hl_source_type').on('change', function() {
        $('.imap_row').addClass('d-none');
        $('.github_row').addClass('d-none');
        $('.feeds_row').addClass('d-none');
        var selected = $(this).val();
        $('.'+selected+'_row').removeClass('d-none');
    });
});
