<?php

namespace Services\Contracts\Queue;

use Services\Core\Jobs\Hm_BaseJob;

interface Hm_Queueable
{
    public function process(Hm_BaseJob $job): void;
}