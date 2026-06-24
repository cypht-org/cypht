/**
 * Allow external resources for the provided element.
 *
 * @param {HTMLElement} element - The element containing the allow button.
 * @param {string} messagePart - The message part associated with the resource.
 * * @param {Boolean} inline - true if the message is displayed in inline mode, false otherwise.
 * @returns {void}
 */
function handleAllowResource(element, messagePart, inline = false) {
    element.querySelector('a').addEventListener('click', function (e) {
        e.preventDefault();
        $('.msg_text_inner').remove();
        const externalSources = $(this).data('src').split(',');
        externalSources?.forEach((source) => Hm_Utils.save_to_local_storage(source, 1));
        if (inline) {
            return inline_imap_msg(window.inline_msg_details, window.inline_msg_uid);
        }
        return get_message_content(getParam('part'), getMessageUidParam(), getListPathParam(), getParam('list_parent'), false, false, false);
    });
}

/**
 * Create and insert in the DOM an element containing a message and a button to allow the resource.
 *
 * @param {HTMLElement} element - The element having the blocked resource.
 * @param {Boolean} inline - true if the message is displayed in inline mode, false otherwise.
 * @returns {void}
 */
function handleInvisibleResource(element, inline = false) {
    const dataSrc = element.dataset.src;

    const allowResource = document.createElement('div');
    allowResource.classList.add('alert', 'alert-warning', 'p-1');

    const source = dataSrc.substring(0, 40) + (dataSrc.length > 40 ? '...' : '');
    allowResource.innerHTML = `Source blocked: ${element.alt ? element.alt : source}
    <a href="#" data-src="${dataSrc}" class="btn btn-light btn-sm">
    Allow</a></div>
    `;

    document.querySelector('.external_notices').insertAdjacentElement('beforeend', allowResource);
    handleAllowResource(allowResource, element.dataset.messagePart, inline);
}

