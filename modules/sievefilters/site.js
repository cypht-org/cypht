$(function () {

    let condition_fields;
    let possible_actions;
    let is_editing_script = false;
    let current_editing_script_name = '';
    let is_editing_filter = false;
    let current_editing_filter_name = '';
    let current_account;

    if (hm_page_name() === 'sievefilters') {
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
                    options: ['Contains', 'Matches']
                },
                {
                    name: 'body',
                    description: 'Body',
                    type: 'string',
                    options: ['Contains', 'Matches']
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
                    options: ['Contains', 'Matches']
                },
                {
                    name: 'from',
                    description: 'From',
                    type: 'string',
                    options: ['Contains', 'Matches']
                },
                {
                    name: 'cc',
                    description: 'CC',
                    type: 'string',
                    options: ['Contains', 'Matches']
                },
                {
                    name: 'bcc',
                    description: 'BCC',
                    type: 'string',
                    options: ['Contains', 'Matches']
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
                type: 'none'
            },
            {
                name: 'copy',
                description: 'Copy email to mailbox',
                placeholder: 'Mailbox Name (Folder)',
                type: 'string'
            },
            {
                name: 'discard',
                description: 'Discard',
                type: 'none'
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
                console.log('modal open');
            },
            onClose: function () {
                console.log('modal closed');
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
                console.log('modal open');
            },
            onClose: function () {
                console.log('modal closed');
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
            save_filter(current_account);
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

            let idx = 0;
            conditions.forEach(function (elem) {
                 conditions_parsed.push(
                     {
                         'condition': elem,
                         'type': conditions_type[idx],
                         'value': conditions_value[idx]
                     }
                 )
                idx = idx + 1;
            });

            let actions_type = $('select[name^=sieve_selected_actions]').map(function(idx, elem) {
                return $(elem).val();
            }).get();
            let actions_value = $('input[name^=sieve_selected_action_value]').map(function(idx, elem) {
                return $(elem).val();
            }).get();

            idx = 0;
            actions_type.forEach(function (elem) {
                actions_parsed.push(
                    {
                        'action': elem,
                        'value': actions_value[idx]
                    }
                )
                idx = idx + 1;
            });

            Hm_Ajax.request(
                [   {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_save_filter'},
                    {'name': 'imap_account', 'value': imap_account},
                    {'name': 'sieve_filter_name', 'value': $('.modal_sieve_filter_name').val()},
                    {'name': 'sieve_filter_priority', 'value': $('.modal_sieve_filter_priority').val()},
                    {'name': 'is_editing_filter', 'value': is_editing_filter},
                    {'name': 'current_editing_filter_name', 'value': current_editing_filter_name},
                    {'name': 'conditions_json', 'value': JSON.stringify(conditions_parsed)},
                    {'name': 'actions_json', 'value': JSON.stringify(actions_parsed)},
                    ],
                function(res) {
                    
                }
            );
        }

        function save_script(imap_account) {
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
            let script_name = $(this).parent().parent().children().html();
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

        /**
         * Add Condition Button
         */
        $(document).on('click', '.sieve_add_condition_modal_button', function () {
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

            $('.sieve_list_conditions_modal').append(
                '                            <tr>' +
                '                                <td>' +
                '                                    <select class="add_condition_sieve_filters" name="sieve_selected_conditions_field[]">' +
                '                                        <optgroup label="Message">' +
                message_fields +
                '                                        </optgroup>' +
                '                                        <optgroup label="Header">' +
                header_fields +
                '                                        </optgroup>' +
                '                                    </select>' +
                '                                </td>' +
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
                '                                    </select>' +
                '                                </td>' +
                '                                <td>' +
                '                                    <input type="text" name="sieve_selected_option_value[]" />' +
                '                                </td>' +
                '                                <td style="vertical-align: middle; width: 50px;">' +
                '                                    <a href="#" class="delete_condition_modal_button">Delete</a>' +
                '                                </td>' +
                '                            </tr>'
            );
        });

        /**
         * Add Action Button
         */
        $(document).on('click', '.filter_modal_add_action_btn', function () {
            let possible_actions_html = '';

            possible_actions.forEach(function (value) {
                if (value.selected === true) {
                    possible_actions_html += '<option selected value="'+value.name+'">' + value.description + '</option>';
                    return;
                }
                possible_actions_html += '<option value="'+value.name+'">' + value.description + '</option>';
            });

            $('.filter_actions_modal_table').append(
                '<tr style="border-bottom-color: black;">' +
                '   <td>' +
                '       <select class="sieve_actions_select" name="sieve_selected_actions[]">' +
                '          ' + possible_actions_html +
                '       </select>' +
                '    </td>' +
                '    <td>' +
                '    <input type="hidden" name="sieve_selected_action_value[]" value="">' +
                '    </input>' +
                '    <td style="vertical-align: middle; width: 50px;">' +
                '           <a href="#" class="delete_action_modal_button">Delete</a>' +
                '    </td>' +
                '</tr>'
            );
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
            let elem = $(this).parent().next();
            let action_name = $(this).val();
            let selected_action;
            possible_actions.forEach(function (action) {
               if (action_name === action.name) {
                    selected_action = action;
               }
            });
            if (selected_action) {
                if (selected_action.type === 'none') {
                    elem.html('<input name="sieve_selected_action_value[]" type="hidden" value="" />');
                }
                if (selected_action.type === 'string') {
                    elem.html('<input name="sieve_selected_action_value[]" type="text" />');
                }
                if (selected_action.type === 'int') {
                    elem.html('<input name="sieve_selected_action_value[]" type="number" />');
                }
                if (selected_action.type === 'number') {
                    elem.html('<input name="sieve_selected_action_value[]" type="number" />');
                }
                if (selected_action.type === 'text') {
                    elem.html('<textarea name="sieve_selected_action_value[]"></textarea>');
                }
            }
        })

        /**
         * Condition type change
         */
        $(document).on('change', '.add_condition_sieve_filters', function () {
            let condition_name = $(this).val();
            let elem = $(this).parent().next().find('.condition_options');
            let elem_type = $(this).parent().next().next();
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
         * Delete script event
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
    }
});
