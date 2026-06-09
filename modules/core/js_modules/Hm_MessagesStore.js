/**
 * An abstraction object of the Message_List focused on state management without UI interaction.
 */
class Hm_MessagesStore {

    static SOURCE_PREFS_KEY = 'combined_view_source_prefs';

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

    constructor(path, page = 1, filter = '', sortFld = 'arrival', rows = [], expandSearch = false) {
        this.path = path;
        this.expandSearch = expandSearch;
        this.list = path + '_' + (filter ? filter.replace(/\s+/g, '_') + '_' + sortFld + '_': '') + page + (expandSearch ? '_expanded' : '');
        this.sortFld = sortFld;
        this.rows = rows;
        this.sources = {};
        this.count = 0;
        this.flagAsReadOnOpen = true;
        this.pages = 0;
        this.page = page;
        this.newMessages = [];
        this.forceGithubRefresh = false;
        // Resolvers for in-flight load() outer promises; abort() drains these so Promise.all settles.
        this._cancelResolvers = new Set();
    }

    /**
     * Cancel all in-flight load() calls by settling their pending promises immediately.
     * Call this on page teardown to prevent stale DOM writes and unblock the event loop.
     */
    abort() {
        this._cancelResolvers.forEach(resolve => resolve());
        this._cancelResolvers.clear();
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
            this.sort();
            if (messagesReadyCB) {
                messagesReadyCB(this);
            }
            if (!reload) {
                return this;
            }
        }

        if (doNotFetch) {
            return this;
        }

        const sourcesToRemove = Object.keys(this.sources).filter(key => !this.currentlyAvailableSources().includes(key));
        sourcesToRemove.forEach(key => delete this.sources[key]);

        // Batch processing for multiple requests
        const pendingResponses = new Map();
        let processingTimeout = null;

        const processPendingResponses = () => {
            if (pendingResponses.size === 0) return;

            // Process all pending responses at once
            const responses = Array.from(pendingResponses.values());
            pendingResponses.clear();

            responses.forEach(({ formatted_message_list: updatedMessages, pages, folder_status, do_not_flag_as_read_on_open, sourceId }) => {
                // count and pages only available in non-combined pages where there is only one ajax call, so it is safe to overwrite
                this.count = folder_status && Object.values(folder_status)[0]?.messages;
                this.pages = parseInt(pages);
                this.newMessages = this.getNewMessages(updatedMessages);

                if (typeof do_not_flag_as_read_on_open == 'boolean') {
                    this.flagAsReadOnOpen = !do_not_flag_as_read_on_open;
                }

                if (this.sources[sourceId]) {
                    this.rows = this.rows.filter(row => !this.sources[sourceId].includes(row['1']));
                }
                this.sources[sourceId] = Object.keys(updatedMessages);
                for (const id in updatedMessages) {
                    if (this.rows.map(row => row['1']).indexOf(id) === -1) {
                        this.rows.push(updatedMessages[id]);
                    } else {
                        const index = this.rows.map(row => row['1']).indexOf(id);
                        this.rows[index] = updatedMessages[id];
                    }
                }
            });

            // Do expensive operations only once for all responses
            if (this.path == 'unread') {
                $('.total_unread_count').html('&#160;'+this.rows.length+'&#160;');
            }

                    this.sort();
                    this.saveToLocalStorage();

            if (messagesReadyCB) {
                messagesReadyCB(this);
            }

            responses.forEach(response => {
                response.resolvePromise(response);
            });
        };

