window.addEventListener('popstate', function(event) {
    if (event.state) {
        $('main').html(event.state.main);
    }
    renderPage(window.location.href);
    
});

history.replaceState({ main: $('main').html() }, "");

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

        renderPage(url);

        history.pushState({ main }, "", url);
    } catch (error) {
        throw new Error(error);
    }

    hideRoutingToast();
}

function renderPage(href) {
    const searchParams = new URL(href, window.location.origin).searchParams;
    const page = searchParams.get('page');
    
    if (page) {
        const route = ROUTES.find(route => route.page === page);
        const routeParams = Object.fromEntries(searchParams.entries());
        if (route) {
            route.handler(routeParams);
        }
    }
}
