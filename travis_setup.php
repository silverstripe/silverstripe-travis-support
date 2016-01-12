#!/usr/bin/env php
<?php

require_once 'src/ComposerGenerator.php';
require_once 'lib.php';

use SilverStripe\TravisSupport\ComposerGenerator;

/**
 * Initialises a test project that can be built by travis.
 *
 * Travis downloads the module, but in order to run unit tests it needs
 * to be part of a SilverStripe "installer" project.
 * This script generates a custom composer.json with the required dependencies
 * and installs it into a separate webroot. The originally downloaded module
 * code is re-installed via composer.
 */
if (php_sapi_name() != 'cli') {
	header('HTTP/1.0 404 Not Found');
	exit;
}

$defaults = array(
	// Readonly token for 'silverstripe-issues' user to increase our rate limitation.
	// Please be fair and define your own token if using for own projects.
	'GITHUB_API_TOKEN' => '2434108664388ca0199319b98a6068af8e5dc547'
);

/**
 * 1. Check and parse command line options
 */
$opts = getopt('', array(
	'source:', // Required: Path to the module root directory
	'target:', // Required: Path to where the environment will be built
	'config:', // Optional: Location to custom mysite/_config.php to use
	'require:', // Optional: Additional composer requirement. E.g. --require silverstripe/behat-extension:dev-master
	'install-suggested:' // Optional: Install the modules suggested packages
));

// Sanity checks
if (!$opts) {
	echo "Invalid arguments specified\n";
	exit(1);
}

$dir = __DIR__;
$configPath = (isset($opts['config'])) ? $opts['config'] : null;
$targetPath = $opts['target'];
$modulePath = $opts['source'];
$moduleName = basename($modulePath);
$parent = dirname($modulePath);

/**
 * 2. Check and parse environment variables
 */
$requiredEnvs = array('TRAVIS_COMMIT', 'CORE_RELEASE');
foreach($requiredEnvs as $requiredEnv) {
	if(!getenv($requiredEnv)) {
		echo(sprintf('Environment variable "%s" not defined', $requiredEnv) . "\n");
		exit(1);
	}
}
if(!getenv('TRAVIS_TAG') && !getenv('TRAVIS_BRANCH')) {
	echo("One of TRAVIS_BRANCH or TRAVIS_TAG must be defined\n");
	exit(1);
}

$coreBranch = getenv('CORE_RELEASE');
$moduleVersion = getenv('TRAVIS_TAG') ?: getenv('TRAVIS_BRANCH');
$moduleRef = getenv('TRAVIS_TAG')
	? ComposerGenerator::REF_TAG
	: ComposerGenerator::REF_BRANCH;

/**
 * 3. Display environment variables
 */
printf("Environment:\n");
printf("  * MySQL:      %s\n", trim(`mysql --version`));
printf("  * PostgreSQL: %s\n", trim(`pg_config --version`));
printf("  * SQLite:     %s\n\n", trim(`sqlite3 -version`));
printf("  * PHP:     %s\n\n", trim(`php --version`));

/**
 * 4. Set up Github API token for higher rate limits (optional)
 * See http://blog.simplytestable.com/creating-and-using-a-github-oauth-token-with-travis-and-composer/
 */
if(!getenv('GITHUB_API_TOKEN')) putenv('GITHUB_API_TOKEN=' . $defaults['GITHUB_API_TOKEN']);
if(
	getenv('GITHUB_API_TOKEN')
	// Defaults to unencrypted tokens, so we don't need to exclude pull requests
	// && (!getenv('TRAVIS_PULL_REQUEST') || getenv('TRAVIS_PULL_REQUEST') == 'false')
) {
	$composerGlobalConf = array('config' => array('github-oauth' => array('github.com' => getenv('GITHUB_API_TOKEN'))));
	$composerConfDir = getenv("HOME") . '/.composer/';
	if(!file_exists($composerConfDir)) mkdir($composerConfDir);
	file_put_contents($composerConfDir . '/config.json', json_encode($composerGlobalConf));
	echo "Using GITHUB_API_TOKEN...\n";
}

/**
 * 5. Extract the package info from the module composer file, both for this module (from local)
 * and the core framework (from packagist)
 */
echo "Reading composer information...\n";
if(!file_exists("$modulePath/composer.json")) {
	echo("File not found: $modulePath/composer.json");
	exit(1);
}
$modulePackageInfo = json_decode(file_get_contents("$modulePath/composer.json"), true);
$corePackageInfo = json_decode(file_get_contents('https://packagist.org/packages/silverstripe/framework.json'), true);

/**
 * 6. Generate composer data
 */
$composerGenerator = new ComposerGenerator(
	$coreBranch,
	$moduleVersion,
	$moduleRef,
	$corePackageInfo,
	$modulePackageInfo
);

$moduleArchivePath = "$parent/$moduleName.tar";
$composer = $composerGenerator->generateComposerConfig($opts, $moduleArchivePath);
$composerStr = json_encode($composer);

echo "Generated composer file:\n";
echo "$composerStr\n\n";

/**
 * 7. Run it
 */
run("cd $modulePath");

run("tar -cf $moduleArchivePath ./*");

run("git clone --depth=100 --quiet -b $coreBranch git://github.com/silverstripe/silverstripe-installer.git $targetPath");

run("cp $dir/_ss_environment.php $targetPath/_ss_environment.php");
if($configPath) run("cp $configPath $targetPath/mysite/_config.php");

run("rm $targetPath/composer.json");
echo "Writing new composer.json to $targetPath/composer.json\n";
file_put_contents("$targetPath/composer.json", $composerStr);

if(file_exists("$targetPath/composer.lock")) {
	run("rm $targetPath/composer.lock");
}

run("cd ~ && composer install --no-ansi --prefer-dist -d $targetPath");

/**
 * 8. Installer doesn't work out of the box without cms - delete the Page class if its not required
 */
if(
	!file_exists("$targetPath/cms")
	&& file_exists("$targetPath/mysite/code/Page.php")
	&& ($coreBranch == 'master' || version_compare($coreBranch, '3') >= 0)
) {
	echo "Removing Page.php (building without 'silverstripe/cms')...\n";
	run("rm $targetPath/mysite/code/Page.php");
}
