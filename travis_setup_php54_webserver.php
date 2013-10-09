#!/usr/bin/env php
<?php
/**
 * Assumes to run in a SilverStripe webroot
 */
require_once 'lib.php';

$opts = getopt('', array(
	'if-env:',
	'base-url:'
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!checkenv(@$opts['if-env'])) {
	echo "PHP Webserver Setup skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

$baseurl = (isset($opts['base-url'])) ? $opts['base-url'] : 'localhost:8000';

echo "Starting PHP internal webserver at $baseurl...\n";
run("php -S $baseurl framework/main.php > /dev/null 2>&1 &");
sleep(5);
run("ps aux | grep php");