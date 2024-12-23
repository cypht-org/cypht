function refreshNextButton(current) {
    const totalPages = $(".pagination .total").text();
    if (current >= totalPages) {
        $(".pagination .next").prop('disabled', true);
    } else {
        $(".pagination .next").prop('disabled', false);
    }
}

function refreshPreviousButton(current) {
    if (current <= 1) {
        $(".pagination .prev").prop('disabled', true);
    } else {
        $(".pagination .prev").prop('disabled', false);
    }
}

async function nextPage() {
    const currentPage = $(".pagination .current").text();

    const nextPage = parseInt(currentPage) + 1;

    await changePage(nextPage, this);
}

async function previousPage() {
    const currentPage = $(".pagination .current").text();

    const previousPage = parseInt(currentPage) - 1;

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

    const messagesStore = new Hm_MessagesStore(getListPathParam(), toPage);
    try {
        await messagesStore.load();
        Hm_Utils.tbody().attr('id', messagesStore.list);
        display_imap_mailbox(messagesStore.rows, null, messagesStore.list);
        $(".pagination .current").text(toPage);
    } catch (error) {
        Hm_Utils.add_sys_message("Failed to fetch content", "danger");
    } finally {
        $(button).removeClass('active');
        refreshNextButton(toPage);
        refreshPreviousButton(toPage);
    }
}
