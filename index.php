<?php

include_once("extensions/API/class.api.php");

// Plugin Name: WooScan
// Author: Jerry Tieben
// Version: 0.1
// Description: Scan your products

add_action('init', 'WooScanAPI::API');

Class WooScan
{
	private static function checkWooLicense()
	{
		// TODO: UPDATE SERVER LICENSE EVERY DAY SO APP CAN CHECK IF THIS LICENSE IS STILL ACTIVE ON LOGIN
	}

}