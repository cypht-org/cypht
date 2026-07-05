/**
 * Gmail-style label management: create/edit/delete tags from modals
 * triggered directly from the folder menu, instead of a dedicated page.
 */

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

var show_tag_form_modal = function(tag) {
    tag = tag || {};
    var isEdit = !!tag.id;
    var modal = new Hm_Modal({
        modalId: 'tagFormModal',
        title: isEdit ? hm_trans('Edit label') : hm_trans('Create new label'),
        btnSize: 'sm'
    });

    var content = '<div class="form-floating mb-3">';
    content += '<input required type="text" class="form-control" id="modal_tag_name" placeholder="' + hm_trans('Label name') + '" value="' + esc_html(tag.name || '') + '">';
    content += '<label for="modal_tag_name">' + hm_trans('Label name') + '</label>';
    content += '</div>';
    content += '<div class="form-check mb-3">';
    content += '<input class="form-check-input" type="checkbox" id="modal_tag_nest"' + (tag.parent ? ' checked' : '') + '>';
    content += '<label class="form-check-label" for="modal_tag_nest">' + hm_trans('Nest label under') + '</label>';
    content += '</div>';
    content += '<div class="form-floating mb-3" id="modal_tag_parent_wrapper" style="display:' + (tag.parent ? 'block' : 'none') + '">';
    content += '<select class="form-select" id="modal_tag_parent">' + build_tag_parent_options(tag.parent, tag.id) + '</select>';
    content += '<label for="modal_tag_parent">' + hm_trans('Parent label') + '</label>';
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
    modal.addFooterBtn(submitLabel, 'btn-primary', function() {
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
        title: hm_trans('Remove label'),
        btnSize: 'sm'
    });

    var content = '<p>' + hm_trans('Are you sure you want to remove this label? Messages will keep their content but lose this label.') + '</p>';
    content += '<p><strong>' + esc_html(tag.name) + '</strong></p>';

    modal.setContent(content);
    modal.addFooterBtn(hm_trans('Remove'), 'btn-danger', function() {
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
