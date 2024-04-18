/*!
 * An experimental shim to partially emulate onBeforeUnload on iOS.
 * Part of https://github.com/codedance/jquery.AreYouSure/
 *
 * Copyright (c) 2012-2014, Chris Dance and PaperCut Software http://www.papercut.com/
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * Author:  chris.dance@papercut.com
 * Date:    19th May 2014
 */
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
