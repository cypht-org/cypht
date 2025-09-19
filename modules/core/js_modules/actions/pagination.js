function refreshNextButton(current) {
    const totalPages = $(".pagination .max").text();
    if (parseInt(current) >= parseInt(totalPages)) {
        $(".pagination .next").prop('disabled', true);
    } else {
        $(".pagination .next").prop('disabled', false);
    }
}

function refreshPreviousButton(current) {
    if (parseInt(current) <= 1) {
        $(".pagination .prev").prop('disabled', true);
    } else {
        $(".pagination .prev").prop('disabled', false);
    }
}

async function nextPage() {
    const currentPage = $(".pagination .current").text();

    const nextPage = parseInt(currentPage) + 1;

    const store = new Hm_MessagesStore(getListPathParam(), nextPage, `${getParam('keyword')}_${getParam('filter')}`, getParam('sort'));
    store.load(false, false, true);

    await changePage(nextPage, this);
}

async function previousPage() {
    const currentPage = $(".pagination .current").text();

    const previousPage = parseInt(currentPage) - 1;

    if (previousPage > 1) {
        const store = new Hm_MessagesStore(getListPathParam(), previousPage, `${getParam('keyword')}_${getParam('filter')}`, getParam('sort'));
        store.load(false, false, true);
    }

    await changePage(previousPage, this);

    if (previousPage > 1) {
        $(this).prop('disabled', false);
    }
}

async function changePage(toPage, button) {
    $(button).prop('disabled', true);
    $(button).addClass('active');

    const url = new URL(window.location.href);
    url.searchParams.set('list_page', toPage);

    history.pushState(history.state, "", url.toString());
    window.location.next = url.search;

    const messagesStore = new Hm_MessagesStore(getListPathParam(), toPage, `${getParam('keyword')}_${getParam('filter')}`, getParam('sort'));
    try {
        Hm_Utils.tbody().attr('id', messagesStore.list);
        await messagesStore.load(false, false, false, store => {
            display_imap_mailbox(store.rows, store.list, store);
        });
        $(".pagination .current").text(toPage);
    } catch (error) {
        Hm_Notices.show("Failed to fetch content", "danger");
    } finally {
        $(button).removeClass('active');
        refreshNextButton(toPage);
        refreshPreviousButton(toPage);
    }
}
