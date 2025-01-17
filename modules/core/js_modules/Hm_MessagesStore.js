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

    constructor(path, page = 1, rows = {}, abortController = new AbortController()) {
        this.path = path;
        this.list = path + '_' + page;
        this.rows = rows;
        this.count = 0;
        this.flagAsReadOnOpen = true;
        this.abortController = abortController;
        this.pages = 0;
        this.page = page;
        this.offsets = '';
    }

    /**
     * Check if the store has data for the current instance
     * @returns {Boolean}
     */
    hasLocalData() {
        return this.#retrieveFromLocalStorage() !== false;
    }

    /**
     * 
     * @returns {Promise<this>}
     */
    async load(reload = false, hideLoadingState = false, doNotFetch = false) {
        const storedMessages = this.#retrieveFromLocalStorage();
        if (storedMessages) {
            this.rows = storedMessages.rows;
            this.pages = parseInt(storedMessages.pages);
            this.count = storedMessages.count;
            this.flagAsReadOnOpen = storedMessages.flagAsReadOnOpen;
            this.offsets = storedMessages.offsets;
            if (!reload) {
                return this;
            }
        }

        if (doNotFetch) {
            return this;
        }

        const { formatted_message_list: updatedMessages, pages, folder_status, do_not_flag_as_read_on_open, offsets } = await this.#fetch(hideLoadingState);

        this.count = folder_status && Object.values(folder_status)[0]?.messages;
        this.pages = parseInt(pages);
        this.rows = updatedMessages;
        this.flagAsReadOnOpen = !do_not_flag_as_read_on_open;
        this.offsets = offsets;

        this.#saveToLocalStorage();

        return this;
    }

    /**
     * 
     * @param {String} uid the id of the message to be marked as read
     * @returns {Boolean} true if the message was marked as read, false otherwise
     */
    markRowAsRead(uid) {
        const rows = Object.entries(this.rows);
        const row = this.getRowByUid(uid)?.value;
        
        if (row) {
            const htmlRow = $(row[1]['0']);
            const wasUnseen = htmlRow.find('.unseen').length > 0 || htmlRow.hasClass('unseen');

            htmlRow.removeClass('unseen');
            htmlRow.find('.unseen').removeClass('unseen');
            const objectRows = Object.fromEntries(rows);
            objectRows[row[0]]['0'] = htmlRow[0].outerHTML;
            
            this.rows = objectRows;
            this.#saveToLocalStorage();

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
        const rows = Object.entries(this.rows);
        const row = this.getRowByUid(uid)?.index;
        
        if (row !== false) {
            const nextRow = rows[row + 1];
            if (nextRow) {
                return nextRow[1];
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
        const rows = Object.entries(this.rows);
        const row = this.getRowByUid(uid)?.index;
        if (row) {
            const previousRow = rows[row - 1];
            if (previousRow) {
                return previousRow[1];
            }
        }
        return false;
    }
    
    removeRow(uid) {
        const rows = Object.entries(this.rows);
        const row = this.getRowByUid(uid);
        if (row) {
            const newRows = rows.filter((_, i) => i !== row.index);
            this.rows = Object.fromEntries(newRows);
            this.#saveToLocalStorage();
        }
        
    }

    #fetch(hideLoadingState = false) {
        return new Promise((resolve, reject) => {
            Hm_Ajax.request(
              this.#getRequestConfig(),
              (response) => {
                resolve(response);
              },
              [],
              hideLoadingState,
              undefined,
              reject,
              this.abortController?.signal
            );
        });
    }

    #getRequestConfig() {
        let hook;
        const config = [];
        if (this.path.startsWith('imap')) {
            hook = "ajax_imap_folder_display";

            const detail = Hm_Utils.parse_folder_path(this.path, 'imap');
            config.push({ name: "imap_server_id", value: detail.server_id });
            config.push({ name: "folder", value: detail.folder });

        } else if (this.path.startsWith('feeds')) {
            hook = "ajax_feed_combined";
            const serverId = this.path.split('_')[1];
            if (serverId) {
                config.push({ name: "feed_server_ids", value: serverId });
            }
        } else if (this.path.startsWith('github')) {
            hook = "ajax_github_data";
            config.push({ name: "github_repo", value: this.path.split('_')[1] });
        } else {
            switch (this.path) {
                case 'unread':
                case 'flagged':
                    hook = "ajax_imap_filter_by_type";
                    config.push({ name: "filter_type", value: this.path });
                    break;
                case 'combined_inbox':
                    hook = "ajax_combined_message_list";
                    break;
                case 'email':
                    hook = "ajax_imap_combined_inbox";
                    break;
                case 'tag':
                    hook = "ajax_imap_tag_data";
                    folder = getParam('tag_id');
                    break;
                default:
                    hook = "ajax_imap_folder_data";
                    break;
            }
        }
        
        config.push({ name: "hm_ajax_hook", value: hook });
        config.push({ name: "list_page", value: this.page });

        return config;
    }

    #saveToLocalStorage() {
        Hm_Utils.save_to_local_storage(this.list, JSON.stringify({ rows: this.rows, pages: this.pages, count: this.count, offsets: this.offsets }));
        Hm_Utils.save_to_local_storage('flagAsReadOnOpen', this.flagAsReadOnOpen);
    }

    #retrieveFromLocalStorage() {
        const stored = Hm_Utils.get_from_local_storage(this.list);
        const flagAsReadOnOpen = Hm_Utils.get_from_local_storage('flagAsReadOnOpen');
        if (stored) {
            return {...JSON.parse(stored), flagAsReadOnOpen: flagAsReadOnOpen !== 'false'};
        }
        return false;
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
        const rows = Object.entries(this.rows);
        const row = rows.find(([key, value]) => $(value['0']).attr('data-uid') == uid);
        
        if (row) {
            const index = rows.indexOf(row);
            return { index, value: row };
        }
        return false;
    }
}

[
    Hm_MessagesStore
].forEach((item) => {
    window[item.name] = item;
});
