function handleMessagesDragAndDrop() {
    const tableBody = document.querySelector('.message_table_body');
    if(tableBody && !hm_mobile()) {
        const allFoldersClassNames = [];
        let targetFolder;
        let movingElement;
        let movingNumber;
        Sortable.create(tableBody, {
            sort: false,
            group: 'messages',
            ghostClass: 'table-secondary',
            draggable: 'tr.email',
    
            onMove: (sortableEvent) => {
                movingElement = sortableEvent.dragged;
                targetFolder = sortableEvent.related?.className.split(' ')[0];
                if (sortableEvent.originalEvent.type === 'touchmove') {
                    const touch = sortableEvent.originalEvent.touches[0];
                    const element = document.elementFromPoint(touch.clientX, touch.clientY);
                    const closestFolder = element?.closest('li');
                    targetFolder = closestFolder?.className.split(' ')[0];
                    if (closestFolder) {
                        document.querySelectorAll('.email_folders .inner_list > li').forEach(folder => {
                            folder.classList.remove('bg-secondary-subtle');
                        });
                        closestFolder.classList.add('bg-secondary-subtle');
                    }
                }
                return false;
            },
    
            onEnd: (evt) => {
                document.querySelectorAll('.message_table_body > tr.table-secondary').forEach((row) => {
                    row.classList.remove('table-secondary');
                });
                if (evt.type === 'touchend') {
                    evt.preventDefault();
                }
                return false;
            }
        });
    
        const isValidFolderReference = (className='') => {
            return className.startsWith('imap_') && allFoldersClassNames.includes(className)
        }

        alterDragImage(tableBody);
    
        const handleDragEnd = () => {
            // If the target is not a folder, do nothing
            if (!isValidFolderReference(targetFolder ?? '')) {
                return;
            }
    
            const page = getPageNameParam();
            const selectedRows = [];
    
            if(movingNumber > 1) {
                document.querySelectorAll('.message_table_body > tr').forEach(row => {
                    if (row.querySelector('.checkbox_cell input[type=checkbox]:checked')) {
                        selectedRows.push(row);
                    }
                });
            }
    
            if (selectedRows.length == 0) {
                selectedRows.push(movingElement);
            }
    
            const movingIds = selectedRows.map(row => row.className.split(' ')[0]);
    
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_move_copy_action'},
                {'name': 'imap_move_ids', 'value': movingIds.join(',')},
                {'name': 'imap_move_to', 'value': targetFolder},
                {'name': 'imap_move_page', 'value': page},
                {'name': 'imap_move_action', 'value': 'move'}],
                async (res) =>{
                    const store = new Hm_MessagesStore(getListPathParam(), Hm_Utils.get_url_page_number(), `${getParam('keyword')}_${getParam('filter')}`, getParam('sort'));
                    await store.load(false, true, true);
                    const moveResponses = Object.values(res['move_responses']);
                    moveResponses.forEach((response) => {
                        store.removeRow(response.oldUid);
                    });
                    display_imap_mailbox(store.rows, store.list, store);
                }
            );
    
            // Reset the target folder
            targetFolder = null;
        };

        Sortable.utils.on(tableBody, 'dragend', handleDragEnd);
        tableBody.addEventListener('touchend', handleDragEnd);

        const mutationObserver = new MutationObserver(() => {
            if (document.querySelectorAll('.email_folders')) {

                const emailFoldersGroups = document.querySelectorAll('.email_folders .inner_list');
                const emailFoldersElements = document.querySelectorAll('.email_folders .inner_list > li');

                // Keep track of all folders class names
                allFoldersClassNames.push(...[...emailFoldersElements].map(folder => folder.className.split(' ')[0]));


                emailFoldersGroups.forEach((emailFolders) => {
                    Sortable.create(emailFolders, {
                        sort: false,
                        group: {
                            put: 'messages'
                        }
                    });
                });

                emailFoldersElements.forEach((emailFolder) => {
                    emailFolder.addEventListener('dragover', () => {
                        emailFolder.classList.add('bg-secondary-subtle');
                    });
                    emailFolder.addEventListener('dragleave', () => {
                        emailFolder.classList.remove('bg-secondary-subtle');
                    });
                    emailFolder.addEventListener('drop', () => {
                        emailFolder.classList.remove('bg-secondary-subtle');
                    });
                });

                mutationObserver.disconnect();
            }
        });

        mutationObserver.observe(document.querySelector('.folder_cell'), {
            childList: true,
            subtree: true
        });
    }
}

function alterDragImage(tableBody) {
    Sortable.utils.on(tableBody, 'dragstart', (evt) => {
        let movingElements = [];
        // Is the target element checked
        const isChecked = evt.target.querySelector('.checkbox_cell input[type=checkbox]:checked');
        if (isChecked) {
            movingElements = document.querySelectorAll('.message_table_body > tr > .checkbox_cell input[type=checkbox]:checked');
            // Add a highlight class to the tr
            movingElements.forEach((checkbox) => {
                checkbox.parentElement.parentElement.classList.add('table-secondary');
            });
        } else {
            // If not, uncheck all other checked elements so that they don't get moved
            document.querySelectorAll('.message_table_body > tr > .checkbox_cell input[type=checkbox]:checked').forEach((checkbox) => {
                checkbox.checked = false;
            });
        }

        movingNumber = movingElements.length || 1;

        const element = document.createElement('div');
        element.textContent = `Move ${movingNumber} conversation${movingNumber > 1 ? 's' : ''}`;
        element.style.position = 'absolute';
        element.className = 'dragged_element';
        document.body.appendChild(element);

        document.addEventListener('drag', () => {
            element.style.display = 'none'
        });
        document.addEventListener('mouseover', () => {
            element.remove();
        });

        evt.dataTransfer.setDragImage(element, 0, 0);
    });
}