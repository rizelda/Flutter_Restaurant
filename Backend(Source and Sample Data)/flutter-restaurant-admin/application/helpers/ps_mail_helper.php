<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Send Booking Request Email to hotel
 * @param  [type] $booking_id [description]
 * @return [type]             [description]
 */
if ( !function_exists( 'send_transaction_order_emails' )) {

	function send_transaction_order_emails( $trans_header_id, $to_who = "", $subject = "" )
	{
		// get ci instance
		$CI =& get_instance();

		$sender_name = $CI->Backend_config->get_one('be1')->sender_name;
		
		$shop_obj = $CI->Shop->get_all()->result();

		$shop_id = $shop_obj[0]->id;

		$trans_header_obj = $CI->Transactionheader->get_one($trans_header_id);

		$shop_name = $CI->Shop->get_one($shop_id)->name;

		$shop_email = $CI->Shop->get_one($shop_id)->email;

		$trans_currency = $CI->Shop->get_one($shop_id)->currency_symbol;

		$user_email =  $CI->User->get_one($trans_header_obj->added_user_id)->user_email;

		$user_name =  $CI->User->get_one($trans_header_obj->added_user_id)->user_name;

		//bank info 
		$bank_account = $CI->Shop->get_one($shop_id)->bank_account;
		$bank_name = $CI->Shop->get_one($shop_id)->bank_name;
		$bank_code = $CI->Shop->get_one($shop_id)->bank_code;
		$branch_code = $CI->Shop->get_one($shop_id)->branch_code;
		$swift_code = $CI->Shop->get_one($shop_id)->swift_code;


		$bank_info  = get_msg('bank_acc_label') . $bank_account . " <br> " .
					get_msg('bank_name_label') . $bank_name . " <br> " .
					get_msg('bank_code_label') . $bank_code . " <br> " .
					get_msg('branch_code_label') . $branch_code . " <br> " .
		            get_msg('swift_code_label') . $swift_code . " <br><br> " ;

		//For Payment Method 
		$payment_info = "";
		if($trans_header_obj->payment_method == "COD") {
			$payment_info = get_msg('pay_met_cod');
		} else if($trans_header_obj->payment_method == "PAYPAL") {
			$payment_info = get_msg('pay_met_paypal');
		} else if($trans_header_obj->payment_method == "STRIPE") {
			$payment_info = get_msg('pay_met_stripe');
		} else if($trans_header_obj->payment_method == "BANK") {
			$payment_info = get_msg('pay_met_bank') . $bank_info;
		}


		$conds['transactions_header_id'] = $trans_header_obj->id;

		$trans_details_obj = $CI->Transactiondetail->get_all_by($conds)->result();

		//For Transaction Detials
		for($i=0;$i<count($trans_details_obj);$i++) 
		{
				if($trans_details_obj[$i]->product_attribute_id != "") {
					

					$att_name_info  = explode("#", $trans_details_obj[$i]->product_attribute_name);
					
					$att_price_info = explode("#", $trans_details_obj[$i]->product_attribute_price);

					$att_info_str = "";
					$att_flag = 0;
					if( count($att_name_info[0]) > 0 ) {

						//loop attribute info
						for($k = 0; $k < count($att_name_info); $k++) {
							
							if($att_name_info[$k] != "") {
								$att_flag = 1;
								$att_info_str .= $att_name_info[$k] . " : " . $att_price_info[$k] . "(". $trans_currency ."),";

							}
						}


					} else {
						$att_info_str = "";
					}

					$att_info_str = rtrim($att_info_str, ","); 

					


					$order_items .= $i + 1 .". " . $trans_details_obj[$i]->product_name . 
					" (". get_msg('price_label') .   $trans_details_obj[$i]->original_price  . html_entity_decode($trans_currency) . 
					"," . get_msg('qty_label') ." : " . $trans_details_obj[$i]->qty . ",". get_msg('unit_label') ." : " . $trans_details_obj[$i]->product_measurement .' ' . $trans_details_obj[$i]->product_unit . ") {". $att_info_str ."}<br>";





				} else {
					
					$order_items .= $i + 1 .". " . $trans_details_obj[$i]->product_name . 
					" (". get_msg('price_label') .   $trans_details_obj[$i]->original_price  . html_entity_decode($trans_currency) . 
					"," . get_msg('qty_label') ." : " . $trans_details_obj[$i]->qty . ",". get_msg('unit_label') ." : " . $trans_details_obj[$i]->product_measurement .' ' . $trans_details_obj[$i]->product_unit . ") <br>";
					
				}
				
				$sub_total_amt += $trans_details_obj[$i]->original_price * $trans_details_obj[$i]->qty;
				
				
		}


		

		$trans_status = $CI->Transactionstatus->get_one($trans_header_obj->trans_status_id)->title;

		
		$total_amt = $total_amount .' ' . html_entity_decode($trans_currency);

		$coupon_discount_amount = $trans_header_obj->coupon_discount_amount;
		$tax_amount = $trans_header_obj->tax_amount;
		$shipping_method_amount = $trans_header_obj->shipping_method_amount;
		$shipping_tax_amount = $trans_header_obj->shipping_method_amount * $trans_header_obj->shipping_tax_percent;

		$total_balance_amount = ($trans_header_obj->sub_total_amount + ($trans_header_obj->tax_amount + $trans_header_obj->shipping_method_amount + ($trans_header_obj->shipping_method_amount * $trans_header_obj->shipping_tax_percent)));  	
		//for msg label
		$hi = get_msg('hi_label');
    	$order_receive_info = get_msg('order_receive_info');
    	$trans_code = get_msg('trans_code');
    	$trans_status_label = get_msg('trans_status_label');
    	$memo_label = get_msg('memo_label');
    	$prd_detail_info = get_msg('prd_detail_info');
    	$sub_total = get_msg('sub_total');
    	$coupon_dis_amount = get_msg('coupon_dis_amount');
    	$overall_tax = get_msg('overall_tax');
    	$shipping_tax = get_msg('shipping_tax');
    	$total_bal_amt = get_msg('total_bal_amt');
    	$best_regards = get_msg( 'best_regards_label' );
		//Shop or User
		if($to_who == "shop") {
		
			$to = $shop_email;
			
			$msg = <<<EOL
<p>{$hi} {$shop_name},</p>

<p>{$order_receive_info}</p>

<p>
{$trans_code} : {$trans_header_obj->trans_code}<br/>
</p>

<p>
{$trans_status_label} : {$trans_status}<br/>
</p>

<p>
{$payment_info}<br/>
</p>

<p>{$prd_detail_info} :</p>
{$order_items}            

<p>
{$sub_total} : {$sub_total_amt} {$trans_currency}
</p>
<p>
{$coupon_dis_amount} : {$coupon_discount_amount} {$trans_currency}
</p>
<p>
{$overall_tax} : {$tax_amount} {$trans_currency}
</p>
<p>
{$shipping_tax} : {$shipping_tax_amount} {$trans_currency}
</p>
<p>
{$total_bal_amt} : {$total_balance_amount} {$trans_currency}
</p>


<p>
{$best_regards},<br/>
{$sender_name}
</p>
EOL;

		} else if ($to_who == "user") {

			$to = $user_email;

			$msg = <<<EOL
<p>{$hi} {$user_name},</p>

<p>{$order_receive_info}</p>

<p>
{$trans_code} : {$trans_header_obj->trans_code}<br/>
</p>

<p>
{$trans_status_label} : {$trans_status}<br/>
</p>

<p>
{$payment_info}<br/>
</p>

<p>
{$memo_label}: {$trans_header_obj->memo}
</p>

<p>{$prd_detail_info} :</p>
{$order_items}            


<p>
{$sub_total} : {$sub_total_amt} {$trans_currency}
</p>
<p>
{$coupon_dis_amount} : {$coupon_discount_amount} {$trans_currency}
</p>
<p>
{$overall_tax} : {$tax_amount} {$trans_currency}
</p>
<p>
{$shipping_tax} : {$shipping_tax_amount} {$trans_currency}
</p>
<p>
{$total_bal_amt} : {$total_balance_amount} {$trans_currency}
</p>


<p>
{$best_regards},<br/>
{$sender_name}
</p>
EOL;

		}

		
		// echo "---------";

		// send email from admin
		return $CI->ps_mail->send_from_admin( $to, $subject, $msg );
	}
}


