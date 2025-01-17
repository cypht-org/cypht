function performSearch(routeParams) {
    if (routeParams.search_terms) {
        const serverIds = hm_data_sources().map((source) => source.id);
        const ids = [...new Set(serverIds)];
        ids.forEach((id) => {
            Hm_Ajax.request([
                {name: 'hm_ajax_hook', value: 'ajax_imap_search'},
                {name: 'imap_server_ids', value: id},
            ],
                function (response) {                
                    if (response.formatted_message_list) {
                        Object.values(response.formatted_message_list).forEach((message) => {
                            Hm_Utils.tbody().append(message['0']);
                        });
                        // sort by arrival date
                        Hm_Message_List.sort(4);
                    }
                    Hm_Message_List.check_empty_list();
                }
            )
        });
    }
}