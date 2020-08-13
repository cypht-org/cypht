"use strict"

var add_remove_terms = function(el) {
    var close = $(globals.close_html);
    var count = $('.adv_terms').length;
    var term = $('#adv_term').clone(false);
    var not_chk = $('<span id="adv_term_not" class="adv_term_nots"><input type="checkbox" value="not" id="adv_term_not" /> !</span>');
    var and_or_html = '<div class="andor"><input checked="checked" type="radio" name="term_and'
    and_or_html += '_or'+count+'" value="and">and <input type="radio" name="term_and_or'+count;
    and_or_html += '" value="or">or</div>';
    var and_or = $(and_or_html);
    term.attr('id', 'adv_term'+count);
    close.attr('id', 'term_adv_remove'+count);
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
    var time_html = '<span id="adv_time" class="adv_times">From <input class="adv_time_fld_from" ';
    time_html += 'type="date" value=""> To <input class="adv_time_fld_to" type="date" value=""></span>';
    var timeset = $(time_html);
    var and_or_html = '<div class="timeandor"><input type="radio" name="time_and_or'+count;
    and_or_html += '" checked="checked" value="or">or</div>';
    var and_or = $(and_or_html);
    timeset.attr('id', 'adv_time'+count);
    close.attr('id', 'time_adv_remove'+count);
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
    var and_or_html = '<div class="andor"><input type="radio" name="target_and_or'+count;
    and_or_html += '" value="and">and <input type="radio" name="target_and_or'+count;
    and_or_html += '" checked="checked" value="or">or</div>';
    var and_or = $(and_or_html);

    target.attr('id', 'adv_target'+count);
    $('.target_radio', target).attr('name', 'target_type'+count);
    $('.target_radio', target).removeAttr('checked');
    close.attr('id', 'target_adv_remove'+count);
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
    }
};

var adv_select_imap_folder = function(el) {
    var close = $(globals.close_html);
    close.addClass('close_adv_folders');
    var list_container = $('.adv_folder_list');
    var folders = $('.email_folders').clone(false);
    folders.find('.manage_folders_li').remove();
    $('.menu_email', folders).remove();
    folders.removeClass('email_folders');
    $(el).after(close);
    list_container.show();
    folders.show();
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
    add_source_to_list(id, label);
    $('.adv_folder_list').html('');
    $('.close_adv_folders').remove();
    $('.adv_folder_list').hide();
};

var add_source_to_list = function(id, label) {
    var close = $(globals.close_html);
    close.addClass('adv_remove_source');
    close.attr('data-target', id);
    var row = '<div class="'+id+'">'+close.prop('outerHTML')+label;
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
        $('.expand_link', list).html('-');
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
    var sources = [];
    var selected_sources = $('div', $('.adv_source_list'));
    if (!selected_sources) {
        return sources;
    }
    selected_sources.each(function() {
        sources.push({'source': this.className, 'label': $(this).text()});
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
    Hm_Notices.hide(true);
    var terms = get_adv_terms();
    if (terms.length == 0) {
        Hm_Notices.show(['ERRYou must enter at least one search term']);
        return;
    }
    var sources = get_adv_sources();
    if (sources.length == 0) {
        Hm_Notices.show(['ERRYou must select at least one source']);
        return;
    }
    var targets = get_adv_targets();
    if (targets.length == 0) {
        Hm_Notices.show(['ERRYou must have at least one target']);
        return;
    }
    var times = get_adv_times();
    if (times.length == 0) {
        Hm_Notices.show(['ERRYou must enter at least one time range']);
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
            {'name': 'adv_charset', 'value': request['other']['charset']},
        ];

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
                var detail = Hm_Utils.parse_folder_path(request['source'], 'imap');
                Hm_Message_List.update([detail.server_id+n], res.formatted_message_list, 'imap');
                if (Hm_Utils.rows().length > 0) {
                    $('.adv_controls').show();
                    $('.core_msg_control').off('click');
                    $('.core_msg_control').on("click", function() { return Hm_Message_List.message_action($(this).data('action')); });
                    Hm_Message_List.set_checkbox_callback();
                    if (typeof check_select_for_imap !== 'undefined') {
                        check_select_for_imap();
                    }
                }
                Hm_Message_List.check_empty_list();
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
                source = sources[s]['source'];
                for (var ti=0, tilen=times.length; ti < tilen; ti++) {
                    time = times[ti];
                    requests.push({'source': source, 'time': time, 'other': other,
                        'targets': target_vals, 'terms': term_vals});
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

$(function() {
    if (hm_page_name() == 'advanced_search') {

        globals.close_html = '<img width="16" height="16" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22';
        globals.close_html += 'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%2';
        globals.close_html += '2%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.21%';
        globals.close_html += '200-4%201.79-4%204s1.79%204%204%204%204-1.79%204-4-1.79-4-4-4zm-1.5%201.781';
        globals.close_html += 'l1.5%201.5%201.5-1.5.719.719-1.5%201.5%201.5%201.5-.719.719-1.5-1.5-1.5%201';
        globals.close_html += '.5-.719-.719%201.5-1.5-1.5-1.5.719-.719z%22%20%2F%3E%0A%3C%2Fsvg%3E" alt="R';
        globals.close_html += 'emove">';

        $('.settings_subtitle').on("click", function() { return Hm_Utils.toggle_page_section($(this).data('target')); });
        $('.adv_folder_select').on("click", function() { adv_select_imap_folder(this); });
        $('.new_time').on("click", function() { add_remove_times(this); });
        $('.new_target').on("click", function() { add_remove_targets(this); });
        $('.new_term').on("click", function() { add_remove_terms(this); });
        $('.adv_expand_all').on("click", function() { adv_expand_sections(); });
        $('.adv_collapse_all').on("click", function() { adv_collapse(); });
        $('#adv_search').on("click", function() { process_advanced_search(); });
        $('.toggle_link').on("click", function() { return Hm_Message_List.toggle_rows(); });
        $('.adv_reset').on("click", function() { adv_reset_page(); });
        $('.combined_sort').on("change", function() { Hm_Message_List.sort($(this).val()); });

        apply_saved_search();
        var data = Hm_Utils.get_from_local_storage('formatted_advanced_search_data');
        if (data && data.length) {
            adv_collapse(); 
            Hm_Utils.tbody().html(data);
            $('.adv_controls').show();
            $('.core_msg_control').off('click');
            $('.core_msg_control').on("click", function() { return Hm_Message_List.message_action($(this).data('action')); });
            Hm_Message_List.set_checkbox_callback();
            if (typeof check_select_for_imap !== 'undefined') {
                check_select_for_imap();
            }
        }
        Hm_Message_List.check_empty_list();
    }
});
