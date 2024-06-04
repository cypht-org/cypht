<?php

/**
 * Webhook
 * @package framework
 * @subpackage webhook
 */

class Hm_Telegram_Webhook {

    /* webhook_token value */
    private $webhook_token;

    private $prefix_ui = 'https://api.telegram.org/';

    /**
     * Load webhook token
     * @param string $webhook_token
     */
    public function __construct($webhook_token) {
        $this->webhook_token = $webhook_token;
    }

    // Function to send Telegram notification
    /**
     * send telegram notiofication using curl
     * @param array $extracted_msgs
     */
    public function send(array $extracted_msgs) {
        // Delete the webhook
        if ($this->delete_webhook($this->webhook_token)) {
            // Get the chat ID
            $chatId = $this->get_chat_id();
            if ($chatId) {
                $text = "New Message\nFrom: {$extracted_msgs['from']}\nSubject: {$extracted_msgs['subject']}\nContent: {$extracted_msgs['body']}";

                $curl_handle = curl_init();
                curl_setopt($curl_handle, CURLOPT_URL, "{$this->prefix_uri}bot{$this->webhook_token}/sendMessage");
                curl_setopt($curl_handle, CURLOPT_POST, true);
                curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query(['chat_id' => $chatId, 'text' => $text]));
                curl_setopt($curl_handle, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);

                $response = curl_exec($curl_handle);
                if (curl_errno($ch)) {
                    Hm_Msgs::add('ERRError:' . curl_error($curl_handle));
                } else {
                    $response_data = json_decode($response, true);
                    if (!$response_data['ok']) {
                        Hm_Msgs::add('ERRFailed to send message: ' . $response_data['description']);
                    }
                }
                curl_close($curl_handle);
                unset($curl_handle);
            }
        }
    }

    /**
     * get the chat ID using webhook_token
     */
    private function get_chat_id() {
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, "{$this->prefix_ui}/bot{$this->webhook_token}/getUpdates");
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl_handle);

        if (curl_errno($curl_handle)) {
            Hm_Msgs::add('ERRError:' . curl_error($curl_handle));
            return false;
        } else {
            $response_data = json_decode($response, true);
            if ($response_data['ok']) {
                if (!empty($response_data['result'])) {
                    $chatId = $response_data['result'][0]['message']['chat']['id'];
                    return $chatId;
                } else {
                    Hm_Msgs::add('ERRNo messages found. Please send a message to your bot first.<br>');
                    return false;
                }
            } else {
                Hm_Msgs::add('ERRFailed to get chat ID: ' . $response_data['description'] . '<br>');
                return false;
            }
        }

        curl_close($curl_handle);
        unset($curl_handle);
    }

    /**
     * This function is usefull when trying to resend, we need to delete old webhook before we send a new one
     * delete the webhook using webhook_token
     */
    private function delete_webhook() {
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, "{$this->prefix_ui}bot{$this->webhook_token}/delete_webhook");
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl_handle);

        if (curl_errno($curl_handle)) {
            Hm_Msgs::add('ERRError:' . curl_error($curl_handle));
            return false;
        } else {
            $response_data = json_decode($response, true);
            if ($response_data['ok']) {
                Hm_Msgs::add('ERRWebhook was deleted successfully.<br>');
                return true;
            } else {
                Hm_Msgs::add('ERRFailed to delete webhook: ' . $response_data['description'] . '<br>');
                return false;
            }
        }

        curl_close($curl_handle);
        unset($curl_handle);
    }
}
