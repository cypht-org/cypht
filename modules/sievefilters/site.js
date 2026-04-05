/**
 * Possible Sieve fields
 * @type {{Message: [{name: string, options: string[], type: string, selected: boolean},{name: string, options: string[], type: string},{name: string, options: string[], type: string}], Header: [{name: string, options: string[], type: string},{name: string, options: string[], type: string},{name: string, options: string[], type: string},{name: string, options: string[], type: string}]}}
 */

// Prefer variables declared with var for better function scope handling,
// as some of these cause runtime errors in Tiki on conflicting names
var is_editing_script = false;
var current_editing_script_name = "";
// var hm_sieve_current_account = "";
var current_account;
var current_account_element;
var is_editing_filter = false;
var current_editing_filter_name = '';

var hm_sieve_condition_fields = function() {
    return {
        'Message': [
            {
                name: 'subject',
                description: 'Subject',
                type: 'string',
                selected: true,
                options: ['Contains', 'Matches', 'Regex'],
            },
            {
                name: 'body',
                description: 'Message body',
                type: 'string',
                options: ['Contains', 'Matches', 'Regex'],
            },
            {
                name: 'size',
                description: 'Size (KB)',
                type: 'int',
                options: ['Over', 'Under'],
            },
        ],
        Header: [
            {
                name: 'to',
                description: 'Recipient (To)',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex'],
            },
            {
                name: 'from',
                description: 'Sender (From)',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex'],
            },
            {
                name: 'cc',
                description: 'Copied recipient (CC)',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex'],
            },
            {
                name: 'to_or_cc',
                description: 'Recipient (To or CC)',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex'],
            },
            {
                name: 'bcc',
                description: 'Blind copied recipient (BCC)',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex'],
            },
            {
                name: 'custom',
                description: 'Custom',
                type: 'string',
                extra_option: true,
                extra_option_description: 'Field Name',
                options: ['Contains', 'Matches', 'Regex'],
            },
        ],
    };
};

  /**************************************************************************************
     *                                    FUNCTIONS
     **************************************************************************************/
var load_sieve_filters = function(pageName) {
    const dataSources = hm_data_sources() ?? [];
    if (dataSources.length) {
        dataSources.forEach((source) => {
            if(source.sieve){
                let spinnerId = `spinner_${source.id}`;
                let spinnnerText = hm_spinner_text(`${source.name}`, spinnerId);
                $('#sieve_accounts').append(spinnnerText);
                Hm_Ajax.request(
                    [{'name': 'hm_ajax_hook', 'value': pageName},
                        {'name': 'imap_server_id', 'value': source.id}],
                    (res) => {
                        $(`#${spinnerId}`).remove();
                        $('#sieve_accounts').append(res.sieve_detail_display);
                    
                    }
                );
            }
            
        })
    }
    return false;
};

function add_filter_match_mode() {
    let conditionRows = $(".sieve_list_conditions_modal tr").length;
    if (conditionRows >= 2) {
        if ($(".sieve_match_mode").length === 0) {
            $(".sieve_list_conditions_modal").before(
              '<div class="sieve_match_mode mb-2">' +
                '   <label class="me-2">Match</label>' +
                '   <select name="sieve_match_mode" class="modal_sieve_filter_test form-select-sm d-inline w-auto">' +
                '       <option value="ALLOF">ALL</option>' +
                '       <option value="ANYOF">ANY</option>' +
                "   </select>" +
                "   of the following rules:" +
                "</div>"
            );
        }
    } else {
        $(".sieve_match_mode").remove();
    }
}

function ordinal_number(n)
{
    let ord = 'th';

    if (n % 10 == 1 && n % 100 != 11) {
        ord = 'st';
    } else if (n % 10 == 2 && n % 100 != 12) {
        ord = 'nd';
    } else if (n % 10 == 3 && n % 100 != 13) {
        ord = 'rd';
    }

    return n + ord;
}

function showErrorMsg(msg, parentClass, fadeTime = null) {
    let $parent = $(parentClass);

    if (!$parent.data("highlight-added")) {
        $parent.data("highlight-added", true);
        $parent.addClass("highlight-active border border-info rounded p-2 bg-light");
    }

    let $msg = $('<small class="text-info d-block mt-1"></small>').text(msg);

    $parent.append($msg).show();

    if (fadeTime !== null) {
        setTimeout(function () {
            $msg.remove();

            if ($parent.data("highlight-added") && $parent.find("small").length === 0) {
                $parent.removeClass("highlight-active border border-info rounded p-2 bg-light");
                $parent.removeData("highlight-added");
            }
        }, fadeTime);
    }
}

/**
 * Possible Sieve actions
 * @type {[{name: string, description: string, placeholder: string, type: string, selected: boolean},{name: string, description: string, placeholder: string, type: string},{name: string, description: string, type: string},{name: string, description: string, type: string},{name: string, description: string, placeholder: string, type: string}]}
 */
var hm_sieve_possible_actions = function() {
    return [
        {
            name: 'keep',
            description: 'Deliver (Keep)',
            type: 'none',
            extra_field: false,
        },
        {
            name: 'copy',
            description: 'Copy email to mailbox',
            placeholder: 'Mailbox Name (Folder)',
            type: 'mailbox',
            extra_field: false,
            require: 'fileinto',
        },
        {
            name: 'move',
            description: 'Move email to mailbox',
            placeholder: 'Mailbox Name (Folder)',
            type: 'mailbox',
            extra_field: false,
            require: 'fileinto',
        },
        {
            name: 'flag',
            description: 'Flag',
            placeholder: 'Example: SEEN',
            type: 'select',
            values: ['Seen', 'Answered', 'Flagged', 'Deleted', 'Draft', 'Recent'],
            extra_field: false,
            require: 'imap4flags',
        },
        {
            name: 'addflag',
            description: 'Add Flag',
            placeholder: 'Example: SEEN',
            type: 'select',
            values: ['Seen', 'Answered', 'Flagged', 'Deleted', 'Draft', 'Recent'],
            extra_field: false,
            require: 'imap4flags',
        },
        {
            name: 'removeflag',
            description: 'Remove Flag',
            placeholder: 'Example: SEEN',
            type: 'select',
            values: ['Seen', 'Answered', 'Flagged', 'Deleted', 'Draft', 'Recent'],
            extra_field: false,
            require: 'imap4flags',
        },
        {
            name: 'redirect',
            description: 'Redirect',
            placeholder: 'mail@example.org',
            type: 'string',
            extra_field: false
        },
        {
            name: 'forward',
            description: 'Forward',
            placeholder: 'mail@example.org',
            type: 'string',
            extra_field: false
        },
        {
            name: 'reject',
            description: 'Reject',
            placeholder: 'Reject message',
            type: 'string',
            extra_field: false,
            require: 'reject',
        },
        {
            name: 'discard',
            description: 'Discard',
            type: 'none',
            extra_field: false,
        },
        {
            name: 'autoreply',
            placeholder: 'Reply Message',
            description: 'Reply Message',
            type: 'text',
            extra_field: true,
            extra_field_type: 'string',
            extra_field_placeholder: 'Subject',
            require: 'vacation'
        }
    ];
};

