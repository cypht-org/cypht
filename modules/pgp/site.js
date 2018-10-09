'use strict';

var Hm_Pgp = {

    public_key: function(key_text) {
        return openpgp.key.readArmored(key_text);
    },

    private_keys: function() {
        /* TODO: load private keys from session storage */
        return [];
    },

    sign_text: function() {
        /* TODO: prompt for passphrase */
        var key_id = $('#pgp_sign').val();
        var key = Hm_Pgp.private_key_by_index(key_id);
        var body = $('#compose_body').val();
        if (!body || !body.length) {
            return true;
        }
        var bodyarray = new TextEncoder('UTF-8').encode(body);
        var options = {data: body, privateKeys: [key], armor: true};
        return openpgp.sign(options).then(function(ciphertext) {
            $('#compose_body').val(ciphertext.data);
            return true;
        });
    },

    private_key_by_index: function(id) {
        var keys = Hm_Pgp.private_keys();
        for (var index in keys.keys) {
            if (index == id) {
                return key.keys[index]
            }
        }
        return false;
    },

    encrypt_text: function(keytext, sign) {
        var body = $('#compose_body').val();
        if (!body || !body.length) {
            return true;
        }
        var bodyarray = new TextEncoder('UTF-8').encode(body);
        var options = {data: bodyarray, publicKeys: Hm_Pgp.public_key(keytext).keys, armor: true};
        if (sign) {
            /* TODO: prompt for passphrase */
            options['privateKeys'] = Hm_Pgp.private_keys().keys;
        }
        openpgp.encrypt(options).then(function(ciphertext) {
            var encrypted = ciphertext.data;
            $('#compose_body').val(encrypted);
        });
    },

    decrypt_text: function() {
    },

    verify_signature: function() {
    },

    load_private_keys: function() {
        var ids;
        var key;
        var options = [];
        var keys = Hm_Pgp.private_keys();
        for (var index in keys.keys) {
            key = keys.keys[index];
            ids = key.getUserIds();
            options.push('<option value="'+index+'">'+ids[0]+'</option>');
        }
        if (options.length) {
            $('.pgp_sign').show();
            $('#pgp_sign').html(options);
        }
    },

    process_settings: function() {
        var sign = $('#pgp_sign').val();
        if (sign === 0) {
            sign = true;
        }
        var encrypt = $('#pgp_encrypt').val();
        if (!encrypt) {
            encrypt = false;
        }
        if (encrypt && sign) {
            Hm_Pgp.encrypt_text(true);
        }
        else if (encrypt) {
            Hm_Pgp.encrypt_text(encrypt);
        }
        else if (sign) {
            Hm_Pgp.sign_text();
        }
        return true;
    },

    check_pgp_msg: function(res) {
        if (res.pgp_msg_part) {
            $('.pgp_msg_controls').show();
        }
        else {
            $('.pgp_msg_controls').hide();
        }
    }

}


$(function() {
    if (hm_page_name() == 'compose') {
        $('.pgp_sign').click(function() { Hm_Pgp.sign_text(); });
        $('.smtp_send').click(function() { return Hm_Pgp.process_settings(); });
        //Hm_Pgp.load_private_keys();
    }
    else if (hm_page_name() == 'message') {
        Hm_Ajax.add_callback_hook('ajax_imap_message_content', Hm_Pgp.check_pgp_msg);
    }
    else if (hm_page_name() == 'pgp') {
        $('.priv_title').click(function() { $('.priv_keys').toggle(); });
        $('.public_title').click(function() { $('.public_keys').toggle(); });
        $('.delete_pgp_key').click(function() { return hm_delete_prompt(); });
        if (window.location.hash == '#public_keys') {
            $('.public_keys').toggle();
        }

    }
});
