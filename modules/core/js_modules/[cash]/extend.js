/* extend cash.js with some useful bits */
$.inArray = function(item, list) {
    for (var i in list) {
        if (list[i] === item) {
            return i;
        }
    }
    return -1;
};
$.isEmptyObject = function(obj) {
    for (var key in obj) {
        if (obj.hasOwnProperty(key)) {
            return false;
        }
    }
    return true;
};
$.fn.submit = function() { this[0].submit(); }
$.fn.focus = function() { this[0].focus(); };
$.fn.serializeArray = function() {
    var parts;
    var res = [];
    var args = this.serialize().split('&');
    for (var i in args) {
        parts = args[i].split('=');
        if (parts[0] && parts[1]) {
            res.push({'name': parts[0], 'value': parts[1]});
        }
    }
    return res.map(function(x) {return {name: x.name, value: decodeURIComponent(x.value.replace(/\+/g, " "))}});
};
$.fn.sort = function(sort_function) {
    var list = [];
    for (var i=0, len=this.length; i < len; i++) {
        list.push(this[i]);
    }
    return $(list.sort(sort_function));
};
$.fn.fadeOut = function(timeout = 600) {
    return this.css("opacity", 0)
    .css("transition", `opacity ${timeout}ms`)
};

$.fn.modal = function(action) {
    const modalElement = this[0];
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        if (action === 'show') {
            modal.show();
        } else if (action === 'hide') {
            modal.hide();
        }
    }
};