        await Promise.all(this.fetch(hideLoadingState).map((req) => {
            return new Promise((resolve) => {
                this._cancelResolvers.add(resolve);
                req.then((response) => {
                    this._cancelResolvers.delete(resolve);
                    response.resolvePromise = resolve;
                    pendingResponses.set(response.sourceId, response);

                    if (processingTimeout) {
                        clearTimeout(processingTimeout);
                    }

                    // Process after a short delay to allow batching
                    processingTimeout = setTimeout(processPendingResponses, 10);
                }, (error) => {
                    // A source failed (network error, server error, rate-limit, etc.).
                    // Resolve with no data so Promise.all can settle and other sources
                    // that did succeed are still rendered.
                    this._cancelResolvers.delete(resolve);
                    console.error('Error loading messages from source:', error);
                    resolve();
                });
            });
        }));
        this._cancelResolvers.clear();

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
            const initialConfig = Object.assign([], config);
            return new Promise((resolve, reject) => {
                Hm_Ajax.request(
                    config,
                    (response) => {
                        if (response) {
                            response.sourceId = store.hashObject(initialConfig); // Do not use this config object because the request appends a "hm_page_key" entry, which would change the hash
                            resolve(response);
                        } else {
                            // Hm_Ajax calls back with false on any failure (network error,
                            // non-JSON response, server error, etc.). Reject so the load()
                            // error handler can resolve the outer Promise and unblock Promise.all.
                            reject(new Error('AJAX request returned empty response'));
                        }
                    },
                    [],
                    hideLoadingState,
                    undefined,
                    reject
                );
            });
        });
    }

    currentlyAvailableSources() {
        let store = this;
        return this.getRequestConfigs().map((config) => store.hashObject(config));
    }

    getRequestConfigs() {
        const config = [{ name: "list_page", value: this.page }, { name: "sort", value: this.sortFld }];
        if (this.expandSearch) {
            config.push({ name: "expand_search", value: 1 });
        }
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
        } else if (this.path.startsWith('github') && this.path !== 'github_all') {
            config.push({ name: "hm_ajax_hook", value: 'ajax_github_data' });
            // Use slice instead of split('_')[1] so repo names/owners with underscores
            // (e.g. "my_org/my_repo") are passed through intact.
            config.push({ name: "github_repo", value: this.path.slice('github_'.length) });
            if (this.forceGithubRefresh) {
                config.push({ name: "github_force_refresh", value: 1 });
            }
            configs.push(config);
        } else {
            if (this.path == 'tag') {
                config.push({ name: "hm_ajax_hook", value: 'ajax_imap_tag_data' });
                config.push({ name: "folder", value: getParam('filter') });
                configs.push(config);
            } else {
                let sources = hm_data_sources();
                sources = sources.filter((source) => Hm_MessagesStore.isSourceEnabledForPath(this.path, source));
                sources.forEach((ds) => {
                    const cfg = config.slice();
                    if (ds.type == 'feeds') {
                        cfg.push({ name: "hm_ajax_hook", value: 'ajax_feed_combined' });
                        cfg.push({ name: "feed_server_ids", value: ds.id });
                    } else if (ds.type == 'custom') {
                        cfg.push({ name: "hm_ajax_hook", value: ds.hook });
                        for (const param in ds.params) {
                            cfg.push({ name: param, value: ds.params[param] });
                        }
                    } else if (ds.type === 'github') {
                        cfg.push({ name: "hm_ajax_hook", value: 'ajax_github_data' });
                        cfg.push({ name: "github_repo", value: ds.id });
                        if (this.path === 'unread') {
                            cfg.push({ name: "github_unread", value: 1 });
                        }
                        if (this.forceGithubRefresh) {
                            cfg.push({ name: "github_force_refresh", value: 1 });
                        }
                    } else {
                        cfg.push({ name: "hm_ajax_hook", value: this.path == 'search' ? 'ajax_imap_search' : 'ajax_imap_message_list' });
                        cfg.push({ name: "imap_server_ids", value: ds.id });
                        cfg.push({ name: "imap_folder_ids", value: ds.folder });
                        cfg.push({ name: "list_path", value: this.path });
                    }
                    configs.push(cfg);
                });
            }
        }
        return configs;
    }

    static isMultiSourcePath(path) {
        return !(
            path == 'tag' ||
            path.startsWith('imap') ||
            path.startsWith('feeds') ||
            path.startsWith('github') ||
            path.startsWith('wp_')
        );
    }

    static getSourceKey(source) {
        if (!source?.type) {
            return '';
        }

        const { type, id = '', folder = '', hook = '', params = {} } = source;

        switch (type) {
            case 'feeds':
            case 'github':
            case 'wordpress':
                return `${type}:${id}`;

            case 'imap':
                return `imap:${id}:${folder}`;

            case 'custom':
                return `custom:${hook}:${JSON.stringify(params)}`;

            default:
                return `${type}:${id}:${folder}`;
        }
    }

    static defaultSourceEnabledForPath(path, source) {
        if (!Hm_MessagesStore.isMultiSourcePath(path)) {
            return true;
        }

        if (Object.prototype.hasOwnProperty.call(source, 'default_enabled')) {
            return [true, 1, '1', 'true'].includes(source.default_enabled);
        }

        const allowedPaths = ['combined_inbox', 'unread', 'search'];

        if (
            ['feeds', 'wordpress', 'github'].includes(source.type) &&
            !allowedPaths.includes(path)
        ) {
            return false;
        }

        return true;
    }

    static getSourcePrefs() {
        const raw = Hm_Utils.get_from_local_storage(Hm_MessagesStore.SOURCE_PREFS_KEY);
        if (!raw) {
            return {};
        }
        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed == 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    static setSourceEnabledForPath(path, sourceKey, enabled) {
        const prefs = Hm_MessagesStore.getSourcePrefs();
        if (!prefs[path] || typeof prefs[path] != 'object') {
            prefs[path] = {};
        }
        prefs[path][sourceKey] = !!enabled;
        Hm_Utils.save_to_local_storage(Hm_MessagesStore.SOURCE_PREFS_KEY, JSON.stringify(prefs));
    }

    static isSourceEnabledForPath(path, source) {
        if (!Hm_MessagesStore.isMultiSourcePath(path)) {
            return true;
        }
        const sourceKey = Hm_MessagesStore.getSourceKey(source);
        if (!sourceKey) {
            return true;
        }
        const prefs = Hm_MessagesStore.getSourcePrefs();
        if (prefs[path] && Object.prototype.hasOwnProperty.call(prefs[path], sourceKey)) {
            return !!prefs[path][sourceKey];
        }
        return Hm_MessagesStore.defaultSourceEnabledForPath(path, source);
    }

    static syncSourceToggleInputs(path) {
        if (!Hm_MessagesStore.isMultiSourcePath(path)) {
            return;
        }
        $('.combined_source_toggle').each(function() {
            const $toggle = $(this);
            try {
                const rawSource = $toggle.attr('data-source') || '';
                const source = rawSource ? JSON.parse(decodeURIComponent(rawSource)) : {};
                $toggle.prop('checked', Hm_MessagesStore.isSourceEnabledForPath(path, source));
            } catch (e) {
                $toggle.prop('checked', true);
                console.warn('Unable to parse source toggle payload', e);
            }
        });
    }

    static bindSourceToggleInputs(path, onChange = null) {
        if (!Hm_MessagesStore.isMultiSourcePath(path)) {
            return;
        }

        Hm_MessagesStore.syncSourceToggleInputs(path);

        $('.combined_source_toggle').off('change').on('change', function() {
            const $toggle = $(this);
            const listPath = $toggle.attr('data-list-path') || path;

            try {
                const rawSource = $toggle.attr('data-source') || '';
                const source = rawSource ? JSON.parse(decodeURIComponent(rawSource)) : {};
                const sourceKey = Hm_MessagesStore.getSourceKey(source);
                if (sourceKey) {
                    Hm_MessagesStore.setSourceEnabledForPath(listPath, sourceKey, $toggle.is(':checked'));
                }
                if (onChange) {
                    onChange(source, $toggle.is(':checked'));
                }
            } catch (e) {
                // Invalid source payload should not break the page.
                console.warn('Unable to update source preference from toggle', e);
            }
        });
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
