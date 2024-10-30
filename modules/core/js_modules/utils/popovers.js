function sessionAvailableOnlyActionInfo(element) {
    return new bootstrap.Popover(element, {
        title: 'Session-limited action', 
        content: 'Note that the action will persist only during the current session, unless the settings are saved.',
        trigger: 'hover',
    });
}