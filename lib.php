<?php

function run($cmd) {
	echo "+ $cmd\n";
	passthru($cmd, $returnVar);
	if($returnVar > 0) {
		die($returnVar);
	}
}

function run_unlink($filename) {
	echo "+ Removing $filename\n";
	if(!unlink($filename)) {
		echo "Couldn't remove $filenamee!";
		exit(1);
	}
}

function checkenv($envs) {
	if($envs) {
		foreach(explode(',', $envs) as $env) {
			if(!getenv($env)) {
				return false;
			}
		}
	}
	
	return true;
}
