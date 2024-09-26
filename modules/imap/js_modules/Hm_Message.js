class Hm_Message {
    constructor(uid, html) {
        this.uid = uid;
        this.html = html;
    }

    getStorageKey() {
        return this.uid + '_' + hm_list_path();
    }

    // TODO: Add message storage implementation
}

/**
 * An abstraction object of the Message_List focused on state management without UI interaction.
 */
class Hm_MessagesStore {

    /**
     * @typedef {Object} RawObject
     * @property {String} 0 - The HTML string of the raw
     * @property {String} 1 - The IMAP key
     */

    /** 
     * @typedef {Array} RawEntry
     * @property {String} 0 - The IMAP key
     * @property {RawObject} 1 - An object containing the raw message and the IMAP key
     */

    constructor(path, page, raws = {}) {
        this.path = path;
        this.list = path + '_' + page;
        this.raws = raws;
        this.links = "";
        this.count = 0;
    }

    /**
     * 
     * @returns {Promise<Array<String>>}
     */
    async load(reload = false, hideLoadingState = false) {
        const storedMessages = this.#retrieveFromLocalStorage();
        if (storedMessages && !reload) {
            this.raws = storedMessages.raws;
            this.links = storedMessages.links;
            this.count = storedMessages.count;
            return this;
        }

        const { formatted_message_list: updatedMessages, page_links: pageLinks, folder_status } = await this.#fetch(hideLoadingState);

        this.count = Object.values(folder_status)[0].messages;
        this.links = pageLinks;
        this.raws = updatedMessages;

        this.#saveToLocalStorage();

        return this;
    }

    /**
     * 
     * @param {String} uid the id of the message to be marked as read
     * @returns {Boolean} true if the message was marked as read, false otherwise
     */
    markRawAsRead(uid) {
        const raws = Object.entries(this.raws);
        const raw = this.#getRawByUid(uid)?.value;
        
        if (raw) {
            const htmlRaw = $(raw[1]['0']);
            const wasUnseen = htmlRaw.find('.unseen').length > 0 || htmlRaw.hasClass('unseen');

            htmlRaw.removeClass('unseen');
            htmlRaw.find('.unseen').removeClass('unseen');
            const objectRaws = Object.fromEntries(raws);
            objectRaws[raw[0]]['0'] = htmlRaw[0].outerHTML;
            
            this.raws = objectRaws;
            this.#saveToLocalStorage();

            return wasUnseen;
        }
        return false;
    }

    /**
     * 
     * @param {*} uid 
     * @returns {RawObject|false} the next raw entry if found, false otherwise
     */
    getNextRawForMessage(uid) {
        const raws = Object.entries(this.raws);
        const raw = this.#getRawByUid(uid)?.index;
        
        if (raw !== false) {
            const nextRaw = raws[raw + 1];
            if (nextRaw) {
                return nextRaw[1];
            }
        }
        return false;
    }

    /**
     * 
     * @param {*} uid 
     * @returns {RawObject|false} the previous raw entry if found, false otherwise
     */
    getPreviousRawForMessage(uid) {
        const raws = Object.entries(this.raws);
        const raw = this.#getRawByUid(uid)?.index;
        if (raw) {
            const previousRaw = raws[raw - 1];
            if (previousRaw) {
                return previousRaw[1];
            }
        }
        return false;
    }

    #fetch(hideLoadingState = false) {
        const detail = Hm_Utils.parse_folder_path(this.path, 'imap');
        return new Promise((resolve, reject) => {
            Hm_Ajax.request(
              [
                { name: "hm_ajax_hook", value: "ajax_imap_folder_display" },
                { name: "imap_server_id", value: detail.server_id },
                { name: "folder", value: detail.folder },
              ],
              (response) => {
                resolve(response);
              },
              [],
              hideLoadingState,
              undefined,
              reject
            );
        });
    }

    #saveToLocalStorage() {
        Hm_Utils.save_to_local_storage(this.list, JSON.stringify({ raws: this.raws, links: this.links, count: this.count }));
    }

    #retrieveFromLocalStorage() {
        const stored = Hm_Utils.get_from_local_storage(this.list);
        if (stored) {
            return JSON.parse(stored);
        }
        return false;
    }

    /**
     * @typedef {Object} RawOutput
     * @property {Number} index - The index of the raw
     * @property {RawEntry} value - The raw entry
     * 
     * @param {String} uid 
     * @returns {RawOutput|false} raw - The raw object if found, false otherwise
     */
    #getRawByUid(uid) {
        const raws = Object.entries(this.raws);
        const raw = raws.find(([key, value]) => $(value['0']).attr('data-uid') == uid);
        
        if (raw) {
            const index = raws.indexOf(raw);
            return { index, value: raw };
        }
        return false;
    }
}

[
    Hm_Message,
    Hm_MessagesStore
].forEach((item) => {
    window[item.name] = item;
});
