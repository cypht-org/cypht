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
    // TODO: FIX THIS. It prevents event handlers from being called for links with href="#"
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

        history.pushState({ main }, "", url);

        renderPage(url);
    } catch (error) {
        throw new Error(error);
    }

    hideRoutingToast();
}

function renderPage(href) {
    const searchParams = new URL(href, window.location.origin).searchParams;
    const page = searchParams.get('page');
    
    switch (page) {
        case 'message':
            imap_setup_message_view_page(searchParams.get('uid'));
            break;

        case 'message_list':
            select_imap_folder(searchParams.get('list_path'));
            Hm_Message_List.set_row_events();
            break;

        default:
            break;
    }
}