#!/usr/bin/env php
<?php
/**
 * Assumes to run in a SilverStripe webroot
 */

require_once 'lib.php';

$opts = getopt('', array(
	'if-env:',
	'username:',
	'access-key:',
	'tunnel-identifier:',
	'base-url:',
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!checkenv(@$opts['if-env'])) {
	echo "Apache skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

echo "Starting Sauce Connect...\n";

// Download Sauce Connect
$connectURL = "http://saucelabs.com/downloads/Sauce-Connect-latest.zip";
$connectDir = "/tmp/sauce-connect-" . rand(100000,999999);
$connectDownload = $connectDir."/Sauce_Connect.zip";
$readyFile = $connectDir."/connect-ready-" . rand(100000,999999);

$CLI_connectURL = escapeshellarg($connectURL);
$CLI_connectDir = escapeshellarg($connectDir);
$CLI_connectDownload = escapeshellarg($connectDownload);
$CLI_readyFile = escapeshellarg($readyFile);

mkdir($connectDir, 0777, true);

// Download sauce connect if not already downloaded
if(!file_exists("Sauce-Connect.jar")) {
	run("curl $CLI_connectURL > $CLI_connectDownload");
	run("unzip $CLI_connectDownload");
	run("rm $CLI_connectDownload");
}

// Start Sauce Connect
run("java -jar Sauce-Connect.jar --readyfile $CLI_readyFile --tunnel-identifier {$opts['tunnel-identifier']}"
	. " {$opts['username']} {$opts['access-key']} > /dev/null &");

while(!file_exists($readyFile)) {
	usleep(500000);
}

// Write templated behat configuration
$behatTemplate = file_get_contents(dirname(__FILE__).'/behat-sauceconnect.tmpl.yml');
$behat = str_replace(
	array('$BASE_URL' ,'$SAUCE_USERNAME', '$SAUCE_ACCESS_KEY', '$TUNNEL_IDENTIFIER'),
	array($opts['base-url'] ,$opts['username'], $opts['access-key'], $opts['tunnel-identifier']),
	$behatTemplate
);
echo "Writing behat.yml\n";
echo $behat . "\n";
file_put_contents("behat.yml", $behat);

if(file_exists("mysite/_config/behat.yml")) unlink("mysite/_config/behat.yml");
run("php framework/cli-script.php dev/generatesecuretoken path=mysite/_config/behat.yml");
