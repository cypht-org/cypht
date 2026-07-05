var get_tags_json_data = function() {
    var el = document.querySelector('.tags_json_data');
    if (!el) {
        return [];
    }
    try {
        return JSON.parse(el.textContent);
    } catch (e) {
        return [];
    }
};

var build_tag_parent_options = function(selectedParent, excludeId) {
    var tags = get_tags_json_data();
    var html = '<option value="">' + hm_trans('No parent (top level)') + '</option>';
    tags.forEach(function(t) {
        if (excludeId && t.id === excludeId) {
            return;
        }
        var indent = '&nbsp;&nbsp;&nbsp;&nbsp;'.repeat(t.depth);
        var selected = (selectedParent && t.id === selectedParent) ? ' selected' : '';
        html += '<option value="' + esc_html(t.id) + '"' + selected + '>' + indent + esc_html(t.name) + '</option>';
    });
    return html;
};


var apply_modern_modal_skin = function(modal) {
    modal.modal.find('.modal-dialog').addClass('modal-dialog-centered');
    modal.modal.find('.modal-content').addClass('custom-modal-content');
    modal.modal.find('.modal-header').addClass('custom-modal-header');
    modal.modal.find('.modal-title').addClass('d-flex align-items-center');
    modal.modal.find('.modal-body').addClass('custom-modal-body');
    modal.modal.find('.modal-footer').addClass('custom-modal-footer');
    modal.modal.find('.modal-footer .btn-secondary').addClass('custom-btn-secondary');
};

var tag_modal_icon = function(icon, danger) {
    return '<div class="modal-icon-wrapper' + (danger ? ' danger' : '') + ' me-2"><i class="bi ' + icon + '" style="font-size: 22px;"></i></div>';
};

var show_tag_form_modal = function(tag) {
    tag = tag || {};
    var isEdit = !!tag.id;
    var modal = new Hm_Modal({
        modalId: 'tagFormModal',
        title: tag_modal_icon('bi-tag-fill') + (isEdit ? hm_trans('Edit label') : hm_trans('Create new label'))
    });
    apply_modern_modal_skin(modal);

    var content = '<div class="mb-3">';
    content += '<label for="modal_tag_name" class="form-label">' + hm_trans('Label name') + ' <span class="text-danger">*</span></label>';
    content += '<input required type="text" class="form-control custom-input" id="modal_tag_name" placeholder="' + hm_trans('e.g. Invoices') + '" value="' + esc_html(tag.name || '') + '">';
    content += '</div>';
    content += '<div class="form-check mb-3">';
    content += '<input class="form-check-input" type="checkbox" id="modal_tag_nest"' + (tag.parent ? ' checked' : '') + '>';
    content += '<label class="form-check-label" for="modal_tag_nest">' + hm_trans('Nest label under') + '</label>';
    content += '</div>';
    content += '<div class="mb-1" id="modal_tag_parent_wrapper" style="display:' + (tag.parent ? 'block' : 'none') + '">';
    content += '<label for="modal_tag_parent" class="form-label">' + hm_trans('Parent label') + '</label>';
    content += '<select class="form-select custom-input" id="modal_tag_parent">' + build_tag_parent_options(tag.parent, tag.id) + '</select>';
    content += '</div>';

    modal.setContent(content);
    modal.modal.on('shown.bs.modal', function() {
        var input = document.getElementById('modal_tag_name');
        if (input) { input.focus(); input.select(); }
    });
    modal.modal.on('change', '#modal_tag_nest', function() {
        $('#modal_tag_parent_wrapper').css('display', this.checked ? 'block' : 'none');
    });

    var submitLabel = isEdit ? hm_trans('Save') : hm_trans('Create');
    modal.addFooterBtn(submitLabel, 'btn-primary custom-btn-primary', function() {
        var name = $('#modal_tag_name').val().trim();
        if (!name.length) {
            Hm_Notices.show('Please enter a label name', 'danger');
            return;
        }
        var parent = $('#modal_tag_nest').is(':checked') ? $('#modal_tag_parent').val() : '';
        var btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + submitLabel);
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_process_tag_update'},
            {'name': 'tag_name', 'value': name},
            {'name': 'parent_tag', 'value': parent},
            {'name': 'tag_id', 'value': isEdit ? tag.id : ''}],
            function(res) {
                if (res.tag_success) {
                    modal.hide();
                    Hm_Folders.reload_folders(true);
                } else {
                    btn.prop('disabled', false).html(submitLabel);
                }
            }
        );
    });
    modal.open();
};

var show_tag_delete_modal = function(tag) {
    var modal = new Hm_Modal({
        modalId: 'tagDeleteModal',
        title: tag_modal_icon('bi-trash', true) + hm_trans('Remove label')
    });
    apply_modern_modal_skin(modal);

    var content = '<p>' + hm_trans('Are you sure you want to remove this label? Messages will keep their content but lose this label.') + '</p>';
    content += '<p><strong>' + esc_html(tag.name) + '</strong></p>';

    modal.setContent(content);
    modal.addFooterBtn(hm_trans('Remove'), 'btn-danger custom-btn-danger', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + hm_trans('Remove'));
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_process_tag_delete'},
            {'name': 'tag_delete', 'value': 1},
            {'name': 'tag_id', 'value': tag.id}],
            function(res) {
                if (res.tag_success) {
                    modal.hide();
                    Hm_Folders.reload_folders(true);
                } else {
                    btn.prop('disabled', false).html(hm_trans('Remove'));
                }
            }
        );
    });
    modal.open();
};

$(document).on('click', '.tag_add_new_btn', function(e) {
    e.preventDefault();
    show_tag_form_modal();
});

$(document).on('click', '.tag_action_add_child', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var li = $(this).closest('.tag_row');
    show_tag_form_modal({parent: String(li.data('tag-id'))});
});

$(document).on('click', '.tag_action_edit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var li = $(this).closest('.tag_row');
    show_tag_form_modal({
        id: String(li.data('tag-id')),
        name: li.data('tag-name'),
        parent: li.data('tag-parent') ? String(li.data('tag-parent')) : ''
    });
});

$(document).on('click', '.tag_action_delete', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var li = $(this).closest('.tag_row');
    show_tag_delete_modal({id: String(li.data('tag-id')), name: li.data('tag-name')});
});

$(document).on('click', '.tag_expand_toggle', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).closest('.tag_row').toggleClass('tag_collapsed');
});
