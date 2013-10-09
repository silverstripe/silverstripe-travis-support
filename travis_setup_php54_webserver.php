#!/usr/bin/env php
<?php


$opts = getopt('', array(
	'if-env:',
	'logpath:',
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!empty($opts['if-env']) && !getenv($opts['if-env'])) {
	echo "PHP Webserver Setup skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

$logpath = (isset($opts['logpath'])) ? $opts['logpath'] : '~/builds/ss/artifacts/access.log';

echo "Starting PHP internal webserver...\n";

mkdir(dirname($logpath), 0777, true);
touch($logpath);
passthru("php -S localhost:80 framework/main.php > $logpath 2>&1 &");
sleep(3);