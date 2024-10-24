<?php

namespace Cypht\Service\Events;

abstract class BaseEvent
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
