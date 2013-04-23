<?php
//error_reporting(E_ALL);
// ini_set("display_errors", 1); 
/**
 * payson.php payment module class for new Payson API
 *
 * @package paymentMethod
 * 
 */


/**
 *  ensure dependencies are loaded
 */ 
include_once( DIR_FS_CATALOG . 'includes/modules/payment/payson/def.payson.php');
/**
 * payson.php payment module class for new Payson API
 *
 */
class payson{
    var $code, 
    	$title, 
    	$description,
    	$enabled, 
    	$payson_email,
    	$md5_key, 
	$agent_id,
            
        $test_payson_email,
    	$test_md5_key, 
        $test_agent_id,
            
        $methods_enabled,
	$payson_warranty,
	$module_name,
	$api_endpoint,
	$exchange_currency,
	$redirect_url,
	$payson_guarantee,
    
        $test_api_endpoint,
	$test_redirect_url;
    	//$codeVersion,
    	//$module_version, 
	//$validate_api_url, 
	//$email_logg, $tax_class_id, $sort_order, $order_status;

    function payson(){
    	global $order, $currency;
     	    
        $this->code             = 'payson';
		$this->module_version 	= '1.6';
		$this->module_name = 'payson oscommerce 1.6';

       	$this->title 			= 	MODULE_PAYMENT_PAYSON_TEXT_TITLE; // Payment Module title in Admin
        $this->description 		= 	MODULE_PAYMENT_PAYSON_TEXT_DESCRIPTION;
        $this->sort_order 		= 	MODULE_PAYMENT_PAYSON_SORT_ORDER;
        $this->enabled 			= 	((MODULE_PAYMENT_PAYSON_STATUS == 'True') ? true : false);
        
        // Getting and setting data from payson settings
        $this->payson_email    	= 	MODULE_PAYMENT_PAYSON_EMAIL_ADDRESS;
        $this->agent_id   		= 	MODULE_PAYMENT_PAYSON_AGENT_ID;
        $this->md5_key 			= 	MODULE_PAYMENT_PAYSON_MD5KEY;
        $this->tax_class_id 	= 	MODULE_PAYMENT_PAYSON_TAX_CLASS;
        
        $this->exchange_currency= 	MODULE_PAYMENT_PAYSON_EXCHANGE_CURRENCIES;
        $this->email_logg      	= 	MODULE_PAYMENT_PAYSON_EMAIL_LOGG;
        
        
        // Payson urls
	$this->validate_api_url = "https://api.payson.se/1.0/Validate/";
	$this->redirect_url     = "https://www.payson.se/paySecure/";
	$this->api_endpoint     = "api.payson.se/1.0/";
            
        //test mode        
        $this->test_payson_email    	= 	'testagent-1@payson.se';
        $this->test_agent_id   		= 	'1';
        $this->test_md5_key 		= 	'fddb19ac-7470-42b6-a91d-072cb1495f0a';
        $this->test_validate_api_url    = "https://test-api.payson.se/1.0/Validate/";
	$this->test_redirect_url        = "https://test-www.payson.se/paysecure/";
	$this->test_api_endpoint        = "test-api.payson.se/1.0/";
	    	
        if ($order->info['total'] > MODULE_PAYMENT_PAYSON_ORDER_LIMIT)
            $this->enabled = false;
	
		// Setting specific orders status
        if ((int)MODULE_PAYMENT_PAYSON_ORDER_STATUS_ID > 0){
            $this->order_status = MODULE_PAYMENT_PAYSON_ORDER_STATUS_ID;
        }       
        
        if (is_object($GLOBALS["order"])){
            $this->update_status();
        }
        //check currency and do not present payment option if currency is not supported
        if (in_array(strtoupper($_SESSION['currency']), paysonCurrenciesSupported())){
            //ok, currency is supported
        } 
        else {
            //currency is not supported, hide option
            $this->enabled = false;
        }
    }
     