if ( !function_exists( 'send_user_register_email' )) {

  function send_user_register_email( $user_id, $subject = "" )
  {
    // get ci instance
    $CI =& get_instance();
    
    $user_info_obj = $CI->User->get_one($user_id);

    $user_name  = $user_info_obj->user_name;
    $user_email = $user_info_obj->user_email;
    $code = $user_info_obj->code;
    

    $to = $user_email;

	  $sender_name = $CI->Backend_config->get_one('be1')->sender_name;
    $hi = get_msg('hi_label');
    $new_user_acc = get_msg('new_user_acc');
    $verify_code = get_msg('verify_code_label');
    $best_regards = get_msg( 'best_regards_label' );

    $msg = <<<EOL
<p>{$hi} {$user_name},</p>

<p>{$new_user_acc}</p>

<p>
{$verify_code} : {$code}<br/>
</p>


<p>
{$best_regards},<br/>
{$sender_name}
</p>
EOL;
    
    
    

    // send email from admin
    return $CI->ps_mail->send_from_admin( $to, $subject, $msg );
  }
}

if ( !function_exists( 'send_contact_us_emails' )) {

  function send_contact_us_emails( $contact_id, $subject = "" )
  {
    // get ci instance  
    $CI =& get_instance();
    
    $contact_info_obj = $CI->Contact->get_one($contact_id);

    $contact_name  = $contact_info_obj->name;
    $contact_email = $contact_info_obj->email;
    $contact_phone = $contact_info_obj->phone;
    $contact_msg   = $contact_info_obj->message;
    

    $to = $CI->Backend_config->get_one('be1')->receive_email;
    $sender_name = $CI->Backend_config->get_one('be1')->sender_name;
    $hi_admin  = get_msg('hi_admin_label');
    $name = get_msg('name_label');
    $email = get_msg('email_label');
    $phone = get_msg('phone_label');
    $message = get_msg('msg_label');
    $best_regards = get_msg( 'best_regards_label' );

    $msg = <<<EOL
<p>{$hi_admin},</p>

<p>
{$name} : {$contact_name}<br/>
{$email} : {$contact_email}<br/>
{$phone} : {$contact_phone}<br/>
{$message} : {$contact_msg}<br/>
</p>


<p>
{$best_regards},<br/>
{$sender_name}
</p>
EOL;
    
    
    

    // send email from admin
    return $CI->ps_mail->send_from_admin( $to, $subject, $msg );
  }
}

