<?php

namespace Services\Contracts\Queue;

use Services\Core\Jobs\BaseJob;

interface Queueable
{
    public function process(BaseJob $job): void;
}