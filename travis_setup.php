#!/usr/bin/env php
<?php
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

$opts = getopt('', array(
	'source:', // required
	'target:', // required
	'config:',
));

// Sanity checks
if (!$opts) {
	echo "Invalid arguments specified\n";
	exit(1);
}
$requiredEnvs = array('TRAVIS_COMMIT', 'TRAVIS_BRANCH', 'CORE_RELEASE');
foreach($requiredEnvs as $requiredEnv) {
	if(!getenv($requiredEnv)) {
		echo(sprintf('Environment variable "%s" not defined', $requiredEnv));
		exit(1);
	}
}

$dir = __DIR__;
$configPath = (isset($opts['config'])) ? $opts['config'] : null;
$targetPath = $opts['target'];
$modulePath = $opts['source'];
$moduleName = basename($modulePath);
$parent = dirname($modulePath);

// Get exact version of downloaded module so we can re-download via composer
$moduleRevision = getenv('TRAVIS_COMMIT');
$moduleBranch = getenv('TRAVIS_BRANCH');
$moduleBranchComposer = (preg_match('/^\d\.\d/', $moduleBranch)) ? $moduleBranch . '.x-dev' : 'dev-' . $moduleBranch;
$coreBranch = getenv('CORE_RELEASE');
$coreBranchComposer = (preg_match('/^\d\.\d/', $coreBranch)) ? $coreBranch . '.x-dev' : 'dev-' . $coreBranch;

// Print out some environment information.
printf("Environment:\n");
printf("  * MySQL:      %s\n", trim(`mysql --version`));
printf("  * PostgreSQL: %s\n", trim(`pg_config --version`));
printf("  * SQLite:     %s\n\n", trim(`sqlite3 -version`));

// Set up Github API token for higher rate limits (optional)
// See http://blog.simplytestable.com/creating-and-using-a-github-oauth-token-with-travis-and-composer/
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

// Extract the package info from the module composer file, and build a
// custom project composer file with the local package explicitly defined.
echo "Reading composer information...\n";
if(!file_exists("$modulePath/composer.json")) {
	echo("File not found: $modulePath/composer.json");
	exit(1);
}
$package = json_decode(file_get_contents("$modulePath/composer.json"), true);

// Override the default framework requirement with the one being built.
$package += array(
	'version' => $moduleBranchComposer,
	'dist' => array(
		'type' => 'tar',
		'url' => "file://$parent/$moduleName.tar"
	),
	'extra' => array(
		'branch-alias' => array(
			'dev-' . $moduleBranch => $coreBranchComposer
		)
	)
);

// Generate a custom composer file.
$composer = array(
	'repositories' => array(array('type' => 'package', 'package' => $package)),
	'require' => array_merge(
		isset($package['require']) ? $package['require'] : array(),
		array($package['name'] => $moduleBranchComposer)
	),
	// Always include DBs, allow module specific version dependencies though
	'require-dev' => array_merge(
		array('silverstripe/postgresql' => '*','silverstripe/sqlite3' => '*'),
		isset($package['require-dev']) ? $package['require-dev'] : array()
	),
	'minimum-stability' => 'dev',
	'config' => array(
		'notify-on-install' => false,
		'process-timeout' => 600, // double default timeout, github archive downloads tend to be slow
	)
);

// Temporary workaround for removed framework dependency in 2.4 cms module
// See https://github.com/silverstripe/silverstripe-cms/commit/2713c462a26494624169e0115323e5cdd5a07d50
if(
	version_compare($coreBranch, '3.0') == -1
	&& $package['name'] == 'silverstripe/framework'
) {
	$composer['require'][$package['name']] .= ' as ' . $coreBranchComposer;
}

// Framework and CMS need special treatment for version dependencies
if(
	in_array($package['name'], array('silverstripe/cms', 'silverstripe/framework'))
	&& $coreBranchComposer != $composer['require'][$package['name']]
) {
	// $composer['repositories'][0]['package']['version'] = $coreBranchComposer;
	$composer['require']['silverstripe/cms'] = $coreBranchComposer;
}

// Override module dependencies in order to test with specific core branch.
// This might be older than the latest permitted version based on the module definition.
// Its up to the module author to declare compatible CORE_RELEASE values in the .travis.yml.
// Leave dependencies alone if we're testing either of those modules directly.
if(isset($composer['require']['silverstripe/framework']) && $package['name'] != 'silverstripe/framework') {
	$composer['require']['silverstripe/framework'] = $coreBranchComposer;
}
if(isset($composer['require']['silverstripe/cms']) && $package['name'] != 'silverstripe/cms') {
	$composer['require']['silverstripe/cms'] = $coreBranchComposer;
}
$composerStr = json_encode($composer);

echo "Generated composer file:\n";
echo "$composerStr\n\n";

echo "Archiving $moduleName...\n";
`cd $modulePath`;
`tar -cf $parent/$moduleName.tar .`;

echo "Cloning installer@$coreBranch...\n";
`git clone --depth=100 --quiet -b $coreBranch git://github.com/silverstripe/silverstripe-installer.git $targetPath`;

echo "Setting up project...\n";
`cp $dir/_ss_environment.php $targetPath/_ss_environment.php`;
if($configPath) `cp $configPath $targetPath/mysite/_config.php`;

echo "Replacing composer.json...\n";
unlink("$targetPath/composer.json");
file_put_contents("$targetPath/composer.json", $composerStr);

if(file_exists("$targetPath/composer.lock")) {
	echo "Removing composer.lock...\n";
	unlink("$targetPath/composer.lock");
}

echo "Running composer...\n";
passthru("composer install --prefer-dist --dev -d $targetPath", $returnVar);

// Installer doesn't work out of the box without cms - delete the Page class if its not required
if(
	!file_exists("$targetPath/cms") 
	&& file_exists("$targetPath/mysite/code/Page.php")
	&& version_compare($coreBranch, '3.0') >= 0
) {
	echo "Removing Page.php (building without 'silverstripe/cms')...\n";
	unlink("$targetPath/mysite/code/Page.php");
}

if($returnVar > 0) die($returnVar);