	function update_status(){
		global $order;
		
		// Checking payment zone and setting enabled / unabled
		if ($this->enabled == true && (int)MODULE_PAYMENT_PAYSON_ZONE > 0){
        	$check_flag = false;
        	$check_query = tep_db_query("SELECT 
        									zone_id 
        								 FROM 
        								 	" . TABLE_ZONES_TO_GEO_ZONES . " 
        								 WHERE 
        								 	geo_zone_id     = '" . MODULE_PAYMENT_PAYSON_ZONE . "' AND 
        								 	zone_country_id = '" . $order->billing['country']['id'] . "' 
        								 ORDER BY zone_id");
			while ($check = tep_db_fetch_array($check_query)){
				if ($check['zone_id'] < 1){
					$check_flag = true;
					break;
				} 
				elseif ($check['zone_id'] == $order->billing['zone_id']){
					$check_flag = true;
					break;
				}
        	}

			// Setting module unabled
			if ($check_flag == false){
				$this->enabled = false;
			}
		}
    }

    // Getting error message
 	function get_error(){
				    return array('title' => MODULE_PAYMENT_PAYSON_PAYMENT_ERROR, 
				    'error' => urldecode($_GET['error'])
				);
  	}
  	
    /**
     * JS validation which does error-checking of data-entry if this module is selected for use
     * (Number, Owner, and CVV Lengths)
     *
     * @return string
     */
    function javascript_validation(){
        return false;
    }

    function selection(){ 
            return array('id' => $this->code, 'module' => $this->title . '<br/>' . tep_image(DIR_WS_IMAGES . "payson.png"));
    }
    
	// Checking before processing order
    function pre_confirmation_check(){
		return false;
    }
    
	// Confirmation
    function confirmation(){
		return false;
    }
    
    function process_button() {
    	    	// Setting help variable for payson payment type
    	$return_string = tep_draw_hidden_field("payson_payment_type", MODULE_PAYMENT_PAYSON_PAYMETHOD);
    }

	// Before processing order
	// Before processing order
    function before_process() 
    {
    	global $order, $order_total_modules,$currencies;
    	
		// Are we called from checkout_process? Update shipping address in that case
    	if(!empty($_GET['token']))
		{		
		/*	// Update shipping address before the order is persisted to DB
			$token = $_GET['token'];
			//$shippingAddress = $this->getShippingAddress($token);
			
			//since this in an invoice, we need to force update shippingaddress
			$order->delivery['firstname']        = $shippingAddress['name'];
			$order->delivery['lastname']         = 'villegas';
			$order->delivery['street_address']   = $shippingAddress['streetAddress'];
			$order->delivery['city']             = $shippingAddress['city'];
			$order->delivery['postcode']         = $shippingAddress['postalCode'];
			$order->delivery['country']['title'] = $shippingAddress['country'];
			
			$order->billing['firstname']        = $shippingAddress['name'];
			$order->billing['lastname']         = 'villegas';
			$order->billing['street_address']   = $shippingAddress['streetAddress'];
			$order->billing['city']             = $shippingAddress['city'];
			$order->billing['postcode']         = $shippingAddress['postalCode'];
			$order->billing['country']['title'] = $shippingAddress['country'];	*/		
			
    		return false;
		}
                
    	$_SESSION['comments'] = $order->info['comments'];
    	// Checking if currency value is approved
    	if(preg_match("/" . $order->info['currency'] . "/i", MODULE_PAYMENT_PAYSON_ACCEPTED_CURRENCIES))
    	{
    		$currencies_code   = $order->info['currency'];
    		$currency_value    = $order->info['currency_value'];
    	}
    	else
    	{
    		// Setting exchange rate to standard currency	
    		$currencies_code   = $this->exchange_currency;
    		
    		// Setting currency value based on exchange currency and current currency
    		$currency_value    = $currencies->get_value($this->exchange_currency);
    	}
    	
    	// Setting general data for Payson
    	$payson_data = array(
    						"returnUrl"                                   => tep_href_link("ext/modules/payment/payson/payson_payments_return.php"), //tep_href_link(FILENAME_CHECKOUT_SUCCESS),
    						"cancelUrl"                                   => tep_href_link(FILENAME_CHECKOUT_PAYMENT),
    						"ipnNotificationUrl"                          => tep_href_link("ext/modules/payment/payson/payson_payment_ipn_p.php"),
    						"currencyCode"                                => $currencies_code,
    						"memo"                                        => mb_convert_encoding(PAYSON_ORDER_FROM . " " . STORE_NAME, "ISO-8859-1", "UTF-8"),
    						"localeCode"                                  => $this->getShopLanguage(),
    						"senderEmail"                                 => $GLOBALS['order']->customer['email_address'],
    						"senderFirstName"                             => $GLOBALS['order']->customer['firstname'],
    						"senderLastName"                              => $GLOBALS['order']->customer['lastname'],
    						"receiverList.receiver(0).email"              => MODULE_PAYMENT_PAYSON_MODE == 'SANDBOX' ? $this->test_payson_email : $this->payson_email
    				   );
    				   
    	// Setting specific payment type
    	
    		//$payson_data['fundingList.fundingConstraint(0).constraint'] = $_POST['payson_payment_type'];
    		//$payson_data['fundingList'] = $_POST['payson_payment_type'];
    
    	if (MODULE_PAYMENT_PAYSON_PAYMETHOD == 'ALL'){
    		$payson_data['fundingList'] = 'ALL';
		}
		else 
		{
    		$payson_data['fundingList.fundingConstraint(0).constraint'] = MODULE_PAYMENT_PAYSON_PAYMETHOD; 
		}
    	
    	$payson_data['guaranteeOffered'] = 'NO';
    	$total_amount = 0;
    	
	    // Looping all products and pushing into order
	    for ($i = 0 ; $i < sizeof($order->products); $i++) 
	    {
      		// Fetching products for order
      		$payson_data['orderItemList.orderItem(' . $i . ').description']   = mb_convert_encoding($order->products[$i]['name'], "ISO-8859-1", "UTF-8");
      		$payson_data['orderItemList.orderItem(' . $i . ').quantity']      = $order->products[$i]['qty'];
      		$payson_data['orderItemList.orderItem(' . $i . ').unitPrice']     = round($order->products[$i]['final_price'] * $currency_value, 4);
      		$payson_data['orderItemList.orderItem(' . $i . ').taxPercentage'] = $order->products[$i]['tax']/100;
      		$payson_data['orderItemList.orderItem(' . $i . ').sku']           = $order->products[$i]['id'];
      		
      		// Atributes for product
	      	if(isset($order->products[$i]['attributes']))
	      	{
				foreach($order->products[$i]['attributes'] as $attr)
				{
		  			$payson_data['orderItemList.orderItem(' . $i . ').description'] .= ", " . $attr['option'] . ": " . $attr['value'];
				}
	    	}
			
			$total_amount += (($payson_data['orderItemList.orderItem(' . $i . ').unitPrice'] * $order->products[$i]['qty']) + 
	    	(($payson_data['orderItemList.orderItem(' . $i . ').unitPrice']  * $order->products[$i]['qty']) * $payson_data['orderItemList.orderItem(' . $i . ').taxPercentage'])
	    	);
    	}
    	
    	// Creating order totals modules for older versions of oscommerce
    	if(!is_object($order_total_modules))
    	{
			require_once(DIR_WS_CLASSES . 'order_total.php');
			$order_total_modules = new order_total;
			$order_total_modules->process();
    	}
		
    	$order_totals = $order_total_modules->modules;
    	
    	if (is_array($order_totals)) 
    	{
      		reset($order_totals);
      		$j = 0;
      		
      		// Checking which orders totals to ignore
      		$table = explode(",", MODULE_PAYMENT_PAYSON_ORDERS_TOTAL_IGNORE);
      
			$ot_i = 0;
			
      		// Looping all values in orders totals
      		while (list(, $value) = each($order_totals)) 
      		{
      			// Stripping .php
				$class = substr($value, 0, strrpos($value, '.'));
				
				// Dont run script if disabled
				if (!$GLOBALS[$class]->enabled)
					continue;
				
				$code    = $GLOBALS[$class]->code;
				$ignore  = false; 
	
				// Ignoring classes
				for ($i=0 ; $i<sizeof($table) && $ignore == false ; $i++) 
				{
					if ($table[$i] == $code)
					  $ignore = true;
				}
	
				// Looping output variables
				$size = sizeof($GLOBALS[$class]->output);
	
				if ($ignore == false && $size > 0) 
				{
	  				for ($i=0; $i<$size; $i++) 
	  				{
						
							$price = round($GLOBALS[$class]->output[$i]['value']*$currency_value,4);
							$tax = tep_get_tax_rate($this->tax_class_id)/100;

							if(DISPLAY_PRICE_WITH_TAX == 'true'){
								$price = $price / (1+$tax);
							}

							// Fetching to order item list
							$payson_data['orderItemList.orderItem(' . (sizeof($order->products) + $ot_i) . ').description']   = rtrim($GLOBALS[$class]->output[$i]['title'], ':');
							$payson_data['orderItemList.orderItem(' . (sizeof($order->products) + $ot_i) . ').quantity']      = 1;
							$payson_data['orderItemList.orderItem(' . (sizeof($order->products) + $ot_i) . ').unitPrice'] = $price;
							$payson_data['orderItemList.orderItem(' . (sizeof($order->products) + $ot_i) . ').taxPercentage'] = $tax;
							$payson_data['orderItemList.orderItem(' . (sizeof($order->products) + $ot_i) . ').sku']           = $class;
							
							// Adding to total amount	
							$total_amount += $price + $price * $tax ;
							$ot_i++;
									      		
				  	}					
				}
      		}
    	}
		
		// Setting total amount
    	$payson_data['receiverList.receiver(0).amount'] = tep_round($total_amount, 2);
    	
    	// Send data to Payson and request a TOKEN for this payment
    	$token = $this->initialize_PAY($payson_data);

    	// if token exists
    	if(strlen($token) > 0)
    	{
    		// Inserting into Payson payments table
    		tep_db_perform(
	    				"payson_payments",
		    				array(
		    					"payson_payments_token"       => tep_db_prepare_input($token),
		    					"payson_payments_session"     => tep_db_prepare_input(serialize($_SESSION)),
		    					"ip_address"                  => tep_db_prepare_input($_SERVER['REMOTE_ADDR']),
		    					"payson_payments_status"      => '0',
								"payson_payments_response"	  => NULL
		    				)
	    				);	

                $redirect_url = MODULE_PAYMENT_PAYSON_MODE == 'SANDBOX' ? $this->test_redirect_url : $this->redirect_url;
    		// Redirecting user to Payson:  https://www.payson.se/paySecure/
    		tep_redirect($redirect_url ."?token=" . $token);
    	}
    	else
    	{
    		// // Getting and setting the error message
	    	// preg_match("/errorList.error\(0\)\.message=(.*?)([^&]+)/i",$curl_result,$matches);
	    	// $error_message = $matches[1];	    	
			
			$error_message = 'Failed to get TOKEN from Payson';
	    	
    		// Redirecting with error message
    		tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=payson_payments&error=' . urlencode($error_message), 'SSL', true, false));
    	}
    	
		return false;
    }
    
    /**
     * Check to see whether module is installed
     *
     * @return boolean
     */
    function check(){
      if (!isset($this->_check)) 
      {
		$check_query = tep_db_query("
									SELECT 
										configuration_value 
									FROM 
										" . TABLE_CONFIGURATION . " 
									WHERE 
										configuration_key = 'MODULE_PAYMENT_PAYSON_STATUS'
									");
		$this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }
        	
	// For curling the data
    function initialize_PAY($data)
    {
		$result = $this->call_payson_api('PAY', $data);
		
		preg_match("/TOKEN=(.*)\&/i", $result, $matches);
		
		if(strlen($matches[1]) == 0)
		{
			tep_mail($this->email_logg, $this->email_logg, "Log from Payson Card/Bank Module::IntializePayment", $data .'\n\n' . $result, "", "");
		}
		
    	return $matches[1];		
    }
    
    function call_payson_api($api_function, $data)
	{
		$post_data = "";
		$res = false;
    	if(is_array($data))
		{
			foreach($data as $key => $value)
			{
				$post_data .= $key . "=" . htmlspecialchars(utf8_encode($value)) . "&";
			}

			// Remove last '&'
			$post_data = rtrim($post_data, '&');
		}
		else
		{
			$post_data = $data;
		}

		$fsocket = false;
		$curl = false;
		$api_endpoint = MODULE_PAYMENT_PAYSON_MODE == 'SANDBOX' ? $this->test_api_endpoint : $this->api_endpoint;
		if ( (PHP_VERSION >= 4.3) && ($fp = @fsockopen('ssl://' . $api_endpoint, 443, $errno, $errstr, 30)) ) {
			$fsocket = true;			
		} elseif (function_exists('curl_exec')) {
			$curl = true;
		}
	
		if ($fsocket == true) {
			$header = 'POST /' . $api_function . '/' . "\r\n" .
            'Host: ' . $server . "\r\n" .
            'Content-Type: application/x-www-form-urlencoded' . "\r\n" .
			"PAYSON-SECURITY-USERID:"   . MODULE_PAYMENT_PAYSON_MODE == 'SANDBOX' ? $this->test_agent_id : $this->agent_id . "\r\n".
			"PAYSON-SECURITY-PASSWORD:" . MODULE_PAYMENT_PAYSON_MODE == 'SANDBOX' ? $this->test_md5_key : $this->md5_key . "\r\n".
            'Content-Length: ' . strlen($post_data) . "\r\n" .
            'Connection: close' . "\r\n\r\n";

			@fputs($fp, $header . $post_data);

			$res = '';
			while (!@feof($fp)) {
				$string = @fgets($fp, 1024);
				$res .= $string;
			}

			@fclose($fp);			
			
		} elseif ($curl == true) {
		
			if(MODULE_PAYMENT_PAYSON_MODE == 'SANDBOX')
                            $header	= $this->getHeaderData($this->test_agent_id, $this->test_md5_key, $this->module_version);
                        else
                            $header	= $this->getHeaderData($this->agent_id, $this->md5_key, $this->module_version);
			
			$ch = curl_init();					
			
			curl_setopt($ch, CURLOPT_URL, 'https://' . $api_endpoint . $api_function . '/');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header ); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data );
			
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);			
			
			$res = curl_exec($ch);
			curl_close($ch);
		}
		
		tep_mail($this->email_logg, $this->email_logg, "Log from Payson Card/Bank Module::API CALL(" . $api_function . ")", "Data: \r\n" . str_replace("&", "\r\n", $post_data) . "\r\n\r\n\r\n Response: \r\n" . str_replace("&", "\r\n", $res), "", "");			
		
		if($res === false)
		{				
			// Redirecting with error message
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT,
									'payment_error=payson_payments&error='.
									urlencode("Connection error!"),
									'SSL', true, false));
		}		
		return 	$res;
	}
	
	function validate_IPN($data)
	{
		$result = $this->call_payson_api("validate", $data);
		if(preg_match("/VERIFIED/i", $result))
			return true;
			
		return false;
	}
	
	function paysonTokenResponseValidate($paysonTokenResponse)
	{
		if(preg_match("/responseEnvelope.ack\=SUCCESS/i", $paysonTokenResponse))
			return true;
		
		return false;
	}
	
	/*function getShippingAddress($token)	
	{
		$data = array(	"token"    => $token);
		$result = $this->call_payson_api('PaymentDetails', $data);
			
		$res_arr = array();
		$res_arr = explode("&",$result);
		
		tep_mail($this->email_logg, $this->email_logg, "Log from Payson Card/Bank Module::GetShippingAddress", $result, "", "");
		
		//find interesting keys
		$i=0;
		while($i < sizeof($res_arr) )
		{
			list($tag, $val) = explode("=", $res_arr[$i]);
			switch ($tag)
			{ 
				case 'shippingAddress.name':
					$shippingAddress['name'] = 'villle';
					break;

				case 'shippingAddress.streetAddress':
					$shippingAddress['streetAddress'] = urldecode($val);
					break;
			 
				case 'shippingAddress.postalCode':
					$shippingAddress['postalCode'] = urldecode($val);
					break;

				case 'shippingAddress.city':
					$shippingAddress['city'] = urldecode($val);
					break;

				case 'shippingAddress.country':
					$shippingAddress['country'] = urldecode($val);
					break;
			 
				default :
					//nothing
			}
			$i++;
		}  
		return $shippingAddress;
	}*/
	
	function getShopLanguage(){
		// Getting language data
        $_lq = tep_db_query("SELECT * FROM " . TABLE_LANGUAGES . " l WHERE languages_id = '" . (int)$GLOBALS['languages_id'] .  "' LIMIT 1");
        while($_l = tep_db_fetch_array($_lq))
        {
        	$language_code = $_l['code'];	
        }
	
		switch ( strtoupper($language_code) )
		{ 
			case 'SV':
			case 'SE':
			case 'SVENSKA':
			case 'SWEDISH':
				$localeCode = 'SV';
				break;

			case 'FI':
			case 'FINNISH':
			case 'FINSKA':
			case 'SUOMI':
				$localeCode = 'FI';
				break;

			default :
				$localeCode = 'EN';
		}
		return $localeCode;
	}
	
	function getHeaderData($userid, $md5key, $moduleversion)
	{
		$headerdata = array('PAYSON-SECURITY-USERID: '.$userid,
							'PAYSON-SECURITY-PASSWORD: '.$md5key,
							'PAYSON-APPLICATION-ID: '.$moduleversion,
							'PAYSON-MODULE-INFO: payson_oscommerce|'.$moduleversion.'|'.tep_get_version()!= null ? tep_get_version() : 'NONE');   
		return $headerdata;
	}
    
	// Installing function
    function install(){
    	// Entry for Active / Inactive status check
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Enable Payson Payments Module",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_STATUS",
		    					"configuration_value"       => "True",
		    					"configuration_description" => "Do you want to accept payson payments?",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "1",
		    					"set_function"              => "tep_cfg_select_option(array('True', 'False'), ",
		    					"date_added"                => "now()"
		    				)
	    				);
            
            tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Payment Sandbox/Live",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_MODE",
		    					"configuration_value"       => "SANDBOX",
		    					"configuration_description" => "Select the mode",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "16",
		    					"set_function"              => "tep_cfg_select_option(array('SANDBOX', 'LIVE'), ",
		    					"date_added"                => "now()"
		    				)
	    				);
	    
	    // Entry for zone checking
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Payment Zone",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_ZONE",
		    					"configuration_value"       => "0",
		    					"configuration_description" => "If a zone is selected, only enable this payment method for that zone.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "2",
		    					"use_function"              => "tep_get_zone_class_title",
		    					"set_function"              => "tep_cfg_pull_down_zone_classes(",
		    					"date_added"                => "now()"
		    				)
	    				);
	    				
	    // Entry for standard orders status
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Set Order Status",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_ORDER_STATUS_ID",
		    					"configuration_value"       => "0",
		    					"configuration_description" => "Set the status of orders made with this payment module to this value",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "0",
		    					"use_function"              => "tep_get_order_status_name",
		    					"set_function"              => "tep_cfg_pull_down_order_statuses(",
		    					"date_added"                => "now()"
		    				)
	    				);	
	    				
	    // Entry for payment method sort order
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Sort order of display.",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_SORT_ORDER",
		    					"configuration_value"       => "10",
		    					"configuration_description" => "Sort order of display. Lowest is displayed first.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "0",
		    					"date_added"                => "now()"
		    				)
	    				);
	    					   
	   	// Entry for Agent ID
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "API UserID (Agent ID)",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_AGENT_ID",
		    					"configuration_value"       => "",
		    					"configuration_description" => "Put in your Payson API Username",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "12",
		    					"date_added"                => "now()"
		    				)
	    				); 	
	    				
	    // Entry for Payson Password
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "API Password (MD5-Key)",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_MD5KEY",
		    					"configuration_value"       => "",
		    					"configuration_description" => "Your Payson API password (MD5 Key)",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "15",
		    					"date_added"                => "now()"
		    				)
	    				); 				
	    				
	    // Entry for Payson email
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Payson Email Address",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_EMAIL_ADDRESS",
		    					"configuration_value"       => "",
		    					"configuration_description" => "This is where the money is sent",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "20",
		    					"date_added"                => "now()"
		    				)
	    				);

	    				
	    				
		// Entry for Payson payment method
		
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Payment method",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_PAYMETHOD",
		    					"configuration_value"       => "ALL",
		    					"configuration_description" => "Select a payment method",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "16",
		    					"set_function"              => "tep_cfg_select_option(array('ALL', 'CREDITCARD', 'BANK'), ",
		    					"date_added"                => "now()"
		    				)
	    				);
	    				    				
		// Entry for Payson max order value
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Credit limit",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_ORDER_LIMIT",
		    					"configuration_value"       => "5000",
		    					"configuration_description" => "Only show this payment alternative for orders less than the value below.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "20",
		    					"date_added"                => "now()"
		    				)
	    				); 	

	    // Entry for vat setting
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Tax Class",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_TAX_CLASS",
		    					"configuration_value"       => "0",
		    					"configuration_description" => "Use the following tax class on the payment charge.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "6",
		    					"use_function"              => "tep_get_tax_class_title",
		    					"set_function"              => "tep_cfg_pull_down_tax_classes(",
		    					"date_added"                => "now()"
		    				)
	    				);
	    				
	     // Ignore this tables
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Ignore table",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_ORDERS_TOTAL_IGNORE",
		    					"configuration_description" => "Ignore these entries from order total list when compiling the invoice data.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "9",
		    					"configuration_value"       => "ot_tax,ot_total,ot_subtotal",
		    					"date_added"                => "now()"
		    				)
	    				);
	    					    				
	    // Payson Accepting Currencies
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Accepted Currencies",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_ACCEPTED_CURRENCIES",
		    					"configuration_description" => "Accepted currencies for Payson.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "20",
		    					"configuration_value"       => "SEK,EUR",
		    					"date_added"                => "now()"
		    				)
	    				);
	    				
	    // Payson Exchange Currencies
	    tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Exchange Currencies To",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_EXCHANGE_CURRENCIES",
		    					"configuration_description" => "Exchange currency for Payson.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "21",
		    					"configuration_value"       => "SEK",
		    					"date_added"                => "now()"
		    				)
	    				);
	    				
	    // Inserting email logg variable
	     tep_db_perform(
	    				TABLE_CONFIGURATION,
		    				array(
		    					"configuration_title"       => "Email logg address",
		    					"configuration_key"         => "MODULE_PAYMENT_PAYSON_EMAIL_LOGG",
		    					"configuration_value"       => "",
		    					"configuration_description" => "Use the following email address for logging.",
		    					"configuration_group_id"    => "6",
		    					"sort_order"                => "15",
		    					"date_added"                => "now()"
		    				)
	    				);	
	    				
	    //Inserting payson table
	  	tep_db_query("
	   		CREATE TABLE IF NOT EXISTS payson_payments (
	          payson_payments_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	          payson_payments_token varchar(255) NOT NULL ,
			  payson_payments_purchaseId int(11) default NULL,
	          payson_payments_session text NOT NULL ,
	          payson_payments_response VARCHAR(255) NOT NULL ,
	          payson_payments_status VARCHAR(20) NOT NULL,
			  order_id int(11) UNSIGNED NOT NULL DEFAULT 0,
	          date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	          date_updated TIMESTAMP NOT NULL,
	          ip_address VARCHAR(255)			  
	        )");
	    				
	}
	
    
	// Uninstall function
	function remove(){
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");																			
	}
	
    function keys(){
        return array(
                       'MODULE_PAYMENT_PAYSON_STATUS',
                       'MODULE_PAYMENT_PAYSON_MODE',
                       'MODULE_PAYMENT_PAYSON_ZONE',
               	       'MODULE_PAYMENT_PAYSON_ORDER_STATUS_ID',
        	       'MODULE_PAYMENT_PAYSON_SORT_ORDER',   
                       'MODULE_PAYMENT_PAYSON_AGENT_ID',
                       'MODULE_PAYMENT_PAYSON_MD5KEY',
        	       'MODULE_PAYMENT_PAYSON_EMAIL_ADDRESS',
        	       'MODULE_PAYMENT_PAYSON_PAYMETHOD',
                       'MODULE_PAYMENT_PAYSON_ORDER_LIMIT',
        	       'MODULE_PAYMENT_PAYSON_TAX_CLASS',
        		'MODULE_PAYMENT_PAYSON_ORDERS_TOTAL_IGNORE',
        		'MODULE_PAYMENT_PAYSON_ACCEPTED_CURRENCIES',
        		'MODULE_PAYMENT_PAYSON_EXCHANGE_CURRENCIES',
        		'MODULE_PAYMENT_PAYSON_EMAIL_LOGG'                             
                      );
    }
    
	function paysonPaymentUpdate($token, $update_cmd){
		if (!isset($token) )
			return false;
		
		if (!isset($update_cmd) )
			return false;
	
		//----------------------------------------------------------------
		$data  = array(	"token"    => urlencode($token),
						"action"   => $update_cmd);
		
		$result = $this->call_payson_api('PAYMENTUPDATE', $data);
		
		return $this->paysonTokenResponseValidate($result);
	}
	
	function stringDump($string){
		$filename= "ipn_payson.txt";
		$handle = fopen($filename, "a");
		fwrite($handle, "** ".date("Y-m-d H:i:s")." **\n");
		fwrite($handle, "Data: $string; \n");
		fclose($handle);
	}
	
	/**
	* Post-processing activities
	* When the order returns from the processor, if PDT was successful, this stores the results in order-status-history and logs data for subsequent reference
	*
	* @return boolean
    */
    function after_process() 
    {
    	global $insert_id, $order;
     
    	$sql_data_array = array('orders_id'         => $insert_id, 
							    'orders_status_id'  => ($order->info['order_status']), 
							    'date_added'        => 'now()', 
							    'customer_notified' => 0,
							    'comments'          => ((isset($_SESSION['comments']) ? $_SESSION['comments'].'  ' : '') . PAYSON_PAYMENT_PAYSON_STATUS_ORDER_CREATED_SHORT . ' - PurchaseId: ' . $_SESSION['payson_payments_purchaseId']));

    	tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
   	
		tep_db_query("UPDATE payson_payments SET order_id = " . $insert_id . " WHERE payson_payments_purchaseId = '" . tep_db_prepare_input($_SESSION['payson_payments_purchaseId']) . "'");
		
		return false;
    }
    
	
	
}
