$(function() {
    if (!navigator.userAgent.toLowerCase().match(/iphone|ipad|ipod|opera/)) {
      return;
    }
    $('a').on('click', function(evt) {
      var href = $(evt.target).closest('a').attr('href');
      if (href !== undefined && !(href.match(/^#/) || href.trim() == '')) {
        var response = $(window).triggerHandler('beforeunload', response);
        if (response && response != "") {
          var msg = response + "\n\n"
            + "Press OK to leave this page or Cancel to stay.";
          if (!confirm(msg)) {
            return false;
          }
        }
        window.location.href = href;
        return false;
       }
    });
  });