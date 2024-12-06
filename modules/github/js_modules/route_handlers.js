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
    - Actually the message list for a particular repo is handled by the imap module, it should be moved to the github module
    - Add background refresh processes for this handler
    - Handle the refresh button click
    */
    if (routeParams.list_path === 'github_all') {
        const dataSources = hm_data_sources().map((source) => source.id);
        dataSources.forEach((id) => {
            const messages = new Hm_MessagesStore('github_' + id);
            messages.load().then(store => {
                for (const row of Object.values(store.rows)) {
                    Hm_Utils.tbody().append(row['0']);
                }
            })
        });
    }
}