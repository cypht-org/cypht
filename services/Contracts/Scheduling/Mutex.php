<?php

namespace Services\Contracts\Scheduling;

interface Mutex
{
    public function create($task, $expiresAt);
    public function exists($task);
    public function release($task);
}
