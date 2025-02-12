const unMountSubscribers = {};

let previousLocationSearch = window.location.search;

function trackLocationSearchChanges() {
    previousLocationSearch = window.location.search;
}

window.addEventListener('popstate', function(event) {
    if (event.state) {
        $('#cypht-main').replaceWith(event.state.main);
        loadCustomScripts(event.state.head);
    }
    const unMountCallback = renderPage(window.location.href);

    if (unMountCallback) {
        unMountSubscribers[window.location.search] = unMountCallback;
    }

    Hm_Folders.hl_selected_menu();

    unMountSubscribers[previousLocationSearch]?.();

    trackLocationSearchChanges();
});

window.addEventListener('load', function() {
    const unMountCallback = renderPage(window.location.href);
    history.replaceState({ main: $('#cypht-main').prop('outerHTML'), head: $('head').prop('outerHTML') }, "");

    if (unMountCallback) {
        unMountSubscribers[window.location.search] = unMountCallback;
    }
});


$(document).on('click', 'a', function(event) {
    if ($(this).attr('href') !== "#" && $(this).attr('target') !== '_blank' && !$(this).data('external')) {
        event.preventDefault();
        const currentPage = new URL(window.location.href).searchParams.toString();
        if (currentPage !== $(this).attr('href').split('?')[1]) {
            navigate($(this).attr('href'));
        }
    }
});

async function navigate(url) {
    showRoutingToast();

    try {
        const response = await fetch(url, {
            method: 'GET',
        });

        if (!response.ok) {
            throw new Error("Request failed with status: " + response.status);
        }

        const html = await response.text();
        const main = html.match(/<main[^>]*>((.|[\n\r])*)<\/main>/i)[0];
        const title = html.match(/<title[^>]*>((.|[\n\r])*)<\/title>/i)[0];

        let cyphtMain;
        if ($(main).attr('id') === 'cypht-main') {
            $('main#cypht-main').replaceWith(main);
            cyphtMain = main;
        } else {
            $('main#cypht-main').replaceWith($(main).find('#cypht-main'));
            cyphtMain = $(main).find('#cypht-main').prop('outerHTML');
        }
        document.title = title.replace(/<[^>]*>/g, '');
        
        // load custom javascript
        const head = html.match(/<head[^>]*>((.|[\n\r])*)<\/head>/i)[0];
        loadCustomScripts(head);

        window.location.next = url;

        scrollTo(0, 0);

        const unMountCallback = renderPage(url);

        history.pushState({ main: cyphtMain, head }, "", url);
        
        if (unMountCallback) {
            unMountSubscribers[url] = unMountCallback;
        }
        Hm_Folders.hl_selected_menu();

        unMountSubscribers[previousLocationSearch]?.();
        
        trackLocationSearchChanges();
    } catch (error) {
        Hm_Notices.show(error.message, 'danger');
    } finally {
        hideRoutingToast();
    }
}

function loadCustomScripts(head) {
    const newHead = $('<div>').append(head);
    $(document.head).find('script#data-store').replaceWith(newHead.find('script#data-store'));
    $(document.head).find('script#search-data').replaceWith(newHead.find('script#search-data'));
    $(document.head).find('script#inline-msg-state').replaceWith(newHead.find('script#inline-msg-state'));
}

function renderPage(href) {
    window.dispatchEvent(new CustomEvent('page-change'));

    const url = new URL(href, window.location.origin);
    const searchParams = url.searchParams;
    const page = searchParams.get('page');

    if (page) {
        const route = ROUTES.find(route => route.page === page);
        const routeParams = Object.fromEntries(searchParams.entries());
        if (route) {
            const unMountCallback = route.handler(routeParams, url.hash?.substring(1));
            return unMountCallback;
        }
    }
}
