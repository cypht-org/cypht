'use strict';


$(function() {
    $('body').on('new_message', function() {
        if (document.hidden && hm_page_name() == 'unread') {
            return;
        }
        var current = $('.total_unread_count').text()*1;
        var past = Hm_Message_List.past_total;
        if (current == past || current < past) {
            return;
        }
        var content;
        if (hm_page_name() == 'unread') {
            content = Hm_Message_List.just_inserted.join("\n");
        }
        else {
            content = globals.Hm_Background_Unread.just_inserted.join("\n");
        }
        Push.create("New Message", {
            body: content,
            timeout: 10000,
            link: '?page=unread',
            icon: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAIBJREFUWIXtlkEOgCAMBEdfpj/Hl+FB8GBiWoqEg7sJt7YzTXoAlL9nAfJMgXUmXAJVYAeOCeyjsO9sQOI6ypEvFdZrRomY4FEiJtgqiIp45zY3fAWu9d0Devu6N4mCTYHw9TrBboFWES+4WcASaQWHBZ4iUXAGsv4DEpCABBTlBOkR5VdJRFCfAAAAAElFTkSuQmCC',
            onClick: function () { window.focus(); this.close(); }
        });
    });
});
