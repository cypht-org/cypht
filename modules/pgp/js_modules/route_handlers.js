function applyPgpPageHandlers() {
    $('.priv_title').on("click", function() { $('.priv_keys').toggle(); });
    $('.public_title').on("click", function() { $('.public_keys').toggle(); });
    $('.delete_pgp_key').on("click", function() { return hm_delete_prompt(); });
    $('#priv_key').on("change", function(evt) { Hm_Pgp.read_private_key(evt); });
    Hm_Pgp.list_private_keys();
    if (window.location.hash == '#public_keys') {
        $('.public_keys').toggle();
    }
    if (window.location.hash == '#private_keys') {
        $('.private_keys').toggle();
    }
}