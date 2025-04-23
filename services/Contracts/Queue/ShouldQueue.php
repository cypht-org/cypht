<?php

namespace Services\Contracts\Queue;

use Services\Core\Queue\Queueable;

interface ShouldQueue
{
    public function push(Queueable $queueable): void;
    public function pop(): ?Queueable;
    public function release(Queueable $queueable, int $delay = 0): void;
}