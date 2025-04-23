<?php

namespace Services\Core\Events;

use Services\Traits\Serializes;

abstract class BaseEvent
{
    use Serializes;
    
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
