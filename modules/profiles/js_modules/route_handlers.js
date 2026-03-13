function applyProfilesPageHandler() {
    $('.add_profile').on("click", function() { $('.edit_profile').show(); });

    hm_init_sig_editor('textarea.html_sig_editor', 'profileSigEditor');
    $('form').on('submit', function() {
        if (window.profileSigEditor) {
            window.profileSigEditor.sync();
        }
    });
}