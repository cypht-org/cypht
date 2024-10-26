<?php

namespace Services\Contracts\Queue;

use Services\Jobs\Hm_BaseJob;

interface Hm_ShouldQueue
{
    public function push(Hm_BaseJob $job): void;
    public function pop(): ?Hm_BaseJob;
    public function release(Hm_BaseJob $job, int $delay = 0): void;
}