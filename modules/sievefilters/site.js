$(function () {

    let condition_fields;
    let possible_actions;
    let is_editing_script = false;
    let current_editing_script_name = '';
    let is_editing_filter = false;
    let current_editing_filter_name = '';
    let current_account;

    if (hm_page_name() === 'block_list') {
        $(document).on('change', '.select_default_behaviour', function(e) {
            let elem = $(this);
            Hm_Ajax.request(
                [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_block_change_behaviour'},
                    {'name': 'selected_behaviour', 'value': elem.val()},
                    {'name': 'imap_server_id', 'value': elem.attr('imap_account')}
                ],
                function(res) {
                    console.log(res);
                }
            );
        });

        
        $(document).on('click', '.unblock_button', function(e) {
           e.preventDefault();
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
                    window.location = window.location;
                }
            );
        });

        $('.sievefilters_accounts_title').on("click", function () {
            $(this).parent().find('.sievefilters_accounts').toggle();
        });
    }

    if (hm_page_name() === 'sieve_filters') {
        /**
         * Possible Sieve fields
         * @type {{Message: [{name: string, options: string[], type: string, selected: boolean},{name: string, options: string[], type: string},{name: string, options: string[], type: string}], Header: [{name: string, options: string[], type: string},{name: string, options: string[], type: string},{name: string, options: string[], type: string},{name: string, options: string[], type: string}]}}
         */
        condition_fields = {
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
        }

        /**
         * Possible Sieve actions
         * @type {[{name: string, description: string, placeholder: string, type: string, selected: boolean},{name: string, description: string, placeholder: string, type: string},{name: string, description: string, type: string},{name: string, description: string, type: string},{name: string, description: string, placeholder: string, type: string}]}
         */
        possible_actions = [
            {
                name: 'keep',
                description: 'Deliver (Keep)',
                type: 'none',
                extra_field: false
            },
            {
                name: 'stop',
                description: 'Stop Filtering',
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
                placeholder: 'mail@mail.com',
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
        ]

        /**************************************************************************************
         *                             TINGLE SCRIPT MODAL
         **************************************************************************************/
        var edit_script_modal = new tingle.modal({
            footer: true,
            stickyFooter: false,
            closeMethods: ['overlay', 'button', 'escape'],
            closeLabel: "Close",
            cssClass: ['custom-class-1', 'custom-class-2'],
            onOpen: function () {
            },
            onClose: function () {
            },
            beforeClose: function () {
                // here's goes some logic
                // e.g. save content before closing the modal
                return true; // close the modal
                return false; // nothing happens
            }
        });

        // set content
        edit_script_modal.setContent(document.querySelector('#edit_script_modal').innerHTML);
        $('#edit_script_modal').remove();

        // add a button
        edit_script_modal.addFooterBtn('Save', 'tingle-btn tingle-btn--primary tingle-btn--pull-right', async function () {
            save_script(current_account);
        });

        // add another button
        edit_script_modal.addFooterBtn('Close', 'tingle-btn tingle-btn--default tingle-btn--pull-right', function () {
            // here goes some logic
            edit_script_modal.close();
        });


        /**************************************************************************************
         *                             TINGLE SIEVE FILTER MODAL
         **************************************************************************************/
        var edit_filter_modal = new tingle.modal({
            footer: true,
            stickyFooter: false,
            closeMethods: ['overlay', 'button', 'escape'],
            closeLabel: "Close",
            cssClass: ['custom-class-1', 'custom-class-2'],
            onOpen: function () {
            },
            onClose: function () {
                $(".sieve_list_conditions_modal").html("");
                $(".filter_actions_modal_table").html("");
                $(".modal_sieve_filter_name").val("");
                $(".modal_sieve_filter_priority").val("0");
                $(".modal_sieve_filter_test").val("ALLOF");
            },
            beforeClose: function () {
                // here's goes some logic
                // e.g. save content before closing the modal
                return true; // close the modal
                return false; // nothing happens
            }
        });

        // set content
        edit_filter_modal.setContent(document.querySelector('#edit_filter_modal').innerHTML);
        $('#edit_filter_modal').remove();

        // add a button
        edit_filter_modal.addFooterBtn('Save', 'tingle-btn tingle-btn--primary tingle-btn--pull-right', async function () {
            let result = save_filter(current_account);
            if (result) {
                edit_filter_modal.close();
            }
        });

        // add another button
        edit_filter_modal.addFooterBtn('Close', 'tingle-btn tingle-btn--default tingle-btn--pull-right', function () {
            // here goes some logic
            edit_filter_modal.close();
        });

        /**************************************************************************************
         *                                    FUNCTIONS
         **************************************************************************************/
        function save_filter(imap_account) {
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
                $('.sys_messages').html('<span class="err">You must provide at least one condition</span>');
                Hm_Utils.show_sys_messages();
                return false;
            }

            conditions.forEach(function (elem) {
                if (conditions_value[idx] === "" && conditions_value[idx] !== 'none') {
                    $('.sys_messages').html('<span class="err">All fields of actions and conditions must be provided</span>');
                    Hm_Utils.show_sys_messages();
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
            let actions_field_type = $('input[name^=sieve_selected_action_value]').map(function(idx, elem) {
                return $(elem).attr('type');
            }).get();
            let actions_extra_value = $('input[name^=sieve_selected_extra_action_value]').map(function(idx, elem) {
                return $(elem).val();
            }).get();

            if (actions_type.length === 0) {
                $('.sys_messages').html('<span class="err">You must provide at least one action</span>');
                Hm_Utils.show_sys_messages();
                return false;
            }

            idx = 0;
            actions_type.forEach(function (elem) {
                console.log(actions_field_type[idx])
                if (actions_value[idx] === "" && actions_field_type[idx] !== 'hidden') {
                    $('.sys_messages').html('<span class="err">All fields of actions and conditions must be provided</span>');
                    Hm_Utils.show_sys_messages();
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

            if ($('.modal_sieve_filter_name').val() == "") {
                $('.sys_messages').html('<span class="err">Filter name is required</span>');
                Hm_Utils.show_sys_messages();
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
                    {'name': 'filter_test_type', 'value': $('.modal_sieve_filter_test').val()}
                    ],
                function(res) {
                    window.location = window.location;
                }
            );
        }

        function save_script(imap_account) {
            if ($('.modal_sieve_script_name').val() === "") {
                $('.sys_messages').html('<span class="err">You must provide a name for your script</span>');
                Hm_Utils.show_sys_messages();
                return false;
            }
            if ($('.modal_sieve_script_textarea').val() === "") {
                $('.sys_messages').html('<span class="err">Empty script</span>');
                Hm_Utils.show_sys_messages();
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
            $(this).parent().find('.sievefilters_accounts').toggle();
        });
        $('.add_filter').on('click', function () {
            $('.filter_modal_title').html('Add Filter');
            current_account = $(this).attr('account');
            edit_filter_modal.open();
        });
        $('.add_script').on('click', function () {
            $('.script_modal_title').html('Add Script');
            $('.modal_sieve_script_textarea').html('');
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
            $('.filter_modal_title').html(script_name);
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

            condition_fields.Message.forEach(function (value) {
                if (value.selected === true) {
                    message_fields += '<option selected value="'+value.name+'">' + value.description + '</option>';
                } else {
                    message_fields += '<option value="'+value.name+'">' + value.description + '</option>';
                }
            });
            condition_fields.Header.forEach(function (value) {
                if (value.selected === true) {
                    header_fields += '<option selected value="'+value.name+'">' + value.description + '</option>';
                } else {
                    header_fields += '<option value="'+value.name+'">' + value.description + '</option>';
                }
            });
            let extra_options = '<td style="width: 230px;"><input type="hidden" class="condition_extra_value" name="sieve_selected_extra_option_value[]" /></td>';
            $('.sieve_list_conditions_modal').append(
                '                            <tr>' +
                '                                <td>' +
                '                                    <select class="add_condition_sieve_filters" name="sieve_selected_conditions_field[]" style="width: 200px;">' +
                '                                        <optgroup label="Message">' +
                message_fields +
                '                                        </optgroup>' +
                '                                        <optgroup label="Header">' +
                header_fields +
                '                                        </optgroup>' +
                '                                    </select>' +
                '                                </td>' +
                extra_options +
                '                                <td>' +
                '                                    <select class="condition_options" name="sieve_selected_conditions_options[]">' +
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
                '                                <td style="width: 43%;">' +
                '                                    <input type="text" name="sieve_selected_option_value[]" />' +
                '                                </td>' +
                '                                <td style="vertical-align: middle; width: 50px;">' +
                '                                    <a href="#" class="delete_condition_modal_button">Delete</a>' +
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

            possible_actions.forEach(function (value) {
                if (value.selected === true) {
                    possible_actions_html += '<option selected value="'+value.name+'">' + value.description + '</option>';
                    return;
                }
                possible_actions_html += '<option value="'+value.name+'">' + value.description + '</option>';
            });

            let extra_options = '<td style="width: 230px;"><input type="hidden" class="condition_extra_action_value" name="sieve_selected_extra_action_value[]" /></td>';
            $('.filter_actions_modal_table').append(
                '<tr style="border-bottom-color: black;" default_value="'+default_value+'">' +
                '   <td>' +
                '       <select class="sieve_actions_select" name="sieve_selected_actions[]" style="width: 200px;">' +
                '          ' + possible_actions_html +
                '       </select>' +
                '    </td>' +
                extra_options +
                '    <td style="width: 43%;">' +
                '    <img style="display: none" src="/modules/sievefilters/assets/spinner.gif" />' +
                '    <input type="hidden" name="sieve_selected_action_value[]" value="">' +
                '    </input>' +
                '    <td style="vertical-align: middle; width: 50px;">' +
                '           <a href="#" class="delete_action_modal_button">Delete</a>' +
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

            possible_actions.forEach(function (value) {
                if (value.selected === true) {
                    possible_actions_html += '<option selected value="'+value.name+'">' + value.description + '</option>';
                    return;
                }
                possible_actions_html += '<option value="'+value.name+'">' + value.description + '</option>';
            });

            $('.filter_else_actions_modal_table').append(
                '<tr style="border-bottom-color: black;">' +
                '   <td>' +
                '       <select class="sieve_actions_select">' +
                '          ' + possible_actions_html +
                '       </select>' +
                '    </td>' +
                '    <td>' +
                '    </td>' +
                '    <td style="vertical-align: middle; width: 50px;">' +
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
            possible_actions.forEach(function (action) {
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
                    elem.html('<input name="sieve_selected_action_value[]" type="hidden" value="" />');
                }
                if (selected_action.type === 'string') {
                    elem.html('<input name="sieve_selected_action_value[]" placeholder="'+selected_action.placeholder+'" type="text" value="" />');
                }
                if (selected_action.type === 'int') {
                    elem.html('<input name="sieve_selected_action_value[]" placeholder="'+selected_action.placeholder+'" type="number" />');
                }
                if (selected_action.type === 'number') {
                    elem.html('<input name="sieve_selected_action_value[]" placeholder="'+selected_action.placeholder+'" type="number" />');
                }
                if (selected_action.type === 'text') {
                    elem.html('<textarea name="sieve_selected_action_value[]" placeholder="'+selected_action.placeholder+'" style="width: 235px;"></textarea>');
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
                    elem.html('<select name="sieve_selected_action_value[]">'+ options +'</select>');
                }
                if (selected_action.type === 'mailbox') {
                    let mailboxes = null;
                    tr_elem.children().find('img').attr('style', '');
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
                            elem.html('<select name="sieve_selected_action_value[]">'+ options +'</select>');
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
            let elem = $(this).parent().next().find('.condition_options');
            let elem_extra = $(this).parent().next().find('.condition_extra_value');
            let elem_type = $(this).parent().next().next().next();
            let condition;
            let options_html = '';
            let input_type_html = '';
            condition_fields.Message.forEach(function (cond) {
                if (condition_name === cond.name) {
                    condition = cond;
                }
            });
            condition_fields.Header.forEach(function (cond) {
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
                    elem_type.html('<input name="sieve_selected_option_value[]" type="text" />')
                }
                if (condition.type === 'int') {
                    elem_type.html('<input name="sieve_selected_option_value[]" type="number" />')
                }
            }
        });

        /**
         * Delete filter event
         */
        $(document).on('click', '.delete_filter', function (e) {
            e.preventDefault();
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
            $('.script_modal_title').html('Edit Script');
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
            $('.modal_sieve_filter_name').val($(this).attr('script_name_parsed'));
            $('.modal_sieve_filter_priority').val($(this).attr('priority'));
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
                        add_filter_action(action.value);
                        $(".sieve_actions_select").last().val(action.action);
                        $(".sieve_actions_select").last().trigger('change');
                        $("[name^=sieve_selected_extra_action_value]").last().val(action.extra_option_value);
                        if ($("[name^=sieve_selected_option_action_value]").last().is('input')) {
                            $("[name^=sieve_selected_option_action_value]").last().val(action.value);
                        }
                    });
                }
            );
        });
    }
});
