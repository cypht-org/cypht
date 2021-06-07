'use strict';

$(function() {
    $('.rule_del').on('click', function() {
        return hm_delete_prompt();
    });
    $('.hl_source_type').on('change', function() {
        $('.imap_row').hide();
        $('.github_row').hide();
        $('.feeds_row').hide();
        var selected = $(this).val();
        $('.'+selected+'_row').toggle();
    });
});
