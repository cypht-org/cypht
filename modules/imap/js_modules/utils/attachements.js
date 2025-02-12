function handleAttachementDownload() {
    $('.download_link a').on("click", async function(e) {
        e.preventDefault();
        const loaderInstance = showLoaderToast("Downloading attachment...");
        const href = $(this).data('src');
        try {
            await fetch(href).then(res => res.blob()).then(blob => {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'attachment';
                a.click();
                URL.revokeObjectURL(url);
            });
        } catch (error) {
            Hm_Notices.show(error.message, 'danger');
        } finally {
            loaderInstance.hide();
        }
    });
}