var find_account_element = function(account_name) {
    const accountElement = $('.add_filter, .edit_filter, .edit_script').filter(function () {
        return $(this).attr('account') === account_name ||
            $(this).attr('imap_account') === account_name;
    }).first();

    return accountElement.length ? accountElement : null;
};

var get_account_actions = () => {
    if (!current_account_element || !current_account_element.length) {
        current_account_element = find_account_element(current_account);
    }

    const extensionsAttr = current_account_element
        ? current_account_element.attr('sieve_extensions')
        : null;
    const extensions = extensionsAttr ? JSON.parse(extensionsAttr) : [];
    let possible_actions = hm_sieve_possible_actions();

    possible_actions = possible_actions.filter((value) => {
        return ! value.hasOwnProperty('require') || extensions.includes(value.require)
    })

    return possible_actions;
}

function createSaveFilter({
    getCurrentAccount,
    getIsEditingFilter,
    getCurrentEditingFilterName,
    getEditScriptModal,
    isFilterFromCustomActions = false,
}) {
    return function save_filter(gen_script = false) {
        // Set global state from dependency injection parameters
        current_account = getCurrentAccount();
        is_editing_filter = getIsEditingFilter();
        current_editing_filter_name = getCurrentEditingFilterName();
        const edit_script_modal = getEditScriptModal();

        // Reuse Hm_Filters.save_filter with custom callback for script modal handling
        const originalRequest = Hm_Ajax.request;
        let customCallbackExecuted = false;

        Hm_Ajax.request = function(params, callback) {
            originalRequest(params, function(res) {
                if (!customCallbackExecuted && res.script_details) {
                    customCallbackExecuted = true;
                    if (Object.keys(res.script_details).length > 0) {
                        edit_script_modal.open();
                        $('.modal_sieve_script_textarea').val(
                            res.script_details.gen_script,
                        );
                        $('.modal_sieve_script_name').val(
                            res.script_details.filter_name,
                        );
                        $('.modal_sieve_script_priority').val(
                            res.script_details.filter_priority,
                        );
                    } else {
                        window.location = window.location;
                    }
                }
                if (callback) {
                    callback(res);
                }
            });
        };

        try {
            return Hm_Filters.save_filter(current_account, gen_script);
        } finally {
            // Restore original Hm_Ajax.request
            Hm_Ajax.request = originalRequest;
        }
    };
}

const Hm_Filters = (function (hm) {
    hm.save_filter = (imap_account, gen_script = false) => {
        let validation_failed = false
        let conditions_parsed = []
        let actions_parsed = []
        let conditions = $('select[name^=sieve_selected_conditions_field]').map(function(idx, elem) {
            return $(elem).val();
        }).get();

        let conditions_type = $('select[name^=sieve_selected_conditions_options]').map(function(idx, elem) {
            return $(elem).val();
        }).get();

        let conditions_value = $('input[name^=sieve_selected_option_value]').map(function(idx, elem) {
            return $(elem).val();
        }).get();

        let conditions_extra_value = $('input[name^=sieve_selected_extra_option_value]').map(function(idx, elem) {
            return $(elem).val();
        }).get();

        let idx = 0;
        if (conditions.length === 0) {
            showErrorMsg(
              "You must provide at least one condition",
              ".sieve-filter-conditions-block",
              10000
            );
            return false;
        }

        conditions.forEach(function (elem, key) {
            if (conditions_value[idx] === "" && conditions_value[idx] !== 'none') {
                let order = ordinal_number(key + 1);
                let previous_messages = $('.sys_messages').html();
                previous_messages += previous_messages ? '<br>': '';
                showErrorMsg(
                  "The " + order + " condition (" + elem + ") must be provided",
                  ".sieve-filter-conditions-block",
                  10000
                );
                validation_failed = true;
            }
             conditions_parsed.push(
                 {
                     'condition': elem,
                     'type': conditions_type[idx],
                     'extra_option': conditions[idx].extra_option,
                     'extra_option_value': conditions_extra_value[idx],
                     'value': conditions_value[idx]
                 }
             )
            idx = idx + 1;
        });

        let actions_type = $('select[name^=sieve_selected_actions]').map(function(idx, elem) {
            return $(elem).val();
        }).get();
        let actions_value = $('[name^=sieve_selected_action_value]').map(function(idx, elem) {
            return $(elem).val();
        }).get();
        let actions_field_type = $('[name^=sieve_selected_action_value]').map(function(idx, elem) {
            return $(elem).attr('type');
        }).get();
        let actions_extra_value = $('input[name^=sieve_selected_extra_action_value]').map(function(idx, elem) {
            return $(elem).val();
        }).get();

        if (actions_type.length === 0) {
            showErrorMsg('You must provide at least one action', '.sieve-filter-actions-block', 10000);
            return false;
        }

        idx = 0;
        actions_type.forEach(function (elem, key) {
            console.log(actions_field_type[idx])
            if (actions_value[idx] === "" && actions_field_type[idx] !== 'hidden') {
                let order = ordinal_number(key + 1);
                let previous_messages = $('.sys_messages').html();
                previous_messages += previous_messages ? '<br>': '';
                showErrorMsg(
                  "The " + order + " action (" + elem + ") must be provided",
                  ".sieve-filter-actions-block",
                  10000
                );
                validation_failed = true;
            }
            actions_parsed.push(
                {
                    'action': elem,
                    'value': actions_value[idx],
                    'extra_option': actions_type[idx].extra_option,
                    'extra_option_value': actions_extra_value[idx],
                }
            )
            idx = idx + 1;
        });

        if ($('#stop_filtering').is(':checked')) {
            actions_parsed.push(
                {
                    'action': "stop",
                    'value': "",
                    'extra_option': "",
                    'extra_option_value': "",
                }
            )
        }
        if ($('.modal_sieve_filter_name').val() == "") {
            showErrorMsg("Filter name is required", ".sieve-filter-name-group");
            return false;
        }

        if (validation_failed) {
            return false;
        }

        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_save_filter'},
                {'name': 'imap_account', 'value': imap_account},
                {'name': 'sieve_filter_name', 'value': $('.modal_sieve_filter_name').val()},
                {'name': 'sieve_filter_priority', 'value': $('.modal_sieve_filter_priority').val()},
                {'name': 'is_editing_filter', 'value': is_editing_filter},
                {'name': 'current_editing_filter_name', 'value': current_editing_filter_name},
                {'name': 'conditions_json', 'value': JSON.stringify(conditions_parsed)},
                {'name': 'actions_json', 'value': JSON.stringify(actions_parsed)},
                {'name': 'filter_test_type', 'value': $('.modal_sieve_filter_test').val()},
                {'name': 'filter_source', 'value': getPageNameParam()},
                {'name': 'gen_script', 'value': gen_script},
            ],
            function(res) {
                if (Object.keys(res.script_details).length === 0) {
                    window.location = window.location;
                } else {
                    edit_script_modal.open();
                    $('.modal_sieve_script_textarea').val(res.script_details.gen_script);
                    $('.modal_sieve_script_name').val(res.script_details.filter_name);
                    $('.modal_sieve_script_priority').val(res.script_details.filter_priority);
                }
            }
        );

        return true;
    }

    hm.save_script = (imap_account) => {
        if ($('.modal_sieve_script_name').val() === "") {
            Hm_Notices.show('You must provide a name for your script', 'warning');
            return false;
        }
        if ($('.modal_sieve_script_textarea').val() === "") {
            Hm_Notices.show('Empty script', 'warning');
            return false;
        }
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_save_script'},
                {'name': 'imap_account', 'value': imap_account},
                {'name': 'sieve_script_name', 'value': $('.modal_sieve_script_name').val()},
                {'name': 'sieve_script_priority', 'value': $('.modal_sieve_script_priority').val()},
                {'name': 'is_editing_script', 'value': is_editing_script},
                {'name': 'current_editing_script', 'value': current_editing_script_name},
                {'name': 'script', 'value': $('.modal_sieve_script_textarea').val()}],
            function(res) {
                window.location = window.location;
            }
        );
    }

    hm.add_filter_condition = () => {
        let header_fields = '';
        let message_fields = '';

        hm_sieve_condition_fields().Message.forEach(function (value) {
            if (value.selected === true) {
                message_fields += '<option selected value="'+value.name+'">' + value.description + '</option>';
            } else {
                message_fields += '<option value="'+value.name+'">' + value.description + '</option>';
            }
        });
        hm_sieve_condition_fields().Header.forEach(function (value) {
            if (value.selected === true) {
                header_fields += '<option selected value="'+value.name+'">' + value.description + '</option>';
            } else {
                header_fields += '<option value="'+value.name+'">' + value.description + '</option>';
            }
        });
        let extra_options = '<td class="col-sm-3"><input type="hidden" class="condition_extra_value form-control form-control-sm" name="sieve_selected_extra_option_value[]" /></td>';
        $('.sieve_list_conditions_modal').append(
            '                            <tr class="sieve_condition_row">' +
            '                                <td class="col-sm-2">' +
            '                                    <select class="add_condition_sieve_filters form-control form-control-sm" name="sieve_selected_conditions_field[]">' +
            '                                        <optgroup label="Message">' +
            message_fields +
            '                                        </optgroup>' +
            '                                        <optgroup label="Header">' +
            header_fields +
            '                                        </optgroup>' +
            '                                    </select>' +
            '                                </td>' +
            extra_options +
            '                                <td class="col-sm-3">' +
            '                                    <select class="condition_options form-control form-control-sm" name="sieve_selected_conditions_options[]">' +
            '                                        <option value="Contains">' +
            '                                            Contains' +
            '                                        </option>' +
            '                                        <option value="!Contains">' +
            '                                            Not Contains' +
            '                                        </option>' +
            '                                        <option value="Matches">' +
            '                                            Matches' +
            '                                        </option>' +
            '                                        <option value="!Matches">' +
            '                                            Not Matches' +
            '                                        </option>' +
            '                                        <option value="Regex">' +
            '                                            Regex' +
            '                                        </option>' +
            '                                        <option value="!Regex">' +
            '                                            Not Regex' +
            '                                        </option>' +
            '                                    </select>' +
            '                                </td>' +
            '                                <td class="col-sm-3">' +
            '                                    <input type="text" name="sieve_selected_option_value[]" class="form-control form-control-sm" />' +
            '                                </td>' +
            '                                <td class="col-sm-1 text-end align-middle">' +
            '                                    <a href="#" class="delete_condition_modal_button btn btn-sm btn-secondary">Delete</a>' +
            '                                </td>' +
            '                            </tr>'
        );
    }

    hm.add_filter_action = (default_value = '') =>{
        let possible_actions_html = '';
        
        get_account_actions().forEach(function (value) {
            if (value.selected === true) {
                possible_actions_html += '<option selected value="'+value.name+'">' + value.description + '</option>';
                return;
            }
            possible_actions_html += '<option value="'+value.name+'">' + value.description + '</option>';
        });
        let extra_options = '<td class="col-sm-3"><input type="hidden" class="condition_extra_action_value form-control form-control-sm" name="sieve_selected_extra_action_value[]" /></td>';
        $(".filter_actions_modal_table").append(
            '<tr class="border draggable_action_row" default_value="' +
                default_value +
                '">' +
                '   <td class="col-sm-1 drag-handle" style="cursor: grab;">&#9776;</td>' +
                '   <td class="col-sm-3">' +
                '       <select class="sieve_actions_select form-control form-control-sm" name="sieve_selected_actions[]">' +
                "          " +
                possible_actions_html +
                "       </select>" +
                "    </td>" +
                extra_options +
                '    <td class="col-sm-5">' +
                '    <input type="hidden" name="sieve_selected_action_value[]" value="">' +
                "    </input>" +
                '    <td class="col-sm-1 text-end align-middle">' +
                '           <a href="#" class="delete_action_modal_button btn btn-sm btn-secondary">Delete</a>' +
                "    </td>" +
            "</tr>"
        );
    }

    return hm;
})({});

