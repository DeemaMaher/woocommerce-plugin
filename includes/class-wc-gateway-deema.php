<?php
if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

if (!class_exists('WC_Payment_Gateway')) {

  return;
}
class WC_Gateway_Deema extends WC_Payment_Gateway {
  public function __construct() {
    $this->id = 'deema_payment';
    $this->method_title = 'Deema Payment';
    $this->title = 'Deema Payment';
    $this->has_fields = false;
    $this->credit_fields = false;
    $this->supports           = [
      'products',
      'refunds'
    ];
    $this->init_form_fields();
    $this->init_settings();

    // Get setting values.
    $this->enabled        = $this->get_option( 'enabled' );

    $this->title          = $this->get_option( 'title' );
    $this->description    = $this->get_option( 'description' );
    $this->instructions   = $this->get_option( 'instructions' );

    $this->sandbox        = $this->get_option( 'sandbox' );
    $this->api_key    = $this->sandbox == 'no' ? $this->get_option( 'api_key' ) : $this->get_option( 'sandbox_api_key' );
    $this->api_url    = $this->sandbox == 'no' ? 'https://api.deema.me/' : 'https://staging-api.deema.me/';

    $this->debug          = $this->get_option( 'debug' );

    // Logs.
    if( $this->debug == 'yes' ) {
      if( class_exists( 'WC_Logger' ) ) {
        $this->log = new WC_Logger();
      }
      else {
        $this->log = $woocommerce->logger();
      }
    }

    $this->callback		=  strtolower( get_class($this) );
    add_action( 'woocommerce_api_' . $this->callback , array( &$this, 'deema_payment_success' ) );
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_deema_payment' ) );
  }

