<?php

namespace Services\Core\Jobs;

use Services\Contracts\Hm_Job;
use Services\Core\Queue\Hm_Queueable;

abstract class Hm_BaseJob extends Hm_Queueable implements Hm_Job
{
    public function __construct(protected array $data = []) {
        $this->data = $data;
    }
}
