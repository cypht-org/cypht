function showRoutingToast() {
    if (window.routingToast) hideRoutingToast();
    window.routingToast = showLoaderToast('Redirecting...');
}

function hideRoutingToast() {
    window.routingToast?.hide();
    window.routingToast = null;
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

function getParam(param) {
    return new URLSearchParams(window.location.search).get(param)
}
