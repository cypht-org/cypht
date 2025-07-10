/**
 * An abstraction object of the Message_List focused on state management without UI interaction.
 */
class Hm_MessagesStore {

    /**
     * @typedef {Object} RowObject
     * @property {String} 0 - The HTML string of the row
     * @property {String} 1 - The IMAP key
     */

    /** 
     * @typedef {Array} RowEntry
     * @property {String} 0 - The IMAP key
     * @property {RowObject} 1 - An object containing the row message and the IMAP key
     */

    constructor(path, page = 1, filter = '', sortFld = 'arrival', rows = [], abortController = new AbortController()) {
        this.path = path;
        this.list = path + '_' + (filter ? filter.replace(/\s+/g, '_') + '_' + sortFld + '_': '') + page;
        this.sortFld = sortFld;
        this.rows = rows;
        this.sources = {};
        this.count = 0;
        this.flagAsReadOnOpen = true;
        this.abortController = abortController;
        this.pages = 0;
        this.page = page;
        this.newMessages = [];
    }

    /**
     * Check if the store has data for the current instance
     * @returns {Boolean}
     */
    hasLocalData() {
        return this.retrieveFromLocalStorage() !== false;
    }

    /**
     * Loads message list from store or reload/initially fetch from configuration.
     * The target ajax request(s) are based on the configuration coming from the URL path params.
     * This method is designed to work with single-source paths like imap folders, github or feed pages
     * as well as combined source paths like All email, unread, sent, trash, etc.
     * When it works on multiple data-sources, you can pass messagesReadyCB to refresh the UI element, so
     * user doesn't have to wait for all sources to be loaded to see something on screen.
     * 
     * @returns {Promise<this>}
     */
    async load(reload = false, hideLoadingState = false, doNotFetch = false, messagesReadyCB = null) {
        const storedMessages = this.retrieveFromLocalStorage();
        if (storedMessages) {
            this.rows = storedMessages.rows;
            this.sources = storedMessages.sources || {};
            this.pages = parseInt(storedMessages.pages);
            this.count = storedMessages.count;
            this.flagAsReadOnOpen = storedMessages.flagAsReadOnOpen;
            if (!reload) {
                this.sort();
                if (messagesReadyCB) {
                    messagesReadyCB(this);
                }
                return this;
            }
        }

        if (doNotFetch) {
            return this;
        }

        this.fetch(hideLoadingState).forEach(async (req) => {
            const { formatted_message_list: updatedMessages, pages, folder_status, do_not_flag_as_read_on_open, sourceId } = await req;
            // count and pages only available in non-combined pages where there is only one ajax call, so it is safe to overwrite
            this.count = folder_status && Object.values(folder_status)[0]?.messages;
            this.pages = parseInt(pages);
            this.newMessages = this.getNewMessages(updatedMessages);

            if (typeof do_not_flag_as_read_on_open == 'booelan') {
                this.flagAsReadOnOpen = !do_not_flag_as_read_on_open;
            }

            if (this.sources[sourceId]) {
                this.rows = this.rows.filter(row => !this.sources[sourceId].includes(row['1']));
            }
            this.sources[sourceId] = Object.keys(updatedMessages);
            for (const id in updatedMessages) {
                if (this.rows.indexOf(updatedMessages[id]) === -1) {
                    this.rows.push(updatedMessages[id]);
                }
            }

            this.sort();
            this.saveToLocalStorage();

            if (messagesReadyCB) {
                messagesReadyCB(this);
            }
        }, this);

        return this;
    }

    sort() {
        let sortFld = this.sortFld;
        this.rows = this.rows.sort((a, b) => {
            let aval, bval;
            const sortField = sortFld.replace('-', '');
            if (['arrival', 'date'].includes(sortField)) {
                aval = new Date($(`input.${sortField}`, $('td.dates', $(a[0]))).val());
                bval = new Date($(`input.${sortField}`, $('td.dates', $(b[0]))).val());
                if (sortFld.startsWith('-')) {
                    return aval - bval;
                }
                return bval - aval;
            }
            aval = $(`td.${sortField}`, $(a[0])).text().replace(/^\s+/g, '');
            bval = $(`td.${sortField}`, $(b[0])).text().replace(/^\s+/g, '');
            if (sortFld.startsWith('-')) {
                return bval.toUpperCase().localeCompare(aval.toUpperCase());
            }
            return aval.toUpperCase().localeCompare(bval.toUpperCase());
        });
    }

    /**
     * 
     * @param {String} uid the id of the message to be marked as read
     * @returns {Boolean} true if the message was marked as read, false otherwise
     */
    markRowAsRead(uid) {
        const row = this.getRowByUid(uid);
        
        if (row) {
            const htmlRow = $(row['0']);
            const wasUnseen = htmlRow.find('.unseen').length > 0 || htmlRow.hasClass('unseen');

            htmlRow.removeClass('unseen');
            htmlRow.find('.unseen').removeClass('unseen');

            row['0'] = htmlRow[0].outerHTML;
            
            this.saveToLocalStorage();

            return wasUnseen;
        }
        return false;
    }

    /**
     * 
     * @param {*} uid 
     * @returns {RowObject|false} the next row entry if found, false otherwise
     */
    getNextRowForMessage(uid) {
        const row = this.getRowByUid(uid);
        
        if (row) {
            const index = this.rows.indexOf(row);
            const nextRow = this.rows[index + 1];
            if (nextRow) {
                return nextRow;
            }
        }
        return false;
    }

