
console.log('imap_folders/site.js loaded');
'use strict';

var folder_page_folder_list = function(container, title, link_class, target, id_dest, subscription = false) {
    var id = $('#imap_server_folder').val();
    var folder_location = $('.'+container);
    $('li', folder_location).not('.'+title).remove();
    var folders = $('.folder_list .imap_'+id+'_').clone(false);
    folders.find('.manage_folders_li').remove();
    $('.imap_folder_link', folders).addClass(link_class).removeClass('imap_folder_link');
    folder_location.prepend(folders);
    folder_location.show();

    const link = document.querySelector('.'+link_class);
    const original_icon = link.querySelector('i');
    const original_icon_clone = original_icon.cloneNode(true);
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border text-info spinner-border-sm';
    spinner.setAttribute('role', 'status');
    spinner.setAttribute('id', 'imap-spinner');
    spinner.innerHTML = '<span class="sr-only"></span>';
    if (original_icon.parentNode === link) {
        link.replaceChild(spinner, original_icon);
    }

    var child_link_target = folder_location.find('a.'+link_class).data('target');
    $('.' + link_class, folder_location).on("click", function () { return expand_folders_page_list($(this).data('target'), container, link_class, target, id_dest, subscription); });
    $('a', folder_location).not('.'+link_class).not('.close').off('click');
    $('a', folder_location).not('.'+link_class).not('.close').on("click", function() { set_folders_page_value($(this).data('id'), container, target, id_dest); return false; });
    $('.close', folder_location).on("click", function() {
        folders.remove();
        folder_location.hide();
        $('.'+target).html('');
        $('#'+id_dest).val('');
        return false;
    });
    expand_folders_page_list(child_link_target, container, link_class, target, id_dest, subscription, original_icon_clone, link);
    return false;
};

var expand_folders_page_list = function(path, container, link_class, target, id_dest, lsub, original_icon, parent_icon_link) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+Hm_Utils.clean_selector(detail.folder), $('.'+container));
    if ($('li', list).length === 0) {
        $('.expand_link', list).html('<i class="bi bi-file-minus-fill"></i>');
        if (detail) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder},
                {'name': 'subscription_state', 'value': lsub},
                {'name': 'count_children', 'value': id_dest === 'delete_source'}],
                function(res) {
                    if (res.imap_expanded_folder_path) {
                        var folder_location = $('.'+container);
                        var folders = $(res.imap_expanded_folder_formatted);
                        folders.find('.manage_folders_li').remove();
                        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), folder_location).append(folders);
                        $('.imap_folder_link', folder_location).addClass(link_class).removeClass('imap_folder_link');
                        $('.'+link_class, folder_location).off('click');
                        $('.'+link_class, folder_location).on("click", function() { return expand_folders_page_list($(this).data('target'), container, link_class, target, id_dest, lsub); });
                        $('a', folder_location).not('.'+link_class).not('.close').off('click');
                        $('a', folder_location).not('.'+link_class).not('.close').on("click", function() { set_folders_page_value($(this).data('id'), container, target, id_dest); return false; });
                        if (lsub) {
                            $('.folder_subscription').on("change", function() { folder_subscribe(this.id, $('#'+this.id).is(':checked')); return false; });
                        }
                    }
                    if(original_icon != null && parent_icon_link != null) {
                        const spinner_element = document.getElementById('imap-spinner');
                        if (spinner_element && spinner_element.parentNode === parent_icon_link) {
                            parent_icon_link.replaceChild(original_icon, spinner_element);
                        }
                    }
                }
            );
        }
    }
    else {
        $('.expand_link', list).html('<i class="bi bi-plus-circle-fill"></i>');
        $('ul', list).remove();
    }
    return false;
};

var set_folders_page_value = function(id, container, target, id_dest) {
    var list = $('.'+container);
    var list_item = $('.'+Hm_Utils.clean_selector(id), list);
    var link = $('a', list_item).not('.expand_link').first().text();
    if (! link) {
        link = $('a', list_item).eq(1).text();
    }
    $('.'+target).html(link);
    $('#'+id_dest).val(id);
    if (id_dest === 'delete_source') {
        const folder = document.querySelector(`.${id}`);
        if (folder) {
            const numberChildren = folder.getAttribute('data-number-children');
            $('#children_number').val(numberChildren);
        } else {
            $('#children_number').val(0);
        }
    }
    list.hide();
};