const add_filter_condition = Hm_Filters.add_filter_condition;
const add_filter_action = Hm_Filters.add_filter_action;

/**************************************************************************************
*                                      MODAL EVENTS
**************************************************************************************/
const hm_sieve_button_events = (edit_filter_modal, edit_script_modal) => {
    $(document).off('click', '.sievefilters_accounts_title').on('click', '.sievefilters_accounts_title', function() {
        $(this).parent().find('.sievefilters_accounts').toggleClass('d-none');
    });

    $(document).off('click', '.add_filter').on('click', '.add_filter', function() {
        edit_filter_modal.setTitle('Add Filter');
        $('.modal_sieve_filter_priority').val('');
        $('.modal_sieve_filter_test').val('ALLOF');
        $('#stop_filtering').prop('checked', false);
        current_account = $(this).attr('account');
        current_account_element = $(this);
        edit_filter_modal.open();

        // Reset the form fields when opening the modal
        $(".modal_sieve_filter_name").val('');
        $(".modal_sieve_script_priority").val('');
        $(".sieve_list_conditions_modal").empty();
        $(".filter_actions_modal_table").empty();
    });

    $(document).off('click', '.add_script').on('click', '.add_script', function() {
        edit_script_modal.setTitle('Add Script');
        $('.modal_sieve_script_textarea').val('');
        $('.modal_sieve_script_name').val('');
        $('.modal_sieve_script_priority').val('');
        is_editing_script = false;
        current_editing_script_name = '';
        current_account = $(this).attr('account');
        current_account_element = $(this);
        edit_script_modal.open();
    });

    /**
     * Delete action Button
     */
    $(document).off('click', '.delete_else_action_modal_button').on('click', '.delete_else_action_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    /**
     * Delete action Button
     */
    $(document).off('click', '.delete_action_modal_button').on('click', '.delete_action_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    /**
     * Delete Condition Button
     */
    $(document).off('click', '.delete_condition_modal_button').on('click', '.delete_condition_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    /**
     * Add Condition Button
     */
    $(document).off('click', '.sieve_add_condition_modal_button').on('click', '.sieve_add_condition_modal_button', function () {
        add_filter_condition();
        add_filter_match_mode();
    });

    /**
     * Add Action Button
     */
    $(document).off('click', '.filter_modal_add_action_btn').on('click', '.filter_modal_add_action_btn', function () {
        add_filter_action();
    });

    /**
     * Add Else Action Button
     */
    $(document).off('click', '.filter_modal_add_else_action_btn').on('click', '.filter_modal_add_else_action_btn', function () {
        let possible_actions_html = '';

        get_account_actions().forEach(function (value) {
            if (value.selected === true) {
                possible_actions_html += '<option selected value="'+value.name+'">' + value.description + '</option>';
                return;
            }
            possible_actions_html += '<option value="'+value.name+'">' + value.description + '</option>';
        });

        $('.filter_else_actions_modal_table').append(
            '<tr class="border">' +
            '   <td class="col-sm-4">' +
            '       <select class="sieve_actions_select form-control form-control-sm">' +
            '          ' + possible_actions_html +
            '       </select>' +
            '    </td>' +
            '    <td>' +
            '    </td>' +
            '    <td class="col-sm-1 text-end align-middle">' +
            '           <a href="#" class="delete_else_action_modal_button">Delete</a>' +
            '    </td>' +
            '</tr>'
        );
    });

    /**
     * Action change
     */
    $(document).off('change', '.sieve_actions_select').on('change', '.sieve_actions_select', function () {
        let tr_elem = $(this).parent().parent();
        console.log(tr_elem.attr('default_value'));
        let elem = $(this).parent().next().next();
        let elem_extra = $(this).parent().next().find('.condition_extra_action_value');
        let action_name = $(this).val();
        let selected_action;
        get_account_actions().forEach(function (action) {
           if (action_name === action.name) {
                selected_action = action;
           }
        });
        if (selected_action) {
            elem_extra.attr('type', 'hidden');
            if (selected_action.extra_field) {
                elem_extra.attr('type', 'text');
                elem_extra.attr('placeholder', selected_action.extra_field_placeholder)
            }
            if (selected_action.type === 'none') {
                elem.html('<input name="sieve_selected_action_value[]" class="form-control form-control-sm" type="hidden" value="" />');
            }
            if (selected_action.type === 'string') {
                elem.html('<input name="sieve_selected_action_value[]" class="form-control form-control-sm" placeholder="'+selected_action.placeholder+'" type="text" value="" />');
            }
            if (selected_action.type === 'int') {
                elem.html('<input name="sieve_selected_action_value[]" class="form-control form-control-sm" placeholder="'+selected_action.placeholder+'" type="number" />');
            }
            if (selected_action.type === 'number') {
                elem.html('<input name="sieve_selected_action_value[]" class="form-control form-control-sm" placeholder="'+selected_action.placeholder+'" type="number" />');
            }
            if (selected_action.type === 'text') {
                elem.html('<textarea name="sieve_selected_action_value[]" class="form-control form-control-sm" placeholder="'+selected_action.placeholder+'"></textarea>');
            }
            if (selected_action.type === 'select') {
                options = '';
                selected_action.values.forEach(function(val) {
                    if (tr_elem.attr('default_value') === val) {
                        options = options + '<option value="' + val + '" selected>'+ val +'</option>'
                    } else {
                        options = options + '<option value="' + val + '">'+ val +'</option>'
                    }
                });
                elem.html('<select name="sieve_selected_action_value[]" class="form-control form-control-sm">'+ options +'</select>');
            }
            if (selected_action.type === 'mailbox') {
                let mailboxes = null;
                tr_elem.children().eq(2).html(hm_spinner());
                Hm_Ajax.request(
                    [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_get_mailboxes'},
                        {'name': 'imap_account', 'value': current_account} ],
                    function(res) {
                        mailboxes = JSON.parse(res.mailboxes);
                        options = '';
                        mailboxes.forEach(function(val) {
                            if (tr_elem.attr('default_value') === val) {
                                options = options + '<option value="' + val + '" selected>'+ val +'</option>'
                            } else {
                                options = options + '<option value="' + val + '">'+ val +'</option>'
                            }
                        });
                        elem.html('<select name="sieve_selected_action_value[]" class="form-control form-control-sm">'+ options +'</select>');
                        $("[name^=sieve_selected_action_value]").last().val(elem.parent().attr('default_value'));
                    }
                );
            }
        }
    })

    /**
     * Condition type change
     */
    $(document).off('change', '.add_condition_sieve_filters').on('change', '.add_condition_sieve_filters', function () {
        let condition_name = $(this).val();
        let elem = $(this).parent().next().next().find('.condition_options');
        let elem_extra = $(this).parent().next().find('.condition_extra_value');
        let elem_type = $(this).parent().next().next().next();
        let condition;
        let options_html = '';
        let input_type_html = '';
        hm_sieve_condition_fields().Message.forEach(function (cond) {
            if (condition_name === cond.name) {
                condition = cond;
            }
        });
        hm_sieve_condition_fields().Header.forEach(function (cond) {
            if (condition_name === cond.name) {
                condition = cond;
            }
        });
        if (condition) {
            if (condition.extra_option === true) {
                elem_extra.attr('type', 'text');
                elem_extra.attr('placeholder', condition.extra_option_description);
            } else {
                elem_extra.attr('type', 'hidden');
            }
            condition.options.forEach(function (option) {
                options_html += '<option value="'+option+'">'+option+'</option>';
                options_html += '<option value="!'+option+'">Not '+option+'</option>';
            });
            elem.html(options_html);

            if (condition.type === 'string') {
                elem_type.html('<input name="sieve_selected_option_value[]" type="text" class="form-control form-control-sm" />')
            }
            if (condition.type === 'int') {
                elem_type.html('<input name="sieve_selected_option_value[]" type="number" class="form-control form-control-sm" />')
            }
            if (condition.type === 'none') {
                elem_type.html('<input name="sieve_selected_option_value[]" type="hidden" value="none" />')
            }
        }
    });

    /**
     * Delete filter event
     */
    $(document).off('click', '.delete_filter').on('click', '.delete_filter', function (e) {
        e.preventDefault();
        if (!confirm('Do you want to delete filter?')) {
            return;
        }
        let obj = $(this);
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_delete_filter'},
                {'name': 'imap_account', 'value': $(this).attr('imap_account')},
                {'name': 'sieve_script_name', 'value': $(this).attr('script_name')}],
            function(res) {
                if (res.script_removed == '1') {
                    obj.parent().parent().remove();
                }
            }
        );
    });

    /**
     * Toggle Filter
     */
    $('.toggle_filter').off('change').on('change', function () {
        const checkbox = $(this);
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_toggle_script_state'},
                {'name': 'imap_account', 'value': checkbox.attr('imap_account')},
                {'name': 'script_state', 'value': checkbox.prop('checked')},
                {'name': 'sieve_script_name', 'value': checkbox.attr('script_name')}],
            function(res) {
                if (res.success) {
                    checkbox.prop('checked', !checkbox.prop('checked'));
                }
            }
        );
    });

    /**
     * Delete script event
     */
    $(document).off('click', '.delete_script').on('click', '.delete_script', function (e) {
        e.preventDefault();
        if (!confirm('Do you want to delete script?')) {
            return;
        }
        let obj = $(this);
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_delete_script'},
                {'name': 'imap_account', 'value': $(this).attr('imap_account')},
                {'name': 'sieve_script_name', 'value': $(this).attr('script_name')}],
            function(res) {
                if (res.script_removed == '1') {
                    obj.parent().parent().remove();
                }
            }
        );
    });

    /**
     * Edit script event
     */
    $(document).off('click', '.edit_script').on('click', '.edit_script', function (e) {
        e.preventDefault();
        let obj = $(this);
        edit_script_modal.setTitle('Edit Script');
        is_editing_script = true;
        current_editing_script_name = $(this).attr('script_name');
        current_account = $(this).attr('imap_account');
        current_account_element = $(this);
        $('.modal_sieve_script_name').val($(this).attr('script_name_parsed'));
        $('.modal_sieve_script_priority').val($(this).attr('priority'));
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_edit_script'},
                {'name': 'imap_account', 'value': $(this).attr('imap_account')},
                {'name': 'sieve_script_name', 'value': $(this).attr('script_name')}],
            function(res) {
                $('.modal_sieve_script_textarea').html(res.script);
                edit_script_modal.open();
            }
        );
    });

    /**
     * Edit filter event
     */
    $(document).off('click', '.edit_filter').on('click', '.edit_filter', function (e) {
        e.preventDefault();
        let obj = $(this);
        current_account = $(this).attr('account');
        current_account_element = $(this);
        is_editing_filter = true;
        current_editing_filter_name = $(this).attr('script_name');
        current_account = $(this).attr('imap_account');
        current_account_element = $(this);
        // $('#stop_filtering').prop('checked', false);
        $('.modal_sieve_filter_name').val($(this).attr('script_name_parsed'));
        $('.modal_sieve_filter_priority').val($(this).attr('priority'));
        $('.sieve_list_conditions_modal').html('');
        $('.filter_actions_modal_table').html('');
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_edit_filter'},
                {'name': 'imap_account', 'value': $(this).attr('imap_account')},
                {'name': 'sieve_script_name', 'value': $(this).attr('script_name')}],
            function(res) {
                conditions = JSON.parse(JSON.parse(res.conditions));
                actions = JSON.parse(JSON.parse(res.actions));
                test_type = res.test_type;
                $(".modal_sieve_filter_test").val(test_type);
                conditions.forEach(function (condition) {
                    add_filter_condition();
                    $(".add_condition_sieve_filters").last().val(condition.condition);
                    $(".add_condition_sieve_filters").last().trigger('change');
                    $(".condition_options").last().val(condition.type);
                    $("[name^=sieve_selected_extra_option_value]").last().val(condition.extra_option_value);
                    if ($("[name^=sieve_selected_option_value]").last().is('input')) {
                        $("[name^=sieve_selected_option_value]").last().val(condition.value);
                    }
                });

                actions.forEach(function (action) {
                    if (action.action === "stop") {
                        $('#stop_filtering').prop('checked', true);
                    } else {
                        add_filter_action(action.value);
                        $(".sieve_actions_select").last().val(action.action);
                        $(".sieve_actions_select").last().trigger('change');
                        $("[name^=sieve_selected_extra_action_value]").last().val(action.extra_option_value);
                        if ($("[name^=sieve_selected_action_value]").last().is('input')) {
                            $("[name^=sieve_selected_action_value]").last().val(action.value);
                        } else if ($("[name^=sieve_selected_action_value]").last().is('textarea')) {
                            $("[name^=sieve_selected_action_value]").last().text(action.value);
                        }
                    }
                });
                edit_filter_modal.setTitle(current_editing_filter_name);
                edit_filter_modal.open();
            }
        );
    });

    /**
     * Actions Drag and Drop
     */
    const actionsTbody = document.querySelector(".filter_actions_modal_table");

    if (actionsTbody) {
        new Sortable(actionsTbody, {
            handle: ".drag-handle",
            animation: 150,
            ghostClass: "sortable-ghost",
        });
    }

    return true;
};

