'use strict'
$('.config_map_page').click(function() {
    var target = $(this).data('target');
    $('.'+target).toggle();
});
