<?php
	// Payson switch
	switch($_GET['action'])
	{
		case "edit":
			// Including language
			require_once("../" . DIR_WS_LANGUAGES . $language . "/modules/payment/payson_payments_invoice.php");
			
			// Query for getting payson data
			$_psq = tep_db_query("
									SELECT 
										pp.payson_payments_token,
										pp.*
									FROM
										" . TABLE_ORDERS . " o INNER JOIN
										payson_payments pp ON (o.orders_id = pp.order_id)
									WHERE
										o.orders_id     = '" . (int)$oID . "' AND
										pp.payson_payments_token != ''
								");
			
			if(tep_db_num_rows($_psq) > 0)
			{
				// Initialzing payson class
				require_once("../" . DIR_WS_MODULES . "payment/payson_payments_invoice.php");
				$payson_payments = new payson_payments_invoice();
			
				$_ps = tep_db_fetch_array($_psq);
				
				$api_response = $payson_payments->call_payson_api("PaymentDetails", array("token" => $_ps['payson_payments_token']));
				
				preg_match("/invoiceStatus=(.\w*)/i", $api_response, $matches);
				$payment_status = $matches[1];
				//print_r($payment_status);
				
				?>
					<tr>
						<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
					</tr>
					<tr>
						<td class="main" valign="top"></td>
						<td class="main" valign="top">
							<?php
								switch($payment_status)
								{
								case "ORDERCREATED":
									echo PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_STATUS_ORDER_CREATED; 
									?>
										<br/><br/>							
										<a href="orders.php?action=payson_invoice_shiporder&oID=<?php echo $_GET['oID']; ?>&token=<?php echo $_ps['payson_payments_token']; ?>&orders_status_id=<?php echo $order->info['orders_status']; ?>" 
										style="text-decoration:underline;font-size:12px"><?php echo PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_SHIP_ORDER; ?> &raquo;</a>
										&nbsp;<a href="orders.php?action=payson_invoice_cancel&oID=<?php echo $_GET['oID']; ?>&token=<?php echo $_ps['payson_payments_token']; ?>&orders_status_id=<?php echo $order->info['orders_status']; ?>" 
										style="text-decoration:underline;font-size:12px"><?php echo PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_CANCEL_INVOICE; ?> &raquo;</a>
									<?php
									break;
								case "SHIPPED":
									echo PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_STATUS_ACTIVATED;
									?>
										<br/><br/>							
										<a href="orders.php?action=payson_invoice_credit&oID=<?php echo $_GET['oID']; ?>&token=<?php echo $_ps['payson_payments_token']; ?>&orders_status_id=<?php echo $order->info['orders_status']; ?>" 
										style="text-decoration:underline;font-size:12px"><?php echo PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_CREDIT_INVOICE; ?> &raquo;</a>										
									<?php
									break;
								case "CREDITED":
									echo PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_STATUS_CREDITED;
									break;
								case "ORDERCANCELLED":
									echo PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_STATUS_CANCELED;
									break;
								}						
							?>
							
						</td>
					</tr>
				<?php			
			}
			
		break;	
	
		case "payson_invoice_shiporder":
		
			// Initialzing payson class
			require_once("../" . DIR_WS_MODULES . "payment/payson_payments_invoice.php");
			// Including language
			require_once("../" . DIR_WS_LANGUAGES . $language . "/modules/payment/payson_payments_invoice.php");
			
			$payson_payments = new payson_payments_invoice();	
				
			// Activating invoice
			$updated = $payson_payments->paysonPaymentUpdate($_GET['token'], "SHIPORDER");													
			
			if($updated)
			{
				// Inserting orders status history connection
				$sql_data_array = array(
									'orders_id'         => $_GET['oID'], 
									'orders_status_id'  => $_GET['orders_status_id'], 
									'date_added'        => 'now()', 
									'customer_notified' => 0,
									'comments'          => (PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_STATUS_ACTIVATED));

				tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);	
				
				// Redirecting with message
				$messageStack->add_session("Invoice sent by Payson on your request.", 'success');
				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . "&action=edit"));
			}
			else
			{
				// Redirecting with message
				$messageStack->add_session("Invoice can't be activated by Payson", 'error');
				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . "&action=edit"));
			}													
														
			break;
		case "payson_invoice_credit":
		
			// Initialzing payson class
			require_once("../" . DIR_WS_MODULES . "payment/payson_payments_invoice.php");
			// Including language
			require_once("../" . DIR_WS_LANGUAGES . $language . "/modules/payment/payson_payments_invoice.php");
			
			$payson_payments = new payson_payments_invoice();	
				
			// Activating invoice
			$updated = $payson_payments->paysonPaymentUpdate($_GET['token'], "CREDITORDER ");													
			
			if($updated)
			{
				// Inserting orders status history connection
				$sql_data_array = array(
									'orders_id'         => $_GET['oID'], 
									'orders_status_id'  => $_GET['orders_status_id'], 
									'date_added'        => 'now()', 
									'customer_notified' => 0,
									'comments'          => (PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_STATUS_CREDITED));

				tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);	
				
				// Redirecting with message
				$messageStack->add_session("Invoice credited by Payson on your request.", 'success');
				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . "&action=edit"));
			}
			else
			{
				// Redirecting with message
				$messageStack->add_session("Invoice can't be credited by Payson", 'error');
				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . "&action=edit"));
			}													
		
		case "payson_invoice_cancel":
		
			// Initialzing payson class
			require_once("../" . DIR_WS_MODULES . "payment/payson_payments_invoice.php");
			// Including language
			require_once("../" . DIR_WS_LANGUAGES . $language . "/modules/payment/payson_payments_invoice.php");
			
			$payson_payments = new payson_payments_invoice();	
				
			// Activating invoice
			$updated = $payson_payments->paysonPaymentUpdate($_GET['token'], "CANCELORDER ");													
			
			if($updated)
			{
				// Inserting orders status history connection
				$sql_data_array = array(
									'orders_id'         => $_GET['oID'], 
									'orders_status_id'  => $_GET['orders_status_id'], 
									'date_added'        => 'now()', 
									'customer_notified' => 0,
									'comments'          => (PAYSON_PAYMENT_PAYSON_PAYMENTS_INVOICE_STATUS_CANCELED));

				tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);	
				
				// Redirecting with message
				$messageStack->add_session("Invoice canceled by Payson on your request.", 'success');
				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . "&action=edit"));
			}
			else
			{
				// Redirecting with message
				$messageStack->add_session("Invoice can't be canceled by Payson.", 'error');
				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . "&action=edit"));
			}					
		break;			
	}