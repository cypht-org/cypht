/**
 * Add custom URL query parameters to all forms on the page as hidden inputs, allowing them to be appended to the browser URL when the form is submitted.
 */
new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length > 0) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const forms = node.querySelectorAll('form');
                    forms.forEach(function(form) {
                        if (hm_append_url_query()) {
                            const urlParams = new URLSearchParams(hm_append_url_query());
                            for (const [key, value] of urlParams.entries()) {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = key;
                                input.value = value;
                                form.appendChild(input);
                            }
                        }
                    });
                }
            });
        }
    });
}).observe(document.body, { childList: true, subtree: true });
