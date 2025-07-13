let K;
KindEditor.ready(function(k) {
    K = k;
});
function useKindEditor() {
    
    window.kindEditor = K.create("#compose_body", {
      items: [
        "formatblock",
        "fontname",
        "fontsize",
        "forecolor",
        "hilitecolor",
        "bold",
        "italic",
        "underline",
        "strikethrough",
        "lineheight",
        "table",
        "hr",
        "pagebreak",
        "link",
        "unlink",
        "justifyleft",
        "justifycenter",
        "justifyright",
        "justifyfull",
        "insertorderedlist",
        "insertunorderedlist",
        "indent",
        "outdent",
        "|",
        "undo",
        "redo",
        "preview",
        "print",
        "|",
        "selectall",
        "cut",
        "copy",
        "paste",
        "plainpaste",
        "wordpaste",
        "|",
        "source",
        "fullscreen",
      ],
      basePath: "third_party/kindeditor/",
    });
}

// useKindEditor();
