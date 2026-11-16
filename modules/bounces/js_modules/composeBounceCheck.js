/**
 * Warns on the compose page when a recipient bounced before
 */
(function() {
    const fieldSelector = '.compose_to, .compose_cc, .compose_bcc';
    let lastState = '';
    let timer = null;

    function recipientValue(selector) {
        if (typeof compose_recipient_value === 'function') {
            return compose_recipient_value(selector);
        }
        return $(selector).val() || '';
    }

    function refresh() {
        const container = $('.bounce_recipients_status');
        if (!container.length) {
            return;
        }
        const values = ['.compose_to', '.compose_cc', '.compose_bcc'].map(recipientValue);
        const state = values.join('|');
        if (state === lastState) {
            return;
        }
        lastState = state;
        if (!values.join('').trim()) {
            container.html('');
            return;
        }
        Hm_Ajax.request([
            {'name': 'hm_ajax_hook', 'value': 'ajax_bounce_recipients_status'},
            {'name': 'compose_to', 'value': values[0]},
            {'name': 'compose_cc', 'value': values[1]},
            {'name': 'compose_bcc', 'value': values[2]},
        ], function(res) {
            if (state === lastState) {
                container.html(res && res.bounce_recipients_display ? res.bounce_recipients_display : '');
            }
        }, [], true);
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(refresh, 500);
    }

    // recipients also change through bubbles: created on enter or focus loss, removed with backspace,
    // the close button or by dragging them to another field
    $(document).on('input change keyup focusout', fieldSelector, schedule);
    $(document).on('click', '.bubble_close', schedule);
    $(document).on('drop', '.compose_container', schedule);

    window.addEventListener('page-change', function() {
        lastState = '';
        setTimeout(refresh, 0);
    });
})();
