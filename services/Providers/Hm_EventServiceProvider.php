<?php

namespace Services\Providers;

use Services\Core\Events\Hm_EventDispatcher;
use Services\Listeners\Hm_NewMaiListener;
use Services\Events\Hm_NewEmailProcessedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Hm_EventServiceProvider
{
    protected array $listen = [
        Hm_NewEmailProcessedEvent::class => [
            Hm_NewMaiListener::class,
        ],
    ];

    public function register(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Hm_EventDispatcher::listen($event, $listener);
            }
        }
    }
}
