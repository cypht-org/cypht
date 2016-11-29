'use strict';

if (hm_page_name() == 'compose') {
    $('.compose_sign').click(function() {
        var server_id = $('.compose_server').val();
        if (profile_signatures[server_id]) {
            var ta = $('.ke-content', $('iframe').contents());
            if (ta.length) {
                ta.html(ta.html() + profile_signatures[server_id].replace(/\n/g, '<br />'));
            }
            else {
                ta = $('#compose_body');
                insert_sig(ta[0], profile_signatures[server_id]);
            }
        }
    });
}

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
