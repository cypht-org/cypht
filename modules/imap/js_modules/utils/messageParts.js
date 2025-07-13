function handleViewMessagePart() {
    $('.msg_part_link').on("click", function(e) {
        e.preventDefault();
        const messagePart = $(this).data('messagePart');
        // We could use navigate() and completely change the current route, but inline messages are not rendered under the routing mechanism.
        // But that is what would make more sense. TODO: Let's refactor this in the future.
        const url = new URL(window.location.href);
        url.searchParams.set('part', messagePart);
        history.replaceState(history.state, "", url.toString());
        get_message_content(messagePart, getMessageUidParam() ?? inline_msg_uid, getListPathParam(), getParam('list_parent') ?? getListPathParam());
    });
}