  // Initialize form fields
  public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title'       => __( 'Enable/Disable', 'woocommerce-gateway-deema' ),
          'label'       => __( 'Enable Deema Payment', 'woocommerce-gateway-deema' ),
          'type'        => 'checkbox',
          'description' => '',
          'default'     => 'no'
        ),
        'title' => array(
          'title'       => __( 'Title', 'woocommerce-gateway-deema' ),
          'type'        => 'text',
          'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-deema' ),
          'default'     => __( 'Deema Payment', 'woocommerce-gateway-deema' ),
          'desc_tip'    => true
        ),
        'description' => array(
          'title'       => __( 'Description', 'woocommerce-gateway-deema' ),
          'type'        => 'text',
          'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-deema' ),
          'default'     => 'Pay with Deema Payment.',
          'desc_tip'    => true
        ),
        'instructions' => array(
          'title'       => __( 'Instructions', 'woocommerce-gateway-deema' ),
          'type'        => 'textarea',
          'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-gateway-deema' ),
          'default'     => '',
          'desc_tip'    => true,
        ),
        'debug' => array(
          'title'       => __( 'Debug Log', 'woocommerce-gateway-deema' ),
          'type'        => 'checkbox',
          'label'       => __( 'Enable logging', 'woocommerce-gateway-deema' ),
          'default'     => 'no',
          'description' => sprintf( __( 'Log Deema Payment events inside <code>%s</code>', 'woocommerce-gateway-deema' ), wc_get_log_file_path( $this->id ) )
        ),
        'sandbox' => array(
          'title'       => __( 'Sandbox', 'woocommerce-gateway-deema' ),
          'label'       => __( 'Enable Sandbox Mode', 'woocommerce-gateway-deema' ),
          'type'        => 'checkbox',
          'description' => __( 'Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'woocommerce-gateway-deema' ),
          'default'     => 'yes'
        ),
        'sandbox_api_key' => array(
          'title'       => __( 'Sandbox API Key', 'woocommerce-gateway-deema' ),
          'type'        => 'password',
          'description' => __( 'Get your Sandbox API keys from your Deema Payment account.', 'woocommerce-gateway-deema' ),
          'default'     => '',
          'desc_tip'    => true
        ),
        'api_key' => array(
          'title'       => __( 'API Key', 'woocommerce-gateway-deema' ),
          'type'        => 'password',
          'description' => __( 'Get your API keys from your Deema Payment account.', 'woocommerce-gateway-deema' ),
          'default'     => '',
          'desc_tip'    => true
        ),
        'webhook_info' => array(
          'title'       => __( 'Webhook', 'woocommerce-gateway-deema' ),
          'type'        => 'text',
          'disabled'    => true,
          'description' => __( 'Add this URL into the webhook at your deema dashbaord.', 'woocommerce-gateway-deema' ),
          'default'     => WC()->api_request_url('wc_deema'),
          'placeholder'     => WC()->api_request_url('wc_deema'),
          'desc_tip'    => true
        ),
      );
    }

  // Process the payment
  public function process_payment($order_id) {
    // Process the payment here (e.g., connect to a payment processor)

    // For this example, we'll simulate a successful payment
    $order = wc_get_order($order_id);

    $success_url = WC()->api_request_url($this->callback);
    // $success_url = add_query_arg( 'order_id' , $order_id, $api_request_url );
    // $this->get_return_url($order)

    $api_url = $this->api_url.'api/merchant/v1/purchase';
    $data = array(
        'amount' => $order->get_total(),
        'currency_code' => get_woocommerce_currency(),
        'merchant_order_id' => $order->get_order_number(),
        'merchant_urls' => array(
            'success' => $success_url,
            'failure' => $order->get_cancel_order_url(),
        ),
    );

    // Send the API request
    $response = $this->send_api_request($api_url, $data, 'POST');

    // Process the API response as needed
    if ($response) {
      $responseData = json_decode($response,true);
      global $wpdb;
      $table_name = $wpdb->prefix . 'deema_order_references';
      $order_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE woocommerce_order_id = %d", $order_id));

      if ($order_exists > 0) {
          // If the order ID exists, update the existing record
          $wpdb->update(
            $table_name,
            array(
                'woocommerce_order_number' => $order->get_order_number(),
                'deema_reference_number' => $responseData['data']['order_reference'],
                'deema_purchase_id' => $responseData['data']['purchase_id'],
            ),
            array('woocommerce_order_id' => $order_id), // Fixed the typo here
            array('%s', '%s', '%d'),
            array('%d') // The data type of the 'woocommerce_order_id' column
        );
      } else {
          // If the order ID doesn't exist, insert a new record
          $wpdb->insert(
              $table_name,
              array(
                  'woocommerce_order_id' => $order_id,
                  'woocommerce_order_number' => $order->get_order_number(),
                  'deema_reference_number' => $responseData['data']['order_reference'],
                  'deema_purchase_id' => $responseData['data']['purchase_id'],
              ),
              array('%d', '%s', '%s', '%d')
          );
      }
      return array(
        'result' => 'success',
        'redirect' => $responseData['data']['redirect_link'],
      );
    }
  }

  // Success return
  public function deema_payment_success() {
    // Get the result parameter from the query string
    $reference = isset($_GET['reference']) ? sanitize_text_field($_GET['reference']) : '';
    if ($reference) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'deema_order_references';
        // Prepare and execute the SQL query to retrieve the row
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE deema_reference_number = %s LIMIT 1", $reference);
        $result = $wpdb->get_row($sql);
        if ($result) {
            // The order_id from the row is stored in the $order_id variable
            $order_id = $result->woocommerce_order_id;
            $transaction_id = $result->deema_purchase_id;
        } else {
            // Handle the case where no matching row is found
            $order_id = null; // or any other default value
        }
        // Handle success action
        // You can use the order ID or any other information to mark the order as paid
        $order = wc_get_order($order_id);
        $order->payment_complete($transaction_id);
        // $thank_you_url = wc_get_checkout_url() . '?key=' . $order->get_order_key();
        $thank_you_url = $this->get_return_url($order);
        wp_redirect($thank_you_url);
    }
    else
    {
        // Handle fail action
        // You can show an error message to the user and redirect them to the cart or checkout page
        wc_add_notice('Payment failed. Please try again.', 'error');
        wp_safe_redirect(wc_get_cart_url());
    }

    exit;
  }

  public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			return new WP_Error( 'error', __( 'Refund failed.', 'woocommerce' ) );
		}

    $totalRefundAmount = $order->get_total_refunded();

    if ($order->get_total() == $totalRefundAmount) {
      $data = array();
      $api_url = $this->api_url .'api/merchant/v1/purchase/' . $order->get_transaction_id() . '/cancel';
    }
    else {
      $data = array(
        'amount' => $amount // Replace with the amount you want to refund
      );
      $api_url = $this->api_url .'api/merchant/v1/purchase/' . $order->get_transaction_id() . '/refund';
    }
    // Send the refund request using WooCommerce's REST API function
    $response = $this->send_api_request($api_url, $data, 'PUT');
    if ($response) {
      $responseData = json_decode($response,true);
      if($responseData['message'] == 'refund released')
      {
        return true;
      }
      elseif($responseData['message'] == 'cancelled')
      {
        return true;
      }
      else{
        throw new Exception( __( 'Something went wrong', 'woocommerce' ) );
      }
    }
    else
    {
      throw new Exception( __( 'Something went wrong', 'woocommerce' ) );
    }
	}

  public function disable_deema_payment($available_gateways){

    $currency_code = get_woocommerce_currency();
    $available_currencies= array("KWD", "BHD");
    if ( !in_array($currency_code, $available_currencies) ) {
      unset( $available_gateways[$this->id] );
    }
    return $available_gateways;
  }

  private function send_api_request($url, $data, $method) {
    $headers = array(
        'Authorization: Basic ' . $this->api_key,
        'Content-Type: application/json',
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Check if the request was successful (HTTP 200 OK)
    if ($http_code === 200) {
        return $response;
    } else {
        // Log or handle the error as needed
        return false;
    }
  }
}