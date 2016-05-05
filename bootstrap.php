<?php

/* load the composer file */
require __DIR__.'/vendor/autoload.php';

/* ensure the session is active */
if (session_id() == '') {
	session_start();
}

/* include the framework */
require __DIR__.'/src/AAF/App.php';

/* register the framework autoloader */
spl_autoload_register('AAF\App::autoload');