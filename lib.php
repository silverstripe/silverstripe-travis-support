<?php
function run($cmd, $echo = true) {
	if($echo) echo "+ $cmd\n";
	passthru($cmd, $returnVar);
	if($returnVar > 0) die($returnVar);
}

function checkenv($envs) {
	if($envs) {
		foreach(explode(',',$envs) as $env) {
			if(!getenv($env)) return false;
		}
	}
	
	return true;
}