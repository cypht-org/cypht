'use strict';

$(function() {
    $(document).on('click', '.js-vendor-detection-toggle', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var targetId = $btn.data('target');
        if (!targetId) {
            return;
        }
        var $panel = $('#' + targetId);
        if ($panel.length === 0) {
            return;
        }
        var isOpen = $panel.hasClass('is-open');
        $panel.toggleClass('is-open', !isOpen);
        $panel.attr('aria-hidden', isOpen ? 'true' : 'false');
        $panel.prop('hidden', isOpen);
        $btn.attr('aria-expanded', isOpen ? 'false' : 'true');
    });
});