var esc_html = function(str) {
    return $('<div>').text(str).html();
};

var assign_special_folder = function(id, folder, type, callback) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_special_folder'},
        {'name': 'imap_server_id', 'value': id},
        {'name': 'special_folder_type', 'value': type},
        {'name': 'folder', 'value': folder}],
        callback
    );
};

var clear_special_folder = function(type, server_id, callback) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_clear_special_folder'},
        {'name': 'imap_server_id', 'value': server_id},
        {'name': 'special_folder_type', 'value': type}],
        callback
    );
};

var folder_subscribe = function(name, state) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_subscription'},
        {'name': 'subscription_state', 'value': state},
        {'name': 'folder', 'value': name}],
        function(res) {
            var el = $('#'+name);
            if (!res.imap_folder_subscription) {
                el.prop('checked', !el.prop('checked'));
            } else {
                el.prev().toggleClass('folder-disabled');
                Hm_Folders.reload_folders(true);
            }
        }
    );
};

/* ===== New Account-based Folders Page ===== */

var load_account_folders = function(server_id, block) {
    console.log('load_account_folders called for', server_id, block);
    var spinner = block.find('.folder_loading_spinner');
    var table = block.find('.folder_table');
    var tbody = block.find('.folder_table_body');
    var badge = block.find('.folder-count-badge');

    spinner.show();
    tbody.empty();
    table.hide();

    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folders_list_all'},
        {'name': 'imap_server_id', 'value': server_id}],
        function(res) {
            spinner.hide();
            if (res.imap_folder_list_all) {
                var folders = JSON.parse(res.imap_folder_list_all);
                badge.text(folders.length + ' folders').show();
                render_folder_table(folders, tbody, server_id);
                table.show();
            }
        }
    );
};

var get_special_icon = function(type) {
    var icons = {
        'trash': 'bi-trash3',
        'sent': 'bi-send-check-fill',
        'archive': 'bi-archive',
        'draft': 'bi-pencil-square',
        'junk': 'bi-envelope-x-fill'
    };
    return icons[type] || '';
};

var get_special_label = function(type) {
    var labels = {
        'trash': 'Trash',
        'sent': 'Sent',
        'archive': 'Archive',
        'draft': 'Draft',
        'junk': 'Junk'
    };
    return labels[type] || '';
};

var get_special_badge_class = function(type) {
    var classes = {
        'trash': 'badge-role-trash',
        'sent': 'badge-role-sent',
        'archive': 'badge-role-archive',
        'draft': 'badge-role-draft',
        'junk': 'badge-role-junk'
    };
    return classes[type] || 'bg-secondary';
};

