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
    if ($(this).attr('href') !== "#" && $(this).attr('target') !== '_blank') {
        event.preventDefault();
        navigate($(this).attr('href'));
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
        $('main').replaceWith(main);

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
        throw error;
    } finally {
        hideRoutingToast();
    }
}

function renderPage(href) {
    const searchParams = new URL(href, window.location.origin).searchParams;
    const page = searchParams.get('page');
    
    if (page) {
        const route = ROUTES.find(route => route.page === page);
        const routeParams = Object.fromEntries(searchParams.entries());
        if (route) {
            const unMountCallback = route.handler(routeParams);
            return unMountCallback;
        }
    }
}
