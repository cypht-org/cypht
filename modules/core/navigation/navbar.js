if(window.hm_mobile && hm_mobile()) {

    window.addEventListener('page-change', () => {
        hideMobileNavbar();
    });

    const menuToggle = `
    <div class="menu-toggle rounded-pill p-2 bg-primary-subtle fw-bold cursor-pointer">
        <i class="bi bi-list fs-5 fw-bold"></i>
    </div>
    `

    $('.mobile nav').before(menuToggle);
    $('.mobile nav').prepend(menuToggle);

    $('.menu-toggle').on('click', function() {
        showMobileNavbar();
    });

    $('nav .menu-toggle').on('click', function() {
        hideMobileNavbar();
    });
}

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