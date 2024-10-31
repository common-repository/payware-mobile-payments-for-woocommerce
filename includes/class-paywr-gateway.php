<?php
    #[\AllowDynamicProperties]
    class WC_PAYWR_Gateway extends WC_Payment_Gateway
    {
	    public $id;
        public $title;
        public $icon;
        public $has_fields;
        public $method_title;
        public $method_description;
        public $form_fields;
        public $description;
        public $enabled;
        public $testmode;
        public $paywr_partner_id;
        public $paywr_public_key;
        public $mobile_page_url;


        public function __construct()
        {
			
            $this->id = 'payware-mobile-payments-for-woocommerce'; // payment gateway plugin ID
            $icon = plugins_url('', dirname(__FILE__)) . '/assets/img/payware-certified-icon.svg'; // URL of the icon that will be displayed on checkout page near your gateway name
            $icon = apply_filters('paywr_icon', $icon); // filter
            $this->icon = $icon;
            $this->has_fields = true; // a custom form
            $this->method_title = __('Mobile payments by payware', 'payware-mobile-payments-for-woocommerce');
            $this->method_description = __('Enables receiving payments by customer\'s favorite payment app.', 'payware-mobile-payments-for-woocommerce'); // will be displayed on the options page
            $this->form_fields = require dirname(__FILE__) . '/class-paywr-form-fields.php';
			
            $sandbox = file_get_contents( dirname(__FILE__, 2) . '/assets/data/sandbox.json');
            $private_fields = (array)json_decode($sandbox, true);

            // jwt validator
            include_once dirname(__FILE__, 2) . '/assets/php/BeforeValidException.php';
            include_once dirname(__FILE__, 2) . '/assets/php/ExpiredException.php';
            include_once dirname(__FILE__, 2) . '/assets/php/SignatureInvalidException.php';
            include_once dirname(__FILE__, 2) . '/assets/php/JWT.php';

            // gateways could support subscriptions, refunds, saved payment methods
            $this->supports = array(
                'products'
            );

            // Load the settings.
            $this->init_settings();
            $this->enabled = sanitize_text_field(wp_unslash($this->get_option('enabled')));
            $this->testmode = sanitize_text_field(wp_unslash('yes' === $this->get_option('testmode')));
            $this->title = sanitize_text_field(wp_unslash($this->get_option('title')));
            $this->description = sanitize_textarea_field(wp_unslash($this->get_option('description')));
            // $this->instructions = $this->get_option( 'instructions');
            $this->paywr_partner_id = sanitize_text_field(wp_unslash($this->testmode ? $private_fields['partner_id'] : $this->get_option('partner_id')));
            $this->paywr_vlogin = sanitize_text_field(wp_unslash($this->testmode ? $private_fields['paywr_vlogin'] : $this->get_option('paywr_vlogin')));
            $this->paywr_public_key = sanitize_textarea_field(wp_unslash($this->testmode ? $private_fields['paywr_public_key'] : $this->get_option('live_payware_public_key')));


			// // This action hook shows instructions on thank you page
			// add_action( 'woocommerce_thankyou_' . $this->id, [
            //      $this, 'thankyou_page' 
            // ]);
		  
			// // Customer Emails
			// add_action( 'woocommerce_email_before_order_table', [
            //      $this, 'email_instructions' 
            // ], 10, 3 );

            add_action( 'wp_enqueue_scripts', function(){
                wp_localize_script('paywr_main_js', 'paywr_params', array(
                    'mobile_page_url' => $this->mobile_page_url,
                    'paywr_ajax_url' => home_url('/?paywr_ajax_url'),
                    'paywr_plugin_js_url' => plugins_url('', dirname(__FILE__)) . 'assets/js',
                    'paywr_is_mobilepage' => paywr_is_mobilepage()
                ));
            });

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
                $this, 'process_admin_options'
            ]);

            // Register a webhook
            add_action('woocommerce_api_paywr', [$this, 'paywr_webhook']);

        }

		// /**
		//  * Output for the order received page.
		//  */
		// public function thankyou_page() {
		// 	if ( $this->instructions ) {
		// 		echo wpautop(wptexturize($this->instructions));
		// 	}
		// }
	
		// /**
		//  * Add content to the WC emails.
		//  *
		//  * @access public
		//  * @param WC_Order $order
		//  * @param bool $sent_to_admin
		//  * @param bool $plain_text
		//  */
		// public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
		// 	if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
		// 		echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
		// 	}
		// }

        /*
        * Get payware payment link
        */
        public function paywr_get_payment_link($order)
        {

            $paywareApi = new PAYWR_payware_API([
                'testmode' => $this->testmode,
                'paywr_partner_id' => $this->paywr_partner_id,
                'order' => $order,
                'fields' => $this->settings,
                'paywr_vlogin' => $this->paywr_vlogin,
                'paywr_public_key' => $this->paywr_public_key
            ]);
            return $paywareApi->paywr_get_payment_link();
        }

        /*
        * Processing the payments
        */
        public function process_payment($order_id)
        {

            global $woocommerce;

            // get order detailes
            $order = wc_get_order($order_id);
            $order->update_status( 'pending', '', true); // recover order status from failed or declined for new processing
            $result = $this->paywr_get_payment_link($order);
            $payment_link = json_decode($result)->transactionId;
            if (isset($payment_link['status']) && $payment_link['status'] == 'error') {
                $error = $payment_link['error'];
                $error = apply_filters('paywr_error_text', $error); // filter
                wc_add_notice($error, 'error');
                return;
            }

            if (!$payment_link) {
                paywrLog('process_payment No valid payment link', [
                    'status' => "FAIL",
                    'order_id' => $order_id,
                    'payment_link' => $payment_link,
                ]);
                wc_add_notice(__('No valid payment link', 'payware-mobile-payments-for-woocommerce'), 'error');
                return;
            }

            $amount = $order->get_total();
            $currency = $order->data['currency'] ? $order->data['currency'] : 'USD';

            $structure = get_option( 'permalink_structure' );

            if ($structure == '') {
                $slug = $this->mobile_page_url ? strtolower ( str_replace ( ' ', '-', $this->mobile_page_url ) ) : 'mobile-payments';
                $page = get_page_by_path($slug);
                    if ($page) {
                        $page_id =  $page->ID;
                        $this->mobile_page_url = '?page_id=' . $page_id . '&';
                    }
            } else {
                $this->mobile_page_url = $this->mobile_page_url ? $this->mobile_page_url . '/?' : 'mobile-payments/?';
            }
            // Redirect to the mobile page
            $redirect_link = $this->mobile_page_url;
            $redirect_link = '/' . $redirect_link . 'payment_link=' . $payment_link . '&order_id=' . (int)$order_id . '&amount=' . $amount . '&currency=' . $currency;
            $redirect_link = home_url($redirect_link);
            $redirect_link = apply_filters('paywr_redirect_link', $redirect_link); // filter

            // redirect to mobile page
            return array(
                'result' => 'success',
                'redirect' => $redirect_link
            );
        }

        public function paywr_md5_hash($payload)
        {
            $hash = md5($payload, true);
            return base64_encode($hash);
        }
        
        /*
        * Webhook
        */
        public function paywr_webhook()
        {
            $post = json_decode(file_get_contents('php://input'), 1);
            if (isset($post['status'])) {
                $paywareApi = new PAYWR_payware_API([
                    'testmode' => $this->testmode,
                    'paywr_partner_id' => $this->paywr_partner_id,
                    'fields' => $this->settings,
                    'paywr_vlogin' => $this->paywr_vlogin,
                    'paywr_public_key' => $this->paywr_public_key
                ]);

                $verify = $paywareApi->paywr_callback_validation();
                // check jwt is valid
                if ($verify['verify'] == 'SUCCESS') {
                    // check callback body is not corrupted
                    $payload_hash = $verify['header']['contentMd5'];
                    $webhook_event_arr = file_get_contents('php://input');
                    $md5 = $this->paywr_md5_hash($webhook_event_arr);
                    if (strcmp ( $payload_hash, $md5 ) == 0 ){
                        // check claims data
                        if ($verify['claims']['aud'] == $this->paywr_partner_id) { 
                            if ($verify['claims']['iss'] == 'https://payware.eu') { 
                                $status_success = 'CONFIRMED';
                                $status_success = apply_filters('paywr_status_success', $status_success); // filter
                                $webhook_event_arr = (array)json_decode(file_get_contents('php://input'));
                                $order_number = $webhook_event_arr['passbackParams'];
                                $order = wc_get_order($order_number);
                                $order_status = $order->get_status();
                                if (isset($webhook_event_arr['passbackParams'])
                                    && strcmp($order_status, 'pending') == 0
                                    && $webhook_event_arr['status'] != $status_success) {
                                    $response_status = $webhook_event_arr['status'];
                                    $payment_status = $response_status == 'DECLINED' ? 'cancelled' : 'failed';
                                    $order->update_status( $payment_status, '', true);
                                    $response_transactionId = $webhook_event_arr['transactionId'];
                                    $response_message = $response_status == 'DECLINED'
                                        ? 'payware: DECLINED (' . $response_transactionId . ') - Payment declined by user. "' . $webhook_event_arr['statusMessage'] . '"'
                                        : 'payware: FAILED (' . $response_transactionId . ') - Payment failed. "' . $webhook_event_arr['statusMessage'] . '"';
                                    $add_order_note = __($response_message, 'payware-mobile-payments-for-woocommerce');
                                    $add_order_note = apply_filters('paywr_add_order_note', $add_order_note); // filter
                                    $order->add_order_note($add_order_note);
                                    return;
                                }
                                if (strcmp($order_status, 'pending') != 0) {
                                    return;
                                }
                                // paywrLog('$webhook_event_arr => ',  $webhook_event_arr);

                                if (isset($webhook_event_arr['passbackParams'])) {

                                    $order_number = $webhook_event_arr['passbackParams'];

                                    $order = wc_get_order($order_number);

                                    $order = apply_filters('paywr_order', $order); // filter

                                    if ($order) {

                                        if (!$order->is_paid()) {
                                            global $woocommerce;
                                            $order->payment_complete();
                                            $order->reduce_order_stock();
                                            $woocommerce->cart->empty_cart();
                                            $response_transactionId = $webhook_event_arr['transactionId'];
                                            $add_order_note = __('payware: PAID (' . $response_transactionId . ')', 'payware-mobile-payments-for-woocommerce');
                                            $add_order_note = apply_filters('paywr_add_order_note', $add_order_note); // filter
                                            $order->add_order_note($add_order_note);
                                            paywrLog('payment completed => ', [$order_number => $order->is_paid() ? 'True' : 'False']);
                                        }

                                    } else {
                                        paywrLog('Incorect invoice number => ', [$order_number]);
                                    }
                                }
                            } else {
                                paywrLog('Unknown partnerId');
                                return new WP_Error( 'unknown_partner_id', 'Unknown partnerId', array( 'status' => 404 ) );
                            }
                        } else {
                            paywrLog('Wrong audience');
                            return new WP_Error( 'wrong_audience', 'Wrong audience', array( 'status' => 404 ) );
                        }
                    } else {
                        paywrLog('callback body CORRUPTED');
                        return new WP_Error( 'callback_body_CORRUPTED', 'Callback body CORRUPTED', array( 'status' => 404 ) );
                    }
                } else {
                    paywrLog('Webhook FAILURE');
                    return new WP_Error( 'webhook_FAILURE', 'Webhook FAILURE', array( 'status' => 404 ) );
                }
            }  else {
                paywrLog('Webhook Failed or Canceled');
                return;
            }
        }
    }