var render_folder_table = function(folders, tbody, server_id) {
    console.log('Folders for account', server_id, folders);
    tbody.empty();
    folders.forEach(function(folder) {
        if (folder.noselect) return;

        var specialBadge = '';
        if (folder.special) {
            specialBadge = '<span class="badge-role ' + get_special_badge_class(folder.special) + '"><i class="bi ' + get_special_icon(folder.special) + ' me-1"></i>' + hm_trans(get_special_label(folder.special)) + '</span>';
        }

        var indent = 0;
        var name = folder.name || '';
        var parts = name.split('/');
        if (parts.length <= 1) {
            parts = name.split('.');
        }
        indent = parts.length - 1;

        var displayName = folder.basename;
        var indentPx = indent * 20;

        var row = '<tr data-folder-hex="' + folder.hex_name + '" data-folder-name="' + esc_html(folder.basename) + '" data-server-id="' + server_id + '">';
        row += '<td style="padding-left:' + (indentPx + 8) + 'px"><i class="bi bi-folder2 me-1"></i>' + esc_html(displayName) + '</td>';
        row += '<td>' + specialBadge + '</td>';
        row += '<td class="text-end">';
        row += '<div class="btn-group btn-group-sm" role="group">';
        row += '<button class="btn btn-outline-primary btn-sm folder_rename_btn" title="' + hm_trans('Rename') + '"><i class="bi bi-pencil"></i></button>';
        row += '<button class="btn btn-outline-danger btn-sm folder_delete_btn" title="' + hm_trans('Delete') + '"><i class="bi bi-trash"></i></button>';

        var roleButtons = [
            {type: 'trash', icon: 'bi-trash3', label: 'Trash'},
            {type: 'archive', icon: 'bi-archive', label: 'Archive'},
            {type: 'draft', icon: 'bi-pencil-square', label: 'Draft'},
            {type: 'junk', icon: 'bi-envelope-x-fill', label: 'Junk'},
            {type: 'sent', icon: 'bi-send-check-fill', label: 'Sent'}
        ];

        row += '<div class="btn-group btn-group-sm dropdown" role="group">';
        row += '<button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="' + hm_trans('Set as...') + '"><i class="bi bi-tag"></i></button>';
        row += '<ul class="dropdown-menu dropdown-menu-end">';
        roleButtons.forEach(function(rb) {
            var active = folder.special === rb.type ? ' active' : '';
            row += '<li><a class="dropdown-item set_special_btn' + active + '" href="#" data-type="' + rb.type + '"><i class="bi ' + rb.icon + ' me-2"></i>' + hm_trans(rb.label);
            if (folder.special === rb.type) {
                row += ' <i class="bi bi-check-lg ms-2"></i>';
            }
            row += '</a></li>';
        });
        row += '<li><hr class="dropdown-divider"></li>';
        row += '<li><a class="dropdown-item clear_special_btn" href="#"><i class="bi bi-x-circle me-2"></i>' + hm_trans('Clear role') + '</a></li>';
        row += '</ul></div>';

        row += '</div></td></tr>';
        tbody.append(row);
    });

    bind_folder_table_actions(tbody, server_id);
};

var bind_folder_table_actions = function(tbody, server_id) {
    tbody.find('.folder_rename_btn').off('click').on('click', function() {
        var tr = $(this).closest('tr');
        var folderHex = tr.data('folder-hex');
        var folderName = tr.data('folder-name');
        var fullFolderName = 'imap_' + server_id + '_' + folderHex;
        show_rename_modal(server_id, fullFolderName, folderName, tr);
        return false;
    });

    tbody.find('.folder_delete_btn').off('click').on('click', function() {
        var tr = $(this).closest('tr');
        var folderHex = tr.data('folder-hex');
        var folderName = tr.data('folder-name');
        var fullFolderName = 'imap_' + server_id + '_' + folderHex;
        show_delete_modal(server_id, fullFolderName, folderName, tr);
        return false;
    });

    tbody.find('.set_special_btn').off('click').on('click', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var folderHex = tr.data('folder-hex');
        var type = $(this).data('type');
        var block = tr.closest('.account_folder_block');
        var folderName = 'imap_' + server_id + '_' + folderHex;
        assign_special_folder(server_id, folderName, type, function() {
            load_account_folders(server_id, block);
        });
    });

    tbody.find('.clear_special_btn').off('click').on('click', function(e) {
        e.preventDefault();
        var tr = $(this).closest('tr');
        var folderHex = tr.data('folder-hex');
        var block = tr.closest('.account_folder_block');
        var currentBadge = tr.find('.badge-role');
        if (currentBadge.length === 0) return;
        var activeBtn = tr.find('.set_special_btn.active');
        if (activeBtn.length === 0) return;
        var type = activeBtn.data('type');
        clear_special_folder(type, server_id, function() {
            load_account_folders(server_id, block);
        });
    });
};