function blockListPageHandlers() {
    $(document).on('change', '.select_default_behaviour', function(e) {
        if ($(this).val() != 'Reject') {
            $(this).closest('.filter_subblock')
                .find('.select_default_reject_message')
                .remove();
        } else {
            $('<input type="text" class="select_default_reject_message form-control" placeholder="'+hm_trans('Reject message')+'" />').insertAfter($(this));
        }
    });

    $(document).on('click', '.submit_default_behavior', function(e) {
        e.preventDefault();
        let parent = $(this).closest('.filter_subblock');
        let elem = parent.find('.select_default_behaviour');
        let submit = $(this);

        const payload = [
            {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_block_change_behaviour'},
            {'name': 'selected_behaviour', 'value': elem.val()},
            {'name': 'imap_server_id', 'value': elem.attr('imap_account')}
        ];
        if (elem.val() == 'Reject') {
            const reject = parent.find('.select_default_reject_message');
            payload.push({'name': 'reject_message', 'value': reject.val()});
        }

        submit.attr('disabled', 1);
        Hm_Ajax.request(
            payload,
            function(res) {
                submit.removeAttr('disabled');
            }
        );
    });

    $(document).on('click', '.unblock_button', function(e) {
        e.preventDefault();
        if (!confirm(hm_trans('Do you want to unblock sender?'))) {
            return;
        }
        let sender = $(this).parent().parent().children().html();
        let elem = $(this);
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_unblock_sender'},
                {'name': 'imap_server_id', 'value': $(this).attr('mailbox_id')},
                {'name': 'sender', 'value': sender}
            ],
            function(res) {
                elem.parent().parent().remove();
                var num_filters = $("#filter_num_" + elem.attr('mailbox_id')).html();
                num_filters = parseInt(num_filters) - 1;
                $("#filter_num_" + elem.attr('mailbox_id')).html(num_filters);
            }
        );
    });

    $(document).on('click', '.edit_blocked_behavior', function(e) {
        e.preventDefault();
        let parent = $(this).closest('tr');
        let elem = parent.find('.block_action');
        let sender = $(this).closest('tr').children().first().html();
        let scope = sender.startsWith('*@') ? 'domain': 'sender';

        Hm_Ajax.request(
            [
                {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_block_unblock'},
                {'name': 'imap_server_id', 'value': $(this).attr('mailbox_id')},
                {'name': 'block_action', 'value': elem.val()},
                {'name': 'scope', 'value': scope},
                {'name': 'sender', 'value': sender},
                {'name': 'reject_message', 'value': $('#reject_message_textarea').val() ?? ''},
                {'name': 'change_behavior', 'value': true}
            ],
            function(res) {
                if (/^(Sender|Domain) Behavior Changed$/.test(res.router_user_msgs[0].text)) {
                    window.location = window.location;
                }
            }
        );
    });

    $(document).on('click', '.block_domain_button', function(e) {
        e.preventDefault();
        let sender = $(this).parent().parent().children().html();
        let elem = $(this);
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_block_domain'},
                {'name': 'imap_server_id', 'value': $(this).attr('mailbox_id')},
                {'name': 'sender', 'value': sender}
            ],
            function(res) {
                if (res && res.reload_page) {
                    window.location = window.location;
                }
            }
        );
    });

    $(document).on('click', '.edit_email_behavior_submit', function(e) {
        e.preventDefault();
        let sender = $(this).parent().parent().children().html();
        let elem = $(this);
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_block_domain'},
                {'name': 'imap_server_id', 'value': $(this).attr('mailbox_id')},
                {'name': 'sender', 'value': sender}
            ],
            function(res) {
                window.location = window.location;
            }
        );
    });
    
    $(document).on('click', '.toggle-behavior-dropdown', function(e) {
        e.preventDefault();
        var default_val = $(this).data('action');
        $('#block_sender_form').trigger('reset');
        $('#reject_message').remove();
        $('#block_action').val(default_val).trigger('change');
        $('#edit_blocked_behavior').attr('data-mailbox-id', $(this).attr('mailbox_id'));
        if (default_val == 'reject_with_message') {
            $('#reject_message_textarea').val($(this).data('reject-message'));
        }
    });

    $(document).off('click', '.sievefilters_accounts_title').on('click', '.sievefilters_accounts_title', function() {
        if (parseInt($(this).data("num-blocked")) > 0) {
            $(this).parent().find('.sievefilters_accounts').toggleClass('d-none');
        } else {
            alert(hm_trans("This action requires at least 1 blocked element."))
        }
    });
    load_sieve_filters('ajax_block_account_sieve_filters');
}

