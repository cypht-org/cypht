/*
NOTE: Handlers are registered as strings instead of functions because some modules might not be enabled, making their pages' handler functions unaccessible.
*/
const modulesRoutes = [
    {
        page: 'message_list',
        handler: 'applyImapMessageListPageHandlers'
    },
    {
        page: 'message',
        handler: 'applyImapMessageContentPageHandlers'
    },
    {
        page: 'compose',
        handler: 'applyComposePageHandlers'
    },
    {
        page: 'servers',
        handler: 'applyServersPageHandlers'
    },
    {
        page: 'settings',
        handler: 'applySettingsPageHandlers'
    },
    {
        page: 'search',
        handler: 'applySearchPageHandlers'
    },
    {
        page: 'home',
        handler: 'applyHomePageHandlers'
    },
    {
        page: 'info',
        handler: 'applyInfoPageHandlers'
    },
    {
        page: 'calendar',
        handler: 'applyCalendarPageHandlers'
    },
    {
        page: 'advanced_search',
        handler: 'applyAdvancedSearchPageHandlers'
    },
    {
        page: 'contacts',
        handler: 'applyContactsPageHandlers'
    },
    {
        page: 'history',
        handler: 'applyHistoryPageHandlers'
    },
    {
        page: 'folders',
        handler: 'applyFoldersPageHandlers'
    },
    {
        page: 'folders_subscription',
        handler: 'applyFoldersSubscriptionPageHandlers'
    }
]

/* 
Now let's validate and use handlers that are given.
*/
ROUTES = modulesRoutes.filter(route => typeof(window[route.handler]) === 'function').map(route => ({
    ...route,
    handler: window[route.handler]
}))
