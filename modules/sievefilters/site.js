/**
 * Possible Sieve fields
 * @type {{Message: [{name: string, options: string[], type: string, selected: boolean},{name: string, options: string[], type: string},{name: string, options: string[], type: string}], Header: [{name: string, options: string[], type: string},{name: string, options: string[], type: string},{name: string, options: string[], type: string},{name: string, options: string[], type: string}]}}
 */
var hm_sieve_condition_fields = function() {
    return {
        'Message': [
            {
                name: 'subject',
                description: 'Subject',
                type: 'string',
                selected: true,
                options: ['Contains', 'Matches', 'Regex']
            },
            {
                name: 'body',
                description: 'Body',
                type: 'string',
                options: ['Contains', 'Matches', 'Regex']
            },
            {
                name: 'size',
                description: 'Size (KB)',
                type: 'int',
                options: ['Over', 'Under']
            }
        ],
        'Header': [
            {
                name: 'to',
                description: 'To',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex']
            },
            {
                name: 'from',
                description: 'From',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex']
            },
            {
                name: 'cc',
                description: 'CC',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex']
            },
            {
                name: 'to_or_cc',
                description: 'To or CC',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex']
            },
            {
                name: 'bcc',
                description: 'BCC',
                type: 'string',
                extra_option: false,
                options: ['Contains', 'Matches', 'Regex']
            },
            {
                name: 'custom',
                description: 'Custom',
                type: 'string',
                extra_option: true,
                extra_option_description: 'Field Name',
                options: ['Contains', 'Matches', 'Regex']
            }
        ]
    };
};

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
            extra_field: false
        },
        {
            name: 'copy',
            description: 'Copy email to mailbox',
            placeholder: 'Mailbox Name (Folder)',
            type: 'mailbox',
            extra_field: false
        },
        {
            name: 'move',
            description: 'Move email to mailbox',
            placeholder: 'Mailbox Name (Folder)',
            type: 'mailbox',
            extra_field: false
        },
        {
            name: 'flag',
            description: 'Flag',
            placeholder: 'Example: SEEN',
            type: 'select',
            values: ['Seen', 'Answered', 'Flagged', 'Deleted', 'Draft', 'Recent'],
            extra_field: false
        },
        {
            name: 'addflag',
            description: 'Add Flag',
            placeholder: 'Example: SEEN',
            type: 'select',
            values: ['Seen', 'Answered', 'Flagged', 'Deleted', 'Draft', 'Recent'],
            extra_field: false
        },
        {
            name: 'removeflag',
            description: 'Remove Flag',
            placeholder: 'Example: SEEN',
            type: 'select',
            values: ['Seen', 'Answered', 'Flagged', 'Deleted', 'Draft', 'Recent'],
            extra_field: false
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
            extra_field: false
        },
        {
            name: 'discard',
            description: 'Discard',
            type: 'none',
            extra_field: false
        },
        {
            name: 'autoreply',
            placeholder: 'Reply Message',
            description: 'Reply Message',
            type: 'text',
            extra_field: true,
            extra_field_type: 'string',
            extra_field_placeholder: 'Subject'
        }
    ];
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
                if (/^(Sender|Domain) Behavior Changed$/.test(res.router_user_msgs[0])) {
                    window.location = window.location;
                }
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

    $(document).on('click', '.block_domain_button', function(e) {
        e.preventDefault();
        let sender = $(this).parent().parent().children().html();
        let elem = $(this);
        Hm_Ajax.request(
            [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_block_domain'},
                {'name': 'imap_server_id', 'value': $(this).attr('data-mailbox_id')},
                {'name': 'sender', 'value': sender}
            ],
            function(res) {
                window.location = window.location;
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

    $('.sievefilters_accounts_title').on("click", function () {
        $(this).parent().find('.sievefilters_accounts').toggleClass('d-none');
    });
}

if (hm_page_name() === 'sieve_filters') {
    /**************************************************************************************
     *                             BOOTSTRAP SCRIPT MODAL
     **************************************************************************************/
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
     *                             BOOTSTRAP SIEVE FILTER MODAL
     **************************************************************************************/
    var edit_filter_modal = new Hm_Modal({
        size: 'xl',
        modalId: 'myEditFilterModal',
    });

    // set content
    edit_filter_modal.setContent(document.querySelector('#edit_filter_modal').innerHTML);
    $('#edit_filter_modal').remove();

    // add a button
    edit_filter_modal.addFooterBtn('Save', 'btn-primary ms-auto', async function () {
        let result = save_filter(current_account);
        if (result) {
            edit_filter_modal.hide();
        }
    });

    // add another button
    edit_filter_modal.addFooterBtn('Convert to code', 'btn-warning', async function () {
        let result = save_filter(current_account, true);
        if (result) {
            edit_filter_modal.hide();
        }
    });


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

    /**************************************************************************************
     *                                    FUNCTIONS
     **************************************************************************************/
    function save_filter(imap_account, gen_script = false) {
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
            Hm_Utils.add_sys_message(hm_trans('You must provide at least one condition'), 'danger');
            return false;
        }

        Hm_Utils.clear_sys_messages();
        conditions.forEach(function (elem, key) {
            if (conditions_value[idx] === "" && conditions_value[idx] !== 'none') {
                let order = ordinal_number(key + 1);
                let previous_messages = $('.sys_messages').html();
                previous_messages += previous_messages ? '<br>': '';
                Hm_Utils.add_sys_message('The ' + order + ' condition (' + elem + ') must be provided', 'danger');
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
            Hm_Utils.add_sys_message(hm_trans('You must provide at least one action'), 'danger');
            return false;
        }

        idx = 0;
        actions_type.forEach(function (elem, key) {
            console.log(actions_field_type[idx])
            if (actions_value[idx] === "" && actions_field_type[idx] !== 'hidden') {
                let order = ordinal_number(key + 1);
                let previous_messages = $('.sys_messages').html();
                previous_messages += previous_messages ? '<br>': '';
                Hm_Utils.add_sys_message('The ' + order + ' action (' + elem + ') must be provided', 'danger');
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
            Hm_Utils.add_sys_message(hm_trans('Filter name is required'), 'danger');
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

    function save_script(imap_account) {
        if ($('.modal_sieve_script_name').val() === "") {
            Hm_Utils.add_sys_message(hm_trans('You must provide a name for your script'), 'danger');
            return false;
        }
        if ($('.modal_sieve_script_textarea').val() === "") {
            Hm_Utils.add_sys_message(hm_trans('Empty script'), 'danger');
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

    /**************************************************************************************
     *                                      MODAL EVENTS
     **************************************************************************************/
    $('.sievefilters_accounts_title').on("click", function () {
        $(this).parent().find('.sievefilters_accounts').toggleClass('d-none');
    });
    $('.add_filter').on('click', function () {
        edit_filter_modal.setTitle('Add Filter');
        $('.modal_sieve_filter_priority').val('');
        $('.modal_sieve_filter_test').val('ALLOF');
        $('#stop_filtering').prop('checked', false);
        current_account = $(this).attr('account');
        edit_filter_modal.open();

        // Reset the form fields when opening the modal
        $(".modal_sieve_filter_name").val('');
        $(".modal_sieve_script_priority").val('');
        $(".sieve_list_conditions_modal").empty();
        $(".filter_actions_modal_table").empty();
    });
    $('.add_script').on('click', function () {
        edit_script_modal.setTitle('Add Script');
        $('.modal_sieve_script_textarea').val('');
        $('.modal_sieve_script_name').val('');
        $('.modal_sieve_script_priority').val('');
        is_editing_script = false;
        current_editing_script_name = '';
        current_account = $(this).attr('account');
        edit_script_modal.open();
    });
    $('.edit_filter').on('click', function (e) {
        e.preventDefault();
        let script_name = $(this).parent().parent().children().next().html();
        edit_filter_modal.setTitle(script_name);
        edit_filter_modal.open();
    });

    /**
     * Delete action Button
     */
    $(document).on('click', '.delete_else_action_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    /**
     * Delete action Button
     */
    $(document).on('click', '.delete_action_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    /**
     * Delete Condition Button
     */
    $(document).on('click', '.delete_condition_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    function add_filter_condition() {
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
            '                            <tr>' +
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

    /**
     * Add Condition Button
     */
    $(document).on('click', '.sieve_add_condition_modal_button', function () {
        add_filter_condition();
    });

    function add_filter_action(default_value = '') {
        let possible_actions_html = '';

        hm_sieve_possible_actions().forEach(function (value) {
            if (value.selected === true) {
                possible_actions_html += '<option selected value="'+value.name+'">' + value.description + '</option>';
                return;
            }
            possible_actions_html += '<option value="'+value.name+'">' + value.description + '</option>';
        });
        let extra_options = '<td class="col-sm-3"><input type="hidden" class="condition_extra_action_value form-control form-control-sm" name="sieve_selected_extra_action_value[]" /></td>';
        $('.filter_actions_modal_table').append(
            '<tr class="border" default_value="'+default_value+'">' +
            '   <td class="col-sm-3">' +
            '       <select class="sieve_actions_select form-control form-control-sm" name="sieve_selected_actions[]">' +
            '          ' + possible_actions_html +
            '       </select>' +
            '    </td>' +
            extra_options +
            '    <td class="col-sm-5">' +
            '    <input type="hidden" name="sieve_selected_action_value[]" value="">' +
            '    </input>' +
            '    <td class="col-sm-1 text-end align-middle">' +
            '           <a href="#" class="delete_action_modal_button btn btn-sm btn-secondary">Delete</a>' +
            '    </td>' +
            '</tr>'
        );
    }

    /**
     * Add Action Button
     */
    $(document).on('click', '.filter_modal_add_action_btn', function () {
        add_filter_action();
    });

    /**
     * Add Else Action Button
     */
    $(document).on('click', '.filter_modal_add_else_action_btn', function () {
        let possible_actions_html = '';

        hm_sieve_possible_actions().forEach(function (value) {
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
    $(document).on('change', '.sieve_actions_select', function () {
        let tr_elem = $(this).parent().parent();
        console.log(tr_elem.attr('default_value'));
        let elem = $(this).parent().next().next();
        let elem_extra = $(this).parent().next().find('.condition_extra_action_value');
        let action_name = $(this).val();
        let selected_action;
        hm_sieve_possible_actions().forEach(function (action) {
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
    $(document).on('change', '.add_condition_sieve_filters', function () {
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
    $(document).on('click', '.delete_filter', function (e) {
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
     * Delete script event
     */
    $(document).on('click', '.delete_script', function (e) {
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
    $(document).on('click', '.edit_script', function (e) {
        e.preventDefault();
        let obj = $(this);
        edit_script_modal.setTitle('Edit Script');
        current_account = $(this).attr('account');
        is_editing_script = true;
        current_editing_script_name = $(this).attr('script_name');
        current_account = $(this).attr('imap_account');
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
    $(document).on('click', '.edit_filter', function (e) {
        e.preventDefault();
        let obj = $(this);
        current_account = $(this).attr('account');
        is_editing_filter = true;
        current_editing_filter_name = $(this).attr('script_name');
        current_account = $(this).attr('imap_account');
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
            }
        );
    });
}

function sieveFiltersPageHandler() {
    let is_editing_script = false;
    let current_editing_script_name = '';
    let is_editing_filter = false;
    let current_editing_filter_name = '';
    let current_account;
    /**************************************************************************************
         *                             BOOTSTRAP SCRIPT MODAL
         **************************************************************************************/
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
     *                             BOOTSTRAP SIEVE FILTER MODAL
     **************************************************************************************/
    var edit_filter_modal = new Hm_Modal({
        size: 'xl',
        modalId: 'myEditFilterModal',
    });

    // set content
    edit_filter_modal.setContent(document.querySelector('#edit_filter_modal').innerHTML);
    $('#edit_filter_modal').remove();

    // add a button
    edit_filter_modal.addFooterBtn('Save', 'btn-primary ms-auto', async function () {
        let result = save_filter(current_account);
        if (result) {
            edit_filter_modal.hide();
        }
    });

    // add another button
    edit_filter_modal.addFooterBtn('Convert to code', 'btn-warning', async function () {
        let result = save_filter(current_account, true);
        if (result) {
            edit_filter_modal.hide();
        }
    });


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

    /**************************************************************************************
     *                                    FUNCTIONS
     **************************************************************************************/
    function save_filter(imap_account, gen_script = false) {
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
            Hm_Utils.add_sys_message(hm_trans('You must provide at least one condition'), 'danger');
            return false;
        }

        Hm_Utils.clear_sys_messages();
        conditions.forEach(function (elem, key) {
            if (conditions_value[idx] === "" && conditions_value[idx] !== 'none') {
                let order = ordinal_number(key + 1);
                let previous_messages = $('.sys_messages').html();
                previous_messages += previous_messages ? '<br>': '';
                Hm_Utils.add_sys_message('The ' + order + ' condition (' + elem + ') must be provided', 'danger');
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
            Hm_Utils.add_sys_message(hm_trans('You must provide at least one action'), 'danger');
            return false;
        }

        idx = 0;
        actions_type.forEach(function (elem, key) {
            console.log(actions_field_type[idx])
            if (actions_value[idx] === "" && actions_field_type[idx] !== 'hidden') {
                let order = ordinal_number(key + 1);
                let previous_messages = $('.sys_messages').html();
                previous_messages += previous_messages ? '<br>': '';
                Hm_Utils.add_sys_message('The ' + order + ' action (' + elem + ') must be provided', 'danger');
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
            Hm_Utils.add_sys_message(hm_trans('Filter name is required'), 'danger');
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

    function save_script(imap_account) {
        if ($('.modal_sieve_script_name').val() === "") {
            Hm_Utils.add_sys_message(hm_trans('You must provide a name for your script'), 'danger');
            return false;
        }
        if ($('.modal_sieve_script_textarea').val() === "") {
            Hm_Utils.add_sys_message(hm_trans('Empty script'), 'danger');
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

    /**************************************************************************************
     *                                      MODAL EVENTS
     **************************************************************************************/
    $('.sievefilters_accounts_title').on("click", function () {
        $(this).parent().find('.sievefilters_accounts').toggleClass('d-none');
    });
    $('.add_filter').on('click', function () {
        edit_filter_modal.setTitle('Add Filter');
        $('.modal_sieve_filter_priority').val('');
        $('.modal_sieve_filter_test').val('ALLOF');
        $('#stop_filtering').prop('checked', false);
        current_account = $(this).attr('account');
        edit_filter_modal.open();

        // Reset the form fields when opening the modal
        $(".modal_sieve_filter_name").val('');
        $(".modal_sieve_script_priority").val('');
        $(".sieve_list_conditions_modal").empty();
        $(".filter_actions_modal_table").empty();
    });
    $('.add_script').on('click', function () {
        edit_script_modal.setTitle('Add Script');
        $('.modal_sieve_script_textarea').val('');
        $('.modal_sieve_script_name').val('');
        $('.modal_sieve_script_priority').val('');
        is_editing_script = false;
        current_editing_script_name = '';
        current_account = $(this).attr('account');
        edit_script_modal.open();
    });
    $('.edit_filter').on('click', function (e) {
        e.preventDefault();
        let script_name = $(this).parent().parent().children().next().html();
        edit_filter_modal.setTitle(script_name);
        edit_filter_modal.open();
    });

    /**
     * Delete action Button
     */
    $(document).on('click', '.delete_else_action_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    /**
     * Delete action Button
     */
    $(document).on('click', '.delete_action_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    /**
     * Delete Condition Button
     */
    $(document).on('click', '.delete_condition_modal_button', function (e) {
        e.preventDefault();
        $(this).parent().parent().remove();
    });

    function add_filter_condition() {
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
            '                            <tr>' +
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

    /**
     * Add Condition Button
     */
    $(document).on('click', '.sieve_add_condition_modal_button', function () {
        add_filter_condition();
    });

    function add_filter_action(default_value = '') {
        let possible_actions_html = '';

        hm_sieve_possible_actions().forEach(function (value) {
            if (value.selected === true) {
                possible_actions_html += '<option selected value="'+value.name+'">' + value.description + '</option>';
                return;
            }
            possible_actions_html += '<option value="'+value.name+'">' + value.description + '</option>';
        });
        let extra_options = '<td class="col-sm-3"><input type="hidden" class="condition_extra_action_value form-control form-control-sm" name="sieve_selected_extra_action_value[]" /></td>';
        $('.filter_actions_modal_table').append(
            '<tr class="border" default_value="'+default_value+'">' +
            '   <td class="col-sm-3">' +
            '       <select class="sieve_actions_select form-control form-control-sm" name="sieve_selected_actions[]">' +
            '          ' + possible_actions_html +
            '       </select>' +
            '    </td>' +
            extra_options +
            '    <td class="col-sm-5">' +
            '    <input type="hidden" name="sieve_selected_action_value[]" value="">' +
            '    </input>' +
            '    <td class="col-sm-1 text-end align-middle">' +
            '           <a href="#" class="delete_action_modal_button btn btn-sm btn-secondary">Delete</a>' +
            '    </td>' +
            '</tr>'
        );
    }

    /**
     * Add Action Button
     */
    $(document).on('click', '.filter_modal_add_action_btn', function () {
        add_filter_action();
    });

    /**
     * Add Else Action Button
     */
    $(document).on('click', '.filter_modal_add_else_action_btn', function () {
        let possible_actions_html = '';

        hm_sieve_possible_actions().forEach(function (value) {
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
    $(document).on('change', '.sieve_actions_select', function () {
        let tr_elem = $(this).parent().parent();
        console.log(tr_elem.attr('default_value'));
        let elem = $(this).parent().next().next();
        let elem_extra = $(this).parent().next().find('.condition_extra_action_value');
        let action_name = $(this).val();
        let selected_action;
        hm_sieve_possible_actions().forEach(function (action) {
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
    $(document).on('change', '.add_condition_sieve_filters', function () {
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
    $(document).on('click', '.delete_filter', function (e) {
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
     * Delete script event
     */
    $(document).on('click', '.delete_script', function (e) {
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
    $(document).on('click', '.edit_script', function (e) {
        e.preventDefault();
        let obj = $(this);
        edit_script_modal.setTitle('Edit Script');
        current_account = $(this).attr('account');
        is_editing_script = true;
        current_editing_script_name = $(this).attr('script_name');
        current_account = $(this).attr('imap_account');
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
    $(document).on('click', '.edit_filter', function (e) {
        e.preventDefault();
        let obj = $(this);
        current_account = $(this).attr('account');
        is_editing_filter = true;
        current_editing_filter_name = $(this).attr('script_name');
        current_account = $(this).attr('imap_account');
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
            }
        );
    });
}

$(function () {
    $(document).on('change', '#block_action', function(e) {
        if ($(this).val() == 'reject_with_message') {
            $('<div id="reject_message"><label>'+hm_trans('Message')+'</label><textarea id="reject_message_textarea"></textarea></div>').insertAfter($(this));
        } else {
            $('#reject_message').remove();
        }
    });
});
