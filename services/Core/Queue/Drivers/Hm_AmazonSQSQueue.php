<?php

namespace Services\Core\Queue\Drivers;

use Hm_AmazonSQS;
use Aws\Sqs\SqsClient;
use Services\Contracts\Queue\Hm_Queueable;
use Services\Core\Jobs\Hm_BaseJob;
use Services\Contracts\Queue\Hm_ShouldQueue;

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
     * @param Hm_BaseJob $job
     * @return void
     */
    public function push(Hm_BaseJob $job): void
    {
        $this->amazonSQS->sendMessage($this->sqsConnection, serialize($job));
    }

    /**
     * Pop the job from the queue
     *
     * @return Hm_BaseJob|null
     */
    public function pop(): ?Hm_BaseJob
    {
        $messages = $this->amazonSQS->receiveMessages($this->sqsConnection);
        if (!empty($messages)) {
            $message = $messages[0];
            $receiptHandle = $message['ReceiptHandle'];
            $body = $message['Body'];
            $this->amazonSQS->deleteMessage($this->sqsConnection, $receiptHandle);

            $job = unserialize($body);

            try {
                $job->handle();
            } catch (\Exception $e) {
                $this->fail($job, $e); // Log the failure
                throw new \Exception("Failed to process job: " . $e->getMessage());
            }

            return $job; // Return the job if it was processed successfully
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
        $this->amazonSQS->sendMessage($this->sqsConnection, $messageBody, $delay);
    }

     /**
     * Process the job and handle failures.
     *
     * @param Hm_BaseJob $job
     * @param int $maxAttempts
     * @return void
     */
    public function process(Hm_BaseJob $job): void
    {
        try {
            $job->handle();
        } catch (\Exception $e) {
            $job->incrementAttempts();
            if ($job->getAttempts() >= $job->tries) {
                $this->fail($job, $e);
            } else {
                $this->release($job, 5);
            }
        }
    }

    /**
     * Move a job to the failed jobs queue after max attempts
     *
     * @param Hm_BaseJob $job
     * @param \Exception $exception
     * @return void
     */
    protected function fail(Hm_BaseJob $job, \Exception $exception): void
    {
        $failedJobData = [
            'job' => serialize($job),
            'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'exception' => $exception->getMessage()
        ];

        // You may want to handle how you store failed jobs.
        // For simplicity, we will just serialize and log them here,
        // but ideally, you would want to use a persistent store.
        $this->amazonSQS->sendMessage($this->sqsConnection, serialize($failedJobData), 0, $this->failedQueue);
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
            $failedJobData = unserialize($body);
            $job = unserialize($failedJobData['job']);
            
            // Optionally reset attempts if your job has that logic
            $job->resetAttempts(); 
            $this->push($job); // Push back to current queue
            
            // Optionally delete the failed job message
            $this->amazonSQS->deleteMessage($this->sqsConnection, $message['ReceiptHandle']);
        }
    }
}
