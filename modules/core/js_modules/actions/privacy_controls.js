async function addSenderToImagesWhitelist(email) {
    return new Promise((resolve, reject) => {
        Hm_Ajax.request([
            { name: "hm_ajax_hook", value: "ajax_privacy_settings" },
            { name: "images_whitelist", value: email },
            { name: "save_settings", value: true },
            { name: "update", value: true }
        ], (response) => {
            resolve(response);
        }, [], false, undefined, () => {
            Hm_Notices.show('An error occurred while adding the sender to the whitelist', 'danger');
            reject();
        });
    });
}

async function addSenderToImagesBlackList(email) {
    return new Promise((resolve, reject) => {
        Hm_Ajax.request([
            { name: "hm_ajax_hook", value: "ajax_privacy_settings" },
            { name: "images_blacklist", value: email },
            { name: "save_settings", value: true },
            { name: "update", value: true }
        ], (response) => {
            resolve(response);
        }, [], false, undefined, () => {
            Hm_Notices.show('An error occurred while adding the sender to the blacklist', 'danger');
            reject();
        });
    });
}

async function removeSenderFromImagesBlackList(email) {
    return new Promise((resolve, reject) => {
        Hm_Ajax.request([
            { name: "hm_ajax_hook", value: "ajax_privacy_settings" },
            { name: "images_blacklist", value: email },
            { name: "save_settings", value: true },
            { name: "update", value: true },
            { name: "pop", value: true }
        ], (response) => {
            resolve(response);
        }, [], false, undefined, () => {
            Hm_Notices.show('An error occurred while removing the sender from the blacklist', 'danger');
            reject();
        });
    });
}
