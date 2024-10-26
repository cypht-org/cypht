<?php

namespace Services\Events;

abstract class Hm_BaseEvent
{
    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    abstract public function getEventName(): string;
}
