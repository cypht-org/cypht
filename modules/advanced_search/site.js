"use strict"

var add_remove_terms = function(el) {
    var close = $(globals.close_html);
    var count = $('.adv_terms').length;
    var term = $('#adv_term').clone(false);
    var not_chk = $('<span id="adv_term_not" class="adv_term_nots"><input type="checkbox" class="form-check-input" value="not" id="adv_term_not" /> !</span>');
    var and_or_html = '<div class="andor px-4"><input class="form-check-input" checked="checked" type="radio" name="term_and'
    and_or_html += '_or'+count+'" value="and">and <input class="form-check-input" type="radio" name="term_and_or'+count;
    and_or_html += '" value="or">or</div>';
    var and_or = $(and_or_html);
    term.attr('id', 'adv_term'+count);
    close.attr('id', 'term_adv_remove'+count);
    close.addClass('ms-2');
    and_or.attr('id', 'term_and_or'+count);
    not_chk.attr('id', 'adv_term_not'+count);
    $(el).prev().after(and_or.prop('outerHTML')+not_chk.prop('outerHTML')+term.prop('outerHTML')+close.prop('outerHTML'));
    $(el).hide();
    $('#term_adv_remove'+count).on("click", function() {
        $('#adv_term'+count).remove();
        $('#adv_term_not'+count).remove();
        $('#term_and_or'+count).remove();
        $(this).remove();
        $(el).show();
    });
};

var add_remove_times = function(el) {
    var close = $(globals.close_html);
    var count = $('.adv_times').length;
    var time_html = '<span id="adv_time" class="adv_times d-flex align-items-center gap-2">From <input class="adv_time_fld_from form-control w-auto" ';
    time_html += 'type="date" value=""> To <input class="adv_time_fld_to form-control w-auto" type="date" value=""></span>';
    var timeset = $(time_html);
    var and_or_html = '<div class="timeandor"><input class="form-check-input" type="radio" name="time_and_or'+count;
    and_or_html += '" checked="checked" value="or">or</div>';
    var and_or = $(and_or_html);
    timeset.attr('id', 'adv_time'+count);
    close.attr('id', 'time_adv_remove'+count);
    close.addClass('me-2');
    and_or.attr('id', 'time_and_or'+count);
    $(el).prev().after(and_or.prop('outerHTML')+timeset.prop('outerHTML')+close.prop('outerHTML'));
    $('#time_adv_remove'+count).on("click", function() {
        $('#adv_time'+count).remove();
        $('#time_and_or'+count).remove();
        $(this).remove();
    });
};

var add_remove_targets = function(el) {
    var close = $(globals.close_html);
    var count = $('.adv_targets').length;
    var target = $('#adv_target').clone(false);
    var and_or_html = '<div class="andor"><input class="form-check-input" type="radio" name="target_and_or'+count;
    and_or_html += '" value="and">and <input class="form-check-input" type="radio" name="target_and_or'+count;
    and_or_html += '" checked="checked" value="or">or</div>';
    var and_or = $(and_or_html);

    target.attr('id', 'adv_target'+count);
    $('.target_radio', target).attr('name', 'target_type'+count);
    $('.target_radio', target).removeAttr('checked');
    close.attr('id', 'target_adv_remove'+count);
    close.addClass('ms-2');
    and_or.attr('id', 'target_and_or'+count);
    $(el).prev().after(and_or.prop('outerHTML')+target.prop('outerHTML')+close.prop('outerHTML'));
    $(el).hide();
    $('#target_adv_remove'+count).on("click", function() {
        $('#adv_target'+count).remove();
        $('#target_and_or'+count).remove();
        $(this).remove();
        $(el).show();
    });
};

var expand_adv_folder = function(res) {
    if (res.imap_expanded_folder_path) {
        var list_container = $('.adv_folder_list');
        var folders = $(res.imap_expanded_folder_formatted);
        folders.find('.manage_folders_li').remove();
        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), $('.adv_folder_list')).append(folders);
        $('.imap_folder_link', list_container).addClass('adv_folder_link').removeClass('imap_folder_link');
        $('.adv_folder_link', list_container).off('click');
        $('.adv_folder_link', list_container).on("click", function() { return expand_adv_folder_list($(this).data('target')); });
        $('a', list_container).not('.adv_folder_link').off('click');
        $('a', list_container).not('.adv_folder_link').on("click", function() { adv_folder_select($(this).data('id')); return false; });
        modifyInnerLists();
    }
};