    /**
     * 
     * @param {*} uid 
     * @returns {RowObject|false} the previous row entry if found, false otherwise
     */
    getPreviousRowForMessage(uid) {
        const row = this.getRowByUid(uid);
        if (row) {
            const index = this.rows.indexOf(row);
            const previousRow = this.rows[index - 1];
            if (previousRow) {
                return previousRow;
            }
        }
        return false;
    }
    
    removeRow(uid) {
        const row = this.getRowByUid(uid);
        if (row) {
            this.rows = this.rows.filter(r => r !== row);
            this.saveToLocalStorage();
        }
        
    }
    
    updateRow(uid, html) {
        const row = this.getRowByUid(uid);
        if (row) {
            row['0'] = html;
            this.saveToLocalStorage();
        }
    }

    getNewMessages(fetchedRows) {
        const actualRows = this.rows;
        const fetchedRowsValues = Object.values(fetchedRows);

        const newMessages = [];

        fetchedRowsValues.forEach(fetchedRow => {
            const isNew = !actualRows.some(actualRow => {
                return $(actualRow['0']).data('uid') === $(fetchedRow['0']).data('uid');
            });
            if (isNew) {
                const row = $(fetchedRow['0']);
                if (row.hasClass('unseen')) {
                    newMessages.push(fetchedRow['0']);
                }
            }
        });

        return newMessages;
    }

    fetch(hideLoadingState = false) {
        let store = this;
        return this.getRequestConfigs().map((config) => {
            return new Promise((resolve, reject) => {
                Hm_Ajax.request(
                    config,
                    (response) => {
                        if (response) {
                            response.sourceId = store.hashObject(config);
                            resolve(response);
                        }
                    },
                    [],
                    hideLoadingState,
                    undefined,
                    reject,
                    this.abortController?.signal
                );
            });
        });
    }

    getRequestConfigs() {
        const config = [{ name: "list_page", value: this.page }, { name: "sort", value: this.sortFld }];
        const configs = [];
        if (this.path.startsWith('imap')) {
            const detail = Hm_Utils.parse_folder_path(this.path, 'imap');
            config.push({ name: "hm_ajax_hook", value: 'ajax_imap_folder_display' });
            config.push({ name: "imap_server_id", value: detail.server_id });
            config.push({ name: "folder", value: detail.folder });
            configs.push(config);
        } else if (this.path.startsWith('feeds')) {
            const serverId = this.path.split('_')[1];
            if (serverId) {
                config.push({ name: "feed_server_ids", value: serverId });
            }
            config.push({ name: "hm_ajax_hook", value: 'ajax_feed_combined' });
            configs.push(config);
        } else if (this.path.startsWith('github')) {
            config.push({ name: "hm_ajax_hook", value: 'ajax_github_data' });
            config.push({ name: "github_repo", value: this.path.split('_')[1] });
            configs.push(config);
        } else {
            if (this.path == 'tag') {
                config.push({ name: "hm_ajax_hook", value: 'ajax_imap_tag_data' });
                config.push({ name: "folder", value: getParam('tag_id') });
                configs.push(config);
            } else {
                let sources = hm_data_sources();
                if (this.path != 'combined_inbox' && this.path != 'search') {
                    sources = sources.filter(s => s.type != 'feeds');
                }
                sources.forEach((ds) => {
                    const cfg = config.slice();
                    if (ds.type == 'feeds') {
                        cfg.push({ name: "hm_ajax_hook", value: 'ajax_feed_combined' });
                        cfg.push({ name: "feed_server_ids", value: ds.id });
                    } else {
                        cfg.push({ name: "hm_ajax_hook", value: this.path == 'search' ? 'ajax_imap_search' : 'ajax_imap_message_list' });
                        cfg.push({ name: "imap_server_ids", value: ds.id });
                        cfg.push({ name: "imap_folder_ids", value: ds.folder });
                    }
                    configs.push(cfg);
                });
            }
        }
        return configs;
    }

    saveToLocalStorage() {
        Hm_Utils.save_to_local_storage(this.list, JSON.stringify({ rows: this.rows, sources: this.sources, pages: this.pages, count: this.count }));
        Hm_Utils.save_to_local_storage('flagAsReadOnOpen', this.flagAsReadOnOpen);
    }

    retrieveFromLocalStorage() {
        const stored = Hm_Utils.get_from_local_storage(this.list);
        const flagAsReadOnOpen = Hm_Utils.get_from_local_storage('flagAsReadOnOpen');
        if (stored) {
            return {...JSON.parse(stored), flagAsReadOnOpen: flagAsReadOnOpen !== 'false'};
        }
        return false;
    }

    removeFromLocalStorage() {
        Hm_Utils.remove_from_local_storage(this.list);
    }

    /**
     * @typedef {Object} RowOutput
     * @property {Number} index - The index of the row
     * @property {RowEntry} value - The row entry
     * 
     * @param {String} uid 
     * @returns {RowOutput|false} row - The row object if found, false otherwise
     */
    getRowByUid(uid) {
        const row = this.rows.find(row => $(row['0']).attr('data-uid') == uid);
        
        if (row) {
            return row;
        }
        return false;
    }

    hashObject(obj) {
        const str = JSON.stringify(obj);
        let hash = 0, i, chr;
        for (i = 0; i < str.length; i++) {
        chr = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + chr;
            hash |= 0; // Convert to 32-bit int
        }
        return `id_${Math.abs(hash)}`;
    }
}

[
    Hm_MessagesStore
].forEach((item) => {
    window[item.name] = item;
});
