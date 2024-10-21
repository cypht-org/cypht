function handleViewMessagePart() {
    $('.msg_part_link').on("click", function(e) {
        e.preventDefault();
        const messagePart = $(this).data('messagePart');
        get_message_content(messagePart, getMessageUidParam() ?? inline_msg_uid, getListPathParam());
    });
}