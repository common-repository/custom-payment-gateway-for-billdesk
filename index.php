<?php
/*
Plugin Name: Custom Payment Gateway for BillDesk
Description: Extends WooCommerce to Process Payments with Billdesk gateway
Author: Manoj Singh
Version: 1.3.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
add_action('plugins_loaded', 'woocommerce_billdesk_payment_init', 0);
function woocommerce_billdesk_payment_init() {
  if ( !class_exists( 'WC_Payment_Gateway' ) ) 
    return;
   /**
   * Localisation
   */
   load_plugin_textdomain('wc-tech-autobilldesk', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
   
   /**
   * Billdesk Payment Gateway class
   */
   class WC_Tech_Billdesk extends WC_Payment_Gateway 
   {
      protected $msg = array();
      
      public function __construct(){
         $this->id               = 'billdesk';
         $this->method_title     = __('Billdesk', 'wc-tech-autobilldesk');
	       $this->title = __('BillDesk','wc-tech-autobilldesk');
         $this->icon             = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/Logo.png';
         $this->has_fields       = true;
         $this->init_form_fields();
         $this->init_settings();
         $this->description      = $this->settings['description'];
         $this->merchantid       = $this->settings['merchantid'];
         $this->checksum         = $this->settings['checksum'];
         $this->security_id      = $this->settings['security_id'];
         $this->liveurl          = $this->settings['live_url'];;
		     $this -> msg['message'] = "";
		     $this -> msg['class'] = "";
		 
		add_action( 'woocommerce_api_wc_tech_billdesk', array( &$this, 'check_billdesk_response' ) );
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
        }
          add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
      }
      
      function init_form_fields()
      {
         $this->form_fields = array(
            'enabled'      => array(
                  'title'        => __('Enable/Disable', 'wc-tech-autobilldesk'),
                  'type'         => 'checkbox',
                  'label'        => __('Enable Billdesk Payment Module.', 'wc-tech-autobilldesk'),
                  'default'      => 'no'),
            'merchantid'        => array(
                  'title'        => __('Merchant ID:', 'wc-tech-autobilldesk'),
                  'type'         => 'text',
                  'description'  => __('This ID provided by Billdesk Server.', 'wc-tech-autobilldesk')),
            'checksum'     => array(
                  'title'        => __('Checksum', 'wc-tech-autobilldesk'),
                  'type'         => 'text',
                  'description'  => __('This is use in API integration, provided by Billdesk server')),
			'security_id'  => array(
                 'title'        => __('Security ID:', 'wc-tech-autobilldesk'),
                 'type'         => 'text',
                 'description'  => __('Security ID provided by Billdesk server', 'wc-tech-autobilldesk')),
			'live_url'=> array(
                  'title'        => __('Live URL:', 'wc-tech-autobilldesk'),
                  'type'         => 'text',
                  'description'  => __('This URL provided by Billdesk Server.', 'wc-tech-autobilldesk')),				 
            'description'  => array(
                  'title'        => __('Description:', 'wc-tech-autobilldesk'),
                  'type'         => 'textarea',
                  'description'  => __('This controls the description which the user sees during checkout.', 'wc-tech-autobilldesk'),
                  'default'      => __('Pay securely by Credit Card through Bildesk Secure Servers.', 'wc-tech-autobilldesk'))
		 );
      }
      
      /**
       * Admin Panel Options
       * 
      **/
      public function admin_options()
      {
         
         echo '<h3>'.__('Billdesk Payment Gateway','wc-tech-autobilldesk').'</h3>';
         
         echo '<p>'.__('Billdesk is most popular payment gateway for online payment processing').'</p>';
         
         echo '<table class="form-table">';
         
         $this->generate_settings_html();
         
         echo '</table>';
      }
      
     /*
      *  Fields for Bildesk
      *
	  */
      function payment_fields()
      {
         if ( $this->description )echo wpautop(wptexturize($this->description));
      }
     
     /*
	  *
      * Receipt Page
      */
     function receipt_page($order){
        echo '<p>'.__('Thank you for your order, please click the button below to pay with BillDesk.', 'wc-tech-autobilldesk').'</p>';
        echo $this->billdesk_payment_form($order);
    }
	
	 
	 /*
	  * BillDesk redirect URL
      * 
      */
     function billdesk_redirect_url($order){
		 
		 $redirect_url = $this->get_return_url($order);
		 $redirect_url .= "&wc-api=WC_Tech_Billdesk";	 
		return $redirect_url;
	}
      
	 /*
	  * BillDesk Payment form
      * 
      */
      public function billdesk_payment_form($order_id)
      {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $txnid = $order_id.'_'.date("ymd");
		$redirect_url = $this->billdesk_redirect_url($order);
        $productinfo = "Order $order_id";		 
 
		$str = "$this->merchantid|$txnid|NA|$order->order_total|NA|NA|NA|INR|NA|R|$this->security_id|NA|NA|F|$productinfo|$order->billing_city|$order->billing_state|NA|$order->billing_email|$order->billing_phone|NA|$redirect_url";
		 
		$checksum = hash_hmac('sha256',$str,$this->checksum, false); 
		$checksum = strtoupper($checksum);
		$str .='|'.$checksum;		
		
		$billDesk_args = array(
          'txnid' => $txnid,          
          'productinfo' => $productinfo,
          'firstname' 	=> $order->billing_first_name,
          'lastname' 	  => $order->billing_last_name,
          'address1' 	  => $order->billing_address_1,
          'address2' 	  => $order->billing_address_2,
          'city' 		    => $order->billing_city,
          'state' 		  => $order->billing_state,
          'country' 	  => $order->billing_country,
          'zipcode'		  => $order->billing_postcode,
          'email' 	    => $order->billing_email,
          'phone'       => $order->billing_phone,
          'surl'        => $redirect_url,
          'furl'        => $redirect_url,
          'curl'        => $redirect_url,
          'msg'         => $str
          );
 
        $billdesk_args_array = array();
        foreach($billDesk_args as $key => $value){
          $billdesk_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }
		return '<form action="'.$this->liveurl.'" method="post" id="billdesk_payment_form">
            ' . implode('', $billdesk_args_array) . '
            <input type="submit" class="button-alt" id="submit_billdesk_payment_form" value="'.__('Pay via BillDesk', 'wc-tech-autobilldesk').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'wc-tech-autobilldesk').'</a>
            <script type="text/javascript">
				jQuery(function(){ 
				jQuery("body").block(
						{
							message: "<img src=\"'.plugins_url().'/custom-payment-gateway-for-billDesk/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'wc-tech-autobilldesk').'",
								overlayCSS:
						{
							background: "#000",
								opacity: 0.4
					},
					css: {
							padding:        20,
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:"32px"
					}
					});
					jQuery("#submit_billdesk_payment_form").click();});</script>
            </form>';
		
         
      }
	   
    /*
	 *
     * Process the payment and return the result
     */
	  function process_payment($order_id){
	    global $woocommerce;
       $order = new WC_Order( $order_id );
        return array(
			'result' => 'success', 
			'redirect'	=> add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, $order->get_checkout_payment_url( true ) ) )
		);
			
	}
	
  /*
   * Bildesk after payment response
   *
   */
	function check_billdesk_response(){
    global $woocommerce;
    $status = '';
		$message = '';
    	if(isset($_REQUEST['msg'])){
		 $_paymentResp = $_REQUEST['msg'];                      
		 $arrResponse = explode('|',$_paymentResp); //PG
		 $merchantId = $arrResponse[0];
		 $_customerId = $arrResponse[1];
		 $txnReferenceNo = $arrResponse[2];
		 $bankReferenceNo = $arrResponse[3];
		 $txnAmount = $arrResponse[4];
		 $bankId = $arrResponse[5];
		 $bankMerchantId = $arrResponse[6];
		 $txnType = $arrResponse[7];
		 $currency = $arrResponse[8];
		 $itemCode = $arrResponse[9];
		 $securityType = $arrResponse[10];
		 $securityId = $arrResponse[11];
		 $securityPassword = $arrResponse[12];
		 $txnDate = $arrResponse[13]; //dd-mm-yyyy
		 $authStatus = $arrResponse[14];
		 $settlementType = $arrResponse[15];
		 $productinfo = $arrResponse[16];
		 $additionalInfo2 = $arrResponse[17];
		 $additionalInfo3 = $arrResponse[18];
		 $additionalInfo4 = $arrResponse[19];
		 $additionalInfo5 = $arrResponse[20];
		 $additionalInfo6 = $arrResponse[21];
		 $additionalInfo7 = $arrResponse[22];
		 $errorStatus = $arrResponse[23]; 
		 $hash = $arrResponse[24]; 
	 
		if($authStatus == "0300"){
			$status = 'Success';
			$message = "Successful Transaction";
		 }else if($authStatus == "0399"){
			$status = 'Cancel Transaction';
			$message = "Invalid Authentication at Bank";
		 }else if($authStatus == "NA"){
			$status = 'Cancel Transaction';
			$message = "Invalid In	put in the Request Message";
		 }else if($authStatus == "0002"){
			$status = 'pending';
			$message = "BillDesk is waiting for Response from Bank";
		 }else if($authStatus == "0001"){
			$status = 'Cancel Transaction';
			$message = "Error at BillDesk";
		 }else{
			$status = 'Failed Transaction';
			$message = "Security Error. Illegal access detected";			 
		 }
		 
		/* Parse authStatus end*/
        $order_id = explode('_', $_customerId)[0]; //237_17011238
         if($order_id!=''){
                try{
                    $order = new WC_Order($order_id);
                    $transauthorised = false;
                    if($order -> status !=='completed'){
                         
							$status = strtolower($status);
                            if($status=="success"){
                                $transauthorised = true;
                                $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                $this -> msg['class'] = 'success';
                                if($order -> status == 'processing'){
 
                                }else{
                                    $order -> payment_complete();
                                    $order -> add_order_note('billdesk payment successful<br/>Unnique Id from billdesk: '.$txnReferenceNo);
                                    $order -> add_order_note($this->msg['message']);
                                    $woocommerce -> cart -> empty_cart();
                                }
                            }else if($status=="pending"){
                                $this -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
                                $this -> msg['class'] = 'error';
                                $order -> add_order_note('billdesk payment status is pending ('.$message.')<br/>Unnique Id from billdesk: '.$txnReferenceNo);
                                $order -> add_order_note($this->msg['message']);
                                $order -> update_status('on-hold');
                                $woocommerce -> cart -> empty_cart();
                            }
                            else{
                                $this -> msg['class'] = 'error';
                                $this -> msg['message'] = "The transaction has been declined due to ".$message;
                                $order -> add_order_note('Error: '.$errorStatus);
                                
                            }
                        
                        if($transauthorised==false){
                            $order -> update_status('failed');
                            $order -> add_order_note($this->msg['message']);
                        }
                        
						
                    }}catch(Exception $e){
						$this -> msg['class'] = 'error';
						$this -> msg['message'] = "An unexpected error occurred ! Transaction failed.";
					}

             }
			
			/*Register message to the woocommerce*/
			wc_add_notice( __( $this -> msg['message'], 'woocommerce' ), $this -> msg['class'] );
        }
		
		/*Redirect to Thank you page*/
		$redirect_url = $this->get_return_url($order);
		if ( wp_redirect( $redirect_url ) ) {
			exit;
		}
    }
 } 
} 

/**
* Add Billdesk Payment Gateway to WooCommerce
**/
function woocommerce_add_tech_billdesk_gateway($methods) 
{
  $methods[] = 'WC_Tech_Billdesk';
  return $methods;
}
add_filter('woocommerce_payment_gateways', 'woocommerce_add_tech_billdesk_gateway' );