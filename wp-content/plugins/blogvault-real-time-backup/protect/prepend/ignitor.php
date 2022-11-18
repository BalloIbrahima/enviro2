<?php
if (!defined('MCDATAPATH')) exit;

if (defined('MCCONFKEY')) {
	require_once dirname( __FILE__ ) . '/protect.php';

	$mcProtect = new BVPrependProtect();
	$mcProtect->run();
}