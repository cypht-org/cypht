$(() => {
    if(window.hm_mobile && hm_mobile()) {

        window.addEventListener('page-change', () => {
            hideMobileNavbar();
        });

        const menuToggle = `
        <div class="menu-toggle rounded-pill p-2 fw-bold cursor-pointer">
            <i class="bi bi-list fs-5 fw-bold"></i>
        </div>
        `
    
        $('.mobile nav').before(menuToggle);
    
        $(document).on('click', '.menu-toggle', showMobileNavbar);
        $(document).on('click', 'nav .menu-toggle', hideMobileNavbar)
    } else {
        $(document).on('click', '.menu-toggle', function() {
            $('nav').toggleClass('collapsed');
            if ($('nav').hasClass('collapsed')) {
                document.documentElement.style.setProperty('--nav-size', 'var(--nav-collapsed-size)');
            } else {
                document.documentElement.style.setProperty('--nav-size', 'var(--nav-expanded-size)');
            }
        })
    }
})

function hideMobileNavbar() {
    $('nav').css('transform', 'translateX(-120%)');
    $('main').css('max-height', 'unset');
    $('main').css('overflow', 'unset');
}

function showMobileNavbar() {
    $('nav').css('transform', 'translateX(0)');
    $('main').css('max-height', 'calc(100vh - 3.5rem)');
    $('main').css('overflow', 'hidden');
}