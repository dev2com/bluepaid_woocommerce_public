<?php
/**
 * bluepaid Payment Gateway
 *
 * Provides payment by credit card via Bluepaid.
 *
 * @class 		WC_Gateway_bluepaid
 * @extends		WC_Payment_Gateway
 * @version		1.2.0
 * @package		WooCommerce/Classes/Payment
 * @category	Payment Gateways
 * @author 		Dev2Com
 * Text Domain: woocommerce-gateway-bluepaid
 * Domain Path: /i18n/
 *
 *
 * Table Of Contents
 *
 * __construct() 
 * init_form_fields()
 * setup_constants() 
 * plugin_url()
 * is_valid_for_use()
 * admin_options()
 * payment_fields()
 * generate_bluepaid_form()
 * process_payment()
 * receipt_page()
 * check_itn_request_is_valid()
 * is_test_mode()
 * check_itn_response()
 * successful_request()
 * log()
 * validate_signature()
 * validate_ip()
 * validate_response_data()
 * check_amounts()
 */
class WC_Gateway_bluepaid extends WC_Payment_Gateway {

	public $version = '1.2';

	public function __construct() {
        global $woocommerce;
        $this->id			= 'bluepaid';
        $this->method_title = __( 'bluepaid', 'dev2com - JL' );
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
        $this->has_fields 	= true;
        $this->debug_email 	= get_option( 'admin_email' );

		// Setup available countries.
		$this->available_countries = array( 'FR' );

		// Setup available currency codes.
		$this->available_currencies = array( 'EUR' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup constants.
		$this->setup_constants();

		// Setup default merchant data.
		$this->merchant_id = $this->settings['merchant_id'];
		$this->url = 'https://paiement-securise.bluepaid.com/in.php';
		$this->validate_url = 'https://paiement-securise.bluepaid.com/in.php';
		$this->title = $this->settings['title'];

		// Setup the test data, if in test mode.
		if ( $this->settings['testmode'] == 'yes' ) {
			//$this->add_testmode_admin_settings_notice();
			//Newer
			$this->url = 'https://paiement-securise.bluepaid.com/in.php';
			$this->validate_url = 'https://paiement-securise.bluepaid.com/in.php';
			//Older
			//$this->url = 'https://www.bluepaid.com/in.php';
			//$this->validate_url = 'https://www.bluepaid.com/in.php';
		}

		$this->response_url	= add_query_arg( 'wc-api', 'WC_Gateway_bluepaid', home_url( '/' ) );

		add_action( 'woocommerce_api_wc_gateway_bluepaid', array( $this, 'check_itn_response' ) );
		add_action( 'valid-bluepaid-standard-itn-request', array( $this, 'successful_request' ) );
		/* 1.0.1 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		/* 1.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_bluepaid', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
    }

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
    						'enabled' => array(
											'title' => __( 'Enable/Disable', 'woocommerce-gateway-bluepaid' ),
											'label' => __( 'Enable bluepaid', 'woocommerce-gateway-bluepaid' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-bluepaid' ),
											'default' => 'yes'
										),
    						'title' => array(
    										'title' => __( 'Title', 'woocommerce-gateway-bluepaid' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-bluepaid' ),
    										'default' => __( 'bluepaid', 'woocommerce-gateway-bluepaid' )
    									),
							'description' => array(
											'title' => __( 'Description', 'woocommerce-gateway-bluepaid' ),
											'type' => 'text',
											'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-bluepaid' ),
											'default' => ''
										),
							'testmode' => array(
											'title' => __( 'bluepaid Sandbox', 'woocommerce-gateway-bluepaid' ),
											'type' => 'checkbox',
											'description' => __( 'Place the payment gateway in development mode.', 'woocommerce-gateway-bluepaid' ),
											'default' => 'yes'
										),
							'merchant_id' => array(
											'title' => __( 'Merchant ID', 'woocommerce-gateway-bluepaid' ),
											'type' => 'text',
											'description' => __( 'This is the merchant ID, received from bluepaid (id boutique).', 'woocommerce-gateway-bluepaid' ),
											'default' => ''
										),
							'send_debug_email' => array(
											'title' => __( 'Send Debug Emails', 'woocommerce-gateway-bluepaid' ),
											'type' => 'checkbox',
											'label' => __( 'Send debug e-mails for transactions through the bluepaid gateway (sends on successful transaction as well).', 'woocommerce-gateway-bluepaid' ),
											'default' => 'yes'
										),
							'debug_email' => array(
											'title' => __( 'Who Receives Debug E-mails?', 'woocommerce-gateway-bluepaid' ),
											'type' => 'text',
											'description' => __( 'The e-mail address to which debugging error e-mails are sent when in test mode.', 'woocommerce-gateway-bluepaid' ),
											'default' => get_option( 'admin_email' )
										)
							);

    } // End init_form_fields()

    /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
	function is_valid_for_use() {
		global $woocommerce;

		$is_available = false;

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ( $is_available_currency && $this->enabled == 'yes' && $this->settings['merchant_id'] != '' )
			$is_available = true;
        return $is_available;
	} // End is_valid_for_use()

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		// Make sure to empty the log file if not in test mode.
		if ( $this->settings['testmode'] != 'yes' ) {
			$this->log( '' );
			$this->log( '', true );
		}

    	?>
    	<h3><?php _e( 'bluepaid', 'dev2com - JL' ); ?></h3>
    	<p><?php printf( __( 'bluepaid works by sending the user to %sbluepaid%s to enter their payment information.', 'woocommerce-gateway-bluepaid' ), '<a target="_blank" href="https://www.bluepaid.com/">', '</a>' ); ?></p>

    	<?php
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table-->
    	<?php
    } // End admin_options()

    /**
	 * There are no payment fields for bluepaid, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    } // End payment_fields()

	/**
	 * Generate the bluepaid button link.
	 *
	 * @since 1.0.0
	 */
    public function generate_bluepaid_form( $order_id ) {

		global $woocommerce;

		$order = new WC_Order( $order_id );

		$shipping_name = explode(' ', $order->shipping_method);
        $user_currency = get_option( 'woocommerce_currency' );

		$return_url = $this->get_return_url( $order );
		$return_url = str_replace('http://', '', $return_url);
		$return_url = str_replace('https://', '', $return_url);
		$cancel_url = $order->get_cancel_order_url();
		$cancel_url = str_replace('http://', '', $cancel_url);
		$cancel_url = str_replace('https://', '', $cancel_url);
		$notify_url = $this->response_url;
		$notify_url = str_replace('http://', '', $notify_url);
		$notify_url = str_replace('https://', '', $notify_url);

		//Verify if data come from Bluepaid
		$sign_key_bpi = "order_key=".md5($order->order_key);
		$sign_key_bpi .= "montant=".$order->order_total;
		$sign_key_bpi .= "id_customer=".$order->id;

		// Construct variables for post
	    $this->data_to_send = array(
	        // Merchant details
	        'id_boutique' => $this->settings['merchant_id'],
	        'url_retour_ok' => $return_url,
	        'url_retour_stop' => $cancel_url,
	        'url_retour_bo' => $notify_url,

			// Billing details
			'prenom' => $order->billing_first_name,
			'nom' => $order->billing_last_name,
			'email_client' => $order->billing_email,

	        // Item details
	        'montant' => $order->order_total,
	        'devise' => $user_currency,
	    	'item_name' => get_bloginfo( 'name' ) .' purchase, Order ' . $order->get_order_number(),
	    	'item_description' => sprintf( __( 'New order from %s', 'woocommerce-gateway-bluepaid' ), get_bloginfo( 'name' ) ),

	    	// Custom strings
	    	//'divers' => $order->order_key,
	    	'divers' => md5($sign_key_bpi),
	    	'test' => $order->order_key,
	    	'id_client' => $order->id,
	    	'source' => 'WooCommerce-Free-Plugin'
	   	);

		$bluepaid_args_array = array();

		foreach ($this->data_to_send as $key => $value) {
			$bluepaid_args_array[] = '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
		}
		wc_enqueue_js( '
			$.blockUI({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to Bluepaid to make payment.', 'woocommerce-gateway-bluepaid' ) ) . '",
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
			jQuery("#submit_bluepaid_payment_form").click();
		' );
		return '<form action="' .  $this->url  . '" method="post" id="bluepaid_payment_form" target="_top">
				' . implode( '', $bluepaid_args_array ) . '
				<!-- Button Fallback -->
				<div class="payment_buttons">
					<input type="submit" class="button alt" id="submit_bluepaid_payment_form" value="' . __( 'Payez via Bluepaid', 'woocommerce-gateway-bluepaid' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce-gateway-bluepaid' ) . '</a>
				</div>
				<script type="text/javascript">
					jQuery(".payment_buttons").hide();
				</script>
			</form>';

	} // End generate_bluepaid_form()

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);

	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to bluepaid.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with bluepaid.', 'woocommerce-gateway-bluepaid' ) . '</p>';

		echo $this->generate_bluepaid_form( $order );
	} // End receipt_page()

	/**
	 * Check bluepaid ITN validity.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	function check_itn_request_is_valid( $data ) {			
			
		global $woocommerce;

		$pfError = false;
		$pfDone = false;
		$pfDebugEmail = $this->settings['debug_email'];

		if ( ! is_email( $pfDebugEmail ) ) {
			$pfDebugEmail = get_option( 'admin_email' );
		}

		$sessionid = $data['divers'];
        $transaction_id = $data['id_trans'];
        $vendor_name = get_option( 'blogname' );
        $vendor_url = home_url( '/' );

		$order_id = (int) $data['id_client'];
		$order_key = esc_attr( $sessionid );
		$order = new WC_Order( $order_id );
		$order_key = $order->order_key;

		$data_string = '';
		$data_array = array();
		
		
		//Verify if data come from Bluepaid
		$sign_key_bpi = "order_key=".md5($order_key);
		$sign_key_bpi .= "montant=".$order->order_total;
		$sign_key_bpi .= "id_customer=".$order_id;
		
	    $signature = md5( $sign_key_bpi );

		$this->log( "\n" . '----------' . "\n" . 'bluepaid ITN call received' );

		// Notify bluepaid that information has been received
        if( ! $pfError && ! $pfDone ) {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }

        // Get data sent by bluepaid
        if ( ! $pfError && ! $pfDone ) {
        	$this->log( 'Get posted data' );

            $this->log( 'bluepaid Data: '. print_r( $data, true ) );

            if ( $data === false ) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_BAD_ACCESS;
            }
        }

        // Verify security signature
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Verify security signature' );

            // If signature different, log for debugging
            if( ! $this->validate_signature( $data, $signature ) ) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_INVALID_SIGNATURE;
            }
        }

        // Verify source IP (If not in debug mode)
        if( ! $pfError && ! $pfDone && $this->settings['testmode'] != 'yes' ) {
            $this->log( 'Verify source IP' );

            if( ! $this->validate_ip( $_SERVER['REMOTE_ADDR'] ) ) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_BAD_SOURCE_IP;
            }
        }

        // Get internal order and verify it hasn't already been processed
        if( ! $pfError && ! $pfDone ) {

            $this->log( "Purchase:\n". print_r( $order, true )  );

            // Check if order has already been processed
            if( $order->status == 'completed' ) {
                $this->log( 'Order has already been processed' );
                $pfDone = true;
            }
        }

        // Verify data received
        if( ! $pfError ) {
            $this->log( 'Verify data received' );

            $pfValid = $this->validate_response_data( $data_array );

            if( ! $pfValid ) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_BAD_ACCESS;
            }
        }

        // Check data against internal order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check data against internal order' );

            // Check order amount
            if( ! $this->check_amounts( $data['montant'], $order->order_total ) ) {
                $pfError = true;
                $pfErrMsg = BPI_ERR_AMOUNT_MISMATCH;
            }
        }
		
		//Check test mode
		$is_refund=false;
		if($data['mode']){
			if($data['mode'] == 'test' && !$this->is_test_mode()){//test
				$data['etat'] = "ko";
                $pfError = true;
                $pfErrMsg = BPI_ERR_TEST_OPERATION_IN_PROD;
			}
			if($data['mode'] == 'r'){//refund
				$is_refund=true;
			}
		}
		

        // Check status and update order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check status and update order' );

		if ( $order->order_key !== $order_key ) { exit; }
    		switch( $data['etat'] ) {
                case 'ok':
					if(!$is_refund){
						$this->log( '- Complete' );			
	
					   // Payment completed
						$order->add_order_note( __( 'ITN payment completed', 'woocommerce-gateway-bluepaid' ) );
						$order->add_order_note( __( 'Transaction Bluepaid '.$posted['id_trans'], 'woocommerce-gateway-bluepaid' ) );
						$order->payment_complete();
	
						if( $this->settings['testmode'] == 'yes' && $this->settings['send_debug_email'] == 'yes' ) {
							$subject = "bluepaid ITN on your site";
							$body =
								"Hi,\n\n".
								"A bluepaid transaction has been completed on your website\n".
								"------------------------------------------------------------\n".
								"Site: ". $vendor_name ." (". $vendor_url .")\n".
								"Purchase ID: ". $data['id_client'] ."\n".
								"bluepaid Transaction ID: ". $data['id_trans'] ."\n".
								"bluepaid Payment Status: ". $data['etat'] ."\n".
								"Order Status Code: ". $order->status;
							wp_mail( $pfDebugEmail, $subject, $body );
						}
					}else{
						// Mark order as refunded
						$order->update_status( 'refunded', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), strtolower( $data['etat'] ) ) );
					}
                    break;

    			case 'ko':
					if(!$is_refund){
						$this->log( '- Failed' );
						$order->add_order_note( __( 'ITN payment refused - Transaction '.$data['id_trans'], 'woocommerce-gateway-bluepaid' ) );
	
						if( $this->settings['testmode'] == 'yes' && $this->settings['send_debug_email'] == 'yes' ) {
							$subject = "bluepaid ITN Transaction on your site";
							$body =
								"Hi,\n\n".
								"A failed bluepaid transaction on your website requires attention\n".
								"------------------------------------------------------------\n".
								"Site: ". $vendor_name ." (". $vendor_url .")\n".
								"Purchase ID: ". $order->id ."\n".
								"User ID: ". $order->user_id ."\n".
								"bluepaid Transaction ID: ". $data['id_trans'] ."\n".
								"bluepaid Payment Status: ". $data['etat'] ."\n".
						
							wp_mail( $pfDebugEmail, $subject, $body );
						}
						//$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woocommerce-gateway-bluepaid' ), strtolower( sanitize( $data['etat'] ) ) ) );
						$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), wc_clean( $data['etat'] ) ) );
					}else{
						// Do not Mark order as refunded => refused
						//	$order->update_status( 'refunded', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), strtolower( $data['etat'] ) ) );
					}
        			break;

    			case 'attente':
                    $this->log( '- Pending' );
                    // Need to wait for "Completed" before processing
        		//	$order->update_status( 'pending', sprintf(__('Payment %s via ITN.', 'woocommerce-gateway-bluepaid' ), strtolower( sanitize( $data['etat'] ) ) ) );
					$order->update_status( 'pending', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), wc_clean( $data['etat'] ) ) );
        			break;

    			default:
                    // If unknown status, do nothing (safest course of action)
    			break;
            }
        }

        // If an error occurred
        if( $pfError ) {
            $this->log( 'Error occurred: '. $pfErrMsg );

            if( $this->settings['testmode'] == 'yes' && $this->settings['send_debug_email'] == 'yes' ) {
	            $this->log( 'Sending email notification' );

	             // Send an email
	            $subject = "bluepaid ITN error: ". $pfErrMsg;
	            $body =
	                "Hi,\n\n".
	                "An invalid bluepaid transaction on your website requires attention\n".
	                "------------------------------------------------------------\n".
	                "Site: ". $vendor_name ." (". $vendor_url .")\n".
	                "Remote IP Address: ".$_SERVER['REMOTE_ADDR']."\n".
	                "Remote host name: ". gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ."\n".
	                "Purchase ID: ". $order->id ."\n".
	                "User ID: ". $order->user_id ."\n";
	            if( isset( $data['pf_payment_id'] ) )
	                $body .= "bluepaid Transaction ID: ". $data['pf_payment_id'] ."\n";
	            if( isset( $data['payment_status'] ) )
	                $body .= "bluepaid Payment Status: ". $data['payment_status'] ."\n";
	            $body .=
	                "\nError: ". $pfErrMsg ."\n";

	            switch( $pfErrMsg ) {
	                case BPI_ERR_AMOUNT_MISMATCH:
	                    $body .=
	                        "Value received : ". $data['montant'] ."\n".
	                        "Value should be: ". $order->order_total;
	                    break;

	                case BPI_ERR_ORDER_ID_MISMATCH:
	                    $body .=
	                        "Value received : ". $data['id_client'] ."\n".
	                        "Value should be: ". $order->id;
	                    break;

	                case BPI_ERR_SESSION_ID_MISMATCH:
	                    $body .=
	                        "Value received : ". $data['divers'] ."\n".
	                        "Value should be: ". $order->id;
	                    break;
					case BPI_ERR_TEST_OPERATION_IN_PROD:
	                    $body .=
	                        "Mode : ". $data['divers'] ."\n".
	                        "Value should be: NULL";
	                    break;

	                // For all other errors there is no need to add additional information
	                default:
	                    break;
	            }

	            wp_mail( $pfDebugEmail, $subject, $body );
            }
        }

        // Close log
        $this->log( '', true );

    	return $pfError;
    } // End check_itn_request_is_valid()
	
	function is_test_mode(){	
		if ( $this->settings['testmode'] == 'yes' ) return true;
		return false;	
	}

	/**
	 * Check bluepaid ITN response.
	 *
	 * @since 1.0.0
	 */
	function check_itn_response() {		
		$_POST = stripslashes_deep( $_REQUEST );
		if ( $this->check_itn_request_is_valid( $_POST ) ) {
			do_action( 'valid-bluepaid-standard-itn-request', $_POST );
		}
	} // End check_itn_response()

	/**
	 * Successful Payment!
	 *
	 * @since 1.0.0
	 */
	function successful_request( $posted ) {
		
		if ( ! isset( $posted['id_client'] ) && ! is_numeric( $posted['id_client'] ) ) { return false; }

		$order_id = (int) $posted['id_client'];
		$order_key = esc_attr( $posted['divers'] );
		$order = new WC_Order( $order_id );
		
		$test_order_key = "order_key=".md5($order->order_key);
		$test_order_key .= "montant=".$order->order_total;
		$test_order_key .= "id_customer=".$order->id;
		$test_order_key = md5($test_order_key);
		
		if ( $test_order_key !== $order_key ) { exit; }				
		//Check test mode
		
		$is_refund=false;
		if($posted['mode']){
			//The user used a test card in production mode, the transaction is denied
			if($posted['mode'] == 'test' && !$this->is_test_mode()){
                $posted['etat'] = "ko";
			}
			if($posted['mode'] == 'r'){//refund
				$is_refund=true;
			}
		}

		if ( $order->status !== 'completed' ) {
			// We are here so lets check status and do actions
			switch ( strtolower( $posted['etat'] ) ) {
				case 'ok' :
					if(!$is_refund){
						// Payment completed
						$order->add_order_note( __( 'ITN payment completed', 'woocommerce-gateway-bluepaid' ) );
						$order->add_order_note( __( 'Transaction Bluepaid '.$posted['id_trans'], 'woocommerce-gateway-bluepaid' ) );
						$order->payment_complete();
					}else{
						// Mark order as refunded
						$order->update_status( 'refunded', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), strtolower( $data['etat'] ) ) );
					}
				break;
				case 'ko' :
					if(!$is_refund){
						// Failed order
						$order->add_order_note( __( 'Transaction Bluepaid '.$posted['id_trans'].' was refused' , 'woocommerce-gateway-bluepaid' ) );
						//$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woocommerce-gateway-bluepaid' ), strtolower( sanitize( $posted['etat'] ) ), 'woocommerce-gateway-bluepaid' ) );
						$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), wc_clean( $posted['etat'] ) ) );
					}
				break;
				default:
					// Hold order
				break;
			} // End SWITCH Statement

