<?php

if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('purify_html_sig')) {
/**
 * Sanitize an HTML signature using HTMLPurifier.
 * Only call this when the compose type is HTML (type == 1).
 * @param string $sig raw HTML from user input
 * @return string sanitized HTML
 */
function purify_html_sig($sig) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('HTML.Allowed',
        'b,strong,i,em,u,s,strike,br,p[style],div[style],span[style],' .
        'font[color|size|face],a[href|title],ul,ol,li,blockquote'
    );
    $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
    $config->set('HTML.TargetBlank', true);
    $config->set('HTML.TargetNoopener', true);
    try {
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($sig);
    } catch (Exception $e) {
        return '';
    }
}}

if (!hm_exists('add_profile')) {
    function add_profile($name, $signature, $reply_to, $is_default, $email, $server, $user, $smtp_server_id, $imap_server_id, $context, $remark = '') {
        $profile = array(
            'name' => $name,
            'sig' => $signature,
            'rmk' => $remark,
            'smtp_id' => $smtp_server_id,
            'imap_id' => $imap_server_id,
            'replyto' => $reply_to,
            'default' => $is_default,
            'address' => $email,
            'server' =>  $server,
            'user' => $user,
            'type' => 'imap'
        );
        $id = Hm_Profiles::add($profile);
        if ($is_default) {
            Hm_Profiles::setDefault($id);
        }
    }
}
