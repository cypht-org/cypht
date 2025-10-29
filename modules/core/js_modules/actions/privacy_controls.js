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
            Hm_Notices.show('An error occured while adding the sender to the whitelist', 'danger');
            reject();
        });
    });
}