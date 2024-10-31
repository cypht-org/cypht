<?php

namespace Services\Core\Events;

use Services\Traits\Hm_Serializes;

abstract class Hm_BaseEvent
{
    use Hm_Serializes;
    
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