const handleExternalResources = (inline) => {
    const messageContainer = document.querySelector('.msg_text_inner');
    const externalNoticesAccordion = document.createElement('div');
    externalNoticesAccordion.classList.add('accordion');
    externalNoticesAccordion.id = 'externalNoticesAccordion';
    messageContainer.insertAdjacentElement('afterbegin', externalNoticesAccordion);
    externalNoticesAccordion.innerHTML = '<div class="external_notices accordion-collapse collapse"></div>';

    const senderEmail = document.querySelector('#contact_info')?.textContent.match(EMAIL_REGEX)[0];

    if (handleBlockedStatus(inline, senderEmail)) {
        return;
    }

    const sender = senderEmail + '_external_resources_allowed';
    const elements = messageContainer.querySelectorAll('[data-src]');
    const blockedResources = [];

    elements.forEach(function (element) {

        const dataSrc = element.dataset.src;
        const senderAllowed = Hm_Utils.get_from_local_storage(sender);
        const allowed = Hm_Utils.get_from_local_storage(dataSrc);

        switch (Number(allowed) || Number(senderAllowed)) {
            case 1:
                element.src = dataSrc;
                break;
            default:
                if ((allowed || senderAllowed) === null) {
                    Hm_Utils.save_to_local_storage(dataSrc, 0);
                }
                handleInvisibleResource(element, inline);
                blockedResources.push(dataSrc);
                break;
        }
    });

    const noticesElement = document.createElement('div');
    noticesElement.classList.add('notices');

    if (blockedResources.length) {
        const allowAll = document.createElement('div');
        allowAll.classList.add('allow_image_link', 'all', 'fw-bold');
        allowAll.textContent = 'For security reasons, external resources have been blocked.';
        if (blockedResources.length > 1) {
            const allowAllLink = document.createElement('a');
            allowAllLink.classList.add('btn', 'btn-light', 'btn-sm', 'text-decoration-none');
            allowAllLink.href = '#';
            allowAllLink.dataset.src = blockedResources.join(',');
            allowAllLink.textContent = 'Allow all';

            const expandLink = document.createElement('a');
            expandLink.classList.add('btn', 'btn-sm', 'd-flex', 'align-items-center', 'gap-2');
            expandLink.href = '#';
            expandLink.setAttribute('data-bs-toggle', 'collapse');
            expandLink.setAttribute('data-bs-target', '.external_notices');
            expandLink.innerHTML = 'Show details <i class="bi bi-chevron-down"></i>';
            document.querySelector('.external_notices').addEventListener('show.bs.collapse', function () {
                expandLink.innerHTML = 'Hide details<i class="bi bi-chevron-up"></i>';
            });
            document.querySelector('.external_notices').addEventListener('hide.bs.collapse', function () {
                expandLink.innerHTML = 'Show details<i class="bi bi-chevron-down"></i>';
            });

            const linksWrapper = $('<div class="d-inline-flex"></div>');
            linksWrapper.append(allowAllLink);
            linksWrapper.append(expandLink);
            allowAll.appendChild(linksWrapper[0]);

            handleAllowResource(allowAll, getParam('part'), inline);
        }
        noticesElement.insertAdjacentElement('afterbegin', allowAll);

        const definitiveActions = $('<div class="definitive_actions ms-auto">From this sender always:</div>');

        const button = document.createElement('a');
        button.setAttribute('href', '#');
        button.classList.add('always_allow_image', 'btn', 'btn-light', 'btn-sm', 'text-decoration-none');
        button.innerHTML = '<i class="bi bi-check"></i> Allow';
        definitiveActions.append(button);
        const popover = sessionAvailableOnlyActionInfo(button)

        button.addEventListener('click', function (e) {
            e.preventDefault();
            addSenderToImagesWhitelist(senderEmail).then(refreshMessageContent.bind(null, inline)).finally(() => {
                popover.dispose();
            })
        });

        const alwaysBlockButton = $('<a href="#" class="btn btn-light btn-sm ms-2 text-decoration-none"><i class="bi bi-shield-lock"></i> Block</a>');
        const blockPopover = sessionAvailableOnlyActionInfo(alwaysBlockButton[0]);
        definitiveActions.append(alwaysBlockButton[0]);

        alwaysBlockButton.on('click', function (e) {
            e.preventDefault();
            addSenderToImagesBlackList(senderEmail).then(refreshMessageContent.bind(null, inline)).finally(() => {
                blockPopover.dispose();
            })
        });

        noticesElement.appendChild(definitiveActions[0]);
    }

    document.querySelector('.external_notices').insertAdjacentElement('beforebegin', noticesElement);
};

function handleBlockedStatus(inline, senderEmail) {
    if ($('[data-external-resources-blocked="1"]').length) {
        const infoElement = $('<div class="fw-bold">External resources from this sender are blocked.</div>');
        const button = $('<a href="#" class="btn btn-light btn-sm ms-2"><i class="bi bi-unlock"></i> Reset permissions</a>');

        $(infoElement).append(button);
        $('#externalNoticesAccordion').append(infoElement);

        const popover = sessionAvailableOnlyActionInfo(button[0]);

        button.on('click', function (e) {
            e.preventDefault();
            removeSenderFromImagesBlackList(senderEmail).then(refreshMessageContent.bind(null, inline)).finally(() => {
                popover.dispose()
            });
        });

        return true;
    }

    return false;
}

function refreshMessageContent(inline) {
    $('.msg_text_inner').remove();
    if (inline) {
        inline_imap_msg(window.inline_msg_details, window.inline_msg_uid);
    } else {
        get_message_content(getParam('part'), getMessageUidParam(), getListPathParam(), getParam('list_parent'), false, false, false)
    }
}

const observeMessageTextMutationAndHandleExternalResources = (inline) => {
    const message = document.querySelector('.msg_text');
    if (message) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.classList.contains('msg_text_inner') && !message.querySelector('.external_notices')) {
                            handleExternalResources(inline);
                        }
                    });
                }
            });
        }).observe(message, {
            childList: true
        });
    }
};