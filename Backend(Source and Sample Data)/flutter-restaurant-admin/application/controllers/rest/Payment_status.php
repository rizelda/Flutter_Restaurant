<?php
require_once( APPPATH .'libraries/REST_Controller.php' );

/**
 * REST API for News
 */
class Payment_status extends API_Controller
{

	/**
	 * Constructs Parent Constructor
	 */
	function __construct()
	{
		parent::__construct( 'Paymentstatus' );

		// set the validation rules for create and update
		$this->validation_rules();
	}

	/**
	 * Determines if valid input.
	 */
	function validation_rules()
	{
		// validation rules for create
		$this->create_validation_rules = array(
			array(
	        	'field' => 'transactions_header_id',
	        	'rules' => 'required'
	        ),
	        array(
	        	'field' => 'payment_status_id',
	        	'rules' => 'required'
	        ),
        );
	}


	/**
	 * Convert Object
	 */
	function convert_object( &$obj )
	{
		// call parent convert object
		parent::convert_object( $obj );

	}

	/**
	 * Adds a post.
	 */
	function update_payment_status_post()
	{

		// set the add flag for custom response
		$this->is_add = true;

		if ( !$this->is_valid( $this->create_validation_rules )) {
		// if there is an error in validation,
			
			return;
		}

		// get the post data
		$id = $this->post('transactions_header_id');
		$data['payment_status_id'] = $this->post('payment_status_id');
		if ( !$this->Transactionheader->save( $data, $id )) {
			$this->error_response( get_msg( 'err_model' ));
		}

		$payment_id = $data['payment_status_id'];
		$title = $this->Transactionstatus->get_one($payment_id)->title;
		$message = "Your order payment status is " . $title;

		$status = $this->send_android_fcm( $device_tokens, array( "message" => $message ));
		if ( !$status ) $error_msg .= "Fail to push all android devices <br/>";

		// response the inserted object	
		$obj = $this->Transactionheader->get_one( $id );

		$this->custom_response( $obj );
	}

	/**
	* Sending Message From FCM For Android
	*/
	function send_android_fcm( $registatoin_ids, $message) 
    {
    	//Google cloud messaging GCM-API url
    	$url = 'https://fcm.googleapis.com/fcm/send';
    	$fields = array(
    	    'registration_ids' => $registatoin_ids,
    	    'data' => $message,
    	);
    	// Update your Google Cloud Messaging API Key
    	//define("GOOGLE_API_KEY", "AIzaSyCCwa8O4IeMG-r_M9EJI_ZqyybIawbufgg");
    	define("GOOGLE_API_KEY", $this->Backend_config->get_one('be1')->fcm_api_key);  	
    		
    	$headers = array(
    	    'Authorization: key=' . GOOGLE_API_KEY,
    	    'Content-Type: application/json'
    	);
    	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_URL, $url);
    	curl_setopt($ch, CURLOPT_POST, true);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);	
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    	$result = curl_exec($ch);				
    	if ($result === FALSE) {
    	    die('Curl failed: ' . curl_error($ch));
    	}
    	curl_close($ch);

    	return $result;
    }


}