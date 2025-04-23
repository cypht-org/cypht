<?php

namespace Services\Providers;

use Services\Core\Events\EventDispatcher;
use Services\Listeners\NewMaiListener;
use Services\Events\NewEmailProcessedEvent;

class EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected array $listen = [
        NewEmailProcessedEvent::class => [
            NewMaiListener::class,
        ],
    ];

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                EventDispatcher::listen($event, $listener);
            }
        }
    }
}
