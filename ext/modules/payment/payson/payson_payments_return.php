<?php
chdir('../../../../');
include('includes/application_top.php');
include('includes/modules/payment/payson.php');
if ( strlen($_GET['TOKEN']) <= 0)
{
   tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT)); 
}

$payson = new payson();

$result = $payson->call_payson_api('PaymentDetails', array("token"    => $_GET['TOKEN']));

$res_arr = explode("&",$result);
$i=0;
$ipn_status = '';
while($i < sizeof($res_arr) ){
    list($tag, $val) = explode("=", $res_arr[$i]);
    if ($val == 'COMPLETED' || $val == 'PENDING' || $val == 'ERROR'){
          $ipn_status = $val;
            break;
                    
    }
    $i++;    
}

switch($ipn_status){
    case 'COMPLETED':
    case 'PENDING':
       // unset($_SESSION[cart]);
        tep_redirect("../../../../" . FILENAME_CHECKOUT_PROCESS . '?token=' . $_GET['TOKEN'].'&type=TRANSFER');
        
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
