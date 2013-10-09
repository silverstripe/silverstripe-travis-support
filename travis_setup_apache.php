#!/usr/bin/env php
<?php
/**
 * Install apache
 */

require_once 'lib.php';

$opts = getopt('', array(
	'if-env:'
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!checkenv(@$opts['if-env'])) {
	echo "Apache skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

echo "Installing Apache and configuring for Travis/SilverStripe usage...\n";

run("sudo apt-get update > /dev/null");
run("sudo apt-get install -y --force-yes apache2 libapache2-mod-php5 php5-curl php5-mysql php5-mcrypt");
run("sudo sed -i -e \"s,/var/www,$(pwd),g\" /etc/apache2/sites-available/default");
run("sudo sed -i -e \"s,AllowOverride .*,AllowOverride all,g\" /etc/apache2/sites-available/default");
run("sudo a2enmod rewrite");
run("sudo /etc/init.d/apache2 restart");
run("mkdir silverstripe-cache");
run("sudo chmod 777 -R .");

