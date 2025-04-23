<?php

namespace Services\Contracts;

interface Job
{
    public function handle(): void;
    public function failed(): void;
}