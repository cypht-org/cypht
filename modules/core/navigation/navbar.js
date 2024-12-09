$(() => {
    if(window.hm_mobile && hm_mobile()) {

        window.addEventListener('page-change', () => {
            hideMobileNavbar();
        });

        const menuToggle = `
        <div class="menu-toggle rounded-pill fw-bold cursor-pointer">
            <i class="bi bi-list fs-5 fw-bold"></i>
        </div>
        `
    
        $('.mobile .cypht-layout nav').before(menuToggle);
    
        $(document).on('click', '.cypht-layout .menu-toggle', showMobileNavbar);
        $(document).on('click', '.cypht-layout nav .menu-toggle', hideMobileNavbar)
    } else {
        $(document).on('click', '.menu-toggle', function() {
            $('.cypht-layout nav').toggleClass('collapsed');
            if ($('.cypht-layout nav').hasClass('collapsed')) {
                document.documentElement.style.setProperty('--nav-size', 'var(--nav-collapsed-size)');
            } else {
                document.documentElement.style.setProperty('--nav-size', 'var(--nav-expanded-size)');
            }
        })
    }
})

function hideMobileNavbar() {
    $('.cypht-layout nav').css('transform', 'translateX(-120%)');
    $('#cypht-main').css('max-height', 'unset');
    $('#cypht-main').css('overflow', 'unset');
}

function showMobileNavbar() {
    $('.cypht-layout nav').css('transform', 'translateX(0)');
    $('#cypht-main').css('max-height', 'calc(100vh - 3.5rem)');
    $('#cypht-main').css('overflow', 'hidden');
}