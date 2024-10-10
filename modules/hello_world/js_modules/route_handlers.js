/**
 * If we are on the "hello_world" page, activate the click handler
 */
function applyHelloWorldPageHandlers() {
    $('.hw_ajax_link').on("click", function() {
        hello_world_ajax_update();
    });
}