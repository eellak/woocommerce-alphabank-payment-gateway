<?php
/*
  Plugin Name: Alpha Bank Greece WooCommerce Payment Gateway
  Plugin URI: http://emspace.gr
  Description: Alpha Bank Greece Payment Gateway allows you to accept payment through various channels such as Maestro, Mastercard and Visa cards On your Woocommerce Powered Site.
  Version: 1.0.0
  Author: emspace.gr
  Author URI: http://emspace.gr
  License: GPL-3.0+
  License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 */

if (!defined('ABSPATH'))
    exit;

add_action('plugins_loaded', 'woocommerce_alphabank_init', 0);
//require_once( 'simplexml.php' );
function woocommerce_alphabank_init() {

    if (!class_exists('WC_Payment_Gateway'))
        return;

    load_plugin_textdomain('woocommerce-alphabank-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    /**
     * Gateway class
     */
    class WC_alphabank_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            global $woocommerce;

            $this->id = 'alphabank_gateway';
            $this->icon = apply_filters('alphabank_icon', plugins_url('assets/alphabank_cards.jpg', __FILE__));
            $this->has_fields = false;
            $this->notify_url = WC()->api_request_url('WC_alphabank_Gateway');
			      $this->method_description = __('Alpha Bank Greece Payment Gateway allows you to accept payment through various channels such as Maestro, Mastercard  and Visa cards On your Woocommerce Powered Site.', 'woocommerce-alphabank-payment-gateway');
//            $this->redirect_page_id = $this->get_option('redirect_page_id');
//            $this->redirect_fail_page_id = $this->get_option('redirect_fail_page_id');
            $this->redirect_page_id = get_page_link($this->get_option('redirect_page_id'));
            $this->redirect_fail_page_id = get_page_link($this->get_option('redirect_fail_page_id'));
			      $this->method_title = 'Alpha Bank Gateway';

			// Load the form fields.
			     $this->init_form_fields();

            //dhmioyrgia vashs

            global $wpdb;

            if ($wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "alphabank_transactions'") === $wpdb->prefix . 'alphabank_transactions') {
                // The database table exist
            } else {
                // Table does not exist
                $query = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'alphabank_transactions (id int(11) unsigned NOT NULL AUTO_INCREMENT,merchantreference varchar(30) not null, reference varchar(100) not null, orderid varchar(100) not null , timestamp datetime default null, PRIMARY KEY (id))';
                $wpdb->query($query);
            }


            // Load the settings.
            $this->init_settings();


            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
			$this->alphabank_Merchant_ID = $this->get_option('alphabank_Merchant_ID');
            $this->alphabank_Secret_key = $this->get_option('alphabank_Secret_key');
            $this->mode = $this->get_option('mode');
            $this->alphabank_installments= $this->get_option('alphabank_installments');
            //Actions
            add_action('woocommerce_receipt_alphabank_gateway', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_alphabank_gateway', array($this, 'check_alphabank_response'));
        }

        /**
         * Admin Panel Options
         * */
        public function admin_options() {
            echo '<h3>' . __('Alpha Bank Gateway', 'woocommerce-alphabank-payment-gateway') . '</h3>';
            echo '<p>' . __('Alpha Bank Gateway allows you to accept payment through various channels such as Maestro, Mastercard  and Visa cards.', 'woocommerce-alphabank-payment-gateway') . '</p>';


            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * Initialise Gateway Settings Form Fields
         * */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Alpha Bank Gateway', 'woocommerce-alphabank-payment-gateway'),
                    'description' => __('Enable or disable the gateway.', 'woocommerce-alphabank-payment-gateway'),
                    'desc_tip' => true,
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-alphabank-payment-gateway'),
                    'desc_tip' => false,
                    'default' => __('Alpha Bank Greece Gateway', 'woocommerce-alphabank-payment-gateway')
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-alphabank-payment-gateway'),
                    'default' => __('Pay Via Alpha Bank Greece: Accepts  Mastercard, Visa cards and etc.', 'woocommerce-alphabank-payment-gateway')
                ),'alphabank_Merchant_ID' => array(
                    'title' => __('Alpha Bank  Merchant ID', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter your Alpha Bank Merchant ID', 'woocommerce-alphabank-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),'alphabank_Secret_key' => array(
                    'title' => __('Alpha Bank Secret key', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter your Secret key', 'woocommerce-alphabank-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ), 'mode' => array(
                    'title' => __('Mode', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Test Mode', 'woocommerce-alphabank-payment-gateway'),
                    'default' => 'yes',
                    'description' => __('This controls  the payment mode as TEST or LIVE.', 'woocommerce-alphabank-payment-gateway')
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'select',
                    'options' => $this->alphabank_get_pages('Select Page'),
                    'description' => __('URL of success page', 'woocommerce-alphabank-payment-gateway')
                ),
                'redirect_fail_page_id' => array(
                    'title' => __('Return Fail Page', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'select',
                    'options' => $this->alphabank_get_pages('Select Page'),
                    'description' => __('URL of fail page', 'woocommerce-alphabank-payment-gateway')
                ),
                'alphabank_installments' => array(
                    'title' => __('Max Installments', 'woocommerce-alphabank-payment-gateway'),
                    'type' => 'select',
                    'options' => $this->alphabank_get_installments('Select Installments'),
                    'description' => __('1 to 24 Installments,1 for one time payment ', 'woocommerce-alphabank-payment-gateway')
                )
            );
        }

        function alphabank_get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
			$page_list[-1] = __('Thank you page', 'woocommerce-alphabank-payment-gateway');
            return $page_list;
        }

        function alphabank_get_installments($title = false, $indent = true) {


            for($i = 1; $i<=24;$i++) {
                $installment_list[$i] = $i;
            }
            return $installment_list;
        }


        function generate_alphabank_form($order_id) {

            global $wpdb;
            $order = new WC_Order($order_id);
			$merchantreference = substr(sha1(rand()), 0, 30);
			
			if ($this->mode == "yes") 
			{//test mode
			     $post_url = 'https://alpha.test.modirum.com/vpos/shophandlermpi';
			}
            else
            { //live mode
			     $post_url ='https://www.alphaecommerce.gr/vpos/shophandlermpi';			 		 
			 }
			
            //Alpha.rand(0,99).date("YmdHms")
		$form_data = "";
		$form_data_array = array();
		
		$form_mid = $this->alphabank_Merchant_ID;					$form_data_array[1] = $form_mid;					//Req
		$form_lang = "el";								            $form_data_array[2] = $form_lang;					//Opt
		$form_device_cate = ""; 					$form_data_array[3] = $form_device_cate;			//Opt
		$form_order_id = $order_id;			$form_data_array[4] = $form_order_id;				//Req
		$form_order_desc = "";						                $form_data_array[5] = $form_order_desc;				//Opt
        $form_order_amount = $order->get_total();					$form_data_array[6] = $form_order_amount;			//Req
//        $form_order_amount = 0.10;					$form_data_array[6] = $form_order_amount;			//Req
        $form_currency = "EUR";						                $form_data_array[7] = $form_currency;				//Req
		$form_email = $order->billing_email;							$form_data_array[8] = $form_email;					//Req
		$form_phone = "";							$form_data_array[9] = $form_phone;					//Opt
		$form_bill_country = "";					$form_data_array[10] = $form_bill_country;			//Opt
		$form_bill_state = "";						$form_data_array[11] = $form_bill_state;			//Opt
		$form_bill_zip = "";						$form_data_array[12] = $form_bill_zip;				//Opt
		$form_bill_city = "";						$form_data_array[13] = $form_bill_city;				//Opt
		$form_bill_addr = "";					    $form_data_array[14] = $form_bill_addr;				//Opt
		$form_weight = "";							$form_data_array[15] = $form_weight;				//Opt
		$form_dimension = "";						$form_data_array[16] = $form_dimension;				//Opt
		$form_ship_counrty = "";					$form_data_array[17] = $form_ship_counrty;			//Opt
		$form_ship_state = "";						$form_data_array[18] = $form_ship_state;			//Opt
		$form_ship_zip = "";						$form_data_array[19] = $form_ship_zip;				//Opt
		$form_ship_city = "";						$form_data_array[20] = $form_ship_city;				//Opt
		$form_ship_addr = "";					    $form_data_array[21] = $form_ship_addr;				//Opt
		$form_add_fraud_score = "";			        $form_data_array[22] = $form_add_fraud_score;		//Opt
		$form_max_pay_retries = "";			        $form_data_array[23] = $form_max_pay_retries;		//Opt
		$form_reject3dsU = "";					    $form_data_array[24] = $form_reject3dsU;			//Opt
		$form_pay_method = "";						$form_data_array[25] = $form_pay_method;			//Opt
		$form_trytpe = "";							$form_data_array[26] = $form_trytpe;				//Opt
		$form_ext_install_offset = "";	  $form_data_array[27] = $form_ext_install_offset;	//Opt
		$form_ext_install_period = "";	$form_data_array[28] = $form_ext_install_period;	//Opt
		$form_ext_reccuring_freq = "";	$form_data_array[29] = $form_ext_reccuring_freq;	//Opt
		$form_ext_reccuring_enddate = "";$form_data_array[30] = $form_ext_reccuring_enddate;	//Opt
		$form_block_score = "";					$form_data_array[31] = $form_block_score;			//Opt
		$form_cssurl = "";							$form_data_array[32] = $form_cssurl;				//Opt
		$form_confirm_url = $this->redirect_page_id;					$form_data_array[33] = $form_confirm_url;			//Req
		$form_cancel_url = $this->redirect_fail_page_id;						$form_data_array[34] = $form_cancel_url;			//Req
		$form_var1 = "";								$form_data_array[35] = $form_var1;			
		$form_var2 = "";								$form_data_array[36] = $form_var2;			
		$form_var3 = "";								$form_data_array[37] = $form_var3;			
		$form_var4 = "";								$form_data_array[38] = $form_var4;			
		$form_var5 = "";								$form_data_array[39] = $form_var5;			
		$form_secret = $this->alphabank_Secret_key;					$form_data_array[40] = $form_secret;				//Req
		
		
		$form_data = implode("", $form_data_array);
		
		$digest = base64_encode(sha1(utf8_encode($form_data),true));
			
//    if ($digest)
//	{	
		//If response success save data in DB and redirect
		$wpdb->insert($wpdb->prefix . 'alphabank_transactions', array('reference' => $form_email,'merchantreference'=> $merchantreference , 'orderid' => $order_id, 'timestamp' => current_time('mysql', 1)));
				
		
		          /* */  wc_enqueue_js('
				$.blockUI({
						message: "' . esc_js(__('Thank you for your order. We are now redirecting you to Alpha Bank Greece to make payment.', 'woocommerce-alphabank-payment-gateway')) . '",
						baseZ: 99999,
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:        "20px",
							zindex:         "9999999",
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"24px",
						}
					});
				jQuery("#submit_alphabank_payment_form").click();
			');
	
		 return '<form action="' . $post_url . '" method="post" id="alphabank_payment_form" target="_top">
                    <input type="hidden" name="mid" value="'.$form_mid.'"/>
                    <input type="hidden" name="lang" value="'.$form_lang .'"/>
                    <input type="hidden" name="deviceCategory" value="'.$form_device_cate .'"/>
                    <input type="hidden" name="orderid" value="'.$form_order_id .'"/>
                    <input type="hidden" name="orderDesc" value="'.$form_order_desc .'"/>
                    <input type="hidden" name="orderAmount" value="'.$form_order_amount .'"/>
                    <input type="hidden" name="currency" value="'.$form_currency .'"/>
                    <input type="hidden" name="payerEmail" value="'.$form_email .'"/>
                    <input type="hidden" name="payerPhone" value="'.$form_phone .'"/>
                    <input type="hidden" name="billCountry" value="'.$form_bill_country .'"/>
                    <input type="hidden" name="billState" value="'.$form_bill_state .'"/>
                    <input type="hidden" name="billZip" value="'.$form_bill_zip .'"/>
                    <input type="hidden" name="billCity" value="'.$form_bill_city .'"/>
                    <input type="hidden" name="billAddress" value="'.$form_bill_addr .'"/>
                    <input type="hidden" name="weight" value="'.$form_weight .'"/>
                    <input type="hidden" name="dimensions" value="'.$form_dimension .'"/>
                    <input type="hidden" name="shipCountry" value="'.$form_ship_counrty .'"/>
                    <input type="hidden" name="shipState" value="'.$form_ship_state .'"/>
                    <input type="hidden" name="shipZip" value="'.$form_ship_zip .'"/>
                    <input type="hidden" name="shipCity" value="'.$form_ship_city .'"/>
                    <input type="hidden" name="shipAddress" value="'.$form_ship_addr .'"/>
                    <input type="hidden" name="addFraudScore" value="'.$form_add_fraud_score .'"/>
                    <input type="hidden" name="maxPayRetries" value="'.$form_max_pay_retries .'"/>
                    <input type="hidden" name="reject3dsU" value="'.$form_reject3dsU .'"/>
                    <input type="hidden" name="payMethod" value="'.$form_pay_method .'"/>
                    <input type="hidden" name="trType" value="'.$form_trytpe .'"/>
                    <input type="hidden" name="extInstallmentoffset" value="'.$form_ext_install_offset .'"/>
                    <input type="hidden" name="extInstallmentperiod" value="'.$form_ext_install_period .'"/>
                    <input type="hidden" name="extRecurringfrequency" value="'.$form_ext_reccuring_freq .'"/>
                    <input type="hidden" name="extRecurringenddate" value="'.$form_ext_reccuring_enddate .'"/>
                    <input type="hidden" name="blockScore" value="'.$form_block_score .'"/>
                    <input type="hidden" name="cssUrl" value="'.$form_cssurl .'"/>
                    <input type="hidden" name="confirmUrl" value="'.$form_confirm_url .'"/>
                    <input type="hidden" name="cancelUrl" value="'.$form_cancel_url .'"/>
                    <input type="hidden" name="var1" value="'.$form_var1 .'"/>
                    <input type="hidden" name="var2" value="'.$form_var2 .'"/>
                    <input type="hidden" name="var3" value="'.$form_var3 .'"/>
                    <input type="hidden" name="var4" value="'.$form_var4 .'"/>
                    <input type="hidden" name="var5" value="'.$form_var5 .'"/>
                    <input type="hidden" name="digest" value="'.$digest .'"/>
				
					<!-- Button Fallback -->
					<div class="payment_buttons">
						<input type="submit" class="button alt" id="submit_alphabank_payment_form" value="' . __('Pay via alphabank', 'woocommerce-alphabank-payment-gateway') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce-alphabank-payment-gateway') . '</a>
					</div>
					<script type="text/javascript">
						jQuery(".payment_buttons").hide();
					</script>
				</form>';
	//} 
}

        /**
         * Process the payment and return the result
         * */
        function process_payment($order_id) {

            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Output for the order received page.
         * */
        function receipt_page($order) {
            echo '<p>' . __('Thank you - your order is now pending payment. You should be automatically redirected to Alpha Bank Greece to make payment.', 'woocommerce-alphabank-payment-gateway') . '</p>';
            echo $this->generate_alphabank_form($order);
        }

        /**
         * Verify a successful Payment!
         * */
        function check_alphabank_response() {
            global $woocommerce;
            global $wpdb;
           //if (( preg_match( '/success/i', $_SERVER['REQUEST_URI'] ) && preg_match( '/alphabank/i', $_SERVER['REQUEST_URI'] ) )) {	
			
//			$merchantreference= $_GET['MerchantReference']; 
			
			$post_data_array = array();
	
            if (isset($_POST['mid'])) {$post_data_array[0] = $_POST['mid'];}
            if (isset($_POST['orderid'])) {$post_data_array[1] = $_POST['orderid'];}
            if (isset($_POST['status'])) {$post_data_array[2] = $_POST['status'];}
            if (isset($_POST['orderAmount'])) {$post_data_array[3] = $_POST['orderAmount'];}
            if (isset($_POST['currency'])) {$post_data_array[4] = $_POST['currency'];}
            if (isset($_POST['paymentTotal'])) {$post_data_array[5] = $_POST['paymentTotal'];}
            if (isset($_POST['message'])) {$post_data_array[6] = $_POST['message'];}
            if (isset($_POST['riskScore'])) {$post_data_array[7] = $_POST['riskScore'];}
            if (isset($_POST['payMethod'])) {$post_data_array[8] = $_POST['payMethod'];}
            if (isset($_POST['txId'])) {$post_data_array[9] = $_POST['txId'];}
            if (isset($_POST['paymentRef'])) {$post_data_array[10] = $_POST['paymentRef'];}
            $post_data_array[11] = $_POST['digest'];

            $post_DIGEST = $_POST['digest'];

            $post_data = implode("", $post_data_array);
            $digest = base64_encode(sha1(utf8_encode($post_data),true));
						
			if ($this->mode == "yes") 
			{//test mode
			$post_url = 'https://alpha.test.modirum.com/vpos/shophandlermpi';			
			}
			 else
			 { //live mode
			 $post_url ='https://www.alphaecommerce.gr/vpos/shophandlermpi';	
			 }	
//               
               $ttquery = 'SELECT *
				FROM `' . $wpdb->prefix . 'alphabank_transactions`
				WHERE `orderid` = "'.$_POST['orderid'].'";';
				$ref = $wpdb->get_results($ttquery);
                $merchantreference= $_GET['MerchantReference']; 
				$orderid=$ref['0']->orderid;
		
			$order = new WC_Order($orderid);
        
            //$order = $_POST['orderid'];               
			
			if ($_POST['status'] == 'AUTHORIZED' || $_POST['status'] == 'CAPTURED')
			{
                //verified - successful payment
                //complete order            
                if ($order->status == 'processing') {
                    $order->add_order_note(__('Payment Via alphabank<br />Transaction ID: ', 'woocommerce-alphabank-payment-gateway') . $trans_id);
                    //Add customer order note
                    $order->add_order_note(__('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />alphabank Transaction ID: ', 'woocommerce-alphabank-payment-gateway') . $trans_id, 1);
                    // Reduce stock levels
                    $order->reduce_order_stock();
                    // Empty cart
                    WC()->cart->empty_cart();
                    $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'woocommerce-alphabank-payment-gateway');
                    $message_type = 'success';
                } else {
                    if ($order->has_downloadable_item()) {
                        //Update order status
                        $order->update_status('completed', __('Payment received, your order is now complete.', 'woocommerce-alphabank-payment-gateway'));
                        //Add admin order note
                        $order->add_order_note(__('Payment Via alphabank Payment Gateway<br />Transaction ID: ', 'woocommerce-alphabank-payment-gateway') . $trans_id);
                        //Add customer order note
                        $order->add_order_note(__('Payment Received.<br />Your order is now complete.<br />alphabank Transaction ID: ', 'woocommerce-alphabank-payment-gateway') . $trans_id, 1);
                        $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', 'woocommerce-alphabank-payment-gateway');
                        $message_type = 'success';
                    } else {
                        //Update order status
                        $order->update_status('processing', __('Payment received, your order is currently being processed.', 'woocommerce-alphabank-payment-gateway'));
                        //Add admin order note
                        $order->add_order_note(__('Payment Via alphabank Payment Gateway<br />Transaction ID: ', 'woocommerce-alphabank-payment-gateway') . $trans_id);
                        //Add customer order note
                        $order->add_order_note(__('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />alphabank Transaction ID: ', 'woocommerce-alphabank-payment-gateway') . $trans_id, 1);
                        $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'woocommerce-alphabank-payment-gateway');
                        $message_type = 'success';
                    }
                    
                    $alphabank_message = array(
                        'message' => $message,
                        'message_type' => $message_type
                    );
                    update_post_meta($order_id, '_alphabank_message', $alphabank_message);
                    // Reduce stock levels
                    $order->reduce_order_stock();
                    // Empty cart
                    WC()->cart->empty_cart();
                } 
            }
		
				elseif ($_POST['status'] == 'CANCELED') 
				{//payment has failed - retry
					$message = __('Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'woocommerce-alphabank-payment-gateway');
						$message_type = 'error';
						$alphabank_message = array(
							'message' => $message,
							'message_type' => $message_type
						);
						update_post_meta($order_id, '_alphabank_message', $pb_message);
						//Update the order status
						$order->update_status('failed', '');
						$checkout_url = $woocommerce->cart->get_checkout_url();
						wp_redirect($checkout_url);
						exit;
				}
				
//			elseif ($_POST['status'] == 'CANCELED') 
//			{//an error occurred
//						$message = __('Thank you for shopping with us. <br />However, an error occurred and the transaction wasn\'t successful, payment wasn\'t received.', 'woocommerce-alphabank-payment-gateway');
//						$message_type = 'error';
//						$alphabank_message = array(
//							'message' => $message,
//							'message_type' => $message_type
//						);
//						update_post_meta($order_id, '_alphabank_message', $pb_message);
//						//Update the order status
//						$order->update_status('failed', '');
//						$checkout_url = $woocommerce->cart->get_checkout_url();
//						wp_redirect($checkout_url);
//						exit;			
//			}
            //$this->redirect_page_id = "https://donate.ellak.gr/".get_page_link($this->get_option('redirect_page_id'));
				if ($this->redirect_page_id=="-1"){				
				$redirect_url = $this->get_return_url( $order );	
				}else	
				{							
                $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg(array('msg' => urlencode($this->msg['message']), 'type' => $this->msg['class']), $redirect_url);								
				}
				wp_redirect($redirect_url);
               
       
	
	
			//exit;			
		
		 //}		
//		 if(isset($_GET['alphabank'])&& ($_GET['alphabank']==='cancel')) {	
//		
		  $checkout_url = $woocommerce->cart->get_checkout_url();
		  wp_redirect($checkout_url);
          exit;
//		  
		 //}
			
        }


    }

    function alphabank_message() {
        $order_id = absint(get_query_var('order-received'));
        $order = new WC_Order($order_id);
        $payment_method = $order->payment_method;

        if (is_order_received_page() && ( 'alphabank_gateway' == $payment_method )) {

            $alphabank_message = get_post_meta($order_id, '_alphabank_message', true);
            $message = $alphabank_message['message'];
            $message_type = $alphabank_message['message_type'];

            delete_post_meta($order_id, '_alphabank_message');

            if (!empty($alphabank_message)) {
                wc_add_notice($message, $message_type);
            }
        }
    }

    add_action('wp', 'alphabank_message');

    /**
     * Add Alpha Bank Greeece Gateway to WC
     * */
    function woocommerce_add_alphabank_gateway($methods) {
        $methods[] = 'WC_alphabank_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_alphabank_gateway');





    /**
     * Add Settings link to the plugin entry in the plugins menu for WC below 2.1
     * */
    if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {

        add_filter('plugin_action_links', 'alphabank_plugin_action_links', 10, 2);

        function alphabank_plugin_action_links($links, $file) {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_alphabank_Gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }
    /**
     * Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
     * */ else {
        add_filter('plugin_action_links', 'alphabank_plugin_action_links', 10, 2);

        function alphabank_plugin_action_links($links, $file) {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_alphabank_Gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }
}
