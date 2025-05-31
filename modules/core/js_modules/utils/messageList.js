function triggerNewMessageEvent(uid, row) {
    const newRowEvent = new CustomEvent('new-message', {
        detail: {
            uid: uid,
            row: row,
        }
    });
    window.dispatchEvent(newRowEvent);
}