var show_rename_modal = function(server_id, folderHex, folderName, tr) {
    var modal = new Hm_Modal({
        modalId: 'renameFolderModal',
        title: hm_trans('Rename Folder'),
        btnSize: 'sm'
    });

    var content = '<div class="mb-3">';
    content += '<label class="form-label">' + hm_trans('Current name') + ': <strong>' + esc_html(folderName) + '</strong></label>';
    content += '</div>';
    content += '<div class="form-floating mb-3">';
    content += '<input type="text" class="form-control" id="modal_rename_value" placeholder="' + hm_trans('New Folder Name') + '">';
    content += '<label for="modal_rename_value">' + hm_trans('New Folder Name') + '</label>';
    content += '</div>';

    modal.setContent(content);
    modal.addFooterBtn(hm_trans('Rename'), 'btn-primary', function() {
        var newName = $('#modal_rename_value').val().trim();
        if (!newName.length) {
            Hm_Notices.show($('#rename_folder_error').val(), 'danger');
            return;
        }
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folders_rename'},
            {'name': 'imap_server_id', 'value': server_id},
            {'name': 'folder', 'value': folderHex},
            {'name': 'new_folder', 'value': newName}],
            function(res) {
                if (res.imap_folders_success) {
                    modal.hide();
                    var block = tr.closest('.account_folder_block');
                    load_account_folders(server_id, block);
                    Hm_Folders.reload_folders(true);
                }
            }
        );
    });
    modal.open();
};

var show_delete_modal = function(server_id, folderHex, folderName, tr) {
    var modal = new Hm_Modal({
        modalId: 'deleteFolderModal',
        title: hm_trans('Delete Folder'),
        btnSize: 'sm'
    });

    var content = '<p>' + hm_trans('Are you sure you want to delete this folder, and all the messages in it?') + '</p>';
    content += '<p><strong>' + esc_html(folderName) + '</strong></p>';

    modal.setContent(content);
    modal.addFooterBtn(hm_trans('Delete'), 'btn-danger', function() {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folders_delete'},
            {'name': 'imap_server_id', 'value': server_id},
            {'name': 'folder', 'value': folderHex}],
            function(res) {
                if (res.imap_folders_success) {
                    modal.hide();
                    var block = tr.closest('.account_folder_block');
                    load_account_folders(server_id, block);
                    Hm_Folders.reload_folders(true);
                }
            }
        );
    });
    modal.open();
};

var show_create_folder_modal = function(server_id, block) {
    var modal = new Hm_Modal({
        modalId: 'createFolderModal',
        title: hm_trans('Create a New Folder'),
        btnSize: 'sm'
    });

    var content = '<div class="form-floating mb-3">';
    content += '<input type="text" class="form-control" id="modal_create_value" placeholder="' + hm_trans('New Folder Name') + '">';
    content += '<label for="modal_create_value">' + hm_trans('New Folder Name') + '</label>';
    content += '</div>';

    modal.setContent(content);
    modal.addFooterBtn(hm_trans('Create'), 'btn-primary', function() {
        var folder = $('#modal_create_value').val().trim();
        if (!folder.length) {
            Hm_Notices.show($('#folder_name_error').val(), 'danger');
            return;
        }
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folders_create'},
            {'name': 'imap_server_id', 'value': server_id},
            {'name': 'folder', 'value': folder}],
            function(res) {
                if (res.imap_folders_success) {
                    modal.hide();
                    load_account_folders(server_id, block);
                    Hm_Folders.reload_folders(true);
                }
            }
        );
    });
    modal.open();
};

function bindFoldersEventHandlers() {
    // Account expand/collapse
    $('.account_folder_header').on('click', function() {
        var block = $(this).closest('.account_folder_block');
        var body = block.find('.account_folder_body');
        var icon = block.find('.account_expand_icon');
        var server_id = block.data('server-id');
        var visible = body.css('display') !== 'none';

        if (visible) {
            body.css('display', 'none');
            icon.removeClass('bi-chevron-down').addClass('bi-chevron-right');
        } else {
            body.css('display', '');
            icon.removeClass('bi-chevron-right').addClass('bi-chevron-down');
            // Load folders if table is empty
            if (block.find('.folder_table_body tr').length === 0) {
                load_account_folders(server_id, block);
            }
        }
    });

    // Create folder button
    $('.create_folder_btn').on('click', function() {
        var server_id = $(this).data('server-id');
        var block = $(this).closest('.account_folder_block');
        show_create_folder_modal(server_id, block);
        return false;
    });
}