function cleanUpSieveFiltersPage() {
    bootstrap.Modal.getInstance(document.getElementById('myEditFilterModal')).dispose();
    bootstrap.Modal.getInstance(document.getElementById('myEditScript')).dispose();
    document.getElementById('myEditScript').remove();
    document.getElementById('myEditFilterModal').remove();
}

function createEditFilterModal(save_filter, current_account, options = {}) {
    const { isFromMessageList = false } = options;

    var edit_filter_modal = new Hm_Modal({
        size: 'xl',
        modalId: 'myEditFilterModal',
    });

    // Store template content on first use, then reuse it
    if (!edit_filter_template_content) {
        const templateEl = document.querySelector('#edit_filter_modal');
        if (templateEl) {
            edit_filter_template_content = templateEl.innerHTML;
            $('#edit_filter_modal').remove();
        }
    }

    edit_filter_modal.setContent(edit_filter_template_content);

    edit_filter_modal.addFooterBtn(
        hm_trans('Save'),
        'btn-primary ms-auto',
        async function () {
            let result = save_filter(current_account());
            if (result) {
                edit_filter_modal.hide();
            }
        },
    );

    edit_filter_modal.addFooterBtn(
        hm_trans('Convert to code'),
        'btn-warning',
        async function () {
            let result = save_filter(current_account(), true);
            if (result) {
                edit_filter_modal.hide();
            }
        },
    );

    // Add Dry Run button only when creating filter from message list
    if (isFromMessageList) {
        edit_filter_modal.addFooterBtn(
            hm_trans('Dry Run'),
            'btn-secondary',
            async function () {
                dryRunFilterFromModal();
            },
        );
    }

    return edit_filter_modal;
}

