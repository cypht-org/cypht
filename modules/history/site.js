'use strict';

$(function() {
    if (hm_page_name() == 'history') {
        // When Message list style setting is set to news
        $('.news_cell').removeClass('checkbox_cell');
        $('.news_cell').attr('colspan', 5);
    }
});
