<?php

namespace Services\Contracts;

interface Hm_Job
{
    public function handle(): void;
    public function failed(): void;
}