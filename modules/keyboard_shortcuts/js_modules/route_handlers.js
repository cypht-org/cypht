function applyShortcutsPageHandlers() {
    $('.reset_shortcut').on("click", function() {
        Hm_Utils.redirect('?page=shortcuts');
    });
}
