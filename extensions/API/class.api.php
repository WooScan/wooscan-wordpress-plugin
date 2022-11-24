<?php

class WooScanAPI extends WooScan
{
	public static function API()
	{
		if(isset($_GET['WooScanApiKey']) && isset($_GET['WooScanApiSecret']) && isset($_GET['action'])):
			self::checkApiLogin($_GET['WooScanApiKey'], $_GET['WooScanApiSecret']);
			self::{$_GET['action']}();
			die();
		elseif(isset($_POST['WooScanApiKey']) && isset($_POST['WooScanApiSecret']) && isset($_POST['action'])):
			self::checkApiLogin($_POST['WooScanApiKey'], $_POST['WooScanApiSecret']);
			self::{$_POST['action']}();
			die();
		elseif(isset($_PUT['WooScanApiKey']) && isset($_PUT['WooScanApiSecret']) && isset($_PUT['action'])):
			self::checkApiLogin($_PUT['WooScanApiKey'], $_PUT['WooScanApiSecret']);
			self::{$_PUT['action']}();
			die();
		elseif(isset($_GET['action']) && $_GET['action'] == 'WooScanApiCredentials'):
			self::getApiCredentials();
			die();
		endif;
	}

	public static function success($message='successs')
	{
		header("HTTP/1.1 200 OK");
		$error['error'] = false;
		$error['message'] = $message;

		echo json_encode($error);
		die();
	}

	private static function logBadRequest($message, $countAsMalicious=false)
	{
		if($countAsMalicious):
			self::registerFalseLogin();
		endif;

		$error['error'] = true;
		$error['message'] = $message;

		header("HTTP/1.1 400 Bad Request");
		echo json_encode($error);
		die();
	}

	private static function getApiCredentials()
	{
		if(!self::banned()):
			self::checkLicense();

			if(!isset($_GET['action'])):
				self::logBadRequest('getApiCredentials is a GET request');
			endif;

			if(isset($_GET['username']) ):
				self::logBadRequest('username is not used, please use email variable to login');
			endif;

			if(!isset($_GET['email']) || trim($_GET['email']) == ''):
				self::logBadRequest('email variable is not set');
			endif;

			if(!isset($_GET['password']) || trim($_GET['password']) == ''):
				self::logBadRequest('password variable not set');
			endif;

			// GET USER
			global $wpdb;
			$user = get_user_by('email', $_GET['email']);

			if($user):
				// IS USER ADMIN?
				if(!user_can($user, 'manage_woocommerce')):
					self::logBadRequest('User has insufficient rights to perform woocommerce actions');
				endif;

				$SQL = 'SELECT * FROM '.$wpdb->prefix.'users WHERE `ID` = '.$user->ID;
				$user = $wpdb->get_row($SQL);

				$hashedPass = wp_hash_password($_GET['password']);

				//CHECK PASSWORD
				if(wp_check_password($_GET['password'], $user->user_pass, $user->ID)):
					self::resetBan();
					self::checkAPI($user->ID); // MADE API KEYS IF NOT YET EXIST
					$return['error'] = false;
					$return['apiKey'] = get_user_meta($user->ID, 'WooScanApiKey', true);
					$return['apiSecret'] = get_user_meta($user->ID, 'WooScanApiSecret', true);
					$return['license'] = get_option('wooscan_license')->license;
				else:
					$return['error'] = true;
					$return['message'] = __('Login credentials incorrect', 'CF');
					self::logBadRequest('Login credentials incorrect');
				endif;
			else:
				$return['error'] = true;
				$return['message'] = __('Login credentials incorrect', 'CF');

				self::logBadRequest('Login credentials incorrect', true);
			endif;

			echo json_encode($return);
		else:
			self::logBadRequest('Error: Too many bad login attempts. Please wait 60 minutes and try again', true);
			die();
		endif;
		die();
	}

	private static function checkAPI($userid=false)
	{
		if(!$userid): $userid = get_current_user_id(); endif;
		if(!get_user_meta($userid, 'wooscan_api_key_made', true)):
			$apikey = sha1($userid.time());
			$apisecret = md5($userid.time());

			update_user_meta($userid, 'WooScanApiKey', $apikey);
			update_user_meta($userid, 'WooScanApiSecret', $apisecret);
			update_user_meta($userid, 'wooscan_api_key_made', true);
		endif;
	}

