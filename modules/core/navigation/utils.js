function showRoutingToast(message = 'Loading in progress...') {
    if (window.routingToast) hideRoutingToast();
    window.routingToast = showLoaderToast(message);
}

function hideRoutingToast() {
    window.routingToast?.hide();
    window.routingToast = null;
}

// Undefined is used as the default value instead of null to comply with the route handlers, which also use undefined as the default value.
function getListPathParam() {
    return getParam('list_path');
}

function getMessageUidParam() {
    return getParam('uid');
}

function getPageNameParam() {
    return getParam('page');
}

function getParam(param) {
    let urlOrFragment = window.location.next || window.location.search;
    let sp = null;
    if (urlOrFragment.match(/^https?:\/\//)) {
        let url = new URL(urlOrFragment);
        sp = url.searchParams;
    } else {
        sp = new URLSearchParams(urlOrFragment);
    }
    return sp.get(param) ?? undefined;
}
