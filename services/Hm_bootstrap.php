<?php

use Services\Core\Hm_Container;
use Services\ImapConnectionManager;

$containerBuilder = Hm_Container::getContainer();

if (!$containerBuilder->hasDefinition(ImapConnectionManager::class)) {
    //register cypht(we need imap class in the service)
    $containerBuilder
        ->register(ImapConnectionManager::class, ImapConnectionManager::class)
        ->addArgument([]);
}

return [$containerBuilder];
