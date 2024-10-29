<?php

namespace Services\Core\Events;

abstract class Hm_BaseEvent
{

    protected array $params;

    public function __construct(...$params)
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
