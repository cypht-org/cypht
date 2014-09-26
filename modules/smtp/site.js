if (hm_page_name == 'servers') {
    var dsp = get_from_local_storage('.smtp_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.smtp_section').css('display', dsp);
    }
}