$(document).on("change", "input[name='all_folders'],input[name='all_special_folders']", function() {
    const folderLi = $(this).closest('li');
    const divergentCheckboxName = this.name === 'all_folders' ? 'all_special_folders' : 'all_folders';
    const divergentCheckbox = $(this).closest('div').find(`input[name='${divergentCheckboxName}']`)
    
    if ($(this).is(':checked')) {
        folderLi.find('a').attr('disabled', 'disabled');
        divergentCheckbox.prop('checked', false);
        divergentCheckbox.attr('disabled', 'disabled');
    } else {
        folderLi.find('a').removeAttr('disabled');
        divergentCheckbox.removeAttr('disabled');
    }
});

var adv_select_imap_folder = function(el) {
    var close = $(globals.close_html);
    close.addClass('close_adv_folders ms-2');
    var list_container = $('.adv_folder_list');
    var folders = $('.email_folders').clone(false);
    folders.find('.manage_folders_li').remove();
    $('.menu_email', folders).remove();
    folders.removeClass('email_folders');
    $(el).after(close);
    list_container.show();
    folders.show();

    folders.find('li').each(function(index) {
        const wrapper = $('<div class="d-flex justify-content-between wrapper"></div>');
        $(this).wrapInner(wrapper);
        const allSpecialFoldersCheckbox = `
        <span class="form-check">
            <label class="form-check-label" for="all_special_folders-${index}">All special folders</label>
            <input class="form-check-input" type="checkbox" name="all_special_folders" id="all_special_folders-${index}">
        </span>
        `;
        const allFoldersCheckbox = `
        <span class="form-check">
            <label class="form-check-label" for="all_folders-${index}">All folders</label>
            <input class="form-check-input" type="checkbox" name="all_folders" id="all_folders-${index}">
        </span>
        `;
        const checkboxesWrapper = $('<div class="d-flex gap-3"></div>');
        checkboxesWrapper.append(allSpecialFoldersCheckbox);
        checkboxesWrapper.append(allFoldersCheckbox);
        $(this).find('.wrapper').append(checkboxesWrapper);
    });

    modifyInnerLists();

    $('.imap_folder_link', folders).addClass('adv_folder_link').removeClass('imap_folder_link');
    $('.adv_folder_list').html(folders.html());

    $('.adv_folder_link', list_container).on("click", function() { return expand_adv_folder_list($(this).data('target')); });
    $('a', list_container).not('.adv_folder_link').not('.close_adv_folders').off('click');
    $('a', list_container).not('.adv_folder_link').not('.close_adv_folders').on("click", function() { adv_folder_select($(this).data('id')); return false; });
    $('.close_adv_folders').on("click", function() {
        $('.adv_folder_list').html('');
        $('.adv_folder_list').hide();
        $(this).remove();
        return false;
    });
};

function modifyInnerLists() {
    $('.adv_folder_list').find('.inner_list li').each(function(index) {
        const subFoldersCheckbox = `
        <span class="form-check form-text">
            <label class="form-check-label" for="include_subfolders-${index}">Include subfolders</label>
            <input class="form-check-input" type="checkbox" name="include_subfolders" id="include_subfolders-${index}">
        </span>
        `;
        $(this).wrapInner('<div class="d-flex wrapper"></div>');
        $(this).find('.wrapper').append(subFoldersCheckbox);
        $(this).find('#main-link').css('flex-grow', 0)
    });
}

var adv_folder_select = function(id) {
    if ($('.'+id, $('.adv_source_list')).length > 0) {
        $('.adv_folder_list').html('');
        $('.close_adv_folders').remove();
        $('.adv_folder_list').hide();
        return;
    }
    var container = $('.adv_folder_list');
    var list_item = $('.'+Hm_Utils.clean_selector(id));
    var folder = $('a', list_item).first().text();
    if (folder == '+' || folder == '-') {
        folder = $('a', list_item).eq(1).text();
    }
    var parts = id.split('_', 3);
    var parent_class = '.'+parts[0]+'_'+parts[1]+'_';
    var account = $('a', $(parent_class, container)).first().text();
    var label = account+' &gt; '+folder;
    const includeSubfolders = $(`.${id}`).closest('li').find('input[name="include_subfolders"]').is(':checked');
    
    add_source_to_list(id, label, includeSubfolders);
    $('.adv_folder_list').html('');
    $('.close_adv_folders').remove();
    $('.adv_folder_list').hide();
};

