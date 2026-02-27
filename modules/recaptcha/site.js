'use strict';

document.addEventListener("DOMContentLoaded", function() {
    var recaptcha = document.querySelector(".g-recaptcha");
    var loginButton = document.querySelector("#login");
    if (recaptcha && loginButton) {
        var gridContainer = loginButton.closest(".d-grid");
        if (gridContainer) {
            gridContainer.parentNode.insertBefore(recaptcha, gridContainer);
        } else {
            loginButton.parentNode.insertBefore(recaptcha, loginButton);
        }
    }
});
