<?php

namespace Services\Core\Queue\Drivers;

use PDO;
use Hm_DB;
use Services\Contracts\Queue\Hm_Queueable;
use Services\Contracts\Queue\Hm_ShouldQueue;
use Services\Core\Notifications\Hm_Notification;
use Services\Core\Queue\Hm_Queueable as Hm_QueueableClass;

/**
 * Class Hm_DatabaseQueue
 * @package Services\Core\Queue\Drivers
 */
class Hm_DatabaseQueue implements Hm_ShouldQueue, Hm_Queueable
{
    protected const FAILED_JOBS_TABLE = 'hm_failed_jobs';

    /**
     * Hm_DatabaseQueue constructor.
     * @param Hm_DB $db
     * @param PDO $dbConnection
     */
    public function __construct(private Hm_DB $db, protected PDO $dbConnection) {}

    /**
     * Push the job to the queue
     *
     * @param Hm_QueueableClass $item
     * @return void
     */
    public function push(Hm_QueueableClass $item): void {
        $sql = "INSERT INTO hm_jobs (payload) VALUES (:payload)";
        try {
            // Use the __serialize method from the Serializer trait
            $this->db->execute($this->dbConnection, $sql, ['payload' => serialize($item)], 'insert');
        } catch (\Throwable $th) {
            throw new \Exception("Failed to push job to the queue: " . $th->getMessage());
        }
    }

    /**
     * Pop the job from the queue
     *
     * @return Hm_QueueableClass|null
     */
    public function pop(): ?Hm_QueueableClass {
        $sql = "SELECT * FROM hm_jobs ORDER BY id ASC LIMIT 1";
        $itemRecord = $this->db->execute($this->dbConnection, $sql, [], 'select');

        if ($itemRecord) {
            $deleteSql = "DELETE FROM hm_jobs WHERE id = :id";
            $this->db->execute($this->dbConnection, $deleteSql, ['id' => $itemRecord['id']], 'modify');

            // Use the __unserialize method from the Serializer trait
            $job = unserialize($itemRecord['payload']);
            $job->incrementAttempts();
            return $job;
        }

        return null;
    }

    /**
     * Release the job back into the queue
     *
     * @param Hm_QueueableClass $item
     * @param int $delay
     * @return void
     */
    public function release(Hm_QueueableClass $item, int $delay = 0): void {
        if ($delay > 0) {
            sleep($delay);
        }
        $this->push($item);
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
            // Check if the item is a notification, if so send it
            if($item instanceof Hm_Notification) {
                $item->send();
            }else {
                // Otherwise handle the job
                $item->handle();
            }
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
     * Move job to failed jobs table after max attempts.
     *
     * @param Hm_QueueableClass $item
     * @param Exception $exception
     * @return void
     */
    public function fail(Hm_QueueableClass $item, \Exception $exception): void
    {
        $sql = "INSERT INTO " . self::FAILED_JOBS_TABLE . " (payload, failed_at, exception) VALUES (:payload, :failed_at, :exception)";
        $this->db->execute(
            $this->dbConnection,
            $sql,
            [
                'payload' => serialize($item), // This still requires serialization, keep in mind
                'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'exception' => $exception->getMessage()
            ],
            'insert'
        );
    }

    /**
     * Retry a failed job by moving it back to the main queue.
     *
     * @param int $failedJobId
     * @return void
     */
    public function retry(int $failedItemId): void
    {
        $sql = "SELECT * FROM " . self::FAILED_JOBS_TABLE . " WHERE id = :id";
        $failedItemRecord = $this->db->execute($this->dbConnection, $sql, ['id' => $failedItemId], 'select');

        if ($failedItemRecord) {
            $item = unserialize($failedItemRecord['payload']);

            // Remove from failed jobs table
            $deleteSql = "DELETE FROM " . self::FAILED_JOBS_TABLE . " WHERE id = :id";
            $this->db->execute($this->dbConnection, $deleteSql, ['id' => $failedItemId], 'modify');

            // Push back to the main queue
            $this->push($item);
        }
    }

    /**
     * Log the failed job.
     *
     * @param Hm_QueueableClass $item
     * @return void
     */
    protected function logFailedJob(Hm_QueueableClass $item): void {
        $sql = "INSERT INTO failed_jobs (payload, attempts) VALUES (:payload, :attempts)";
        $this->db->execute($this->dbConnection, $sql, [
            'payload' => serialize($item), // This still requires serialization
            'attempts' => $item->getAttempts()
        ], 'insert');
    }
}
