<?php

namespace Services\Core\Queue\Drivers;

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
     * @var Hm_DB
     */
    protected Hm_DB $db;

    /**
     * Hm_DatabaseQueue constructor.
     * @param Hm_DB $db
     */
    public function __construct(Hm_DB $db) {
        $this->db = $db;
    }

    /**
     * Push the job to the queue
     *
     * @param Hm_BaseJob $job
     * @return void
     */
    public function push(Hm_BaseJob $job): void {
        $dbh = $this->db->connect($this->db->getConfig());
        $sql = "INSERT INTO jobs (payload) VALUES (:payload)";
        $this->db->execute($dbh, $sql, ['payload' => serialize($job)], 'insert');
    }

    /**
     * Pop the job from the queue
     *
     * @return Hm_BaseJob|null
     */
    public function pop(): ?Hm_BaseJob {
        $dbh = $this->db->connect($this->db->getConfig());
        $sql = "SELECT * FROM jobs ORDER BY id ASC LIMIT 1";
        $jobRecord = $this->db->execute($dbh, $sql, [], 'select');

        if ($jobRecord) {
            $deleteSql = "DELETE FROM jobs WHERE id = :id";
            $this->db->execute($dbh, $deleteSql, ['id' => $jobRecord['id']], 'modify');
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
