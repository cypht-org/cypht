function githubServersPageHandler() {
    var dsp = Hm_Utils.get_from_local_storage('.github_connect_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.github_connect_section').css('display', dsp);
    }
    $('.github_disconnect').on("click", function(e) {
        if (!hm_delete_prompt()) {
            e.preventDefault();
            return false;
        }
        return true;
    });
    $('.github_remove_repo').on("click", function(e) {
        if (!hm_delete_prompt()) {
            e.preventDefault();
            return false;
        }
        return true;
    });
}

function applyGithubMessageContentPageHandlers(routeParams) {
    github_item_view(routeParams.list_path, routeParams.uid);
}

function applyGithubMessageListPageHandler(routeParams) {
    if (routeParams.list_path === 'github_all') {
        // Single Hm_MessagesStore for the whole view.  getRequestConfigs() falls
        // through to the hm_data_sources() loop (same as combined_inbox) and fires
        // one ajax_github_data request per repo in parallel, merging client-side.
        const messages = new Hm_MessagesStore(
            'github_all',
            Hm_Utils.get_url_page_number(),
            `${getParam('keyword')}_${getParam('filter')}`,
            getParam('sort')
        );

        // page-change fires as soon as navigation begins; set the flag so any
        // callbacks that resolve after navigation don't write to the new page's DOM.
        let navigatedAway = false;
        const onPageChange = () => { navigatedAway = true; };
        window.addEventListener('page-change', onPageChange, { once: true });

        messages.load().then(store => {
            if (navigatedAway) return;
            for (const row of store.rows) {
                Hm_Utils.tbody().append(row['0']);
            }
        });

        const refreshIntervalId = setInterval(() => {
            if (!navigatedAway) refreshGithubAll(messages, true, () => navigatedAway);
        }, 30000);

        $('.refresh_link').on("click", function(e) {
            e.preventDefault();
            refreshGithubAll(messages, false, () => navigatedAway);
        });

        return () => {
            window.removeEventListener('page-change', onPageChange);
            messages.abort();
            clearInterval(refreshIntervalId);
        };
    }
}

function refreshGithubAll(messages, background = false, isAborted = () => false) {
    messages.forceGithubRefresh = !background;
    messages.load(true, background).then(store => {
        if (isAborted()) return;
        for (let row of store.rows) {
            row = row['0'];
            if (!row) continue;
            const rowUid = $(row).data('uid');
            const tableRow = Hm_Utils.tbody().find(`tr[data-uid="${rowUid}"]`);
            if (!tableRow.length) {
                Hm_Utils.tbody().append(row);
            } else if (tableRow.attr('class') !== $(row).attr('class')) {
                tableRow.replaceWith(row);
            }
        }
    }).finally(() => {
        messages.forceGithubRefresh = false;
    });
}