			wp_redirect( $this->get_return_url( $order ) );
			exit;
		} // End IF Statement

		exit;
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the bluepaid gateway.
	 *
	 * @since 1.0.0
	 */
	function setup_constants () {
		global $woocommerce;
		//// Create user agent string
		// User agent constituents (for cURL)
		define( 'BPI_SOFTWARE_NAME', 'WooCommerce' );
		define( 'BPI_SOFTWARE_VER', $woocommerce->version );
		define( 'BPI_MODULE_NAME', 'WooCommerce-bluepaid-Free' );
		define( 'BPI_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pfFeatures = 'PHP '. phpversion() .';';

		// - cURL
		if( in_array( 'curl', get_loaded_extensions() ) )
		{
		    define( 'BPI_CURL', '' );
		    $pfVersion = curl_version();
		    $pfFeatures .= ' curl '. $pfVersion['version'] .';';
		}
		else
		    $pfFeatures .= ' nocurl;';

		// Create user agrent
		define( 'BPI_USER_AGENT', BPI_SOFTWARE_NAME .'/'. BPI_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. BPI_MODULE_NAME .'/'. BPI_MODULE_VER );

		// General Defines
		define( 'BPI_TIMEOUT', 15 );
		define( 'BPI_EPSILON', 0.01 );

		// Messages
		    // Error
		define( 'BPI_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_BAD_ACCESS', __( 'Bad access of page', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_CONNECT_FAILED', __( 'Failed to connect to bluepaid', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_MERCHANT_ID_MISMATCH', __( 'Merchant ID mismatch', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_PDT_FAIL', __( 'PDT query failed', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_UNKNOWN', __( 'Unkown error occurred', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_ERR_TEST_OPERATION_IN_PROD', __( 'Transaction with test card in production', 'woocommerce-gateway-bluepaid' ) );

		    // General
		define( 'BPI_MSG_OK', __( 'Payment was successful', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_MSG_FAILED', __( 'Payment has failed', 'woocommerce-gateway-bluepaid' ) );
		define( 'BPI_MSG_PENDING',
		    __( 'The payment is pending. Please note, you will receive another Instant', 'woocommerce-gateway-bluepaid' ).
		    __( ' Transaction Notification when the payment status changes to', 'woocommerce-gateway-bluepaid' ).
		    __( ' "Completed", or "Failed"', 'woocommerce-gateway-bluepaid' ) );
	} // End setup_constants()

	/**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */

	function log ( $message, $close = false ) {
		if ( ( $this->settings['testmode'] != 'yes' && ! is_admin() ) ) { return; }

		static $fh = 0;

		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/bluepaid.log', 'w' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()

	/**
	 * validate_signature()
	 *
	 * Validate the signature against the returned data.
	 *
	 * @param array $data
	 * @param string $signature
	 * @since 1.0.0
	 */

	function validate_signature ( $data, $signature ) {
	    $result = ( $data['divers'] == $signature );
	    $this->log( 'Signature = '. ( $result ? 'valid' : 'invalid' ) );

	    return( $result );
	} // End validate_signature()

	/**
	 * validate_ip()
	 *
	 * Validate the IP address to make sure it's coming from bluepaid.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */

	function validate_ip( $sourceIP ) {
	    // Variable initialization
	    $validHosts = array(
	        'www.bluepaid.com',
	        'monitoring.bluepaid.com',
	        'paiement-securise.bluepaid.com',
	        'test-paiement-securise.bluepaid.com',
	        );

	    $validIps = array();

	    foreach( $validHosts as $pfHostname ) {
	        $ips = gethostbynamel( $pfHostname );

	        if( $ips !== false )
	            $validIps = array_merge( $validIps, $ips );
	    }

	    // Remove duplicates
	    $validIps = array_unique( $validIps );

	    $this->log( "Valid IPs:\n". print_r( $validIps, true ) );

	    if( in_array( $sourceIP, $validIps ) ) {
	        return( true );
	    } else {
	        return( false );
	    }
	} // End validate_ip()

	/**
	 * validate_response_data()
	 *
	 * @param $pfHost String Hostname to use
	 * @param $pfParamString String Parameter string to send
	 * @param $proxy String Address of proxy to use or NULL if no proxy
	 * @since 1.0.0
	 */
	function validate_response_data( $pfParamString, $pfProxy = null ) {
		return true; //Bluepaid does not accept response from me
		global $woocommerce;
	    $this->log( 'Host = '. $this->validate_url );
	    $this->log( 'Params = '. print_r( $pfParamString, true ) );

		if ( ! is_array( $pfParamString ) ) { return false; }

		$post_data = $pfParamString;

		$url = $this->validate_url;

		$response = wp_remote_post( $url, array(
       				'method' => 'POST',
        			'body' => $post_data,
        			'timeout' => 70,
        			'sslverify' => true,
        			'user-agent' => BPI_USER_AGENT //'WooCommerce/' . $woocommerce->version . '; ' . get_site_url()
    			));

		if ( is_wp_error( $response ) ) throw new Exception( __( 'There was a problem connecting to the payment gateway.', 'woocommerce-gateway-bluepaid' ) );

		if( empty( $response['body'] ) ) throw new Exception( __( 'Empty bluepaid response.', 'woocommerce-gateway-bluepaid' ) );

		parse_str( $response['body'], $parsed_response );

		$response = $parsed_response;

	    $this->log( "Response:\n". print_r( $response, true ) );

	    // Interpret Response
	    if ( is_array( $response ) && in_array( 'VALID', array_keys( $response ) ) ) {
	    	return true;
	    } else {
	    	return false;
	    }
	} // End validate_responses_data()

	/**
	 * check_amounts()
	 *
	 * Checks if received amount is equal to sent amount
	 *
	 * @param $amount1 Float 1st amount for comparison
	 * @param $amount2 Float 2nd amount for comparison
	 * @since 1.0.0
	 */
	function check_amounts ( $amount1, $amount2 ) {
		if(number_format($amount1) !== number_format($amount2))return false;
		return true;
	} // End check_amounts()

} // End Class