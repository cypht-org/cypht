function loadMarkdownStylesheet(href) {
    if (document.querySelector('link[href="' + href + '"]')) {
        return Promise.resolve();
    }
    return new Promise(function(resolve, reject) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = resolve;
        link.onerror = reject;
        document.head.appendChild(link);
    });
}

function loadMarkdownScript(src) {
    if (document.querySelector('script[src="' + src + '"]')) {
        return Promise.resolve();
    }
    return new Promise(function(resolve, reject) {
        var script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function useMarkdownEditor() {
    var root = hm_web_root_path();
    var cssUrl = root + 'modules/smtp/assets/markdown/editor.css';
    var editorUrl = root + 'modules/smtp/assets/markdown/editor.js';
    var markedUrl = root + 'modules/smtp/assets/markdown/marked.js';
    var textarea = document.getElementById('compose_body');

    if (!textarea) {
        return;
    }

    loadMarkdownStylesheet(cssUrl)
        .then(function() { return loadMarkdownScript(editorUrl); })
        .then(function() { return loadMarkdownScript(markedUrl); })
        .then(function() {
            window.mdEditor = new Editor({ element: textarea });
        });
}
