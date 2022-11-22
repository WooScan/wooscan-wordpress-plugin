<?php

include_once("extensions/API/class.api.php");

// Plugin Name: WooScan
// Author: Jerry Tieben
// Version: 0.1
// Description: Scan your products

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