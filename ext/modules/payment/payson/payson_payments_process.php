<?php
/*
  Payson AB
  https://www.payson.se

  Copyright (c) 2010 Payson AB

  Released under the GNU General Public License
*/

if ( strlen($_GET["token"]) > 0)
{
	$token = $_GET["token"];
	$_psq = tep_db_query("SELECT * FROM payson_payments WHERE payson_payments_token  = '" . tep_db_prepare_input($token) . "' LIMIT 1");
						
	while($_ps = tep_db_fetch_array($_psq))
	{
            switch($_ps['payson_payments_status']){
                case 'COMPLETED':
                case 'PENDING':
                    break;
                default :
                    die("Payment not accepted!");
                    
            }
	   	/*if($_ps['payson_payments_status'] != 'COMPLETED')
	   	{
	   		die("Payment not accepted!");	
	   	}*/
	 	
      	$session_temp = unserialize($_ps["payson_payments_session"]);
      	
		if ( is_array($session_temp) )
		{
			// Setting session variable
			$_SESSION = $session_temp;
			$cart = new shoppingCart;
			$cart->unserialize( $_SESSION["cart"] );

			// Setting customers id, sendto and billto
			$customer_id = $_SESSION['customer_id'];
			$sendto      = $_SESSION['sendto'];
			$billto      = $_SESSION['billto'];

			// Set shipping method
			$shipping    = $_SESSION['shipping'];

			// Hack for buysafe module problems
			if(!isset($buysafe_module))
			{
				if(file_exists(DIR_WS_CLASSES . "buysafe.php"))
				{
					include_once(DIR_WS_CLASSES . "buysafe.php");
					$buysafe_module = new buysafe_class();	
				}
			}

			// Setting payment_method
			if($_GET['type'] == 'TRANSFER'){
				$payment = "payson";	 
			}else
				$payment = "payson_payments_invoice";
			
			$_SESSION["payson_payments_purchaseId"] = $_ps["payson_payments_purchaseId"];

			// Clear stored session to avoid creation of multiple orders on multiple IPN calls
			tep_db_query("UPDATE payson_payments SET payson_payments_session = '' WHERE payson_payments_token = '" . tep_db_prepare_input($token) . "'");			
		}
	}
}
