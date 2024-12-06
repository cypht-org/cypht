const unMountSubscribers = {};

let previousLocationSearch = window.location.search;

function trackLocationSearchChanges() {
    previousLocationSearch = window.location.search;
}

window.addEventListener('popstate', function(event) {
    if (event.state) {
        $('main').replaceWith(event.state.main);
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
    history.replaceState({ main: $('main').prop('outerHTML') }, "");

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
        $('main').replaceWith(main);
        document.title = title.replace(/<[^>]*>/g, '');
        
        // load custom javascript
        const head = html.match(/<head[^>]*>((.|[\n\r])*)<\/head>/i)[0];
        $(document.head).find('script').remove();
        $('<div>').append(head).find('script').each(function() {
            if (!this.src) {
                const newScript = document.createElement('script');
                newScript.textContent = this.textContent;
                document.head.appendChild(newScript);
            }
        });

        window.location.next = url;

        const unMountCallback = renderPage(url);

        history.pushState({ main }, "", url);
        
        if (unMountCallback) {
            unMountSubscribers[url] = unMountCallback;
        }
        Hm_Folders.hl_selected_menu();

        unMountSubscribers[previousLocationSearch]?.();
        
        trackLocationSearchChanges();
    } catch (error) {
        Hm_Notices.show([`ERR${error.message}`]);
    } finally {
        hideRoutingToast();
    }
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
