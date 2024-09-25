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
    constructor(path, page, raws = []) {
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
}

[
    Hm_Message,
    Hm_MessagesStore
].forEach((item) => {
    window[item.name] = item;
});
