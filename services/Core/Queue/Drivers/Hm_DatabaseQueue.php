<?php

namespace Services\Core\Queue\Drivers;

use PDO;
use Hm_DB;
use Services\Core\Jobs\Hm_BaseJob;
use Services\Contracts\Queue\Hm_Queueable;
use Services\Contracts\Queue\Hm_ShouldQueue;

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
     * @param Hm_BaseJob $job
     * @return void
     */
    public function push(Hm_BaseJob $job): void {
        $sql = "INSERT INTO hm_jobs (payload) VALUES (:payload)";
        try {
            // Use the __serialize method from the Serializer trait
            $this->db->execute($this->dbConnection, $sql, ['payload' => serialize($job)], 'insert');
        } catch (\Throwable $th) {
            throw new \Exception("Failed to push job to the queue: " . $th->getMessage());
        }
    }

    /**
     * Pop the job from the queue
     *
     * @return Hm_BaseJob|null
     */
    public function pop(): ?Hm_BaseJob {
        $sql = "SELECT * FROM hm_jobs ORDER BY id ASC LIMIT 1";
        $jobRecord = $this->db->execute($this->dbConnection, $sql, [], 'select');

        if ($jobRecord) {
            $deleteSql = "DELETE FROM hm_jobs WHERE id = :id";
            $this->db->execute($this->dbConnection, $deleteSql, ['id' => $jobRecord['id']], 'modify');

            // Use the __unserialize method from the Serializer trait
            $job = unserialize($jobRecord['payload']);
            $job->incrementAttempts();
            return $job;
        }

        return null;
    }

    /**
     * Release the job back into the queue
     *
     * @param Hm_BaseJob $job
     * @param int $delay
     * @return void
     */
    public function release(Hm_BaseJob $job, int $delay = 0): void {
        if ($delay > 0) {
            sleep($delay);
        }
        $this->push($job);
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
     * Move job to failed jobs table after max attempts.
     *
     * @param Hm_BaseJob $job
     * @param Exception $exception
     * @return void
     */
    public function fail(Hm_BaseJob $job, \Exception $exception): void
    {
        $sql = "INSERT INTO " . self::FAILED_JOBS_TABLE . " (payload, failed_at, exception) VALUES (:payload, :failed_at, :exception)";
        $this->db->execute(
            $this->dbConnection,
            $sql,
            [
                'payload' => serialize($job), // This still requires serialization, keep in mind
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
    public function retry(int $failedJobId): void
    {
        $sql = "SELECT * FROM " . self::FAILED_JOBS_TABLE . " WHERE id = :id";
        $failedJobRecord = $this->db->execute($this->dbConnection, $sql, ['id' => $failedJobId], 'select');

        if ($failedJobRecord) {
            $job = unserialize($failedJobRecord['payload']);

            // Remove from failed jobs table
            $deleteSql = "DELETE FROM " . self::FAILED_JOBS_TABLE . " WHERE id = :id";
            $this->db->execute($this->dbConnection, $deleteSql, ['id' => $failedJobId], 'modify');

            // Push back to the main queue
            $this->push($job);
        }
    }

    /**
     * Log the failed job.
     *
     * @param Hm_BaseJob $job
     * @return void
     */
    protected function logFailedJob(Hm_BaseJob $job): void {
        $sql = "INSERT INTO failed_jobs (payload, attempts) VALUES (:payload, :attempts)";
        $this->db->execute($this->dbConnection, $sql, [
            'payload' => serialize($job), // This still requires serialization
            'attempts' => $job->getAttempts()
        ], 'insert');
    }
}