function sieveFiltersPageHandler() {
    const getCurrentAccount = () => current_account;
    const getIsEditingFilter = () => is_editing_filter;
    const getCurrentEditingFilterName = () => current_editing_filter_name;
    const getEditScriptModal = () => edit_script_modal;
    /**************************************************************************************
         *                             BOOTSTRAP SCRIPT MODAL
         **************************************************************************************/
    const save_filter_inner = createSaveFilter({
        getCurrentAccount,
        getIsEditingFilter,
        getCurrentEditingFilterName,
        getEditScriptModal,
        isFilterFromCustomActions: false,
    });

    const edit_filter_modal = createEditFilterModal(
        save_filter_inner,
        getCurrentAccount,
    );

    var edit_script_modal = new Hm_Modal({
        size: 'xl',
        modalId: 'myEditScript'
    });

    // set content
    edit_script_modal.setContent(document.querySelector('#edit_script_modal').innerHTML);
    $('#edit_script_modal').remove();

    // add a button
    edit_script_modal.addFooterBtn('Save', 'btn-primary', async function () {
        save_script(current_account);
    });

    /**************************************************************************************
     * Initialize sieve button events
     **************************************************************************************/
    hm_sieve_button_events(edit_filter_modal, edit_script_modal);

    const save_script = Hm_Filters.save_script;
    // const save_filter = Hm_Filters.save_filter;

    load_sieve_filters('ajax_account_sieve_filters');
}

function get_list_block_sieve() {
    sessionStorage.removeItem('list_blocked');
    let detail = Hm_Utils.parse_folder_path(hm_list_path());
    let list_blocked_senders = [];
    if (getPageNameParam() == 'message_list' && detail) {
        Hm_Ajax.request(
            [
                { name: 'hm_ajax_hook', value: 'ajax_list_block_sieve' },
                { name: 'imap_server_id', 'value': detail.server_id},
            ],
            function (res) {
                if (res.ajax_list_block_sieve) {
                    sessionStorage.setItem('list_blocked', res.ajax_list_block_sieve);
                }
            }
        );
    }
};

function populateFilterFromDraft(filterDraft) {
    $('.sieve_list_conditions_modal').empty();
    $('.filter_actions_modal_table').empty();
    $('.sieve_match_mode').remove();

    (filterDraft.from || []).forEach((fromVal) => {
        add_filter_condition();

        $('.add_condition_sieve_filters').last().val('from').trigger('change');

        let op = 'Matches';
        if (filterDraft.from_filter_type === 'matches') op = 'Matches';
        else if (filterDraft.from_filter_type === 'not_matches')
            op = '!Matches';
        $('.condition_options').last().val(op);

        $('[name^=sieve_selected_option_value]').last().val(fromVal);
    });

    if (filterDraft.use_subject) {
        (filterDraft.subject_contains || []).forEach((subjectVal) => {
            add_filter_condition();

            $('.add_condition_sieve_filters')
                .last()
                .val('subject')
                .trigger('change');

            let op = 'Contains';
            if (filterDraft.subject_filter_type === 'not_contains')
                op = '!Contains';

            $('.condition_options').last().val(op);

            $('[name^=sieve_selected_option_value]').last().val(subjectVal);
        });
    }

    if ($('.filter_actions_modal_table tr').length === 0) {
        add_filter_action();
    }

    window.setTimeout(function () {
        add_filter_match_mode();
    }, 0);
}

function collectChips(container) {
    return $(container)
        .find('.chip')
        .map((_, el) => $(el).attr('data-value'))
        .get();
}

let current_mailbox_for_filter;
let edit_filter_modal_for_custom_actions;
let edit_filter_template_content;

function createFilterFromList(launcherModal) {
    const froms = collectChips('#filter-from-list');
    const subjects = collectChips('#filter-subject-list');

    const subjectFilterType = $(
        "input[name='subjectFilterType']:checked",
    ).val();
    const fromFilterType = $("input[name='fromFilterType']:checked").val();

    // Use the stored mailbox from the button click
    const mailboxName = current_mailbox_for_filter;
    current_account = mailboxName;
    current_account_element = find_account_element(mailboxName);

    const filterDraft = {
        from: froms,
        subject_contains: subjects,
        use_subject: subjectFilterType !== 'any',
        subject_filter_type: subjectFilterType,
        from_filter_type: fromFilterType,
    };

    const getCurrentAccount = function () {
        return mailboxName;
    };
    const getIsEditingFilter = () => false;
    const getCurrentEditingFilterName = () => '';
    const getEditScriptModal = () => {};

    const save_filter_inner = createSaveFilter({
        getCurrentAccount,
        getIsEditingFilter,
        getCurrentEditingFilterName,
        getEditScriptModal,
        isFilterFromCustomActions: true,
    });

    const save_filter = function (imap_account, gen_script = false) {
        return save_filter_inner(gen_script);
    };

    // Dispose previous modal if it exists
    if (edit_filter_modal_for_custom_actions) {
        try {
            const existingModal = document.getElementById('myEditFilterModal');
            if (existingModal) {
                const bsModal = bootstrap.Modal.getInstance(existingModal);
                if (bsModal) bsModal.dispose();
                existingModal.remove();
            }
        } catch (e) {
            // Ignore errors during cleanup
        }
    }

    edit_filter_modal_for_custom_actions = createEditFilterModal(
        save_filter,
        getCurrentAccount,
        { isFromMessageList: true },
    );
    edit_filter_modal_for_custom_actions.open();
    launcherModal.hide();

    // Remove any previous dry run results
    $('.dry-run-results').remove();

    populateFilterFromDraft(filterDraft);
}

