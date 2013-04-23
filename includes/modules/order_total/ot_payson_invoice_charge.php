<?php
/*
  $Id: ot_payson_invoice_charge.php

  Payson AB
  https://www.payson.se

  Copyright (c) 2010 Payson AB

  Released under the GNU General Public License
*/

class ot_payson_invoice_charge {
    var $title, $output;

    function ot_payson_invoice_charge() 
	{
		$this->code = 'ot_payson_invoice_charge';
		$this->title = MODULE_PAYSON_INVOICE_CHARGE_TITLE;
		$this->description = MODULE_PAYSON_INVOICE_CHARGE_DESCRIPTION;
		$this->enabled = MODULE_PAYSON_INVOICE_CHARGE_STATUS;
		$this->sort_order = MODULE_PAYSON_INVOICE_CHARGE_SORT_ORDER;
		$this->tax_class = MODULE_PAYSON_INVOICE_CHARGE_TAX_CLASS;
		$this->output = array();
    }

   function process() 
   {
		global $order, $ot_subtotal, $currencies;

		$od_amount = $this->calculate_credit($this->get_order_total());

		if ($od_amount != 0) {
			$this->output[] = array('title' => $this->title . ':',
						'text' => $currencies->format($od_amount),
						'value' => $od_amount);
			$order->info['total'] = $order->info['total'] + $od_amount;  
			if ($this->sort_order < $ot_subtotal->sort_order) {
			$order->info['subtotal'] = $order->info['subtotal'] - $od_amount;
			}
		}
    }
    

    function calculate_credit($amount) 
	{
		global $order, $customer_id, $payment, $sendto, $customer_id,
			$customer_zone_id,$customer_country_id, $cart;

		$od_amount=0;

		if ($payment != "payson_payments_invoice") 
			return $od_amount;

		if (MODULE_PAYSON_INVOICE_CHARGE_MODE == 'fixed') {
			$od_amount = MODULE_PAYSON_INVOICE_CHARGE_FIXED;
		}
		else {
			$table = split("[:,]" , MODULE_PAYSON_INVOICE_CHARGE_TABLE);

			$size = sizeof($table);
			for ($i=0, $n=$size; $i<$n; $i+=2) 
			{
				if ($amount <= $table[$i]) 
				{
					$od_amount = $table[$i+1];
					break;
				}
			}
		}
		
		if ($od_amount == 0)
			return $od_amount;

		if (MODULE_PAYSON_INVOICE_CHARGE_TAX_CLASS > 0) {
			$tod_rate =tep_get_tax_rate(MODULE_PAYSON_INVOICE_CHARGE_TAX_CLASS);
			$tod_amount = $od_amount - $od_amount/($tod_rate/100+1);
			$order->info['tax'] += $tod_amount;
			$tax_desc = tep_get_tax_description(
			MODULE_PAYSON_INVOICE_CHARGE_TAX_CLASS,
			$customer_country_id, $customer_zone_id);
			$order->info['tax_groups']["$tax_desc"] += $tod_amount;
		}

		if (DISPLAY_PRICE_WITH_TAX=="true") { 
			$od_amount = $od_amount;
		} else {       
			$od_amount = $od_amount-$tod_amount;
			$order->info['total'] += $tod_amount;
		}

		return $od_amount;
    }
   
	function get_order_total() 
	{
		global  $order, $cart;
		$order_total = $order->info['total'];

		// Check if gift voucher is in cart and adjust total
		$products = $cart->get_products();

		for ($i=0; $i<sizeof($products); $i++) 
		{
			$t_prid = tep_get_prid($products[$i]['id']);	

			$gv_query = tep_db_query(
			"select products_price, products_tax_class_id, ".
			"products_model from " . TABLE_PRODUCTS .
			" where products_id = '" . $t_prid . "'");

			$gv_result = tep_db_fetch_array($gv_query);
                       // print_r($gv_result); exit;
			if (preg_match('/^GIFT/', addslashes($gv_result['products_model']))) 
			{ 
				$qty = $cart->get_quantity($t_prid);
				$products_tax =
					tep_get_tax_rate($gv_result['products_tax_class_id']);

				if ($this->include_tax =='false') {
					$gv_amount = $gv_result['products_price'] * $qty;
				} else {
					$gv_amount = ($gv_result['products_price'] +
						  tep_calculate_tax(
							  $gv_result['products_price'],
							  $products_tax)) * $qty;
				}
				$order_total=$order_total - $gv_amount;
			}
		}

		if ($this->include_tax == 'false')
			$order_total=$order_total-$order->info['tax'];

		if ($this->include_shipping == 'false')
			$order_total=$order_total-$order->info['shipping_cost'];

		return $order_total;
    }

    
	function check() 
	{
		if (!isset($this->check)) {
			$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYSON_INVOICE_CHARGE_STATUS'");
			$this->check = tep_db_num_rows($check_query);
		}
		return $this->check;
    }

    function keys() 
	{
		return array('MODULE_PAYSON_INVOICE_CHARGE_STATUS',
		     'MODULE_PAYSON_INVOICE_CHARGE_MODE',
		     'MODULE_PAYSON_INVOICE_CHARGE_FIXED',
		     'MODULE_PAYSON_INVOICE_CHARGE_TABLE',
		     'MODULE_PAYSON_INVOICE_CHARGE_TAX_CLASS',
		     'MODULE_PAYSON_INVOICE_CHARGE_SORT_ORDER'
		     );
    }

	function install() 
	{
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display Total', 'MODULE_PAYSON_INVOICE_CHARGE_STATUS', 'true', 'Do you want to display the payment charge', '6', '1','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_PAYSON_INVOICE_CHARGE_SORT_ORDER', '900', 'Sort order of display.', '6', '2', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Fixed invoice charge', 'MODULE_PAYSON_INVOICE_CHARGE_FIXED', '20', 'Fixed invoice charge (inc. VAT). Use store default currency to calculate the invoice fee.', '6', '7', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Charge Table', 'MODULE_PAYSON_INVOICE_CHARGE_TABLE', '200:20,500:10,10000:5', 'The invoice charge is based on the total cost. Example: 200:20.500,10:10000:5,etc.. Up to 200 charge 20, from there to 500 charge 10, etc', '6', '2', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_PAYSON_INVOICE_CHARGE_TAX_CLASS', '0', 'Use the following tax class on the payment charge.', '6', '6', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Charge Type', 'MODULE_PAYSON_INVOICE_CHARGE_MODE', 'fixed', 'Invoice charge is fixed or based  on the order total.', '6', '0', 'tep_cfg_select_option(array(\'fixed\', \'price\'), ', now())");
    }

    function remove() 
	{
		$keys = '';
		$keys_array = $this->keys();
		for ($i=0; $i<sizeof($keys_array); $i++) {
			$keys .= "'" . $keys_array[$i] . "',";
		}
		$keys = substr($keys, 0, -1);

		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in (" . $keys . ")");
    }
}
?>