	private static function registerFalseLogin()
	{
		// TODO: REGISTER FALSE LOGIN, BAN IF TOO MUCH FALSE LOGINS
		$falselogins = get_option('false_login_'.$_SERVER['REMOTE_ADDR']);
		if(!$falselogins): $falselogins = array(); endif;
		$falselogins[] = time();
		update_option('false_login_'.$_SERVER['REMOTE_ADDR'], $falselogins);
	}

	private static function banned()
	{

		$falselogins = get_option('false_login_'.$_SERVER['REMOTE_ADDR']);
		if($falselogins && count($falselogins) > 15 && (time() - end($falselogins) < 900)):
			return true;
		endif;

		return false;
	}

	private static function resetBan()
	{
		delete_option('false_login_'.$_SERVER['REMOTE_ADDR']);
	}

	private static function checkApiLogin($key, $secret){
		global $wpdb, $error;

		if(!self::banned()):
			self::checkLicense();
			$sql = "SELECT * FROM `".$wpdb->prefix."users` 
					LEFT JOIN `".$wpdb->prefix."usermeta` as meta
							ON `".$wpdb->prefix."users`.`ID` = `meta`.`user_id`
					LEFT JOIN `".$wpdb->prefix."usermeta` as meta2
							ON `".$wpdb->prefix."users`.`ID` = `meta2`.`user_id`
				WHERE `meta`.`meta_key` = 'WooScanApiKey'
					AND `meta`.`meta_value` = '".$key."'
					AND `meta2`.`meta_key` = 'WooScanApiSecret'
					AND `meta2`.`meta_value` = '".$secret."'";

			$row = $wpdb->get_results($sql);

			if(count($row) == 1):
				self::resetBan();
				return end($row)->ID;
			else:
				self::logBadRequest('API credentials not correct', true);
				die();
			endif;
		else:
			self::logBadRequest('Error: Too many bad login attempts. Please wait 60 minutes and try again', true);
			die();
		endif;
	}

	private static function getProductByBarcode()
	{
		global $wpdb;
		if(!isset($_GET['action'])):
			self::logBadRequest('getProductByBarcode is a GET request');
		endif;

		if(!isset($_GET['barcode']) || trim($_GET['barcode']) == ''):
			self::logBadRequest('Barcode variable not set');
		endif;

		$sql = "SELECT * FROM ".$wpdb->prefix."posts as posts
				WHERE (`posts`.`post_type` = 'product' OR `posts`.`post_type` = 'product_variation')
				AND EXISTS (SELECT * FROM ".$wpdb->prefix."postmeta as meta 
						WHERE `meta`.`post_id` = `posts`.`ID` 
						 AND `meta`.`meta_key` LIKE '_%' AND `meta`.`meta_value` = '".trim($_GET['barcode'])."' )";
		$products = $wpdb->get_results($sql);

//		$products = get_posts(
//			array('post_type' => array('product', 'product_variation'),
//			      'post_status' => 'any',
//			      'posts_per_page' => -1,
//			      'meta_query' =>
//				      array(
//					      'relation' => 'OR',
//					      array(  'key' => '_wooscan_barcode',
//					              'value' => $_GET['barcode'],
//					              'compare' => '='
//					      ),
//					      array(  'key' => '_%',
//					              'compare_key' => 'LIKE',
//					              'value' => $_GET['barcode'],
//					              'compare' => '='
//					      )
//				      )
//			)
//		);

		if(!$products || count($products) == 0):
			echo json_encode(array());
			return;
		endif;

		$returnProducts = array();
		foreach($products as $product):
			$returnProducts[] = self::getProductDetails($product->ID);
		endforeach;

		echo json_encode($returnProducts);
	}