var add_source_to_list = function(id, label, includeSubfolders) {
    var close = $(globals.close_html);
    close.addClass('adv_remove_source');
    close.attr('data-target', id);
    var row = '<div class="'+id+'" data-subfolders="'+includeSubfolders+'">'+close.prop('outerHTML')+label;
    row += '<input type="hidden" value="'+id+'" /></div>';
    $('.adv_source_list').append(row);
    $('.adv_remove_source').off('click');
    $('.adv_remove_source').on("click", function() {
        $('.'+$(this).data('target'), $('.adv_source_list')).remove();
    });
};

var expand_adv_folder_list = function(path) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+Hm_Utils.clean_selector(detail.folder), $('.adv_folder_list'));
    if ($('li', list).length === 0) {
        $('.expand_link', list).html('<i class="bi bi-file-minus-fill"></i>');
        if (detail) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                function (res) { expand_adv_folder(res); }
            );
        }
    }
    else {
        $('.expand_link', list).html('+');
        $('ul', list).remove();
    }
    return false;
};

var adv_collapse = function() {
    $('.terms_section').hide();
    $('.source_section').hide();
    $('.targets_section').hide();
    $('.time_section').hide();
    $('.other_section').hide();
    $('.adv_expand_all').show();
    $('.adv_collapse_all').hide();
}

var adv_expand_sections = function() {
    $('.terms_section').show();
    $('.source_section').show();
    $('.targets_section').show();
    $('.time_section').show();
    $('.other_section').show();
    $('.adv_expand_all').hide();
    $('.adv_collapse_all').show();
}

var get_adv_sources = function() {
    const sources = [];

    const searchInAllFolders = $('.adv_folder_list li input[name="all_folders"]:checked');
    searchInAllFolders.each(function() {
        const li = $(this).closest('li');
        sources.push({'source': li.attr('class'), 'label': li.find('a').text(), allFolders: true});
    });

    const searchInSpecialFolders = $('.adv_folder_list li input[name="all_special_folders"]:checked');
    searchInSpecialFolders.each(function() {
        const li = $(this).closest('li');
        sources.push({'source': li.attr('class'), 'label': li.find('a').text(), specialFolders: true});
    });
    
    const selected_sources = $('div', $('.adv_source_list'));
    if (!selected_sources) {
        return sources;
    }
    selected_sources.each(function() {
        const source = this.className;
        const mailboxSource = source.split('_').slice(0, 2).join('_');
        if (!sources.find(s => s.source.indexOf(mailboxSource) > -1)) {
            sources.push({'source': source, 'label': $('a', $(this)).text(), subFolders: $(this).data('subfolders')});
        }
    });
    return sources;
};

var get_adv_terms = function() {
    var term;
    var term_id;
    var condition;
    var not;
    var terms = [];
    var term_flds = $('.adv_terms');
    term_flds.each(function() {
        term = $(this).val();
        if (term && term.trim()) {
            term_id = this.id.substr(8);
            if (term_id) {
                condition = $('input:checked', $('#term_and_or'+term_id)).val();
            }
            else {
                condition = false;
            }
            if ($('input:checked', $('#adv_term_not'+term_id)).val() == 'not') {
                term = 'NOT '+term;
            }
            terms.push({'term': term, 'condition': condition});
        }
    });
    return terms;
};

var get_adv_times = function() {
    var time;
    var from;
    var to;
    var times = [];
    var time_flds = $('.adv_times');
    time_flds.each(function() {
        from = $('.adv_time_fld_from', $(this)).val();
        to = $('.adv_time_fld_to', $(this)).val();
        if (to && from && to.trim() && from.trim()) {
            times.push({'from': from, 'to': to});
        }
    });
    return times;

};

var get_adv_targets = function() {
    var target;
    var value;
    var target_id;
    var condition;
    var targets = [];
    var target_flds = $('.adv_targets');
    target_flds.each(function() {
        target = $('.target_radio:checked', $(this)).val();
        if (target == 'header') {
            value = $('.adv_header_select', $(this)).val();
        }
        else if (target == 'custom') {
            value = 'HEADER '+$('.adv_custom_header', $(this)).val();
        }
        else {
            value = target;
        }
        if (target) {
            target_id = this.id.substr(10);
            if (target_id) {
                condition = $('input:checked', $('#target_and_or'+target_id)).val();
            }
            else {
                condition = false;
            }
            targets.push({'target': value, 'orig': target, 'condition': condition});
        }
    });
    return targets;
};

var get_adv_other = function() {
    var charset = $('.charset').val();
    var flags = [];
    var flag_flds = $('.adv_flag:checked');
    if (flag_flds) {
        flag_flds.each(function() {
            flags.push($(this).val());
        });
    }
    var limit = $('.adv_source_limit').val();
    return {'limit': limit, 'flags': flags, 'charset': charset};
};

