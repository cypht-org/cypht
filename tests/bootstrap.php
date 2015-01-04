<?php

/* debug mode has to be set to something or include files will die() */
define('DEBUG_MODE', false);

/* determine current absolute path used for require statements */
define('APP_PATH', dirname(dirname(__FILE__)).'/');

/* get the framework */
require APP_PATH.'lib/framework.php';

/* get mock objects */
require APP_PATH.'tests/mocks.php';

?>
