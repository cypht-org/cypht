async function downloadAttachment(href, showToast = true) {
    const loaderInstance = showToast ? showLoaderToast("Downloading attachment...") : false;
    try {
        const res = await fetch(href);

        if (!res.ok) {
            throw new Error(`Download failed: ${res.status} ${res.statusText}`);
        }

        // Extract filename from Content-Disposition header if present
        const disposition = res.headers.get('Content-Disposition') || '';
        const filenameMatch = disposition.match(
            /filename\*?=(?:UTF-8'')?"?([^;\r\n"]+)"/i
        );
        const filename = filenameMatch?.[1] || 'attachment';
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();

        /**
         * We delay revocation of the blob URL to ensure the browser’s download
         * mechanism has had time to consume it. Immediate revocation in some
         * browsers (notably Safari/WebKit) can abort or corrupt the download.
         */
        const tm = setTimeout(() => {
            URL.revokeObjectURL(url);
            clearTimeout(tm);
        }, 1000);
    } catch (error) {
        Hm_Notices.show(error.message, 'danger');
    } finally {
        if (loaderInstance) {
            loaderInstance.hide();
        }
    }
}

function handleAttachementDownload() {
    $('.download_link a, .attachment_card').on("click", function(e) {
        e.preventDefault();
        downloadAttachment($(this).data('src'));
    });

    $('.attached_files_download_all').on("click", async function(e) {
        e.preventDefault();
        const cards = $(this).closest('.attached_files_box').find('.attachment_card');
        const loaderInstance = showLoaderToast("Downloading attachments...");
        for (const card of cards) {
            await downloadAttachment($(card).data('src'), false);
        }
        loaderInstance.hide();
    });
}
