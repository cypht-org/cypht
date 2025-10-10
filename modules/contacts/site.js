'use strict';

var existingRecipients = [];

var delete_contact = function(id, source, type) {
    if (!hm_delete_prompt()) {
        return false;
    }
    var request_data = [
        {'name': 'hm_ajax_hook', 'value': 'ajax_delete_contact'},
        {'name': 'contact_id', 'value': id},
        {'name': 'contact_type', 'value': type},
        {'name': 'contact_source', 'value': source}
    ];
    
    Hm_Ajax.request(
        request_data,
        function(res) {
            if (res.contact_deleted && res.contact_deleted === 1) {
                $('.contact_row_'+id).remove();
            }
        }
    );
};

var remove_recipient_from_list = function(recipientId) {
    existingRecipients = existingRecipients.filter(item => item !== recipientId);
};

var add_contact_from_message_view = function() {
    var contact = $('#add_contact').val();
    var source = $('#contact_source').val();

    if (contact) {
      Hm_Ajax.request(
        [
          { name: 'hm_ajax_hook', value: 'ajax_add_contact' },
          { name: 'contact_value', value: contact },
          { name: 'contact_source', value: source },
        ],
        function (res) {
          $('.add_contact_controls').toggle();
          window.location.reload();
        }
      );
    }
  };

var add_contact_from_popup = function(event) {
    event.stopPropagation()
    var source = 'local:local';
    var contact = $('#contact_info').text().replace('>','').replace('<','');


    if (contact) {
        var email = contact.match(EMAIL_REGEX)[0];
        var name = contact.replace(EMAIL_REGEX, "");

        var saveContactContent = `<div><table>
                                            <tr><td><strong>${hm_trans('Name')} :</strong></td><td>${name}</td></tr>
                                            <tr><td><strong>${hm_trans('Email')} :</strong></td><td>${email}</td></tr>
                                            <tr><td><strong>${hm_trans('Source')} :</strong></td><td>Local</td></tr>
                                </table></div>`

        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_add_contact'},
            {'name': 'contact_value', 'value': contact},
            {'name': 'contact_source', 'value': source}],
            function (res) {
                $("#contact_popup_body").html(saveContactContent);
                sessionStorage.removeItem(`${window.location.pathname}imap_4_${getListPathParam()}`);
                sessionStorage.removeItem(`${window.location.pathname}${getMessageUidParam()}_${getListPathParam()}`);
            }
        );
    }
};

var get_search_term = function(class_name) {
    var fld_val = $(class_name).val();
    var addresses = fld_val.split(' ');
    if (addresses.length > 1) {
        fld_val = addresses.pop();
    }
    return fld_val;
};

var autocomplete_contact = function(e, class_name, list_div) {
    var key_code = e.keyCode;
    if (key_code >= 37 && key_code <= 40) {
        return;
    }
    var first;
    var div = $('<div></div>');
    var fld_val = get_search_term(class_name);
    if (fld_val.length > 0) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_autocomplete_contact'},
            {'name': 'contact_value', 'value': fld_val}],
            function(res) {
                if (res.contact_suggestions) {
                    var i;
                    var count = 0;
                    $(list_div).html('');
                    for (i in res.contact_suggestions) {
                        var suggestion = JSON.parse(res.contact_suggestions[i].replace(/&quot;/g, '"'))
                        
                        div.html(suggestion.contact);
                        if (existingRecipients.includes(suggestion.contact_id)) {
                            continue;
                        }
                        if ($(class_name).val().match(div.text())) {
                            continue;
                        }
                        if (count == 0) {
                            first = 'first ';
                        }
                        else {
                            first = '';
                        }
                        count++;
                        $(list_div).append('<a tabindex="1" href="#" class="'+first+'contact_suggestion" data-id="'+suggestion.contact_id+'" data-type="'+suggestion.type+'" data-source="'+suggestion.source+'" unread_link">'+suggestion.contact+'</a>');
                    }
                    if (count > 0) {
                        $(list_div).show();
                        setup_autocomplete_events(class_name, list_div, fld_val);
                    }
                    else {
                        $(list_div).hide();
                    }
                }
            }, [], true
        );
    }
};

var autocomplete_keyboard_nav = function(event, list_div, class_name, fld_val) {
    var in_list = false;
    if (event.keyCode == 40) {
        if ($(event.target).prop('nodeName') == 'INPUT') {
            $('.first').addClass('selected_menu');
            $('.first').focus();
            in_list = true;
        }
        else {
            $(event.target).removeClass('selected_menu');
            $(event.target).next().addClass('selected_menu');
            $(event.target).next().focus();
            in_list = true;
        }
        return false;
    }
    else if (event.keyCode == 38) {
        if ($(event.target).prev().length) {
            $(event.target).removeClass('selected_menu');
            $(event.target).prev().addClass('selected_menu');
            $(event.target).prev().focus();
            in_list = true;
        }
        else {
            $(class_name).focus();
            $(event.target).removeClass('selected_menu');
        }
        return false;
    }
    else if (event.keyCode == 13) {
        $(class_name).focus();
        $(list_div).hide();
        add_autocomplete(event, class_name, list_div);
        return false;
    }
    else if (event.keyCode == 27) {
        $(list_div).html('');
        $(list_div).hide();
        $(class_name).focus();
        return false;
    }
    else if (event.keyCode == 9) {
        $(list_div).html('');
        $(list_div).hide();
        $(class_name).trigger('focusout');
        return true;
    }
    if (in_list) {
        return false;
    }
    return true;
};

