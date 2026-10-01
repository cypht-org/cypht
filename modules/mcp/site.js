'use strict';

/**
 * "API and MCP" settings page
 */
var applyMcpPageHandlers = function() {
    $('.mcp_revoke_form').on('submit', function() {
        return window.confirm(hm_trans('Revoke this connection? Clients using it will lose access immediately.'));
    });

    $('.mcp_copy').on('click', function() {
        var button = $(this);
        var input = document.getElementById(button.data('target'));
        if (!input) {
            return;
        }
        var done = function() {
            button.text(button.data('copied'));
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(done);
        } else {
            input.select();
            document.execCommand('copy');
            done();
        }
    });

    /* enable the checkbox lists only when their mode selects them */
    var syncChoices = function(form) {
        $(form).find('.mcp_choices').each(function() {
            var choices = $(this);
            var name = choices.data('mode-name');
            var value = choices.data('mode-value');
            var active = $(form).find('input[name="' + name + '"]:checked').val() === value;
            choices.toggleClass('mcp_inactive', !active);
            choices.find('input[type=checkbox]').each(function() {
                if (!$(this).data('locked')) {
                    $(this).prop('disabled', !active);
                }
            });
        });
    };
    $('.mcp_settings_page form').each(function() {
        var form = this;
        $(form).find('.mcp_choices input[type=checkbox]:disabled').data('locked', true);
        syncChoices(form);
        $(form).find('.mcp_mode').on('change', function() {
            syncChoices(form);
        });
    });
};
