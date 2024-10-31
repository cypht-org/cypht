<?php

namespace Services\Listeners;

class Hm_NewMaiListener
{
    public function handle(object $event): void
    {
        // dd($event);
        // dd("Hm_NewMaiListener: New email processed: {$event->email}");
        //TO DO: we implment notification dispatch here
    }
}