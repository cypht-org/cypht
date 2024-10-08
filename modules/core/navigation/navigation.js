const unMountSubscribers = [];

window.addEventListener('popstate', function(event) {
    if (event.state) {
        $('main').replaceWith(event.state.main);
    }
    const unMountCallback = renderPage(window.location.href);

    if (unMountCallback) {
        unMountSubscribers.push({ url: window.location.search, callback: unMountCallback });
    }
    
});

window.addEventListener('load', function() {
    renderPage(window.location.href);
    history.replaceState({ main: $('main').prop('outerHTML') }, "");
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
        const previousUrl = window.location.search;

        const unMountCallback = renderPage(url);

        history.pushState({ main }, "", url);
        
        if (unMountCallback) {
            unMountSubscribers.push({ url, callback: unMountCallback });
        }
        Hm_Folders.hl_selected_menu();

        unMountSubscribers.find(subscriber => subscriber.url === previousUrl)?.callback();
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
