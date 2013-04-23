<?php
chdir('../../../../');
include('includes/application_top.php');
//include('includes/modules/payment/payson.php');
if ( strlen($_GET['TOKEN']) <= 0)
{
   tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT)); 
}
$payson_payments_status = tep_db_query("SELECT payson_payments_status FROM payson_payments WHERE payson_payments_token = '" . $_GET['TOKEN'] . "'");

if(tep_db_num_rows($payson_payments_status) == 0)
{
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT));
}


$ipn_status = tep_db_fetch_array($payson_payments_status);
switch($ipn_status['payson_payments_status']){
    case 'COMPLETED':
    case 'PENDING':
        unset($_SESSION[cart]);
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS));
        break;
    case 'ERROR':
paysonApiError('Payment denaid by Payson');
        exit;
    default:
        
        
}


function paysonApiError($error) {
    $error_code = '<html>
                    <head>
			<script type="text/javascript"> 
				alert("'.$error.'");
				window.location="'.tep_href_link(FILENAME_CHECKOUT_PAYMENT).'";
			</script>
		</head>
		</html>';
    echo $error_code;
    exit;
			
}
?>
