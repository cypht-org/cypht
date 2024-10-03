ROUTES = [
    {
        page: 'message_list',
        handler: applyImapMessageListPageHandlers
    },
    {
        page: 'message',
        handler: applyImapMessageContentPageHandlers
    },
    {
        page: 'compose',
        handler: applySmtpPageHandlers
    }
]