if ( !function_exists( 'send_user_register_email_without_verify' )) {

  function send_user_register_email_without_verify( $user_id, $subject = "" )
  {
     // get ci instance
    $CI =& get_instance();
    
    $user_info_obj = $CI->User->get_one($user_id);

    $user_name  = $user_info_obj->user_name;
    $user_email = $user_info_obj->user_email;
    
    

    $to = $user_email;

	$sender_name = $CI->Backend_config->get_one('be1')->sender_name;
    $hi = get_msg('hi_label');
    $user_auto_approved = get_msg('user_auto_approved');
    
    $best_regards = get_msg( 'best_regards_label' );

    $msg = <<<EOL
<p>{$hi} {$user_name},</p>

<p>{$user_auto_approved}</p>

<p>
{$best_regards},<br/>
{$sender_name}
</p>
EOL;
    
    // send email from admin
    return $CI->ps_mail->send_from_admin( $to, $subject, $msg );
  }
}

if ( !function_exists( 'send_email_to_user' )) {
	function send_email_to_user($user_id, $user_email, $user_name, $user_phone, $shop_id, $resv_id, $resv_date, $resv_time, $note) 
	{
		// get ci instance  
    	$CI =& get_instance();

		$shop = $CI->Shop->get_one($shop_id);
		$resv_info = "Please take note your reservation id is " . $resv_id . " for future inquiry to the shop.";
		
		
		$sender_email = trim($shop->sender_email);
		$sender_name  = $shop->name;
		$to = $user_email;
		$subject = 'Reservation';
		
		$msg = "<p>Hi ".$user_name.",</p>".
				"<p>Your reservation has been sent to the restaurant for the following dish at below : </p><br/><br/>".
				"Date : " . $resv_date . " (DD/MM/YYYY)<br>".
				"Time : " . $resv_time . " (HH-MM)<br>".
				"Additional Note : " . $note . " <br>".
				$resv_info.
				"<p>Best Regards,<br/>".$sender_name."</p>";
					
		// send email from admin
    	return $CI->ps_mail->send_from_admin( $to, $subject, $msg ); 
	}
}

