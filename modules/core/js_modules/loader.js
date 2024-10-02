const toastHTML = `
<div class="position-fixed bottom-0 start-0 p-3" style="z-index: 11">
    <div class="toast bg-primary text-white" id="routing-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
    <div class="toast-body">
        <div class="d-flex align-items-center">
            <strong>Redirecting...</strong>
            <div class="spinner-border ms-auto" role="status" aria-hidden="true"></div>
        </div>
    </div>
    </div>
</div>
`
document.body.insertAdjacentHTML('beforeend', toastHTML)

function showRoutingToast() {
    bootstrap.Toast.getOrCreateInstance(document.getElementById('routing-toast')).show()
}

function hideRoutingToast() {
    bootstrap.Toast.getOrCreateInstance(document.getElementById('routing-toast')).hide()
}