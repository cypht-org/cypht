'use strict';

$('.config_map_page').on("click", function() {
    var target = $(this).data('target');
    $('.'+target).toggle();
});
