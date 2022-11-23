<?php

// Plugin Name: WooScan
// Author: Jerry Tieben
// Version: 1.0.1
// Description: Scan your WooCommerce Product Barcodes

//CHECK FOR UPDATES
require 'plugin-update-checker-master/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/WooScan/wooscan-wordpress-plugin',
    __FILE__,
    'wooscan'
);

//Set the branch that contains the stable release.
$updateChecker->setBranch('main');
//$updateChecker->setAuthentication('your-token-here');

include_once("extensions/API/class.api.php");
add_action('init', 'WooScanAPI::API');

Class WooScan
{
	public static function checkLicense()
	{
		$license = get_option('wooscan_license');

		// NO LICENSE
		if(!$license):
			$license = self::getLicense();
		endif;

		$lastupdate = get_option('wooscan_license_lastupdated');
		if(time() - $lastupdate > 86400 || time() - $lastupdate < 0):
			$license = self::getLicense();
		endif;
	}

	public static function getLicense()
	{
	    global $plugin_version;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.wooscan.eu/?getLicense&domain=".urlencode(get_site_url()));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);

		$license = json_decode($output);
		update_option('wooscan_license', $license);
		update_option('wooscan_license_lastupdated', time());
		return $license;
	}

}