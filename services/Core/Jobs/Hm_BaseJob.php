<?php

namespace Services\Core\Jobs;

use Services\Contracts\Hm_Job;

abstract class Hm_BaseJob implements Hm_Job
{
    protected string $driver = 'database';
    public function __construct(protected array $data = []) {}

    public function handle(): void {}
    public function failed(): void {}

    public function getDriver(): string
    {
        return $this->driver;
    }

}
