<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reservations Controller
 */
class Reservations extends BE_Controller {

	/**
	 * Construt required variables
	 */
	function __construct() {

		parent::__construct( MODULE_CONTROL, 'RESERVATIONS' );
		$this->load->library('email',array(
			'mailtype'  => 'html',
			'newline'   => '\r\n'
		));
		///start allow module check
		$conds_mod['module_name'] = $this->router->fetch_class();
		$module_id = $this->Module->get_one_by($conds_mod)->module_id;
		
		$logged_in_user = $this->ps_auth->get_user_info();

		$user_id = $logged_in_user->user_id;
		if(empty($this->User->has_permission( $module_id,$user_id )) && $logged_in_user->user_is_sys_admin!=1){
			return redirect( site_url('/admin/') );
		}
		///end check
	}

	/**
	 * List down the registered users
	 */
	function index() {
		
		// no delete flag
		$shop_id = "shop0b69bc5dbd68bbd57ea13dfc5488e20a";
		$conds['shop_id'] = $shop_id;
		// get rows count
		$this->data['rows_count'] = $this->Reservation->count_all_by( $conds );

		// get reservations
		$this->data['reservations'] = $this->Reservation->get_all_by( $conds , $this->pag['per_page'], $this->uri->segment( 4 ) );

		$this->load_template('reservations/calendarView', $this->data );
	}

	/**
 	* Update the existing one
	*/
	function edit( $id ) 
	{

		// breadcrumb urls
		$this->data['action_title'] = get_msg( 'resv_edit' );

		// load user
		$this->data['reservation'] = $this->Reservation->get_one( $id );

		// call the parent edit logic
		parent::edit( $id );

	}
	
	function save( $id = false ) {

		if ($this->input->server('REQUEST_METHOD')=='POST') {
			
			if(htmlentities( $this->input->post('resv_status_hidden')) != htmlentities( $this->input->post('resv_status'))) { 
			
				$data = array(
					'status_id' => htmlentities( $this->input->post('resv_status'))
				);

				//save category
				if ( ! $this->Reservation->save( $data, $id )) {
				// if there is an error in inserting user data,	

					// rollback the transaction
					$this->db->trans_rollback();

					// set error message
					$this->data['error'] = get_msg( 'err_model' );
					
					return;
				}

				/** 
				 * Check Transactions 
				 */

				// commit the transaction
				if ( ! $this->check_trans()) {
		        	
					// set flash error message
					$this->set_flash_msg( 'error', get_msg( 'err_model' ));
				} else {

					if ( $id ) {
					// if user id is not false, show success_add message
						
						$this->set_flash_msg( 'success', get_msg( 'success_res_edit' ));
					} else {
					// if user id is false, show success_edit message

						$this->set_flash_msg( 'success', get_msg( 'success_res_add' ));
					}
				}

				///send noti @ MN
				//get device token from user
				$user_id = $this->Reservation->get_one( $id )->user_id;
				$device_token = $this->User->get_one($user_id)->device_token;

				$device_tokens[] = $device_token;
				$title = $this->Reservation_status->get_one(htmlentities( $this->input->post('resv_status')))->title;
				$message = "Reservation status has been changed to " . $title;

				$status = $this->send_notification_flutter( $device_tokens, $message, $id );

				if ( !$status ) $error_msg .= "Fail to push notification <br/>";
				///end noti

				$this->load->library( 'PS_Mail' );
			
				send_email_status_update_to_user(
				htmlentities( $this->input->post('resv_user_id_hidden')), 
				htmlentities( $this->input->post('resv_user_email_hidden')),
				htmlentities( $this->input->post('resv_user_name_hidden')),
				htmlentities( $this->input->post('resv_user_phone_hidden')), 
				htmlentities( $this->input->post('resv_shop_id_hidden')), 
				htmlentities( $this->input->post('resv_id_hidden')), 
				htmlentities( $this->input->post('resv_date_hidden')),
				htmlentities( $this->input->post('resv_time_hidden')), 
				htmlentities( $this->input->post('resv_note_hidden')), 
				$this->Reservation_status->get_one(htmlentities( $this->input->post('resv_status')))->title);
					
				redirect(site_url('/admin/reservations'));
				
			
			} else {
				redirect(site_url('/admin/reservations'));
			}
		}

	}

	 /**
	* Sending Message For Flutter App
	*/
    function send_notification_flutter( $registatoin_ids, $message, $reservation_id) 
    {

    	// get ci instance
		$CI =& get_instance();

		//Google cloud messaging GCM-API url
    	$url = 'https://fcm.googleapis.com/fcm/send';
		
    	$message = $message;
    	$trans_header_id = $trans_header_id;

    

    	// - Testing Start
		$noti_arr = array(
    		'title' => get_msg('site_name'),
    		'body' => $message,
    		'message' => $message,
    		'flag' => 'reservation',
	    	'sound'=> 'default'
    	);
    	// - Testing End


    	$fields = array(
    		'notification' => $noti_arr,
    	    'registration_ids' => $registatoin_ids,
    	    'data' => array(
    	    	'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
    	    	'message' => $message,
    	    	'flag' => 'reservation',
    	    	'reservation_id' => $reservation_id
    	    )

    	);

    	

    	// Update your Google Cloud Messaging API Key
    	//define("GOOGLE_API_KEY", "AIzaSyAzKBPuzGuR0nlvY0AxPrXsEMBuRUxO4WE");
    	$fcm_api_key = $CI->Backend_config->get_one('be1')->fcm_api_key;
    	define("GOOGLE_API_KEY", $fcm_api_key);
    	//define("GOOGLE_API_KEY", $this->config->item( 'fcm_api_key' ));  	
    	
    	//print_r(GOOGLE_API_KEY); die;
    	//print_r($fields); die;
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

	/**
	 * Determines if valid input.
	 *
	 * @return     boolean  True if valid input, False otherwise.
	 */
	function is_valid_input( $id = 0 ) 
	{
		
		return true;
	}
	
	
}
?>