function showLoaderToast(text = 'Loading...') {
    const uniqueId = Math.random().toString(36).substring(7);
    const toastHTML = `
    <div id="loading_indicator" class="position-fixed bottom-0 start-0 p-3" style="z-index: 9999">
        <div class="toast bg-primary text-white" id="${uniqueId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
        <div class="toast-body">
            <div class="d-flex align-items-center">
                <strong>${text}</strong>
                <div class="spinner-border ms-auto" role="status" aria-hidden="true"></div>
            </div>
        </div>
        </div>
    </div>
    `

    if (document.getElementById('loading_indicator')) {
        document.getElementById('loading_indicator').remove();
    }

    document.body.insertAdjacentHTML('beforeend', toastHTML)

    const instance = bootstrap.Toast.getOrCreateInstance(document.getElementById(uniqueId));
    instance.show();

    return instance;
}