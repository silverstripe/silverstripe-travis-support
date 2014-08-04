#!/usr/bin/env php
<?php
/**
 * Assumes to run in a SilverStripe webroot
 */
require_once 'lib.php';

$opts = getopt('', array(
	'if-env:',
	'base-url:',
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!checkenv(@$opts['if-env'])) {
	echo "Apache skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

$baseurl = (isset($opts['base-url'])) ? $opts['base-url'] : 'http://localhost:8000';

echo "Starting Selenium...\n";

if(!putenv('XVFBARGS=:99 -ac -screen 0 1024x768x16"')) echo "ERROR: Could not set xvfb options!\n";
run("sh -e /etc/init.d/xvfb start");
if(!putenv("DISPLAY=:99")) echo "ERROR: Could not set display!\n";
run("wget https://selenium-release.storage.googleapis.com/2.41/selenium-server-standalone-2.41.0.jar");
if(!file_exists('artifacts')) mkdir('artifacts');
run("java -jar selenium-server-standalone-2.41.0.jar > artifacts/selenium.log 2>&1 &");
sleep(5);

// Write templated behat configuration
$behatTemplate = file_get_contents(dirname(__FILE__).'/behat.tmpl.yml');
$behat = str_replace(
	array('$BASE_URL'),
	array($baseurl),
	$behatTemplate
);
echo "Writing behat.yml\n";
echo $behat . "\n";
file_put_contents("behat.yml", $behat);

if(file_exists("mysite/_config/behat.yml")) unlink("mysite/_config/behat.yml");
run("php framework/cli-script.php dev/generatesecuretoken path=mysite/_config/behat.yml");
