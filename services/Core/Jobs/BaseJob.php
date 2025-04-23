<?php

namespace Services\Core\Jobs;

use Services\Contracts\Job;
use Services\Core\Queue\Queueable;

abstract class BaseJob extends Queueable implements Job
{
    public function __construct(protected array $data = []) {
        $this->data = $data;
        $this->driver = env('QUEUE_DRIVER');
    }
}
