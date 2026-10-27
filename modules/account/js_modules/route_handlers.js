function applyAccountsPageHandlers() {
    var pendingForm = null;
    var modalEl = document.getElementById('confirmDeleteAccountModal');

    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    $('.delete_user_form').on('submit', function(e) {
        e.preventDefault();
        pendingForm = this;
        var username = $(this).find('[name="delete_username"]').val();
        $('#confirmDeleteAccountMessage').text(
            hm_trans('Are you sure you want to delete the account "%s"? This cannot be undone.').replace('%s', username)
        );
        modal.show();
    });

    $('#confirmDeleteAccountBtn').on('click', function() {
        if (!pendingForm) {
            return;
        }
        var form = pendingForm;
        pendingForm = null;
        modal.hide();
        form.submit();
    });

    $(modalEl).on('hidden.bs.modal', function() {
        if (pendingForm) {
            pendingForm = null;
        }
    });

    return function() {
        pendingForm = null;
    };
}
