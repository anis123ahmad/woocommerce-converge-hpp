<?php
/**
* Plugin Name: WooCommerce Converge HPP Payment
* Plugin URI: https://impex4u.com
* Description: Converge HPP Credit/Debit Card Payment gateway for woocommerce. This plugin supports woocommerce version 3.0.0 or greater version.
* Author: Anis
* Author URI: https://impex4u.com
* Version: 1.0.2
* Text Domain: woocommerce-converge-hpp
*/
 
defined( 'ABSPATH' ) or exit;
//ob_start();

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways
 */
function wc_converge_hpp_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_ConvergeHPP';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_converge_hpp_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_converge_hpp_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=convergehpp_gateway' ) . '">' . __( 'Configure', 'woocommerce-converge-hpp' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_converge_hpp_gateway_plugin_links' );


/**
 * Converge Hpp Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_ConvergeHPP
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_converge_hpp_gateway_init', 0 );

function wc_converge_hpp_gateway_init() {

	
	class WC_Gateway_ConvergeHPP extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'convergehpp'; //key is very important
			//$this->icon               = apply_filters('woocommerce_converge_hpp_icon', '');
			$this->icon               = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/img/cards.png';
			
			$this->has_fields         = false;
			$this->method_title       = __( 'Converge HPP Payment Gateway', 'woocommerce-converge-hpp' );
			$this->method_description = __( 'Allows online payments using Converge HPP method. Very handy if you use your Payment By HPP gateway for another payment method, and can help with testing. Orders are marked as "completed" when received.', 'woocommerce-converge-hpp' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
		
			
			$this->merchant_id = $this->settings['merchant_id'];
            $this->user_id = $this->settings['user_id'];
            $this->pin = $this->settings['pin'];
			$this->testmode = $this->settings['convergehpp_testmode'];	
			
			
			//$this->description      = 'Pay securely by Credit or Debit Card through Converge HPP Secure Servers.';
			
			$this->msg['message']   = "";
         	$this->msg['class']     = "";
         	$this->newrelay         = "";
			$this->instructions		= "Pay securely by Credit or Debit Card through Elavon Converge HPP Secure Server.";
        
		  	//Actions
			add_action( 'woocommerce_api_convergehpp', array( $this, 'convergehpp' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_receipt_'. $this->id, array(&$this, 'receipt_page') );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			
			
		} //end of function
	
		
		function payment_fields(){
         	if ( $this->description ) 
            echo wpautop(wptexturize($this->description));
      	}
      

		function convergehpp(){
        
         	global $woocommerce;
         	$temp_order = new WC_Order();
			
        //$return_url = $this->return_url."/wc-api/convergehpp/";
				
			
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
			$order = new WC_Order( $_POST['ssl_invoice_number'] );
            
			
			if( $_POST['ssl_result_message'] == 'APPROVAL' && $_POST['ssl_result'] == 0 )
			{


				$order->add_order_note('Converge HPP Card payment successful.<br/>
				Order ID/Invoice Number: '.$_POST['ssl_invoice_number'] . '<br />Converge Txn Id: '.$_POST['ssl_txn_id'] . 
				'<br />Card Type: ' . $_POST['ssl_card_short_description'] );
                
				$order->update_status('completed');
				 
				update_post_meta( $order->get_id(), '_convergehpp_txn_id',  $_POST['ssl_txn_id'] );
				update_post_meta( $order->get_id(), '_convergehpp_dir_server_tran_id',  $_POST['ssl_dir_server_tran_id'] );
				update_post_meta( $order->get_id(), '_convergehpp_3ds_server_trans_id',  $_POST['ssl_3ds_server_trans_id'] );
				update_post_meta( $order->get_id(), '_convergehpp_3ds_trans_status',  $_POST['ssl_3ds_trans_status'] );
                update_post_meta( $order->get_id(), '_convergehpp_card_description',  $_POST['ssl_card_short_description'] );
				update_post_meta( $order->get_id(), '_convergehpp_result_message',  $_POST['ssl_result_message'] );

				$transaction_id = $_POST['ssl_txn_id'];
				$order->set_transaction_id( $transaction_id );
				$order->save();
	
				$redirect_url = $order->get_checkout_order_received_url();
            	$this->web_redirect( $redirect_url ); 
				exit;
			}
			else
			{
				
				
				update_post_meta( $order->get_id(), '_convergehpp_txn_id',  $_POST['ssl_txn_id'] );
				update_post_meta( $order->get_id(), '_convergehpp_dir_server_tran_id',  $_POST['ssl_dir_server_tran_id'] );
				update_post_meta( $order->get_id(), '_convergehpp_3ds_server_trans_id',  $_POST['ssl_3ds_server_trans_id'] );
				update_post_meta( $order->get_id(), '_convergehpp_3ds_trans_status',  $_POST['ssl_3ds_trans_status'] );
                update_post_meta( $order->get_id(), '_convergehpp_card_description',  $_POST['ssl_card_short_description'] );
				update_post_meta( $order->get_id(), '_convergehpp_result_message',  $_POST['ssl_result_message'] );
    
				$order->update_status('failed');
                
				$order->add_order_note('Converge HPP Card payment failed.<br/>
				Order ID/Invoice Number: '.$_POST['ssl_invoice_number'] . '<br />Converge Txn Id: '.$_POST['ssl_txn_id'] . 
				'<br />Card Type: ' . $_POST['ssl_card_short_description'] );
                
				$msg = $_POST['ssl_result_message'];
				$redirect_url = $order->get_checkout_order_received_url();
            	$this->web_redirect( $redirect_url . "?msg=$msg" );
            	exit;
		
			}//end of If Else block

		
		}//end of Tracking Post Request If block
 	
	exit;
	
    } //end of function
    		
	/**
	* Initialize Gateway Settings Form Fields
	*/
		
	public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_convergecc_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce-converge-hpp' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Credit/Debit Payment via Elavon Converge', 'woocommerce-converge-hpp' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'woocommerce-converge-hpp' ),
					'type'        => 'text',
			'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'woocommerce-converge-hpp' ),
					'default'     => __( 'Payment using Credit/Debit Card', 'woocommerce-converge-hpp' ),
					'desc_tip'    => true,
					'css'		=> 'width:500px;',
				),
				
				'description' => array(
					'title'       => __( 'Description', 'woocommerce-converge-hpp' ),
					'type'        => 'textarea',
					'description' => __( 'Pay via Elavon Converge - you can pay using your credit/debit card.', 'woocommerce-converge-hpp' ),
					'default'     => __( 'Pay via Elavon Converge - you can pay using your credit/debit card.', 'woocommerce-converge-hpp' ),
					'desc_tip'    => true,
				),
				
				'convergehpp_testmode' => array(
				
				'title'       => __( 'Elavon Converge HPP Sandbox Mode', 'woocommerce-converge-hpp' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in sandbox mode.', 'woocommerce-converge-hpp' ),
				'default'     => 'yes',
				'desc_tip'    => true,
				
				),
			
				'merchant_id' => array(
					'title'       => __( 'Merchant ID', 'woocommerce-converge-hpp' ),
					'type'        => 'text',
				'description' => __( 'This is the Merchant ID, received from Elavon Converge Merchant Account.', 'woocommerce-converge-hpp' ),
					'default'     => '',
					'desc_tip'    => true,
					'css'		=> 'width:200px;',
				),
				
				'user_id' => array(
					'title'       => __( 'User ID', 'woocommerce-converge-hpp' ),
					'type'        => 'text',
				'description' => __( 'This is the Merchant User ID, received from Elavon Converge Merchant Account.', 'woocommerce-converge-hpp' ),
					'default'     => '',
					'desc_tip'    => true,
					'css'		=> 'width:200px;',
				),
				
			'pin' => array(
				'title'       => __( 'PIN', 'woocommerce-converge-hpp' ),
				'type'        => 'password',
			'description' => __( 'This is the Merchant PIN, received from Elavon Converge Merchant Account.', 'woocommerce-converge-hpp' ),
				'default'     => '',
				'css'		=> 'width:600px;',
				'desc_tip'    => true,
			),
			
		
			) );
		
		
	} //end of function
	
	
	/**
	* Output for the order received page.
	*/
		
	public function thankyou_page() {
		if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
	}//end of function
	    
	
	/**
	* Add content to the WC emails.
	*
	* @access public
	* @param WC_Order $order
	* @param bool $sent_to_admin
	* @param bool $plain_text
	*/
	
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
	} //end of function
	
	
	public function web_redirect($url){
      
      echo "<html>
		 		<head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head>
				<body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>
		";
      
    }//end of function
      
	public function receipt_page($order)
    {
         
		 global $woocommerce;
	     $order         = new WC_Order($order);
		 
		 $current_version = get_option( 'woocommerce_version', null );
	
		 
		 if (version_compare( $current_version, '3.0.0', '<' )) {
             $order->reduce_order_stock();
          } else {
             wc_reduce_stock_levels( $order->get_id() );
          }
		
		//Remove cart
		 WC()->cart->empty_cart();
		 
		 $order->update_status('pending');
		 
		 $order->add_order_note('Converge HPP Payment Tried<br/>By : '.$order->get_billing_first_name() );
		 
		 echo '<p>'.__('Thank you for your order, please click the button below to pay with Converge HPP Method. Do not close this window (or click the back button). You will be redirected back to the website once your payment has been received.', 'woocommerce-converge-hpp').'</p>';
         
		 echo $this->generate_convergehpp_form($order);
    
	} //end of function
      
	
	public function generate_convergehpp_form($order_id)
    {
         
		 global $woocommerce;
	     
		 $order         = new WC_Order($order_id);
         
		 //$return_url = $this->return_url."/wc-api/convergehpp/";
         
		if( $this->testmode == 'yes' ) {
			
			// Provide Converge Credentials
			$merchantID = $this->merchant_id; 										//Converge 6-Digit Account ID *Not the 10-Digit Elavon Merchant ID*
			$merchantUserID = $this->user_id; 										//Converge User ID *MUST FLAG AS HOSTED API USER IN CONVERGE UI*
			$merchantPIN = $this->pin; 												//Converge PIN (64 CHAR A/N)

			$url = "https://api.demo.convergepay.com/hosted-payments/transaction_token"; // URL to Converge demo session token server
			$hppurl = "https://api.demo.convergepay.com/hosted-payments"; 			// URL to the demo Hosted Payments Page
  		
		 }else{
			
			// Provide Converge Credentials
			$merchantID = $this->merchant_id; 										//Converge 6-Digit Account ID *Not the 10-Digit Elavon Merchant ID*
			$merchantUserID = $this->user_id; 										//Converge User ID *MUST FLAG AS HOSTED API USER IN CONVERGE UI*
			$merchantPIN = $this->pin; 												//Converge PIN (64 CHAR A/N)

			$url = "https://api.convergepay.com/hosted-payments/transaction_token"; // URL to Converge production session token server
            $hppurl = "https://api.convergepay.com/hosted-payments"; // URL to the production Hosted Payments Page
        
		 }
         
  		/*Payment Field Variables*/
  		// In this section, we set variables to be captured by the PHP file and passed to Converge in the curl request.

  		$amount= $order->get_total(); 											//Hard-coded transaction amount for testing.
  		$merchant_reference = $order->get_id();

  		$firstname  = $order->get_billing_first_name();   						//Capture ssl_first_name as POST data
  		$lastname  = $order->get_billing_last_name();   						//Capture ssl_last_name as POST data


  		$merchanttxnid = $merchant_reference; 									//Capture ssl_merchant_txn_id as POST data
  		$invoicenumber = $merchant_reference; 									//Capture ssl_invoice_number as POST data
        
		$address1 = $order->get_billing_address_1();
		$address2 = $order->get_billing_address_2();
		$city = $order->get_billing_city();
		$country = $order->get_billing_country();
		$state = $order->get_billing_state();
		$zip = $order->get_billing_postcode();
		$phone = $order->get_billing_phone();
		$email = $order->get_billing_email();
		
		
	  	if( count($order->get_items()) == 1 )
		{
				  
		  	foreach ( $order->get_items() as $item ) 
			{         
                $goodsInfo = $item['name'] . " - ". get_bloginfo('name');
			}
				
		}
		else
		{
				$goodsInfo = count($order->get_items())." Items - ".get_bloginfo('name'); 
		}
				
		
		/*
		$ch = curl_init();    												// initialize curl handle
	  	curl_setopt($ch, CURLOPT_URL,$url); 								// set POST target URL
	  	curl_setopt($ch,CURLOPT_POST, true); 								// set POST method
	  	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	  	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	  	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	  	//Build the request for the session id. Make sure all payment field variables created above get included in the CURLOPT_POSTFIELDS section below.
	
	  curl_setopt($ch,CURLOPT_POSTFIELDS,
	 
	  "ssl_merchant_id=$merchantID".
	  "&ssl_user_id=$merchantUserID".
	  "&ssl_pin=$merchantPIN".
	  "&ssl_transaction_type=ccsale".
	  "&ssl_amount=$amount".
	  "&ssl_first_name=$firstname".
	  "&ssl_last_name=$lastname".
	  "&ssl_merchant_txn_id=$merchanttxnid".
	  "&ssl_avs_address=$address1".
	  "&ssl_address2=$address2".
	  "&ssl_city=$city".
	  "&ssl_state=$state".
	  "&ssl_avs_zip=$zip".
	  "&ssl_country=$country".
	  "&ssl_phone=$phone".
	  "&ssl_email=$email".
	  "&ssl_description=$goodsInfo".
	  "&ssl_invoice_number=$merchanttxnid"
	  
	  );
	
	
	  	$result = curl_exec($ch); // run the curl to post to Converge

	  	if ($result === false) {
		   echo 'Curl error message: '.curl_error($ch).'<br />';
		   echo 'Curl error code: '.curl_errno($ch);
	  	}else{
		 	$httpstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
			if ($httpstatus == 200) {            
			
				$sessiontoken = urlencode($result);
  			
				//header("Location: https://api.demo.convergepay.com/hosted-payments?ssl_txn_auth_token=$sessiontoken");  //Sandbox Redirect
  				//header("Location: https://api.convergepay.com/hosted-payments?ssl_txn_auth_token=$sessiontoken"); 	//Prod Redirect
   			
			}else{
        	
				echo 'Response: '.$result.'<br>';
        		echo 'HTTP status: '.$httpstatus;
        	
			}//Inner If Else block
    	
		
		}//Outer If Else block

    	curl_close($ch); //Close cURL
        
		*/
		

	$data =

	  "ssl_merchant_id=$merchantID".
	  "&ssl_user_id=$merchantUserID".
	  "&ssl_pin=$merchantPIN".
	  "&ssl_transaction_type=ccsale".
	  "&ssl_amount=$amount".
	  "&ssl_first_name=$firstname".
	  "&ssl_last_name=$lastname".
	  "&ssl_merchant_txn_id=$merchanttxnid".
	  "&ssl_avs_address=$address1".
	  "&ssl_address2=$address2".
	  "&ssl_city=$city".
	  "&ssl_state=$state".
	  "&ssl_avs_zip=$zip".
	  "&ssl_country=$country".
	  "&ssl_phone=$phone".
	  "&ssl_email=$email".
	  "&ssl_description=$goodsInfo".
	  "&ssl_invoice_number=$merchanttxnid"

	  ;

	$request = wp_remote_post( $url, array( 'body' => $data ) );

	if ( !is_wp_error($request) ){
		
		$response = wp_remote_retrieve_body($request);
	}

	$sessiontoken = urlencode($response);
  			

		 if( $this->testmode == 'yes' ) {
			$processURI = "https://api.demo.convergepay.com/hosted-payments?ssl_txn_auth_token=$sessiontoken";		//Sandbox
		 }else{
			$processURI = "https://api.convergepay.com/hosted-payments?ssl_txn_auth_token=$sessiontoken";			//Prodcution
		 }//end of If Else block
         
		 
		 $html_form = '<form action="'.$processURI.'" method="post" id="hpp_payment_form">' 
                
         . '<input type="submit" class="button" id="submit_hpp_payment_form" value="'.__('Pay via Converge HPP Method', 'woocommerce-converge-hpp').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel Order &amp; Restore Cart', 'woocommerce-converge-hpp').'</a>'
         . '<script type="text/javascript">
                  jQuery(function(){
                     jQuery("body").block({
        message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/wpspin-2x.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Elavon Converge HPP Page to make a Payment.','woocommerce-converge-hpp').'",
                     
					 overlayCSS:
                        {
                           background: "#ccc",
                           opacity: 0.6,
                           "z-index": "99999999999999999999999999999999"
                        },
                     css: {
                           padding:          20,
                           textAlign:        "center",
                           color:            "#555",
                           border:           "3px solid #aaa",
                           backgroundColor:  "#fff",
                           cursor:           "wait",
                           lineHeight:       "32px",
                           "z-index": "999999999999999999999999999999999"
                     	}
                     
					 });
                  jQuery("#submit_hpp_payment_form").click();
               });
           </script>
               </form>';

         return $html_form;
    }

	public function process_payment($order_id)
    {
         
		 $order = new WC_Order($order_id);
         
		 return array(
         				'result' 	=> 'success',
         				'redirect'	=> $order->get_checkout_payment_url( true )
         	);
    
	
	} //end of function
      
	
  } // end \WC_Gateway_ConvergeHPP class


} //end of outer function