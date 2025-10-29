'use strict';

var Hm_Pgp = {

    error_msg: '',
    del_img_src: "data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%228%22%20height%3D%228%22%20viewBox%3D%220%200%208%208%22%3E%0A%20%20%3Cpath%20d%3D%22M4%200c-2.21%200-4%201.79-4%204s1.79%204%204%204%204-1.79%204-4-1.79-4-4-4zm-1.5%201.781l1.5%201.5%201.5-1.5.719.719-1.5%201.5%201.5%201.5-.719.719-1.5-1.5-1.5%201.5-.719-.719%201.5-1.5-1.5-1.5.719-.719z%22%20%2F%3E%0A%3C%2Fsvg%3E",

    public_key: async function(key_text) {
        var keys = (await openpgp.key.readArmored(key_text)).keys;
        return keys;
    },

    private_keys: async function() {
        var index;
        var keyring = [];
        var keystrings = Hm_Pgp.get_private_keys();
        for (index in keystrings) {
            keyring.push(await openpgp.key.readArmored(keystrings[index]));
        }
        return keyring;
    },

    sign_text: async function(pass, key_id) {
        var key = await Hm_Pgp.private_key_by_index(key_id);
        var body = $('#compose_body').val();
        if (!body || !body.length) {
            return true;
        }
        body += "\n";
        try {
            var decrypted = await key.decrypt(pass);
        }
        catch (e) {
            Hm_Pgp.error_msg = hm_trans('Could not unlock key with supplied passphrase');
            Hm_Pgp.show_result();
            return;
        }
        var options = {message: openpgp.message.fromText(body), privateKeys: [key], armor: true};
        return openpgp.sign(options).then(function(ciphertext) {
            $('#compose_body').val(ciphertext.data);
            Hm_Pgp.show_result();
            return true;
        });
    },

    private_key_by_index: async function(id) {
        var keys;
        var keylist = await Hm_Pgp.private_keys();
        for (var i in keylist) {
            keys = keylist[i];
            for (var index in keys.keys) {
                if (i+'.'+index == id) {
                    return keys.keys[index]
                }
            }
        }
        return false;
    },

    encrypt_text: async function(pass, keytext, sign) {
        var body = $('#compose_body').val();
        if (!body || !body.length) {
            return true;
        }
        var options = {message: await openpgp.message.fromText(body), publicKeys: await Hm_Pgp.public_key(keytext), armor: true};
        if (sign) {
            var key = await Hm_Pgp.private_key_by_index(sign);
            try {
                var decrypted = await key.decrypt(pass);
            }
            catch (e) {
                Hm_Pgp.error_msg = hm_trans('Could not unlock key with supplied passphrase');
                Hm_Pgp.show_result();
                return;
            }
            options['privateKeys'] = key;
        }
        return openpgp.encrypt(options).then(function(ciphertext) {
            var encrypted = ciphertext.data;
            $('#compose_body').val(encrypted);
            Hm_Pgp.show_result();
            return true;
        });
    },


    decrypt_text: async function(pass) {
        var index = $('.pgp_private_keys').val();
        if (!index) {
            Hm_Pgp.error_msg = hm_trans('Could not access private key');
            Hm_Pgp.show_result();
            return;
        }
        var msg = $('.msg_text_inner').text().replace(/\u00A0/g, ' ');
        msg = await openpgp.message.readArmored(msg);
        var key = await Hm_Pgp.private_key_by_index(index);
        try {
            var decrypted = await key.decrypt(pass);
        }
        catch (e) {
            Hm_Pgp.error_msg = hm_trans('Could not unlock key with supplied passphrase');
            Hm_Pgp.show_result();
            return;
        }
        return openpgp.decrypt({message: msg, privateKeys: [key]}).then(function(plaintext) {
            var plain = plaintext.data;
            $('.msg_text_inner').html('<pre>'+Hm_Utils.html_entities(plain)+'</pre>');
            Hm_Pgp.show_result();
            return true;
        });
    },

    verify_signature: function() {
    },

    validate_private_key: function(key) {
        var res = openpgp.key.readArmored(key);
        if (res['err'] && res['err'].length > 0) {
            return false;
        }
        return true;
    },

    load_private_keys: async function() {
        var ids;
        var key;
        var options = ['<option disabled selected value=""></option>'];
        options = options.concat(await Hm_Pgp.private_key_options());
        if (options.length > 1) {
            $('.pgp_sign').show();
            $('#pgp_sign').html(options);
        }
    },

    private_key_options: async function() {
        var ids;
        var key;
        var options = [];
        var keys;
        var keylist = await Hm_Pgp.private_keys();
        for (var i in keylist) {
            keys = keylist[i];
            for (var index in keys.keys) {
                key = keys.keys[index];
                ids = key.getUserIds();
                options.push('<option value="'+i+'.'+index+'">'+Hm_Utils.html_entities(ids[0])+'</option>');
            }
        }
        return options;
    },

    list_private_keys: async function() {
        $('.private_key_list tbody').html('');
        var keylist = await Hm_Pgp.private_keys();
        var rows = [];
        var keys;
        var key;
        var ids;
        for (var i in keylist) {
            keys = keylist[i];
            for (var index in keys.keys) {
                key = keys.keys[index];
                ids = key.getUserIds();
                rows.push('<tr><td>'+Hm_Utils.html_entities(ids[0])+'</td><td><img data-id="'+i+'" class="delete_pgp_key delete_private_key" src="'+Hm_Pgp.del_img_src+'"/></td></tr>');
            }
        }
        var count = rows.length;
        var total = $('.private_key_count').text();
        $('.private_key_count').html(total.replace(/[0-9]/g, count));
        $('.private_key_list tbody').html(rows.join(''));
        $('.delete_private_key').on("click", function() { Hm_Pgp.delete_private_key($(this).data('id')); });
    },

    process_settings: async function() {
        var sign = $('#pgp_sign').val();
        if (!sign) {
            sign = false;
        }
        var encrypt = $('#pgp_encrypt').val();
        if (!encrypt) {
            encrypt = false;
        }
        if (encrypt && sign) {
            await Hm_Pgp.get_passphrase(Hm_Pgp.encrypt_text, encrypt, sign);
        }
        else if (encrypt) {
            await Hm_Pgp.encrypt_text(false, encrypt);
        }
        else if (sign) {
            await Hm_Pgp.get_passphrase(Hm_Pgp.sign_text, sign);
        }
    },

    show_result: function() {
        if (Hm_Pgp.error_msg) {
            Hm_Pgp.show_error();
        }
    },

    show_error: function() {
        Hm_Notices.show(Hm_Pgp.error_msg, 'danger');
        $('.smtp_send').removeClass('disabled_input');
        Hm_Pgp.error_msg = '';
    },

    update_private_keys: function(key) {
        if (!Hm_Pgp.validate_private_key(key)) {
            Hm_Notices.show('Unable to import private key', 'danger');
            return;
        }
        var keys = Hm_Pgp.get_private_keys();
        keys.push(key);
        Hm_Utils.save_to_local_storage('pgp_keys', JSON.stringify(keys));
        Hm_Pgp.list_private_keys();
        Hm_Notices.show('Private key saved', 'success');
    },

    get_private_keys: function() {
        var keys;
        var keystr = Hm_Utils.get_from_local_storage('pgp_keys');
        try { keys = JSON.parse(keystr); } catch(e) {}
        if (!keys) {
            keys = [];
        }
        return keys;
    },

    get_passphrase: async function(callback, encrypt, sign) {
        $('.passphrase_prompt').show();
        $('#submit_pgp_pass').on("click", function() {
            $('.passphrase_prompt').hide();
            Hm_Pgp.precheck();
            var pass = $('#pgp_pass').val();
            $('#pgp_pass').val('');
            setTimeout(function() { callback(pass, encrypt, sign); }, 100);
        });
    },

    read_private_key: function(evt) {
        if (!evt.target.files.length) {
            Hm_Notices.show('Unable to import private key', 'danger');
            return;
        }
        var reader = new FileReader();
        reader.onload = (function(file) {
            return function(e) {
                Hm_Pgp.update_private_keys(e.target.result);
            };
        })(evt.target.files[0]);
        reader.readAsText(evt.target.files[0]);
    },

    check_pgp_msg: async function(res) {
        var keylist = await Hm_Pgp.private_key_options();

        if (keylist && res.pgp_msg_part) {
            $('.pgp_private_keys').html(keylist);
            $('.pgp_msg_controls').show();
            $('.pgp_btn').on("click", async function() { await Hm_Pgp.get_passphrase(Hm_Pgp.decrypt_text); });
        }
        else {
            $('.pgp_msg_controls').hide();
        }
    },

    delete_private_key: function(del_index) {
        if (hm_delete_prompt()) {
            var keys;
            var keylist = Hm_Pgp.get_private_keys();
            var newkeys = [];
            for (var i in keylist) {
                if (i != del_index) {
                    newkeys.push(keylist[i]);
                }
            }
            Hm_Utils.save_to_local_storage('pgp_keys', JSON.stringify(newkeys));
            Hm_Notices.show('Private key removed', 'info');
            Hm_Pgp.list_private_keys();
        }
    },

    precheck: function() {
        var msg;
        var sign = $('#pgp_sign').val();
        var encrypt = $('#pgp_encrypt').val();
        var decrypt = $('#pgp_btn');
        if (!sign && !encrypt && !decrypt) {
            return;
        }
        if (sign && encrypt) {
            msg = 'Encrypting and signing message...';
        }
        else if (sign) {
            msg = 'Signing message...';
        }
        else if (decrypt) {
            msg = 'Decrypting message...';
        }
        else {
            msg = 'Encrypting message...';
        }
        Hm_Notices.show(msg, 'info');
    }
}

function pgpComposePageHandler() {
    if (($('#pgp_encrypt option').length + $('#pgp_sign option').length) == 0) {
        $('.pgp_section').hide();
    }
    $('.smtp_send_placeholder').on("click", function() { Hm_Pgp.process_settings(); return false; });
}

function pgpMessageContentPageHandler() {
    Hm_Ajax.add_callback_hook('ajax_imap_message_content', Hm_Pgp.check_pgp_msg);
}
