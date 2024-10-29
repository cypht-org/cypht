<?php

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

/**
 * Amazon SQS wrapper
 * @package framework
 * @subpackage queue
 */
class Hm_AmazonSQS {

    /** SQS Client */
    private static $sqsClient;

    /** Required configuration parameters */
    static private $required_config = ['aws_key', 'aws_secret', 'aws_region', 'sqs_queue_url'];

    /** SQS config */
    static private $config;

    /**
     * Load SQS configuration from the site config
     * @param object $site_config site config
     * @return void
     */
    static private function parse_config($site_config) {
        self::$config = [
            'aws_key' => $site_config->get('aws_key', false),
            'aws_secret' => $site_config->get('aws_secret', false),
            'aws_region' => $site_config->get('aws_region', false),
            'sqs_queue_url' => $site_config->get('sqs_queue_url', false),
        ];

        foreach (self::$required_config as $v) {
            if (!self::$config[$v]) {
                Hm_Debug::add(sprintf('Missing configuration setting for %s', $v));
            }
        }
    }

    /**
     * Connect to Amazon SQS
     * @param object $site_config site settings
     * @return this|false
     */
    static public function connect($site_config) {
        self::parse_config($site_config);

        if (self::$sqsClient) {
            return self::$sqsClient;
        }

        try {
            self::$sqsClient = new SqsClient([
                'version' => 'latest',
                'region' => self::$config['aws_region'],
                'credentials' => [
                    'key' => self::$config['aws_key'],
                    'secret' => self::$config['aws_secret'],
                ],
            ]);
            Hm_Debug::add('Connected to Amazon SQS');
            return self::$sqsClient;
        } catch (AwsException $e) {
            Hm_Debug::add($e->getMessage());
            Hm_Msgs::add('ERRUnable to connect to Amazon SQS. Please check your configuration settings and try again.');
            return false;
        }
    }

    /**
     * Send a message to the SQS queue
     * @param string $message
     * @return string|false Message ID or false on failure
     */
    static public function sendMessage($message, int $delay = 0) {
        if (!self::$sqsClient) {
            return false;
        }

        try {
            $result = self::$sqsClient->sendMessage([
                'QueueUrl'    => self::$config['sqs_queue_url'],
                'MessageBody' => $message,
                'DelaySeconds' => $delay,
            ]);
            return $result['MessageId'];
        } catch (AwsException $e) {
            Hm_Debug::add($e->getMessage());
            return false;
        }
    }

    /**
     * Receive messages from the SQS queue
     * @param int $maxMessages
     * @return array
     */
    static public function receiveMessages($maxMessages = 1) {
        if (!self::$sqsClient) {
            return [];
        }

        try {
            $result = self::$sqsClient->receiveMessage([
                'QueueUrl' => self::$config['sqs_queue_url'],
                'MaxNumberOfMessages' => $maxMessages,
                'WaitTimeSeconds' => 10,
            ]);
            return $result->get('Messages') ?: [];
        } catch (AwsException $e) {
            Hm_Debug::add($e->getMessage());
            return [];
        }
    }

    /**
     * Delete a message from the SQS queue
     * @param string $receiptHandle
     * @return bool
     */
    static public function deleteMessage($receiptHandle) {
        if (!self::$sqsClient) {
            return false;
        }

        try {
            self::$sqsClient->deleteMessage([
                'QueueUrl' => self::$config['sqs_queue_url'],
                'ReceiptHandle' => $receiptHandle,
            ]);
            return true;
        } catch (AwsException $e) {
            Hm_Debug::add($e->getMessage());
            return false;
        }
    }
}
