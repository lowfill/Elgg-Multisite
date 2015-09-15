<?php

require_once dirname(__FILE__)."/../docroot/engine/settings.php";

$periods = array(
		'minute',
		'fiveminute',
		'fifteenmin',
		'halfhour',
		'hourly',
		'daily',
		'weekly',
		'monthly',
		'yearly',
		// reboot is deprecated and probably does not work
		'reboot',
	);

$params = (is_array($argv)) ? $argv : array_keys($_GET);
array_shift($params);
$period = array_shift($params);
$period = (empty($period)) ? 'daily' : $period;

if(!in_array($period,$periods)){
	echo "$period is not a valid period!\n";
	exit;
}

$query = "SELECT * FROM domains";
$resp = elggmulti_getdata($query);

if(!empty($resp)){
	foreach($resp as $domain){
		$url = "http://{$domain->domain}/cron/$period";
		if(file_exists("/usr/bin/curl")){
			$cmd = "curl -o /dev/null $url ";
		}
		else if(file_exists("/usr/bin/wget")){
			$cmd = "wget $url > /dev/null";
		}
		echo $cmd."\n";
		echo exec($cmd);
	}
}
