function showRoutingToast(message = 'Redirecting...') {
    if (window.routingToast) hideRoutingToast();
    window.routingToast = showLoaderToast(message);
}

function hideRoutingToast() {
    window.routingToast?.hide();
    window.routingToast = null;
}

// Undefined is used as the default value instead of null to comply with the route handlers, which also use undefined as the default value.
function getListPathParam() {
    return new URLSearchParams(window.location.next || window.location.search).get('list_path') ?? undefined;
}

function getMessageUidParam() {
    return new URLSearchParams(window.location.next || window.location.search).get('uid') ?? undefined;
}

function getPageNameParam() {
    return new URLSearchParams(window.location.next || window.location.search).get('page') ?? undefined;
}

function getParam(param) {
    return new URLSearchParams(window.location.next || window.location.search).get(param) ?? undefined;
}
