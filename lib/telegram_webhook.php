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
        // var_dump($chatId);
        // print_r('chatId response: ');
        // print_r($chatId);
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
                    Hm_Debug::add("ERRMessage not sent: ".$response_data['description']);
                }
            }
        }else{
            Hm_Debug::add('No chat found, please check your token.');
        }
    }

    /**
     * get the chat ID using webhook_token
     * @param string $webhook_token
     */
    public static function get_chat_id($webhook_token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::PREFIX_URI . 'bot' . $webhook_token . '/getUpdates');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $curl_result = curl_exec($ch);

        if ($curl_result === false) {
            Hm_Msgs::add('cURL Error: ' . curl_error($ch) . '<br>');
            curl_close($ch);
            return '';
        }

        curl_close($ch);
        if (trim($curl_result)) {
            $response_data = json_decode($curl_result, true);
            file_put_contents('./debug.log', 'Raw cURL result: ' . $response_data['result'] . "\n", FILE_APPEND);
    
            // Log the decoded response data for debugging    
            if (isset($response_data['result'][0]['message']['chat']['id']) && !empty($response_data['result'][0]['message']['chat']['id'])) {
                $chatId = $response_data['result'][0]['message']['chat']['id'];
                return $chatId;
            } else {
                Hm_Debug::add('ERRNo messages found. Please send a message to your bot first.<br>');
                return '';
            }
        }





        // $curl_handle = Hm_Functions::c_init();
        // Hm_Functions::c_setopt($curl_handle, CURLOPT_URL, static::PREFIX_URI . 'bot' . $webhook_token . '/getUpdates');
        // Hm_Functions::c_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        // $curl_result = Hm_Functions::c_exec($curl_handle);
        // file_put_contents('./debug.log', 'Raw cURL result: ' . $curl_result . "\n", FILE_APPEND);

        // if ($curl_result === false) {
        //     // Hm_Msgs::add('cURL Error: ' . Hm_Functions::c_error($curl_handle) . '<br>');
        //     // Hm_Functions::c_close($curl_handle);
        //     return '';
        // }
    
        // // Hm_Functions::c_close($curl_handle);
    
        // if (trim($curl_result)) {
        //     $response_data = json_decode($curl_result, true);
        //     if (isset($response_data['result'][0]['message']['chat']['id']) && !empty($response_data['result'][0]['message']['chat']['id'])) {
        //         $chatId = $response_data['result'][0]['message']['chat']['id'];
        //         return $chatId;
        //     } else {
        //         Hm_Msgs::add('ERRNo messages found. Please send a message to your bot first.<br>');
        //         return '';
        //     }
        // }
    }

    /**
     * This function is usefull when trying to resend, we need to delete old webhook before we send a new one
     * delete the webhook using webhook_token if there is one
     * sometimes the webhook is not deleted, so we need to delete it manually
     * and sometines we are gettiting not found error
     * @param string $webhook_token
     */
    public static function delete_webhook($webhook_token) {
        $curl_handle = Hm_Functions::c_init();
        Hm_Functions::c_setopt($curl_handle, CURLOPT_URL, static::PREFIX_URI.'bot'.$webhook_token.'/delete_webhook');
        Hm_Functions::c_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        Hm_Functions::c_exec($curl_handle);
    }
}
