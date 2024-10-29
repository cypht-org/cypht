<?php

namespace Services\Listeners;

class Hm_NewMaiListener
{
    public function handle(object $event): void
    {
        var_dump("Hm_NewMaiListener: New email processed: {$event->email}");
        //TO DO: we implment notification dispatch here
    }
}