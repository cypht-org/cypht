'use strict';

var insert_sig = function(textarea, sig) {
    var tmpta = document.createElement('textarea');
    tmpta.innerHTML = sig;
    sig = tmpta.value;
    if (document.selection) {
        textarea.focus();
        var sel = document.selection.createRange();
        sel.text = sig;
    }
    else if (textarea.selectionStart || textarea.selectionStart == '0') {
        var startPos = textarea.selectionStart;
        var endPos = textarea.selectionEnd;
        textarea.value = textarea.value.substring(0, startPos) + sig + textarea.value.substring(endPos, textarea.value.length);
    }
    else {
        textarea.value += textarea;
    }
};

function profilesComposePageHandler() {
    // Ensure profile_signatures is defined
    if (typeof profile_signatures === 'undefined') {
        window.profile_signatures = {};
    }

    $('.compose_sign').on("click", function() {
        var server_id = $('.compose_server').val();
        if (profile_signatures && profile_signatures[server_id]) {
            var ta = $('.ke-content', $('iframe').contents());
            if (ta.length) {
                if (window.sig_is_html) {
                    ta.html(ta.html() + profile_signatures[server_id]);
                } else {
                    ta.html(ta.html() + profile_signatures[server_id].replace(/\n/g, '<br />'));
                }
            }
            else {
                ta = $('#compose_body');
                var sig = profile_signatures[server_id];
                var tmp = document.createElement('div');
                tmp.innerHTML = sig;
                var plainSig = (tmp.textContent || tmp.innerText)
                    .split('\n')
                    .map(function(l) { return l.trim(); })
                    .join('\n')
                    .replace(/\n{2,}/g, '\n')
                    .trim();
                sig = '\n' + plainSig + '\n';
                insert_sig(ta[0], sig);
            }
        } else {
            Hm_Notices.show($('#sign_msg').val(), 'danger');
        }
    });
}
