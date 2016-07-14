#!/usr/bin/env php
<?php
/**
 * Creates an index.html with links to all files in a given directory structure, recursively.
 * This is useful for Amazon S3 uploads with static file hosting, since it doesn't list files by default.
 *
 * Assumes to run in a SilverStripe webroot
 */
require_once 'lib.php';

$opts = getopt('', array(
	'artifacts-path:',
	'target-path:',
	'if-env:',
	'artifacts-base-url:',
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!checkenv(@$opts['if-env'])) {
	echo "Apache skipped; {$opts['if-env']} wasn't set.\n";
	exit(0);
}

$artifactsPath = (isset($opts['artifacts-path'])) ? $opts['artifacts-path'] : 'artifacts/';
$targetPath = $opts['target-path'];
$baseUrl = $opts['artifacts-base-url'];

if(!file_exists($artifactsPath)) {
	echo "No artifacts found, skipped\n";
	exit(0);
}

echo "Installing artifacts script to ~/bin/artifacts\n";
run("curl -sL https://raw.githubusercontent.com/travis-ci/artifacts/master/install | bash");

echo "Creating $artifactsPath/index.html...\n";

$html = '<html><head></head><body><ul>';
$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath($artifactsPath)), RecursiveIteratorIterator::SELF_FIRST);
foreach($objects as $name => $object){
	if($object->isDir()) {
		continue;
	}
	$relativePath = trim(str_replace(realpath($artifactsPath) . '/', '', $object->getPathName()), '/');
    $html .= sprintf('<li><a href="%s">%s</a></li>', $relativePath, $relativePath);
}
$html .= '</ul></body></html>';

file_put_contents("$artifactsPath/index.html", $html);

run("~/bin/artifacts upload --permissions public-read --target-paths $targetPath $artifactsPath");

$fullPath = str_replace('//', '/', "$baseUrl/$targetPath/artifacts/index.html");
$fullPath = str_replace('https:/s3','https://s3', $fullPath);
echo "Uploaded artifacts to $fullPath\n";
