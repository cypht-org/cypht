const unMountSubscribers = {};

let previousLocationSearch = window.location.search;

function trackLocationSearchChanges() {
    previousLocationSearch = window.location.search;
}

window.addEventListener('popstate', function(event) {
    Hm_Ajax.abort_all_requests();

    if (event.state) {
        $('#cypht-main').replaceWith(event.state.main);
        loadCustomScripts(event.state.scripts);
    }

    window.location.next = window.location.search;
    
    const unMountCallback = renderPage(window.location.href);

    if (unMountCallback) {
        unMountSubscribers[window.location.search] = unMountCallback;
    }

    Hm_Folders.hl_selected_menu();

    unMountSubscribers[previousLocationSearch]?.();

    trackLocationSearchChanges();
});

window.addEventListener('load', function() {
    if (!hm_is_logged()) {
        return;
    }
    
    const unMountCallback = renderPage(window.location.href);
    history.replaceState({ main: $('#cypht-main').prop('outerHTML'), scripts: extractCustomScripts($(document)) }, "");

    if (unMountCallback) {
        unMountSubscribers[window.location.search] = unMountCallback;
    }
});


$(document).on('click', '.cypht-layout a', function(event) {
    const href = $(this).attr('href');
    const target = $(this).attr('target');
    const isExternal = $(this).data('external');
    
    const isExternalDomain = href && (
        href.startsWith('http://') || 
        href.startsWith('https://') || 
        href.startsWith('//')
    ) && !href.includes(window.location.hostname);
    
    if (href !== "#" && target !== '_blank' && !isExternal && !isExternalDomain) {
        event.preventDefault();
        const currentUrl = new URL(window.location.href);
        const currentPage = currentUrl.searchParams.toString();
        const targetParams = new URLSearchParams(href.split('?')[1]);
        if (currentPage !== targetParams.toString()) {
            Hm_Ajax.abort_all_requests();
            navigate(autoAppendParamsForNavigation(href));
        }
    }
});

function autoAppendParamsForNavigation(href)
{
    const currentUrl = new URL(window.location.href);
    const currentPage = currentUrl.searchParams.toString();
    const target = new URLSearchParams(href.split('?')[1]);
    if (currentPage !== target.toString()) {
        if ((target.get('page') == 'message' && target.get('list_parent') == 'search') || target.get('page') == 'search') {
            if ($('.search_form form').length > 0) {
                for (let field of $('.search_form form').serializeArray()) {
                    if (field.name != 'page') {
                        target.set(field.name, field.value);
                    }
                }
            } else {
                for (let field of ['list_page', 'search_terms', 'search_fld', 'search_since', 'sort']) {
                    const val = currentUrl.searchParams.get(field);
                    if (val !== null && !target.has(field)) {
                        target.set(field, val);
                    }
                }
            }
            return href.split('?')[0] + '?' + target.toString();
        }
    }
    return href;
}

async function navigate(url, loaderMessage) {
    showRoutingToast(loaderMessage);
    Hm_Ajax.abort_all_requests();

    try {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error("Request failed with status: " + response.status);
        }

        if (response.redirected && response.url) {
            window.location.href = response.url;
            return;
        }

        const html = await response.text();
        if (!html || typeof html !== 'string') {
            throw new Error("Failed navigating to page, empty or invalid response body.");
        }

        // load custom javascript
        const scripts = extractCustomScripts($(html));
        loadCustomScripts(scripts);

        if (!hm_is_logged()) {
            logout();
            return;
        }

        const titleMatch = html.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
        if (!titleMatch) {
            throw new Error("No <title> element found in response HTML");
        }
        const title = titleMatch[1];
        const mainMatch = html.match(/<main\b[^>]*>[\s\S]*?<\/main>/im);
        if (!mainMatch) {
            throw new Error("No <main> element found in response HTML");
        }
        const main = mainMatch[0];

        let $mainEl = $(main);
        let cyphtMain;
        if ($mainEl.attr('id') === 'cypht-main') {
            $('main#cypht-main').replaceWith($mainEl);
            cyphtMain = $mainEl.prop('outerHTML');
        } else {
            const innerMain = $mainEl.find('#cypht-main');
            if (innerMain.length === 0) {
                throw new Error("Element with id='cypht-main' not found in new main.");
            }
            $('main#cypht-main').replaceWith(innerMain);
            cyphtMain = innerMain.prop('outerHTML');
        }
        document.title = title.replace(/<[^>]*>/g, '');

        window.location.next = url;

        scrollTo(0, 0);

        const unMountCallback = renderPage(url);

        history.pushState({ main: cyphtMain, scripts }, "", url);
        
        if (unMountCallback) {
            unMountSubscribers[url] = unMountCallback;
        }
        Hm_Folders.hl_selected_menu();

        unMountSubscribers[previousLocationSearch]?.();
        
        trackLocationSearchChanges();
    } catch (error) {
        Hm_Notices.show(error.message, 'danger');
        console.log(error);
    } finally {
        hideRoutingToast();
    }
}

function extractCustomScripts($el) {
    const scripts = [];
    let candidates = [];
    if ($el.length == 1) {
        for (const el of $el.find('script')) {
            candidates.push(el);
        }
    } else {
        for (const el of $el) {
            if ($(el).is('script')) {
                candidates.push(el);
            } else {
                for (const s of $(el).find('script')) {
                    candidates.push(s);
                }
            }
        }
    }
    for (const script of candidates) {
        if (['data-store', 'search-data', 'inline-msg-state'].indexOf($(script).attr('id')) >= 0) {
            scripts.push($(script).prop('outerHTML'));
        }
    }
    return scripts;
}

function loadCustomScripts(scripts) {
    for (const script of scripts) {
        const id = $(script).attr('id');
        $('script#' + id).replaceWith(script);
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
