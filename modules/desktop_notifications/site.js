'use strict';

$(function() {
    if (!window.inAppContext) {
        return;
    }
    
    window.addEventListener('new-message', (event) => {
        const row = event.detail.row;
        const content = $(row).find('.from').text() + ' - ' + $(row).find('.subject').text();

        const pushNotification = () => Push.create(hm_trans("New Message"), {
            body: content,
            timeout: 10000,
            icon: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAIBJREFUWIXtlkEOgCAMBEdfpj/Hl+FB8GBiWoqEg7sJt7YzTXoAlL9nAfJMgXUmXAJVYAeOCeyjsO9sQOI6ypEvFdZrRomY4FEiJtgqiIp45zY3fAWu9d0Devu6N4mCTYHw9TrBboFWES+4WcASaQWHBZ4iUXAGsv4DEpCABBTlBOkR5VdJRFCfAAAAAElFTkSuQmCC',
            onClick: function () { window.focus(); this.close(); }
        });

        if (Push.Permission.has()) {
            pushNotification();
        } else {
            Push.Permission.request(pushNotification);
        }
    });

    // refresh the unread messages state
    setInterval(() => {
        // undefined_undefined: load with no filter and no keyword
        new Hm_MessagesStore('unread', getParam('list_page') || 1, 'undefined_undefined').load(true, true).then((store) => {
            store.newMessages.forEach((messageRow) => {
                triggerNewMessageEvent($(messageRow).data('uid'), $(messageRow)[0]);
            });
        });
    }, 60000);
});
