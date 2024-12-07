<?php

namespace Services\Core\Queue\Drivers;

use Hm_AmazonSQS;
use Aws\Sqs\SqsClient;
use Services\Contracts\Queue\Hm_Queueable;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Core\Notifications\Hm_Notification;
use Services\Core\Queue\Hm_Queueable as Hm_QueueableClass;

/**
 * Amazon SQS Queue
 */
class Hm_AmazonSQSQueue implements Hm_ShouldQueue, Hm_Queueable
{
    /**
     * @var Hm_AmazonSQS
     */
    protected Hm_AmazonSQS $amazonSQS;

    /**
     * @var SqsClient
     */
    protected SqsClient $sqsConnection;

    /**
     * Queue name for failed jobs
     *
     * @var string
     */
    protected string $failedQueue;

    /**
     * Constructor
     *
     * @param Hm_AmazonSQS $amazonSQS
     * @param SqsClient $sqsConnection
     */
    public function __construct(Hm_AmazonSQS $amazonSQS, SqsClient $sqsConnection)
    {
        $this->amazonSQS = $amazonSQS;
        $this->sqsConnection = $sqsConnection;
        $this->failedQueue = 'failed_jobs';
    }

    /**
     * Push the job to the queue
     *
     * @param Hm_QueueableClass $item
     * @return void
     */
    public function push(Hm_QueueableClass $item): void
    {
        $this->amazonSQS->sendMessage($this->sqsConnection, serialize($item));
    }

    /**
     * Pop the job from the queue
     *
     * @return Hm_QueueableClass|null
     */
    public function pop(): ?Hm_QueueableClass
    {
        $messages = $this->amazonSQS->receiveMessages($this->sqsConnection);
        if (!empty($messages)) {
            $message = $messages[0];
            $receiptHandle = $message['ReceiptHandle'];
            $body = $message['Body'];
            $this->amazonSQS->deleteMessage($this->sqsConnection, $receiptHandle);

            $item = unserialize($body);

            try {
                // Check if the item is a notification, if so send it
                if($item instanceof Hm_Notification) {
                    $item->send();
                }else {
                    // Otherwise handle the job
                    $item->handle();
                }
            } catch (\Exception $e) {
                $this->fail($item, $e); // Log the failure
                throw new \Exception("Failed to process job: " . $e->getMessage());
            }

            return $item; // Return the job if it was processed successfully
        }

        return null;
    }

    /**
     * Release the job back to the queue
     *
     * @param Hm_QueueableClass $item
     * @param int $delay
     * @return void
     */
    public function release(Hm_QueueableClass $item, int $delay = 0): void
    {
        $messageBody = serialize($item);
        $this->amazonSQS->sendMessage($this->sqsConnection, $messageBody, $delay);
    }

     /**
     * Process the job and handle failures.
     *
     * @param Hm_QueueableClass $item
     * @param int $maxAttempts
     * @return void
     */
    public function process(Hm_QueueableClass $item): void
    {
        try {
            $item->handle();
        } catch (\Exception $e) {
            $item->incrementAttempts();
            if ($item->getAttempts() >= $item->tries) {
                $this->fail($item, $e);
            } else {
                $this->release($item, 5);
            }
        }
    }

    /**
     * Move a job to the failed jobs queue after max attempts
     *
     * @param Hm_QueueableClass $iten
     * @param \Exception $exception
     * @return void
     */
    protected function fail(Hm_QueueableClass $item, \Exception $exception): void
    {
        $failedItemData = [
            'item' => serialize($item),
            'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'exception' => $exception->getMessage()
        ];

        // You may want to handle how you store failed jobs.
        // For simplicity, we will just serialize and log them here,
        // but ideally, you would want to use a persistent store.
        $this->amazonSQS->sendMessage($this->sqsConnection, serialize($failedItemData), 0, $this->failedQueue);
    }

    /**
     * Retry failed jobs
     *
     * @return void
     */
    public function retryFailedJobs(): void
    {
        $messages = $this->amazonSQS->receiveMessages($this->sqsConnection, $this->failedQueue);
        foreach ($messages as $message) {
            $body = $message['Body'];
            $failedItemData = unserialize($body);
            $item = unserialize($failedItemData['item']);
            
            // Optionally reset attempts if your job has that logic
            $item->resetAttempts(); 
            $this->push($item); // Push back to current queue
            
            // Optionally delete the failed job message
            $this->amazonSQS->deleteMessage($this->sqsConnection, $message['ReceiptHandle']);
        }
    }
}
