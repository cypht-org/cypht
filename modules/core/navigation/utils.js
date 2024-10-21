function showRoutingToast() {
    window.routingToast = showLoaderToast('Redirecting...');
}

function hideRoutingToast() {
    window.routingToast.hide();
}

function getListPathParam() {
    return new URLSearchParams(window.location.search).get('list_path')
}

function getMessageUidParam() {
    return new URLSearchParams(window.location.search).get('uid')
}

function getPageNameParam() {
    return new URLSearchParams(window.location.search).get('page')
}
