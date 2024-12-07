<?php

namespace Services\Contracts\Queue;

use Services\Core\Queue\Hm_Queueable;

interface Hm_ShouldQueue
{
    public function push(Hm_Queueable $queueable): void;
    public function pop(): ?Hm_Queueable;
    public function release(Hm_Queueable $queueable, int $delay = 0): void;
}