// Dry run filter using conditions from the edit filter modal
function dryRunFilterFromModal() {
    // TODO: Future improvement - Fetch 100-200 messages from server via AJAX
    // for more thorough dry run testing. Would need:
    // - New AJAX endpoint: ajax_sieve_get_messages_for_dry_run
    // - Parameters: imap_server_id, folder, limit (100-200)
    // - Return: array of {uid, from_email, subject, to, cc} for each message
    // - Show loading spinner while fetching
    // - Display "Tested against X of Y messages in folder"
    // Current approach: Test against visible messages only (fast, no server load)

    // Get all visible messages from message table
    const selectedMessages = [];    
    $('.message_table tbody tr').each(function () {
        const $row = $(this);
        const uid = $row.data('uid');
        
        // Skip rows without data (e.g., header rows or empty rows)
        if (!uid) {
            return;
        }

        selectedMessages.push({
            uid: uid,
            from_email: ($row.find('td.from').data('title') || '')
                .trim()
                .toLowerCase(),
            subject: (
                $row.find('td.subject a').attr('title') || ''
            ).toLowerCase(),
        });
    });

    if (selectedMessages.length === 0) {
        Hm_Notices.show(hm_trans('No messages to test'), 'warning');
        return;
    }

    // Get conditions from the edit filter modal
    const conditions = [];
    $('select[name^=sieve_selected_conditions_field]').each(function (idx) {
        const field = $(this).val();
        const type = $('select[name^=sieve_selected_conditions_options]')
            .eq(idx)
            .val();
        const value = $('input[name^=sieve_selected_option_value]')
            .eq(idx)
            .val();
        if (value) {
            conditions.push({ field, type, value: value.toLowerCase() });
        }
    });

    if (conditions.length === 0) {
        showErrorMsg(
            "Please add at least one condition to dry run the filter",
            ".sieve-filter-conditions-block",
            10000
        );
        return;
    }
    const testType = $('.modal_sieve_filter_test').val(); // ANYOF or ALLOF

    // Test each message against filter conditions
    const matchedMessages = [];
    const unmatchedMessages = [];

    selectedMessages.forEach((msg) => {
        const conditionResults = conditions.map((cond) => {
            let fieldValue = '';
            if (cond.field === 'from') {
                fieldValue = msg.from_email;
            } else if (cond.field === 'subject') {
                fieldValue = msg.subject;
            } else if (cond.field === 'to' || cond.field === 'to_or_cc') {
                fieldValue = ''; // Can't test these without fetching message
            }

            let matches = false;
            if (cond.type === 'Contains') {
                matches = fieldValue.includes(cond.value);
            } else if (cond.type === '!Contains') {
                matches = !fieldValue.includes(cond.value);
            } else if (cond.type === 'Matches') {
                matches =
                    fieldValue === cond.value ||
                    fieldValue.includes(cond.value);
            } else if (cond.type === '!Matches') {
                matches =
                    fieldValue !== cond.value &&
                    !fieldValue.includes(cond.value);
            } else if (cond.type === 'Regex') {
                try {
                    matches = new RegExp(cond.value, 'i').test(fieldValue);
                } catch (e) {
                    matches = false;
                }
            } else if (cond.type === '!Regex') {
                try {
                    matches = !new RegExp(cond.value, 'i').test(fieldValue);
                } catch (e) {
                    matches = true;
                }
            }
            return matches;
        });

        // Apply ALLOF (AND) or ANYOF (OR) logic
        let overallMatch = false;
        if (testType === 'ALLOF') {
            overallMatch = conditionResults.every((r) => r);
        } else {
            overallMatch = conditionResults.some((r) => r);
        }

        if (overallMatch) {
            matchedMessages.push(msg);
        } else {
            unmatchedMessages.push(msg);
        }
    });

    // Display results in a notice or modal section
    let resultHtml =
        '<div class="dry-run-results mt-3 p-3 border rounded bg-light" style="overflow-wrap: break-word; word-break: break-word; max-width: 100%;">';
    resultHtml +=
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
        '<h6 class="fw-bold mb-0"><i class="bi bi-lightning me-2"></i>' +
        hm_trans('Filter Match Preview for Visible Messages') +
        '</h6>' +
        '<button type="button" class="btn btn-sm btn-outline-secondary dry-run-close" aria-label="Close">' +
        '<i class="bi bi-x"></i>' +
        '</button></div>';

    if (matchedMessages.length > 0) {
        resultHtml += '<div class="alert alert-success py-2 mb-2">';
        resultHtml +=
            '<strong>' +
            matchedMessages.length +
            '</strong> ' +
            hm_trans('message(s) would match this filter') +
            ':';
        resultHtml += '<ul class="mb-0 mt-1 small" style="list-style: none; padding-left: 1rem;">';
        matchedMessages.forEach((msg) => {
            // Truncate the combined display to max 80 characters
            const fullLine = msg.from_email + ' - ' + msg.subject;
            const truncatedLine =
                fullLine.length > 80
                    ? fullLine.substring(0, 80) + '...'
                    : fullLine;
            resultHtml +=
                '<li style="overflow-wrap: break-word; word-break: break-word;">' +
                escapeHtml(truncatedLine) +
                '</li>';
        });
        resultHtml += '</ul></div>';

        // Only show unmatched messages if there are also matched ones (to show the contrast)
        if (unmatchedMessages.length > 0) {
            resultHtml += '<div class="alert alert-secondary py-2">';
            resultHtml +=
                '<strong>' +
                unmatchedMessages.length +
                '</strong> ' +
                hm_trans('message(s) would NOT match') +
                ':';
            resultHtml += '<ul class="mb-0 mt-1 small" style="list-style: none; padding-left: 1rem;">';
            unmatchedMessages.forEach((msg) => {
                // Truncate the combined display to max 80 characters
                const fullLine = msg.from_email + ' - ' + msg.subject;
                const truncatedLine =
                    fullLine.length > 80
                        ? fullLine.substring(0, 80) + '...'
                        : fullLine;
                resultHtml +=
                    '<li style="overflow-wrap: break-word; word-break: break-word;">' +
                    escapeHtml(truncatedLine) +
                    '</li>';
            });
            resultHtml += '</ul></div>';
        }
    } else {
        resultHtml +=
            '<div class="alert alert-warning py-2 mb-2">' +
            hm_trans('No messages would match this filter') +
            ' (' +
            selectedMessages.length +
            ' ' +
            hm_trans('tested') +
            ')' +
            '</div>';
    }

    resultHtml += '</div>';

    // Remove any previous results and add new ones to the modal
    $('.dry-run-results').remove();
    $('#myEditFilterModal .modal-body').append(resultHtml);

    // Scroll to results
    const resultsElement = document.querySelector('.dry-run-results');
    if (resultsElement) {
        resultsElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Add close button handler
    $('.dry-run-close').on('click', function () {
        $('.dry-run-results').remove();
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

$(function () {
    $(document).on('change', '#block_action', function(e) {
        if ($(this).val() == 'reject_with_message') {
            $('<div id="reject_message"><label>'+hm_trans('Message')+'</label><textarea id="reject_message_textarea"></textarea></div>').insertAfter($(this));
        } else {
            $('#reject_message').remove();
        }
    });

    $(document).on("submit", "#create-filter-form", function (e) {
        e.preventDefault();
        current_account = $(this).attr("account");
        current_account_element = find_account_element(current_account);

        const edit_filter_modal = createEditFilterModal(
            Hm_Filters.save_filter,
            function () {
                return current_account;
            },
        );
        edit_filter_modal.setTitle("Add Filter for message like this");
        const add_filter_condition = Hm_Filters.add_filter_condition;
        const add_filter_action = Hm_Filters.add_filter_action;

        const $form = $(this);
        const data = {};

        if ($form.find("#use_from").is(":checked"))
            data["from"] = $form.find('input[name="from"]').val();
        if ($form.find("#use_to").is(":checked"))
            data["to"] = $form.find('input[name="to"]').val();
        if ($form.find("#use_subject").is(":checked"))
            data["subject"] = $form.find('input[name="subject"]').val();
        if ($form.find("#use_reply").is(":checked"))
            data["reply-to"] = $form.find('input[name="reply-to"]').val();

        if ($.isEmptyObject(data)) {
            Hm_Notices.show(
                "Please check at least one condition to create a filter.",
                "#create-filter-form"
            );
            return;
        }

        $('.modal_sieve_filter_name').val('');
        $('.modal_sieve_filter_priority').val('');
        $('.modal_sieve_filter_test').val('ALLOF');
        $('.sieve_list_conditions_modal').empty();
        $('.filter_actions_modal_table').empty();
        $('#stop_filtering').prop('checked', false);

        edit_filter_modal.open();

        const allFields = hm_sieve_condition_fields();
        const availableFields = [
            ...allFields.Message.map((f) => f.name),
            ...allFields.Header.map((f) => f.name),
        ];

        for (const [key, value] of Object.entries(data)) {
            // If key is not in available fields, skip it
            if (!availableFields.includes(key)) {
            continue;
            }

            add_filter_condition();

            const $lastRow = $(".sieve_list_conditions_modal tr").last();
            const $selectField = $lastRow.find(
                ".add_condition_sieve_filters"
            );
            const $selectOp = $lastRow.find(".condition_options");
            const $inputVal = $lastRow.find(
                'input[name="sieve_selected_option_value[]"]'
            );
            $selectField.val(key);
            $selectOp.val("Contains");
            $inputVal.val(value);
        }

        add_filter_match_mode();

        if (data["reply-to"]) {
            add_filter_action("autoreply");

            const $lastRow = $(".filter_actions_modal_table tr").last();
            const $select = $lastRow.find(".sieve_actions_select");
            $select.val("autoreply").trigger("change");

            // Focus the input field for the message
            const $input = $lastRow.find(
                'input[name="sieve_selected_action_value[]"]'
            );
            if ($input.length) {
                $input.focus();
            }
        } 
    });

    $(document).on('click', '.msg_filter_action', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const filterId = $(this).data('filter-id');
        const imapAccount = $(this).data('imap-account');
        const filterName = $(this).data('filter-name');

        current_mailbox_for_filter = imapAccount;

        // Setup the save filter function for this context
        const getCurrentAccount = () => imapAccount;
        const getIsEditingFilter = () => true;
        const getCurrentEditingFilterName = () => filterId;
        const getEditScriptModal = () => {};

        const save_filter_inner = createSaveFilter({
            getCurrentAccount,
            getIsEditingFilter,
            getCurrentEditingFilterName,
            getEditScriptModal,
            isFilterFromCustomActions: true,
        });

        const save_filter = function (imap_account, gen_script = false) {
            return save_filter_inner(gen_script);
        };

        const edit_filter_modal = createEditFilterModal(
            save_filter,
            getCurrentAccount,
            { isFromMessageList: true },
        );

        // Clear previous content
        $('.modal_sieve_filter_name').val(filterName);
        $('.modal_sieve_filter_priority').val('');
        $('.sieve_list_conditions_modal').html('');
        $('.filter_actions_modal_table').html('');
        $('#stop_filtering').prop('checked', false);

        // Fetch filter details from server
        Hm_Ajax.request(
            [
                { name: 'hm_ajax_hook', value: 'ajax_sieve_edit_filter' },
                { name: 'imap_account', value: imapAccount },
                { name: 'sieve_script_name', value: filterId },
            ],
            function (res) {
                const conditions = JSON.parse(JSON.parse(res.conditions));
                const actions = JSON.parse(JSON.parse(res.actions));
                const test_type = res.test_type;

                $('.modal_sieve_filter_test').val(test_type);

                // Populate conditions
                conditions.forEach(function (condition) {
                    add_filter_condition();
                    $('.add_condition_sieve_filters')
                        .last()
                        .val(condition.condition);
                    $('.add_condition_sieve_filters').last().trigger('change');
                    $('.condition_options').last().val(condition.type);
                    $('[name^=sieve_selected_extra_option_value]')
                        .last()
                        .val(condition.extra_option_value);
                    if (
                        $('[name^=sieve_selected_option_value]')
                            .last()
                            .is('input')
                    ) {
                        $('[name^=sieve_selected_option_value]')
                            .last()
                            .val(condition.value);
                    }
                });

                // Populate actions
                actions.forEach(function (action) {
                    if (action.action === 'stop') {
                        $('#stop_filtering').prop('checked', true);
                    } else {
                        add_filter_action(action.value);
                        $('.sieve_actions_select').last().val(action.action);
                        $('.sieve_actions_select').last().trigger('change');
                        $('[name^=sieve_selected_extra_action_value]')
                            .last()
                            .val(action.extra_option_value);
                        if (
                            $('[name^=sieve_selected_action_value]')
                                .last()
                                .is('input')
                        ) {
                            $('[name^=sieve_selected_action_value]')
                                .last()
                                .val(action.value);
                        } else if (
                            $('[name^=sieve_selected_action_value]')
                                .last()
                                .is('textarea')
                        ) {
                            $('[name^=sieve_selected_action_value]')
                                .last()
                                .text(action.value);
                        }
                    }
                });

                edit_filter_modal.setTitle(
                    hm_trans('Edit Filter') + ': ' + filterName,
                );
                edit_filter_modal.open();
            },
        );
    });

    hm_sieve_button_events();

    $(document).on('click', '.remove-chip', function () {
        $(this).parent().remove();
    });
});
