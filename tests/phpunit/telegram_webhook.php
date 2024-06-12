<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the Hm_Servers trait
 */
class Hm_Test_Telegram_Webhook extends TestCase {

    public $telegram_webhook;
    public function setUp(): void {
        require 'bootstrap.php';
        $this->telegram_webhook = new Hm_Telegram_Webhook();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    // public function test_send_with_bad_token() {
    //     $res = $this->telegram_webhook::send(0, 'demo@cypht.org', 'testtoken');
    //     sleep(2);
    //     return $this->assertNull($res);
    // }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    // public function test_send_with_correct_token() {
    //     $res = $this->telegram_webhook::send(0, 'demo@cypht.org', '7253394590:AAHIY9VmoH3uTRFtEwmlfRPGyKoZIQMCX5A');
    //     sleep(2);
    //     return $this->assertNull($res);
    // }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    // public function test_get_chat_id_with_bad_token() {
    //     $res = $this->telegram_webhook::get_chat_id('testtoken');
    //     sleep(2);
    //     return $this->assertEmpty($res);
    // }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_chat_id_with_correct_token() {
        // $res = $this->telegram_webhook::get_chat_id('6132059962:AAHrfhsq9kkg1CK3FsAFRaK4l5zfoCWu14s');
        // sleep(2);
        // var_dump($res);
        // return $this->assertEmpty($res);

        $chatId = '';
        $maxAttempts = 5;
        $attempt = 0;

        while (empty($chatId) && $attempt < $maxAttempts) {
            $chatId = Hm_Telegram_Webhook::get_chat_id('6132059962:AAHrfhsq9kkg1CK3FsAFRaK4l5zfoCWu14s');
            if (empty($chatId)) {
                sleep(1); // Wait for 1 second before retrying
            }
            $attempt++;
        }
        var_dump($chatId);
        // $this->assertNotEmpty($chatId, 'Chat ID should not be empty');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    // public function test_delete_webhook() {
    //     $this->telegram_webhook::delete_webhook('testtoken');
    // }
}
