<?php

namespace Services\Core\Queue\Drivers;

use Hm_AmazonSQS;
use Services\Jobs\Hm_BaseJob;
use Services\Contracts\Queue\Hm_ShouldQueue;

/**
 * Amazon SQS Queue
 */
class Hm_AmazonSQSQueue implements Hm_ShouldQueue
{
    /**
     * @var Hm_AmazonSQS
     */
    protected Hm_AmazonSQS $amazonSQS;

    /**
     * Constructor
     *
     * @param Hm_AmazonSQS $amazonSQS
     */
    public function __construct(Hm_AmazonSQS $amazonSQS)
    {
        $this->amazonSQS = $amazonSQS;
    }

    /**
     * Push the job to the queue
     *
     * @param Hm_BaseJob $job
     * @return void
     */
    public function push(Hm_BaseJob $job): void
    {
        $this->amazonSQS->sendMessage(serialize($job));
    }

    /**
     * Pop the job from the queue
     *
     * @return Hm_BaseJob|null
     */
    public function pop(): ?Hm_BaseJob
    {
        $messages = $this->amazonSQS->receiveMessages();

        if (!empty($messages)) {
            $message = $messages[0];
            $receiptHandle = $message['ReceiptHandle'];

            $this->amazonSQS->deleteMessage($receiptHandle);

            return unserialize($message['Body']);
        }

        return null;
    }

    /**
     * Release the job back to the queue
     *
     * @param Hm_BaseJob $job
     * @param int $delay
     * @return void
     */
    public function release(Hm_BaseJob $job, int $delay = 0): void
    {
        $messageBody = serialize($job);
        $this->amazonSQS->sendMessage($messageBody, $delay);
    }
}