if ( !function_exists( 'send_email_status_update_to_user' )) {	
	function send_email_status_update_to_user($user_id, $user_email, $user_name, $user_phone, $shop_id, $resv_id, $resv_date, $resv_time, $note, $resv_status_title) 
	{
		// get ci instance  
    	$CI =& get_instance();
		
		$shop = $CI->Shop->get_one($shop_id);
		$resv_info = "Please take note your reservation status has been changed to " . $resv_status_title . ". Your reservation detail infromation at below:";
		
		
		$sender_email = trim($shop->sender_email);
		$sender_name  = $shop->name;
		$sender_phone  = $shop->phone;
		$sender_address = $shop->address;
		
		$to = $user_email;
		$subject = 'Reservation';
		
		$msg = "<p>Hi ".$user_name.",</p>".
				$resv_info.
				"<br><br>Date : " . $resv_date . " (DD/MM/YYYY)<br>".
				"Time : " . $resv_time . " (HH-MM)<br>".
				"Additional Note : " . $note . " <br>".
	
				
				"<br>Reserved Person Detail<br>".
				"Name : " . $user_name ."<br>".
				"Email : " . $user_email ."<br>".
				"Phone No : " . $user_phone ."<br><br>".
				
				"<p>Best Regards,<br/>"
				.$sender_name. "<br>".
				"Phone(".$sender_phone.")<br>".
				"Address(".$sender_address.")<br>".
				"</p>";
		
		// send email from admin
    	return $CI->ps_mail->send_from_admin( $to, $subject, $msg );
		
	}
}
	
if ( !function_exists( 'send_email_to_shop' )) {	
	function send_email_to_shop($user_id, $user_email, $user_name, $user_phone, $shop_id, $resv_id, $resv_date, $resv_time, $note) 
	{
		// get ci instance  
    	$CI =& get_instance();
    	
		$shop = $CI->Shop->get_one($shop_id);
		$resv_info = "Please take note your reservation id is " . $resv_id . " for future inquiry to the shop.";
		
		$cust_info  = "Here is customer information.<br/>";
		$cust_info .= "User Name : " . $user_name . "<br>";
		$cust_info .= "Email     : " . $user_email . "<br>";
		$cust_info .= "Phone     : " . $user_phone . "<br>";		
		
		
		$sender_email = $shop->sender_email;
		$sender_name = $CI->Backend_config->get_one('be1')->sender_name;
		$to = $shop->email;
		$subject = 'Reservation';
		$msg = "<p>Hi ".$shop->name.",</p>".
					"<p>You have been received the reservation at below : </p><br/><br/>".
					"Date : " . $resv_date . " (DD/MM/YYYY)<br>".
					"Time : " . $resv_time . " (HH-MM)<br>".
					"Additional Note : " . $note . " <br>".
					$resv_info. "<br/><br/>" .
					$cust_info."<br/><br/>". 
					"<p>Best Regards,<br/>".$sender_name."</p>";
					
		// send email from admin
    	return $CI->ps_mail->send_from_admin( $to, $subject, $msg );
		
	}
}