var process_advanced_search = function() {
    var terms = get_adv_terms();
    if (terms.length == 0) {
        Hm_Notices.show('You must enter at least one search term', 'warning');
        return;
    }
    const sources = get_adv_sources();
    if (sources.length == 0) {
        Hm_Notices.show('You must select at least one source', 'warning');
        return;
    }
    var targets = get_adv_targets();
    if (targets.length == 0) {
        Hm_Notices.show('You must have at least one target', 'warning');
        return;
    }
    var times = get_adv_times();
    if (times.length == 0) {
        Hm_Notices.show('You must enter at least one time range', 'warning');
        return;
    }
    var other = get_adv_other();

    save_search_details(terms, sources, targets, times, other);
    search_summary({ 'terms': terms, 'targets': targets, 'sources': sources,
            'times': times, 'other': other });

    send_requests(build_adv_search_requests(terms, sources, targets, times, other));
};

var save_search_details = function(terms, sources, targets, times, other) {
    Hm_Utils.save_to_local_storage('adv_search_params',
        Hm_Utils.json_encode({
            'terms': terms,
            'targets': targets,
            'sources': sources,
            'times': times,
            'other': other
        })
    );
};

var load_search_details = function() {
    return Hm_Utils.json_decode(Hm_Utils.get_from_local_storage('adv_search_params'));
};


var adv_group_vals = function(data, type) {
    var groups = [];
    if (data.length == 2 && data[1]['condition'] == 'or') {
        groups.push([data[0][type]]);
        groups.push([data[1][type]]);
    }
    else if (data.length == 2) {
        groups.push([data[0][type], data[1][type]]);
    }
    else {
        groups.push([data[0][type]]);
    }
    return groups;
};

var send_requests = function(requests) {
    var request;
    $('tr', Hm_Utils.tbody()).remove();
    Hm_Utils.save_to_local_storage('formatted_advanced_search_data', '');
    adv_collapse();
    $('.adv_controls').hide();
    $('.empty_list').remove();
    for (var n=0, rlen=requests.length; n < rlen; n++) {
        request = requests[n];
        var params = [
            {'name': 'hm_ajax_hook', 'value': 'ajax_adv_search'},
            {'name': 'adv_source', 'value': request['source']},
            {'name': 'adv_start', 'value': request['time']['from']},
            {'name': 'adv_end', 'value': request['time']['to']},
            {'name': 'adv_source_limit', 'value': request['other']['limit']},
            {'name': 'adv_charset', 'value': request['other']['charset']}
        ];

        if (request['all_folders']) {
            params.push({name: 'all_folders', value: true});
        } else if (request['all_special_folders']) {
            params.push({name: 'all_special_folders', value: true});
        } else if (request['sub_folders']) {
            params.push({name: 'include_subfolders', value: true});
        }

        for (var i=0, len=request['terms'].length; i < len; i++) {
            params.push({'name': 'adv_terms[]', 'value': request['terms'][i]});
        }
        for (var i=0, len=request['targets'].length; i < len; i++) {
            params.push({'name': 'adv_targets[]', 'value': request['targets'][i]});
        }
        for (var i=0, len=request['other']['flags'].length; i < len; i++) {
            params.push({'name': 'adv_flags[]', 'value': request['other']['flags'][i]});
        }
        Hm_Ajax.request(
            params,
            function(res) {
                // HACK. As we are sending multiple requests (each source a request), let's keep a snapshot of the last message list before updating the view
                let tableRows = Hm_Utils.rows();
                Hm_Message_List.update(res.formatted_message_list);
                if (Hm_Utils.rows().length > 0) {
                    $('.adv_controls').show();
                    $('.core_msg_control').off('click');
                    $('.core_msg_control').on("click", function() { return Hm_Message_List.message_action($(this).data('action')); });
                    if (typeof check_select_for_imap !== 'undefined') {
                        check_select_for_imap();
                    }
                }
                Hm_Message_List.check_empty_list();

                // prepend the previous message list
                if (n !== 0) {
                    Hm_Utils.tbody().prepend(tableRows);
                }
            },
            [],
            false,
            function() {
                Hm_Message_List.set_message_list_state('formatted_advanced_search_data');
            }
        );
    }
};

