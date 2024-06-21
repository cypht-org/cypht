<?php

/**
 * Webhook
 * @package framework
 * @subpackage webhook
 */

class Hm_Telegram_Webhook {

    const PREFIX_URI = 'https://api.telegram.org/';

    /**
     * send telegram notiofication using curl
     * @param int $msg_count
     * @param string $email_to
     * @param string $webhook_token
     */
    public static function send($msg_count, $email_to, $webhook_token) {
        self::delete_webhook($webhook_token);
        // Get the chat ID
        $chatId = self::get_chat_id($webhook_token);
        if (!empty($chatId)) {
            $text = "You have received: $msg_count unread email.s\nTo: $email_to";
            $curl_handle = Hm_Functions::c_init();
            Hm_Functions::c_setopt($curl_handle, CURLOPT_URL, static::PREFIX_URI.'bot'.$webhook_token.'/sendMessage');
            Hm_Functions::c_setopt($curl_handle, CURLOPT_POST, true);
            Hm_Functions::c_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
            Hm_Functions::c_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query(['chat_id' => $chatId, 'text' => $text]));
            Hm_Functions::c_setopt($curl_handle, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
            $curl_result = Hm_Functions::c_exec($curl_handle);
            if (trim($curl_result)) {
                $response_data = json_decode($curl_result, true);
                if (!$response_data['ok']) {

                }
            }
        }
    }

    /**
     * get the chat ID using webhook_token
     * @param string $webhook_token
     */
    private static function get_chat_id($webhook_token) {
        $curl_handle = Hm_Functions::c_init();
        Hm_Functions::c_setopt($curl_handle, CURLOPT_URL, static::PREFIX_URI.'bot'.$webhook_token.'/getUpdates');
        Hm_Functions::c_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $curl_result = Hm_Functions::c_exec($curl_handle);
        if (trim($curl_result)) {
            $response_data = json_decode($curl_result, true);
            if(!empty($chatId = $response_data['result'][0]['message']['chat']['id'])){
                return $chatId;
            } else {
                Hm_Msgs::add('ERRNo messages found. Please send a message to your bot first.<br>');
                return '';
            }
        }
    }

    /**
     * This function is usefull when trying to resend, we need to delete old webhook before we send a new one
     * delete the webhook using webhook_token if there is one
     * sometimes the webhook is not deleted, so we need to delete it manually
     * and sometines we are gettiting not found error
     * @param string $webhook_token
     */
    private static function delete_webhook($webhook_token) {
        $curl_handle = Hm_Functions::c_init();
        Hm_Functions::c_setopt($curl_handle, CURLOPT_URL, static::PREFIX_URI.'bot'.$webhook_token.'/delete_webhook');
        Hm_Functions::c_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        Hm_Functions::c_exec($curl_handle);
    }
}
