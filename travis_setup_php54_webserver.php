#!/usr/bin/env php
<?php


$opts = getopt('', array(
	'if-env:',
	'basepath:',
	'logpath:',
	'base-url:'
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!empty($opts['if-env']) && !getenv($opts['if-env'])) {
	echo "PHP Webserver Setup skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

$logpath = (isset($opts['logpath'])) ? $opts['logpath'] : '~/builds/ss/artifacts/access.log';
$basepath = (isset($opts['basepath'])) ? $opts['basepath'] : '~/builds/ss/';
$baseurl = (isset($opts['base-url'])) ? $opts['base-url'] : '127.0.0.1:8000';

echo "Starting PHP internal webserver at $baseurl...\n";

passthru("mkdir silverstripe-cache");
passthru("sudo chmod 777 -R .");

passthru('mkdir -p ' . dirname($logpath));
passthru('touch ' . $logpath);
passthru("php -S $baseurl {$basepath}framework/main.php > $logpath 2>&1 &");
passthru("ps aux | grep php");
sleep(3);