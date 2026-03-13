function applyProfilesPageHandler() {
    $('.add_profile').on("click", function() { $('.edit_profile').show(); });

    hm_init_sig_editor('textarea.html_sig_editor', 'profileSigEditor');
    // Capture now: KindEditor.ready fires synchronously post-load, so the editor
    // is already set before the previous page's unmount callback runs.
    var capturedEditor = window.profileSigEditor;

    $('form').on('submit', function() {
        if (window.profileSigEditor) {
            window.profileSigEditor.sync();
        }
    });

    return function() {
        // Only destroy the editor this handler created; avoids clobbering an
        // editor created by the next page's handler (e.g. list → edit nav).
        if (capturedEditor && window.profileSigEditor === capturedEditor) {
            try { capturedEditor.remove(); } catch(e) {}
            window.profileSigEditor = null;
        }
    };
}