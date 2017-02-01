#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';

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

$opts = getopt('', array(
	'source:',      // Required: Path to the module root directory
	'target:',      // Required: Path to where the environment will be built
	'config:',      // Optional: Location to custom mysite/_config.php to use
	'require:',     // Optional: Additional composer requirement. E.g. --require silverstripe/behat-extension:dev-master
	'prefer-source' // Optional: Prefer source (i.e. version control repository) when running composer install
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
$installType = (isset($opts['prefer-source'])) ? '--prefer-source' : '--prefer-dist';

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
$coreInstallerBranch = getenv('CORE_INSTALLER_RELEASE') ? getenv('CORE_INSTALLER_RELEASE') : $coreBranch;
$coreAlias = getenv('CORE_ALIAS');
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
if(
	getenv('GITHUB_API_TOKEN')
	// Defaults to unencrypted tokens, so we don't need to exclude pull requests
	// && (!getenv('TRAVIS_PULL_REQUEST') || getenv('TRAVIS_PULL_REQUEST') == 'false')
) {
	// Set the token without echo'ing the command to keep it secure
	run('composer config -g github-oauth.github.com ' . getenv('GITHUB_API_TOKEN'), false);
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
$installerPackageInfo = json_decode(file_get_contents('https://packagist.org/packages/silverstripe/installer.json'), true);

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

if($coreAlias) {
	$composerGenerator->setCoreAlias($coreAlias);
}

$installerGenerator = new ComposerGenerator(
	$coreInstallerBranch,
	$moduleVersion,
	$moduleRef,
	$installerPackageInfo,
	$modulePackageInfo
);

$coreInstallerConstraint = $installerGenerator->getCoreComposerConstraint();

$moduleArchivePath = "$parent/$moduleName.tar";
$composer = $composerGenerator->generateComposerConfig($opts, $moduleArchivePath);
$composerStr = json_encode($composer);

echo "Generated composer file:\n";
echo "$composerStr\n\n";

/**
 * 7. Run it
 */
run("cd $modulePath");

run("tar -cf $moduleArchivePath * .??*");

run("composer create-project --verbose --no-interaction --no-ansi --prefer-source --no-install --no-progress silverstripe/installer $targetPath $coreInstallerConstraint");

$envVars = array(
	'SS_ENVIRONMENT_TYPE' => 'dev',
	'SS_TRUSTED_PROXY_IPS' => '*',
	'SS_DATABASE_SERVER' => '127.0.0.1',
	'SS_DATABASE_PASSWORD' => '127.0.0.1',
	'SS_DATABASE_CHOOSE_NAME' => '1',
	'SS_DEFAULT_ADMIN_USERNAME' => 'username',
	'SS_DEFAULT_ADMIN_PASSWORD' => 'password',
	'SS_HOST' => 'localhost',
);

// Database connection, including PDO and legacy ORM support
$db = getenv('DB');
$release = getenv('CORE_RELEASE');
$pdo = getenv('PDO');
$legacy = strcasecmp($release, 'master') && version_compare($release, '3.2', '<') && $release != '3';
$pdo = !$legacy && $pdo;
switch($db) {
	case "PGSQL";
		$envVars['SS_DATABASE_CLASS'] = $pdo ? 'PostgrePDODatabase' : 'PostgreSQLDatabase';
		$envVars['SS_DATABASE_USERNAME'] = 'postgres';
		$envVars['SS_DATABASE_PASSWORD'] = '';
		break;

	case "SQLITE":
		if($legacy) {
			// Legacy default is to use PDO
			$envVars['SS_DATABASE_CLASS'] = 'SQLitePDODatabase';
		} else {
			$envVars['SS_DATABASE_CLASS'] = $pdo ? 'SQLite3PDODatabase' : 'SQLite3Database';
		}
		$envVars['SS_DATABASE_USERNAME'] = 'root';
		$envVars['SS_DATABASE_PASSWORD'] = '';
		$envVars['SS_SQLITE_DATABASE_PATH'] = ':memory:';
		break;

	default:
		$envVars['SS_DATABASE_CLASS'] = $pdo ? 'MySQLPDODatabase' : 'MySQLDatabase';
		$envVars['SS_DATABASE_USERNAME'] = 'root';
		$envVars['SS_DATABASE_PASSWORD'] = '';

}


// build up an _ss_environment.php file for SS <4 and define real env vars for
$_ss_env = array(
	'<?php',
);
$dotEnv = array();
foreach ($envVars as $envName => $envVal) {
	$_ss_env[] = sprintf("if (!defined('%s') define('%s', '%s');", $envName, $envName, $envVal);
	$dotEnv[] = (sprintf('%s="%s"', $envName, $envVal));
}
$_ss_env[] = 'global $_FILE_TO_URL_MAPPING;';
$_ss_env[] = '$_FILE_TO_URL_MAPPING[dirname(__FILE__)] = \'http://localhost:8000\';';
file_put_contents("$targetPath/_ss_environment.php", implode(PHP_EOL, $_ss_env));
file_put_contents("$targetPath/.env", implode(PHP_EOL, $dotEnv));

if($configPath) run("cp $configPath $targetPath/mysite/_config.php");

run("rm $targetPath/composer.json");
echo "Writing new composer.json to $targetPath/composer.json\n";
file_put_contents("$targetPath/composer.json", $composerStr);

if(file_exists("$targetPath/composer.lock")) {
	run("rm $targetPath/composer.lock");
}

run("cd ~ && composer install --verbose --optimize-autoloader --no-interaction --no-progress --no-suggest --no-ansi $installType -d $targetPath");

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
