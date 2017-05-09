'use strict';


$(function() {
    $('body').on('new_message', function() {
        var unread_page = false;
        if (hm_page_name() == 'message_list' && hm_list_path() == 'unread') {
            unread_page = true;
        }
        if (!document.hidden && unread_page) {
            return;
        }
        var current = $('.total_unread_count').text()*1;
        var past = Hm_Message_List.past_total;
        if (current == past || current < past) {
            return;
        }
        var content;
        if (unread_page) {
            content = Hm_Message_List.just_inserted.reverse().join("\n\n");
        }
        else if (globals.Hm_Background_Unread) {
            content = globals.Hm_Background_Unread.just_inserted.reverse().join("\n\n");
        }
        if (!content) {
            return;
        }
        Push.create("New Message", {
            body: content,
            timeout: 10000,
            icon: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAIBJREFUWIXtlkEOgCAMBEdfpj/Hl+FB8GBiWoqEg7sJt7YzTXoAlL9nAfJMgXUmXAJVYAeOCeyjsO9sQOI6ypEvFdZrRomY4FEiJtgqiIp45zY3fAWu9d0Devu6N4mCTYHw9TrBboFWES+4WcASaQWHBZ4iUXAGsv4DEpCABBTlBOkR5VdJRFCfAAAAAElFTkSuQmCC',
            onClick: function () { window.focus(); this.close(); }
        });
    });
});
