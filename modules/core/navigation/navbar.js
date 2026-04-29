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

        if (Hm_Utils.get_from_local_storage('navbar_collapsed') === '1') {
            $('.cypht-layout nav').addClass('collapsed');
        }

        $(document).on('click', '.menu-toggle', function() {
            $('.cypht-layout nav').toggleClass('collapsed');
            if ($('.cypht-layout nav').hasClass('collapsed')) {
                document.documentElement.style.setProperty('--nav-size', 'var(--nav-collapsed-size)');
                toggleExpandableNavbarItems(true);

                Hm_Utils.save_to_local_storage('navbar_collapsed', '1');
            } else {
                document.documentElement.style.setProperty('--nav-size', 'var(--nav-expanded-size)');
                toggleExpandableNavbarItems(false);
                
                Hm_Utils.remove_from_local_storage('navbar_collapsed');
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

function updateNavbarDynamicContent() {
    Hm_Folders.request_folder_list_update(({ formatted_folder_list }) => {
        const serverSideDynamicContent = ['#js-logout_link'];

        serverSideDynamicContent.forEach(selector => {
            const newElement = $(formatted_folder_list).find(selector).prop('outerHTML');
            const currentElement = $(selector);
            if (newElement) {
                currentElement.replaceWith(newElement);
            }
        });
    });
}

function toggleExpandableNavbarItems(collapsed) {
    $(".cypht-layout nav .folder_list .src_name").each(function() {
        const initial = $(`<div class="src_name_initial temp" role="button">${$(this).text().trim().charAt(0)}</div>`)
        if (collapsed) {
            $(this).after(initial);

            const popover = bootstrap.Popover.getOrCreateInstance(initial[0], {
                title: $(this).text().trim(),
                html: true,
                trigger: 'manual',
                container: '.cypht-layout',
                customClass: 'navbar-popover',
                sanitize: false
            });

            // Cash fails to handle Bootstrap popover events, so using plain JS here.
            initial[0].addEventListener('shown.bs.popover', function (e) {
                Hm_Folders.folder_list_events();
            });

            const target = $(this).data('bs-target');
            const popoverTitle = $(this).text().trim();
            // manually set popover content on show, to ensure user state is preserved
            initial[0].addEventListener('show.bs.popover', function (e) {
                const srcContent = $(target).clone();
                srcContent.removeClass('collapse');
                popover.setContent({
                    '.popover-body': srcContent[0],
                    '.popover-header': popoverTitle
                });
            });

            let hideTimeout;
            initial.on('mouseenter', function() {
                clearTimeout(hideTimeout);
                popover.show();
            }).on('mouseleave', function() {
                hideTimeout = setTimeout(function() { popover.hide(); }, 150);
            });
            $(document).on('mouseenter', '.navbar-popover', function() {
                clearTimeout(hideTimeout);
            }).on('mouseleave', '.navbar-popover', function() {
                hideTimeout = setTimeout(function() { popover.hide(); }, 150);
            });
        } else {
            $(this).siblings('.src_name_initial').remove();
        }
    });
}
