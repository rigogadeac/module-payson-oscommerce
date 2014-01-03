<?php

/*
  Payson AB
  https://www.payson.se

  Copyright (c) 2010 Payson AB

  Released under the GNU General Public License
 */

if (strlen($_GET["token"]) > 0) {

    // Verify DATA if Request come back before IPN

    if (file_exists(DIR_WS_MODULES . "payment/payson.php")) {
        include_once(DIR_WS_MODULES . "payment/payson.php");
        $payson_payments = new payson;
    } else {
        die('Payson Module not found');
    }


    $token = $_GET["token"];


    
    $_psq = tep_db_query("SELECT * FROM payson_payments WHERE payson_payments_token  = '" . tep_db_prepare_input($token) . "' LIMIT 1");
    
    while ($_ps = tep_db_fetch_array($_psq)) {

        if ($_ps['payson_payments_status'] == 0) {
            try {
                $result = $payson_payments->parseQuery($payson_payments->call_payson_api("PaymentDetails", 'token=' . $token));


                if ($result['responseEnvelope.ack'] == 'SUCCESS') {
                    $_ps['payson_payments_status'] = $result['status'];
                    $_ps['payson_payments_purchaseId'] = $result['purchaseId'];
                    tep_db_query("UPDATE payson_payments SET payson_payments_status = '" . $result['status'] . "', payson_payments_purchaseId = '" . tep_db_prepare_input($result['purchaseId']) . "' WHERE payson_payments_token = '" . tep_db_prepare_input($token) . "'");
                }
            } catch (Exception $e) {
                
            }
        }
        
        switch ($_ps['payson_payments_status']) {
            case 'COMPLETED':
            case 'PENDING':
                break;
            default :
                die("Payment not accepted!");
        }

        $session_temp = unserialize($_ps["payson_payments_session"]);

        if (is_array($session_temp)) {
            // Setting session variable
            $_SESSION = $session_temp;
            $cart = new shoppingCart;
            $cart->unserialize($_SESSION["cart"]);

            // Setting customers id, sendto and billto
            $customer_id = $_SESSION['customer_id'];
            $sendto = $_SESSION['sendto'];
            $billto = $_SESSION['billto'];

            // Set shipping method
            $shipping = $_SESSION['shipping'];

            // Hack for buysafe module problems
            if (!isset($buysafe_module)) {
                if (file_exists(DIR_WS_CLASSES . "buysafe.php")) {
                    include_once(DIR_WS_CLASSES . "buysafe.php");
                    $buysafe_module = new buysafe_class();
                }
            }

            // Setting payment_method
            if ($_GET['type'] == 'TRANSFER') {
                $payment = "payson";
            } else
                $payment = "payson_payments_invoice";

            $_SESSION["payson_payments_purchaseId"] = $_ps["payson_payments_purchaseId"];

            // Clear stored session to avoid creation of multiple orders on multiple IPN calls
            tep_db_query("UPDATE payson_payments SET payson_payments_session = '' WHERE payson_payments_token = '" . tep_db_prepare_input($token) . "'");
        }
    }
}
