<?php

namespace Services\Core\Queue\Drivers;

use PDO;
use Hm_DB;
use Services\Core\Jobs\Hm_BaseJob;
use Services\Contracts\Queue\Hm_ShouldQueue;

/**
 * Class Hm_DatabaseQueue
 * @package App\Queue\Drivers
 */
class Hm_DatabaseQueue implements Hm_ShouldQueue
{

    /**
     * Hm_DatabaseQueue constructor.
     * @param Hm_DB $db
     * @param PDO $dbConnection
     */
    public function __construct(private Hm_DB $db, protected PDO $dbConnection) {
    }

    /**
     * Push the job to the queue
     *
     * @param Hm_BaseJob $job
     * @return void
     */
    public function push(Hm_BaseJob $job): void {
        $sql = "INSERT INTO hm_jobs (payload) VALUES (:payload)";
        $this->db->execute($this->dbConnection, $sql, ['payload' => serialize($job)], 'insert');
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
            return unserialize($jobRecord['payload']);
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
}
