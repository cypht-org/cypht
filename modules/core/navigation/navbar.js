$(() => {
    if(window.hm_mobile && hm_mobile()) {

        window.addEventListener('page-change', () => {
            hideMobileNavbar();
        });

        // Remove any existing menu-toggle elements to avoid conflicts
        $('.mobile .cypht-layout .menu-toggle').remove();
        $('.mobile .cypht-layout nav .menu-toggle').remove();
        
        const hamburgerToggle = `
        <div class="menu-toggle fw-bold cursor-pointer hamburger-toggle">
            <i class="bi bi-list fs-5 fw-bold"></i>
        </div>
        `
        
        const closeToggle = `
        <div class="menu-toggle fw-bold cursor-pointer close-toggle" style="display: none;">
            <i class="bi bi-x-lg fs-5 fw-bold"></i>
        </div>
        `
        
        // Get the appropriate logo based on current theme
        const getLogoPath = () => {
            const isDarkTheme = getComputedStyle(document.documentElement)
                .getPropertyValue('--bs-secondary-bg') === '#333';
            const logoFile = isDarkTheme ? 'logo.svg' : 'logo_dark.svg';
            return `${hm_web_root_path()}modules/core/assets/images/${logoFile}`;
        };

        const navHeader = `
        <div class="nav-header">
            <a href="?page=home" class="menu_home">
                <img class="app-logo" src="${getLogoPath()}">
            </a>
            <div class="menu-toggle fw-bold cursor-pointer close-toggle" style="display: none;">
                <i class="bi bi-x-lg fs-5 fw-bold"></i>
            </div>
        </div>
        `
    
        // Only add these elements on mobile
        $('.mobile .cypht-layout nav').before(hamburgerToggle);
        $('.mobile .cypht-layout nav').prepend(navHeader);
    
        $(document).on('click', '.cypht-layout .hamburger-toggle', showMobileNavbar);
        $(document).on('click', '.cypht-layout nav .close-toggle', hideMobileNavbar)
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
    // Show hamburger, hide X
    $('.hamburger-toggle').show();
    $('.close-toggle').hide();
}

function showMobileNavbar() {
    $('.cypht-layout nav').css('transform', 'translateX(0)');
    $('#cypht-main').css('max-height', 'calc(100vh - 3.5rem)');
    $('#cypht-main').css('overflow', 'hidden');
    // Hide hamburger, show X
    $('.hamburger-toggle').hide();
    $('.close-toggle').show();
}