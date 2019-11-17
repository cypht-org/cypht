'use strict';

if (hm_page_name() == 'compose') {
    $('.compose_sign').on("click", function() {
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
        } else {
            Hm_Notices.show(['ERR'+$('#sign_msg').val()]);
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

$(function() {
    if (hm_page_name() === 'profiles') {
        $('.add_profile').on("click", function() { $('.edit_profile').show(); });
    }
});
