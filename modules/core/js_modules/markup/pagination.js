const paginationMarkup = (totalPages) => {
    const currentPage = getParam('list_page') || 1;
    const markup = `
        <div class="pagination d-flex align-items-center gap-2 mb-2">
            <button class="btn btn-sm rounded-circle prev">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div>
                <span class="current">${currentPage}</span>/<span class="max">${totalPages}</span>
            </div>
            <button class="btn rounded-circle btn-sm next">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    `

    return markup;
}

function handlePagination() {
    $(".pagination .next").on("click", nextPage);
    $(".pagination .prev").on("click", previousPage);
}

function showPagination (totalPages) {
    if ($('.message_list .pagination').length) {
        $('.message_list .pagination').remove();
    }
    if (totalPages > 1) {
        $(paginationMarkup(totalPages)).insertBefore('.message_table');
        $(paginationMarkup(totalPages)).insertAfter('.message_table');
        handlePagination();
        refreshNextButton(getParam('list_page') || 1);
        refreshPreviousButton(getParam('list_page') || 1);
    }
}