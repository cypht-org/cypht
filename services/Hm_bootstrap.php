<?php

// use Services\Core\Scheduling\Scheduler;
use Services\Core\Queue\Hm_QueueManager;
use Services\Core\Queue\Hm_JobDispatcher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Services\Providers\{ Hm_CommandServiceProvider, Hm_EventServiceProvider, Hm_SchedulerServiceProvider, Hm_QueueServiceProvider };

define('APP_PATH', dirname(__DIR__).'/');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('CONFIG_PATH', APP_PATH.'config/');
define('WEB_ROOT', '');
define('ASSETS_THEMES_ROOT', '');
define('DEBUG_MODE', true);
define('CACHE_ID', '');
define('SITE_ID', '');
define('JS_HASH', '');
define('CSS_HASH', '');

/* show all warnings in debug mode */
if (DEBUG_MODE) {
    error_reporting(E_ALL);
}

/* don't let anything output content until we are ready */
ob_start();

require VENDOR_PATH.'autoload.php';
/* get includes */
require APP_PATH.'lib/framework.php';
$environment = Hm_Environment::getInstance();
$environment->load();

/* get configuration */
$config = new Hm_Site_Config_File();
/* set default TZ */
date_default_timezone_set($config->get('default_setting_timezone', 'UTC'));
/* set the default since and per_source values */
$environment->define_default_constants($config);

/* setup ini settings */
if (!$config->get('disable_ini_settings')) {
    require APP_PATH.'lib/ini_set.php';
}
// Initialize the scheduler
// $scheduler = new Scheduler($config);


$containerBuilder = new ContainerBuilder();

// Register Hm_DB
$containerBuilder->register('db', Hm_DB::class)
    ->setShared(true);

// Register Hm_Redis
$containerBuilder->register('redis', Hm_Redis::class)
    ->setShared(true);

// Register Hm_AmazonSQS
$containerBuilder->register('amazon.sqs', Hm_AmazonSQS::class)
    ->setShared(true);

// Register Hm_QueueManager
$containerBuilder->register('queue.manager', Hm_QueueManager::class)
    ->setShared(true);

// Register Hm_JobDispatcher
$containerBuilder->register('job.dispatcher', Hm_JobDispatcher::class)
    ->setShared(true);

// Register Hm_Site_Config_File
$containerBuilder->register('Hm_Site_Config_File', Hm_Site_Config_File::class)
    ->setShared(true);

// Register Hm_CommandServiceProvider
$containerBuilder->register('command.serviceProvider', Hm_CommandServiceProvider::class)
    ->setShared(true);

// Register Hm_QueueServiceProvider
$containerBuilder->register('queue.ServiceProvider',Hm_QueueServiceProvider::class)
    // ->addArgument(new \Symfony\Component\DependencyInjection\Reference(Hm_Site_Config_File::class))
    // ->addArgument(null)
    ->setShared(true);

$containerBuilder->register('scheduler.ServiceProvider', Hm_SchedulerServiceProvider::class)
    ->setShared(true);
$containerBuilder->register('event.ServiceProvider', Hm_EventServiceProvider::class)
    ->setShared(true);

// return $containerBuilder;
return [$containerBuilder,$config];