var setup_autocomplete_events = function(class_name, list_div, fld_val) {
    $('.contact_suggestion').on("click", function(event) { return add_autocomplete(event, class_name, list_div); });
    $(class_name).on('keydown', function(event) { return autocomplete_keyboard_nav(event, list_div, class_name, fld_val); });
    $('.contact_suggestion').on('keydown', function(event) { return autocomplete_keyboard_nav(event, list_div, class_name, fld_val); });
    $(document).on("click", function() { $(list_div).hide(); });
};

var add_autocomplete = function(event, class_name, list_div, fld_val) {
    $(class_name).attr("data-id", $(event.target).data('id'));
    $(class_name).attr("data-type", $(event.target).data('type'));
    $(class_name).attr("data-source", $(event.target).data('source'));

    if (!fld_val) {
        fld_val = get_search_term(class_name);
        existingRecipients.push($(event.target).data('id'));
    }
    var new_address = $(event.target).text()
    var existing = $(class_name).val();
    var re = new RegExp(fld_val+'$');
    existing = existing.replace(re, '');
    if (existing.length) {
        existing = existing.replace(/[\s,]+$/, '')+', ';
    }
    $(list_div).html('');
    $(list_div).hide();
    $(class_name).val(existing+new_address);
    $(class_name).focus();
    return false;
};

var showPage = function(selected_page, total_pages) {
    $('.import_body tr').hide();
    $('.page_' + selected_page).show();
    $('.page_link_selector').removeClass('active');
    $('.page_item_' + selected_page).addClass('active');
    $('.prev_page').toggleClass('disabled', selected_page === 1);
    $('.next_page').toggleClass('disabled', selected_page === total_pages);
};

var contact_import_pagination = function() {
    var selected_page = 1;
    var total_pages = $('#totalPages').val();
    showPage(selected_page, total_pages);

    $('.page_link_selector').on('click', function () {
        selected_page = $(this).data('page');
        showPage(selected_page, total_pages);
    });

    $('.prev_page').on('click', function () {
        if (selected_page > 1) {
            selected_page--;
            showPage(selected_page, total_pages);
        }
    });

    $('.next_page').on('click', function () {
        if (selected_page < total_pages) {
            selected_page++;
            showPage(selected_page, total_pages);
        }
    });
};

var check_cc_exist_in_contacts_list = function() {
    if (typeof list_emails !== "undefined") {
        var compose_cc = $(".compose_cc").val().trim();
        var list_cc = null;
        var list_cc_not_exist_in_my_contact = [];
        if (compose_cc.length > 0) {
            list_cc = compose_cc.split(",");
            var list_html = "<ol>";
            list_cc.forEach(cc => {
                cc = cc.trim().split(" ");
                if (! list_emails.includes(cc.slice(-1)[0])) {
                    list_cc_not_exist_in_my_contact.push(cc.slice(-1)[0])
                    list_html += `<li>${cc.slice(-1)[0]}</li>`;
                }
            });
            list_html += "</ol>";

            if (list_cc_not_exist_in_my_contact) {
                return list_html;
            }
        }
    }
    return "";
};

var initContactTabs = function() {
    $('.category-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetId = $(this).data('target');
        
        $('.category-tab').removeClass('active');
        
        $(this).addClass('active');
        
        $('.tab-content-section').removeClass('active');
        
        $('#' + targetId).addClass('active');
    });
};

var initLocalContactModal = function() {
    $('#manual-entry-btn').on('click', function() {
        $(this).addClass('active');
        $('#csv-import-btn').removeClass('active');
        $('.contact-manual-form').show();
        $('.csv-import-section').hide();
        $('#submit-local-contact-btn').text('Add Contact');
    });

    $('#csv-import-btn').on('click', function() {
        $(this).addClass('active');
        $('#manual-entry-btn').removeClass('active');
        $('.contact-manual-form').hide();
        $('.csv-import-section').show();
        $('#submit-local-contact-btn').text('Import Contacts');
    });

    $('#submit-local-contact-btn').on('click', function(e) {
        e.preventDefault();
        
        if ($('#manual-entry-btn').hasClass('active')) {
            var name = $('#contact_name').val();
            var email = $('#contact_email').val();
            var phone = $('#contact_phone').val();
            var category = $('#contact_category').val();
            
            if (!name || !email) {
                //TODO: Use better error display
                alert('Please fill in the required fields (Name and Email)');
                return;
            }
            Hm_Ajax.request(
                [
                    {'name': 'hm_ajax_hook', 'value': 'ajax_add_contact'},
                    {'name': 'contact_name', 'value': name},
                    {'name': 'contact_email', 'value': email},
                    {'name': 'contact_phone', 'value': phone},
                    {'name': 'contact_category', 'value': category},
                    {'name': 'contact_source', 'value': 'local:local'}
                ],
                function(res) {
                    if (res.contact_added) {
                        const modalElement = document.getElementById('localContactModal');
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }
                        window.location.reload();
                    }
                }
            );
        } else {
            var fileInput = $('#contact_csv')[0];
            if (!fileInput.files.length) {
                alert('Please select a CSV file');
                return;
            }
            //TODO: Implement CSV import functionality
            console.log('CSV import not implemented yet');
        }
    });

    $('#localContactModal').on('hidden.bs.modal', function() {
        $('#manual-contact-form')[0].reset();
        $('#manual-entry-btn').addClass('active');
        $('#csv-import-btn').removeClass('active');
        $('.contact-manual-form').show();
        $('.csv-import-section').hide();
        $('#submit-local-contact-btn').text('Add Contact');
    });
};

