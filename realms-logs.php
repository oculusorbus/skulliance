<?php
// Can be run standalone: php realms-logs.php verify=1
// or included from verify.php where the ?verify guard is already active
if (!defined('REALMS_LOGS_INCLUDED')) {
	if (isset($argv)) {
		parse_str(implode('&', array_slice($argv, 1)), $_GET);
	}
	include_once 'db.php';
	if (!isset($_GET['verify'])) exit;
}

set_time_limit(0);
processMineRewards($conn);
processFactoryDrops($conn);
processArmoryDrops($conn);
?>