	private static function searchProducts()
	{
		global $wpdb;

		if(!isset($_GET['action'])):
			self::logBadRequest('getProductByBarcode is a GET request');
		endif;

		if(!isset($_GET['searchterm']) || trim($_GET['searchterm']) == ''):
			self::logBadRequest('searchterm variable not set');
		endif;

		$sql = "SELECT * FROM ".$wpdb->prefix."posts as posts
				WHERE (`posts`.`post_type` = 'product' OR `posts`.`post_type` = 'product_variation')
				AND `posts`.`post_title` LIKE '%".trim($_GET['searchterm'])."%'";
		$products = $wpdb->get_results($sql);

//		$products = get_posts(
//			array('post_type' => array('product', 'product_variation'),
//			      'post_status' => 'any',
//			      's' => $_GET['searchterm'],
//			      'posts_per_page' => 99)
//		);

		if(!$products || count($products) == 0):
			echo json_encode(array());
			return;
		endif;

		$returnProducts = array();
		foreach($products as $product):
			$returnProducts[] = self::getProductDetails($product->ID);
		endforeach;

		echo json_encode($returnProducts);
	}

	private static function getProductDetails($productid=false)
	{
		if(!$productid):
			die();
		endif;

		$product = get_post($productid);
		$thumb = get_the_post_thumbnail_url($product, 'medium');

		$newproduct = array(
			'ID' => $product->ID,
			'title' => $product->post_title,
			'description' => $product->post_content,
			'image' => ($thumb ? $thumb : wc_placeholder_img_src('small')),
			'barcode' => get_post_meta($product->ID, '_wooscan_barcode', true)
		);

		$productdetails = wc_get_product( $product->ID );

		$newproduct['meta']['stock_management_status'] = $productdetails->get_manage_stock();
		$newproduct['meta']['stock_status'] = $productdetails->get_stock_status();
		$newproduct['meta']['stock_quantity'] = $productdetails->get_stock_quantity();
		$newproduct['meta']['sku'] = $productdetails->get_sku();
		$newproduct['meta']['price'] = doubleval($productdetails->get_price());
		$newproduct['meta']['currency'] = get_woocommerce_currency_symbol();
		$newproduct['meta']['sale'] = $productdetails->get_sale_price() !== '' ? $productdetails->get_sale_price() : $productdetails->get_price();
		$attributes = $productdetails->get_attributes();
		if($attributes && is_array($attributes)):
			foreach($attributes as $key => $value):
				if(!is_array($value) && !is_object($value) && trim($value) != ''):
					$newproduct['meta'][$key] = $value;
				endif;
			endforeach;
		endif;

		return $newproduct;
	}

	private static function updateStockQuantity()
	{
		if(!isset($_POST['action'])):
			self::logBadRequest('updateStockQuantity is a POST request');
		endif;

		if(!isset($_POST['productid']) || trim($_POST['productid']) == ''):
			self::logBadRequest('productid variable not set');
		endif;

		if(!isset($_POST['stockquantity']) || trim($_POST['stockquantity']) == '' ):
			self::logBadRequest('stockquantity variable not valid or not set');
		endif;

		$productdetails = wc_get_product( $_POST['productid'] );
		if($productdetails):
			$productdetails->set_manage_stock(true);
			$productdetails->set_stock_quantity($_POST['stockquantity']);
			$productdetails->save();
			echo json_encode(self::getProductDetails($_POST['productid']));
			die();
		endif;

		self::logBadRequest('Product not found');
	}

	private static function updateProductBarcode()
	{
		if(!isset($_POST['action'])):
			self::logBadRequest('updateStockQuantity is a POST request');
		endif;

		if(!isset($_POST['productid']) || trim($_POST['productid']) == ''):
			self::logBadRequest('productid variable not set');
		endif;

		if(!isset($_POST['barcode']) || trim($_POST['barcode']) == '' ):
			self::logBadRequest('barcode variable not valid or not set');
		endif;

		$productdetails = wc_get_product( $_POST['productid'] );
		if($productdetails):
			update_post_meta($_POST['productid'], '_wooscan_barcode', $_POST['barcode']);
			echo json_encode(self::getProductDetails($_POST['productid']));
			die();
		endif;

		self::logBadRequest('Product not found');
	}

}