var initLdapContactModal = function() {
    $('#submit-ldap-contact-btn').on('click', function(e) {
        e.preventDefault();
        
        var firstName = $('#ldap_first_name').val();
        var lastName = $('#ldap_last_name').val();
        var email = $('#ldap_mail').val();
        
        if (!firstName || !lastName || !email) {
            //TODO: Use better error display
            alert('Please fill in the required fields (First Name, Last Name, and Email)');
            return;
        }
        //TODO: implement: validation for other fields as needed and ajax_add_ldap_contact
        var formData = [
            {'name': 'hm_ajax_hook', 'value': 'ajax_add_ldap_contact'},
            {'name': 'contact_source', 'value': 'ldap'},
            {'name': 'ldap_source', 'value': $('#ldap_source').val()},
            {'name': 'ldap_first_name', 'value': firstName},
            {'name': 'ldap_last_name', 'value': lastName},
            {'name': 'ldap_mail', 'value': email},
            {'name': 'ldap_displayname', 'value': $('#ldap_displayname').val()},
            {'name': 'ldap_uidattr', 'value': $('#ldap_uidattr').val()},
            {'name': 'ldap_uid', 'value': $('#ldap_uid').val()},
            {'name': 'ldap_locality', 'value': $('#ldap_locality').val()},
            {'name': 'ldap_state', 'value': $('#ldap_state').val()},
            {'name': 'ldap_street', 'value': $('#ldap_street').val()},
            {'name': 'ldap_postalcode', 'value': $('#ldap_postalcode').val()},
            {'name': 'ldap_title', 'value': $('#ldap_title').val()},
            {'name': 'ldap_phone', 'value': $('#ldap_phone').val()},
            {'name': 'ldap_fax', 'value': $('#ldap_fax').val()},
            {'name': 'ldap_mobile', 'value': $('#ldap_mobile').val()},
            {'name': 'ldap_room', 'value': $('#ldap_room').val()},
            {'name': 'ldap_car', 'value': $('#ldap_car').val()},
            {'name': 'ldap_org', 'value': $('#ldap_org').val()},
            {'name': 'ldap_org_unit', 'value': $('#ldap_org_unit').val()},
            {'name': 'ldap_org_dpt', 'value': $('#ldap_org_dpt').val()},
            {'name': 'ldap_emp_num', 'value': $('#ldap_emp_num').val()},
            {'name': 'ldap_emp_type', 'value': $('#ldap_emp_type').val()},
            {'name': 'ldap_lang', 'value': $('#ldap_lang').val()},
            {'name': 'ldap_uri', 'value': $('#ldap_uri').val()}
        ];
        
        Hm_Ajax.request(
            formData,
            function(res) {
                if (res.contact_added) {
                    const modalElement = document.getElementById('ldapContactModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                    window.location.reload();
                } else {
                    if (res.error_message) {
                        //TODO: Use better error display
                        alert(res.error_message);
                    }
                }
            }
        );
    });

    $('#ldapContactModal').on('hidden.bs.modal', function() {
        $('#ldap-contact-form')[0].reset();
    });

    $('#ldap_uidattr').on('change', function() {
        if ($(this).val() === 'uid') {
            $('#ldap_uid_field_wrapper').removeClass('d-none');
        } else {
            $('#ldap_uid_field_wrapper').addClass('d-none');
        }
    });
};

var initPagination = function() {
    $(document).on('click', '.pagination-btn:not([disabled]), .pagination-number', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var currentPage = parseInt(getUrlParameter('contact_page')) || 1;
        
        if (page && page !== currentPage) {
            var currentUrl = new URL(window.location);
            currentUrl.searchParams.set('contact_page', page);
            window.location.href = currentUrl.toString();
        }
    });
};

var getUrlParameter = function(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
};

//TODO: Move JS related to local contacts to /modules/local_contacts/ and ldap contacts to /modules/ldap_contacts/
$(document).ready(function() {
    initContactTabs();
    initLocalContactModal();
    initLdapContactModal();
    initPagination();
});