var build_adv_search_requests = function(terms, sources, targets, times, other) {
    var source;
    var time;
    var term_vals;
    var target_vals;
    var requests = []
    var term_groups = adv_group_vals(terms, 'term');
    var target_groups = adv_group_vals(targets, 'target');    

    for (var tv=0, tvlen=term_groups.length; tv < tvlen; tv++) {
        term_vals = term_groups[tv];
        for (var tag=0, taglen=target_groups.length; tag < taglen; tag++) {
            target_vals = target_groups[tag];
            for (var s=0, slen=sources.length; s < slen; s++) {
                source = sources[s];
                for (var ti=0, tilen=times.length; ti < tilen; ti++) {
                    time = times[ti];
                    const config = {'source': source.source, 'time': time, 'other': other,
                        'targets': target_vals, 'terms': term_vals};
                    if (source.allFolders) {
                        config['all_folders'] = true;
                    } else if (source.specialFolders) {
                        config['all_special_folders'] = true;
                    } else if (source.subFolders) {
                        config['sub_folders'] = true;
                    }
                    requests.push(config);
                }
            }
        }
    }    
    
    return requests;
};

var search_summary = function(details) {
    if (!details) {
        return;
    }
    var charset = 0;
    if (details['other']['charset']) { charset = 1; }
    $('.term_count').text($('.term_count').text().replace(/\d+/, details['terms'].length)).show();
    $('.target_count').text($('.target_count').text().replace(/\d+/, details['targets'].length)).show();
    $('.source_count').text($('.source_count').text().replace(/\d+/, details['sources'].length)).show();
    $('.time_count').text($('.time_count').text().replace(/\d+/, details['times'].length)).show();
    $('.other_count').text($('.other_count').text().replace(/\d+/, (charset + details['other']['flags'].length))).show();
};

var apply_saved_search = function() {
    var details = load_search_details();
    if (!details) {
        return;
    }
    search_summary(details);
    var target_id;
    var time_id;
    var not;
    for (var i=0, len=details['terms'].length; i < len; i++) {
        not = false;
        if (details['terms'][i]['term'].substring(0, 4) == 'NOT ') {
            details['terms'][i]['term'] = details['terms'][i]['term'].substring(4);
            not = true;
        }
        if (i == 0) {
            $('#adv_term').val(details['terms'][i]['term']);
            if (not) {
                $('input', $('#adv_term_not')).attr('checked', true);
            }
        }
        else {
            $('.new_term').trigger('click');
            $('#adv_term'+i).val(details['terms'][i]['term']);
            $('input[type=radio][value='+details['terms'][i]['condition']+']', $('#term_and_or'+i)).attr('checked', true);
            if (not) {
                $('input', $('#adv_term_not'+i)).attr('checked', true);
            }
        }
    }
    for (var i=0, len=details['sources'].length; i < len; i++) {
        add_source_to_list(details['sources'][i]['source'], details['sources'][i]['label']);
    }
    for (var i=0, len=details['targets'].length; i < len; i++) {
        if (i == 0) {
            target_id = '#adv_target';
        }
        else {
            target_id = '#adv_target'+i;
            $('.new_target').trigger('click');
            $('input[type=radio][value='+details['targets'][i]['condition']+']', $('#target_and_or'+i)).attr('checked', true);
        }
        $('input[type=radio][value='+details['targets'][i]['orig']+']', $(target_id)).attr('checked', true);
        if (details['targets'][i]['orig'] == 'custom') {
            $('.adv_custom_header', $(target_id)).val(details['targets'][i]['target'].substring(7));
        }
        else if (details['targets'][i]['orig'] == 'header') {
            $('.adv_header_select', $(target_id)).val(details['targets'][i]['target']);
        }
    }
    for (var i=0, len=details['times'].length; i < len; i++) {
        if (i == 0) {
            time_id = '#adv_time';
        }
        else {
            time_id = '#adv_time'+i;
            $('.new_time').trigger('click');
        }
        $('.adv_time_fld_from', $(time_id)).val(details['times'][i]['from']);
        $('.adv_time_fld_to', $(time_id)).val(details['times'][i]['to']);
    }
    $('.charset').val(details['other']['charset']);
    for (var i=0, len=details['other']['flags'].length; i < len; i++) {
        $('input[type=checkbox][value='+details['other']['flags'][i]+']', $('.flags')).attr('checked', true);
    }
    $('.adv_source_limit').val(details['other']['limit']);
};

var adv_reset_page = function() {
    Hm_Utils.save_to_local_storage('formatted_advanced_search_data', '');
    Hm_Utils.save_to_local_storage('adv_search_params', '');
    document.location.href = '?page=advanced_search';
};
