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

    const store = new Hm_MessagesStore(getListPathParam(), currentPage);
    store.load(false, false, true);

    await changePage(nextPage, this, store.offsets);
}

async function previousPage() {
    const currentPage = $(".pagination .current").text();

    const previousPage = parseInt(currentPage) - 1;

    let offsets = '';
    if (previousPage > 1) {
        const store = new Hm_MessagesStore(getListPathParam(), previousPage - 1);
        store.load(false, false, true);
        offsets = store.offsets;
    }

    await changePage(previousPage, this, offsets);

    if (previousPage > 1) {
        $(this).prop('disabled', false);
    }
}

async function changePage(toPage, button, offsets) {
    $(button).prop('disabled', true);
    $(button).addClass('active');

    const url = new URL(window.location.href);
    url.searchParams.set('list_page', toPage);

    if (offsets) {
        url.searchParams.set('offsets', offsets);
    } else {
        url.searchParams.delete('offsets');
    }

    history.pushState(history.state, "", url.toString());
    window.location.next = url.search;

    const messagesStore = new Hm_MessagesStore(getListPathParam(), toPage);
    try {
        await messagesStore.load();
        Hm_Utils.tbody().attr('id', messagesStore.list);
        display_imap_mailbox(messagesStore.rows, null, messagesStore.list);
        $(".pagination .current").text(toPage);
    } catch (error) {
        Hm_Notices.show("Failed to fetch content", "danger");
    } finally {
        $(button).removeClass('active');
        refreshNextButton(toPage);
        refreshPreviousButton(toPage);
    }
}
