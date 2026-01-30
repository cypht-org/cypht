$(document).ready(function() {
    function updateDataRequestButton($section) {
        const $target = $section.find('.js-data-request-target:checked');
        const $type = $section.find('.js-data-request-type:checked');
        const $button = $section.find('.js-data-request-button');
        const baseUrl = $section.data('base-url') || '';
        const slug = $target.data('company-slug') || '';
        const requestType = $type.val() || 'access';

        if (!baseUrl || !slug) {
            $button.attr('aria-disabled', 'true').addClass('disabled');
            $button.removeAttr('href');
            return;
        }

        const url = `${baseUrl}/generator?company=${encodeURIComponent(slug)}&request_type=${encodeURIComponent(requestType)}`;
        $button.attr('href', url);
        $button.attr('aria-disabled', 'false').removeClass('disabled');
    }

    $(document).on('change', '.js-data-request-target, .js-data-request-type', function() {
        const $section = $(this).closest('.vendor-detection-requests');
        updateDataRequestButton($section);
    });

    $('.vendor-detection-requests').each(function() {
        updateDataRequestButton($(this));
    });
});
$(document).ready(function() {
    function updateDataRequestButton($section) {
        const $target = $section.find('.js-data-request-target:checked');
        const $type = $section.find('.js-data-request-type:checked');
        const $button = $section.find('.js-data-request-button');
        const baseUrl = $section.data('base-url') || '';
        const slug = $target.data('company-slug') || '';
        const requestType = $type.val() || 'access';

        if (!baseUrl || !slug) {
            $button.attr('aria-disabled', 'true').addClass('disabled');
            $button.removeAttr('href');
            return;
        }

        const url = `${baseUrl}/generator?company=${encodeURIComponent(slug)}&request_type=${encodeURIComponent(requestType)}`;
        $button.attr('href', url);
        $button.attr('aria-disabled', 'false').removeClass('disabled');
    }

    $(document).on('change', '.js-data-request-target, .js-data-request-type', function() {
        const $section = $(this).closest('.vendor-detection-requests');
        updateDataRequestButton($section);
    });

    $('.vendor-detection-requests').each(function() {
        updateDataRequestButton($(this));
    });
});
