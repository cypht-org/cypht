function handleAttachementDownload() {
    $('.download_link a').on("click", async function(e) {
        e.preventDefault();
        const loaderInstance = showLoaderToast("Downloading attachment...");
        const href = $(this).data('src');
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
            loaderInstance.hide();
        }
    });
}