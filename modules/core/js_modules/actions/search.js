function performSearch(routeParams) {
    if (routeParams.search_terms) {
        hm_data_sources().forEach((source) => {
            const config = [];
            if (source.type == 'feeds') {
                config.push({name: 'hm_ajax_hook', value: 'ajax_feed_combined'});
                config.push({name: 'feed_server_ids', value: source.id});
            } else {
                config.push({name: 'hm_ajax_hook', value: 'ajax_imap_search'});
                config.push({name: 'imap_server_ids', value: source.id});
            }
            Hm_Ajax.request(config,
                function (response) {                
                    if (response.formatted_message_list) {
                        Object.values(response.formatted_message_list).forEach((message) => {
                            Hm_Utils.tbody().append(message['0']);
                        });
                        Hm_Message_List.sort(getParam('sort') || 'arrival');
                    }
                    Hm_Message_List.check_empty_list();
                }
            )
        });
    }
}