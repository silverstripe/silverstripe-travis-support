#!/usr/bin/env php
<?php

/**
 * Install apache
 */

$opts = getopt('', array(
	'if-env:'
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!empty($opts['if-env']) && !getenv($opts['if-env'])) {
	echo "Apache skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

echo "Installing Apache and configuring for Travis/SilverStripe usage...\n";

passthru("sudo apt-get update > /dev/null");
passthru("sudo apt-get install -y --force-yes apache2 libapache2-mod-php5 php5-curl php5-mysql php5-mcrypt");
passthru("sudo sed -i -e \"s,/var/www,$(pwd),g\" /etc/apache2/sites-available/default");
passthru("sudo sed -i -e \"s,AllowOverride .*,AllowOverride all,g\" /etc/apache2/sites-available/default");
passthru("sudo a2enmod rewrite");
passthru("sudo /etc/init.d/apache2 restart");
passthru("mkdir silverstripe-cache");
passthru("sudo chmod 777 -R .");

