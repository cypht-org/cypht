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
    /*
    TODO:
    - Actually the message list for a particular repo is handled by the imap module, it should be moved to the github module.
    */   
    if (routeParams.list_path === 'github_all') {
        const dataSources = hm_data_sources().map((source) => source.id);
        dataSources.forEach((id) => {
            const messages = new Hm_MessagesStore('github_' + id, Hm_Utils.get_url_page_number(), getParam('keyword'));
            messages.load().then(store => {
                for (const row of Object.values(store.rows)) {
                    Hm_Utils.tbody().append(row['0']);
                }
            })
        });

        const abortController = new AbortController();
        const refreshIntervalId = setInterval(() => {
            refreshAll(dataSources, true, abortController);
        }, 30000);

        $('.refresh_link').on("click", function(e) {
            e.preventDefault();
            refreshAll(dataSources, false, abortController);
        });

        return () => {
            clearInterval(refreshIntervalId);
            abortController.abort();
        }
    }
}

function refreshAll(dataSources, background = false, abortController) {
    dataSources.forEach((id) => {
        const messages = new Hm_MessagesStore('github_' + id, Hm_Utils.get_url_page_number(), getParam('keyword'), {}, abortController);
        messages.load(true, background).then(store => {
            const rows = Object.values(store.rows);
            for (const index in rows) {
                const row = rows[index]?.[0];
                if (!row) {
                    continue;
                }
                
                const rowUid = $(row).data('uid');
                const tableRow = Hm_Utils.tbody().find(`tr[data-uid="${rowUid}"]`);
                if (!tableRow.length) {
                    if (Hm_Utils.rows().length >= index) {
                        Hm_Utils.rows().eq(index).after(row);
                    } else {
                        Hm_Utils.tbody().append(row);
                    }
                } else if (tableRow.attr('class') !== $(row).attr('class')) {
                    tableRow.replaceWith(row);
                }                
            }
        });
    });
}