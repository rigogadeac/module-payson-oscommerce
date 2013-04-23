<?php
/*
  Payson AB
  https://www.payson.se

  Copyright (c) 2010 Payson AB

  Released under the GNU General Public License
*/

	chdir('../../../../');
	include('includes/application_top.php');
        
	// Detect type of transaction
	if(isset($_POST['type']) && $_POST['type'] == 'TRANSFER') 
	{
		if(file_exists(DIR_WS_MODULES . "payment/payson.php"))
        {
			include_once(DIR_WS_MODULES . "payment/payson.php");
			$payson_payments  = new payson;
			if(!$payson_payments->check())
				die('Payson CCard Module not enabled');
		}
		else
		{
			die('Payson CCard Module not found');
			error_log("Payson CCard Module not found: " . DIR_WS_MODULES . "payment/payson.php", 0);
		}
	}

	// Getting token for payson ipn
	$token = $_POST['token'];
	
	// Logging email for debugging
	if(!empty($payson_payments->email_logg))
	{
		$log_data  = "Transaction " . $token . " data: 
		";
		$log_data .= "Get:
		";
		$log_data .= print_r($_GET,true);
		$log_data .= "
		
		Post:
		";
		$log_data .= print_r($_POST,true);
		
		tep_mail($payson_payments->email_logg, $payson_payments->email_logg, "Log from Payson Card/Bank Module::IPN", $log_data, "", "");
	}

	// Die if no token
	if(empty($token))
	{
		tep_mail($payson_payments->email_logg, $payson_payments->email_logg, "Log from Payson Card/Bank Module::IPN", "Payment token is empty!", "", "");
		die("Payment token is empty!");
	}
	// Checking that token exists
	$token_exists_query = tep_db_query("SELECT payson_payments_token FROM payson_payments WHERE payson_payments_token = '" . tep_db_prepare_input($token) . "'");
	if(tep_db_num_rows($token_exists_query) == 0)
	{
		tep_mail($payson_payments->email_logg, $payson_payments->email_logg, "Log from Payson Card/Bank Module::IPN", "Token dont exists!", "", "");
		die("Token dont exists!");
	}
	// Checking if we have the correct state to mark order as paid
	/*if($_POST['status'] != "COMPLETED")
	{
		tep_mail($payson_payments->email_logg, $payson_payments->email_logg, "Log from Payson Card/Bank Module::IPN", "Payment not completed!", "", "");
		die("Payment not completed!");
	}*/
	
	// Validation check
	$req = file_get_contents('php://input');

  	
	//tep_db_query("insert into payson_test (id, text, date) values ('" . (int)$customer_id . "', 'hej', '" . date('Ymd') . "')");
	
	// Matching for verified
	if($payson_payments->validate_IPN($req))
	{
		// Logging for debugging
		if(!empty($payson_payments->email_logg))
		{
			tep_mail($payson_payments->email_logg, $payson_payments->email_logg, "Log from Payson Card/Bank Module::IPN Validated", "Purchase: " . $_POST['purchaseId'] . " validated.", "", "");	
		}	
		//tep_db_query("insert into payson_test (id, text, date) values ('" . (int)$customer_id . "', '" . $_POST['type'] . "', '" . date('Ymd') . "')");		
		// Updating payson status by token
		tep_db_query("UPDATE payson_payments SET payson_payments_status = '".$_POST['status']."', payson_payments_purchaseId = '" . tep_db_prepare_input($_POST['purchaseId']) .	"' WHERE payson_payments_token = '" . tep_db_prepare_input($token) . "'");
		tep_redirect("../../../../" . FILENAME_CHECKOUT_PROCESS . '?token=' . $token.'&type='. $_POST['type']);
		
	}
	else
	{
		tep_mail($payson_payments->email_logg, $payson_payments->email_logg, "Log from Payson Card/Bank Module::IPN", "IPN FATAL ERROR :: Purchase: " . $_POST['purchaseId'] . "did not validate. ABORTED.", "", "");			
		die('IPN FATAL ERROR :: Transaction ' . $_POST['purchaseId'] . 'did not validate. ABORTED.');
	}