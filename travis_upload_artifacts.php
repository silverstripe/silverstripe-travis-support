#!/usr/bin/env php
<?php

$opts = getopt('', array(
	'if-env:',
	'artifacts-path:',
	'target-path:',
));

// --if-env=BEHAT_TEST means that this script will only be executed if the given environment var is set
if(!empty($opts['if-env'])) {
	foreach(explode(',', $opts['if-env']) as $env) {
		if(!getenv($env)) {
			echo "Artifacts upload skipped; {$env} wasn't set.\n";
			exit(0);
		}
	}
}

$artifactsPath = (isset($opts['artifacts-path'])) ? $opts['artifacts-path'] : '~/builds/ss/artifacts/';
$artifactsPath = realpath($artifactsPath);

$targetPath = (isset($opts['target-path'])) ? $opts['target-path'] : 'artifacts/';

echo "Creating $artifactsPath/index.html...\n";

$html = '<html><head></head><body><ul>';
$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($artifactsPath), RecursiveIteratorIterator::SELF_FIRST);
foreach($objects as $name => $object){
	if($object->isDir()) continue;
	$relativePath = str_replace($artifactsPath, '', $object->getPath());
    $html .= sprintf('<li><a href="%s">%s</a></li>', $relativePath, $relativePath);
}
$html .= '</ul></body></html>';

file_put_contents("$artifactsPath/index.html", $html);

echo "Uploading artifacts...\n";
passthru(sprintf("travis-artifacts upload --path %s --target-path %s", $artifactsPath, $targetPath));