<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Deema_Webhook_Handler.
 *
 * Handles webhooks from Deema on sources that are not immediately chargeable.
 *
 */
class WC_Deema_Webhook_Handler {
	
	public function __construct() {
		add_action( 'woocommerce_api_wc_deema', [ $this, 'check_for_webhook' ] );
	}

	/**
	 * Check incoming requests for Deema Webhook data and process them.
	 *
	 */
	public function check_for_webhook() {
        
		if ( ! isset( $_SERVER['REQUEST_METHOD'] )
			|| ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
		) {
			return;
		}

		$request_body = file_get_contents( 'php://input' );
		$data = json_decode($request_body,true);
		
		$response_data = array(
			'status' => 'error',
			'message' => 'No action performed',
		);

		if($data['status']=='captured')
		{
			$is_captured = $this->order_captured($data);
			if($is_captured){
				$response_data = array(
					'status' => 'success',
					'message' => 'Payment has been Captured',
				);
			}
			else{
				$response_data = array(
					'status' => 'success',
					'message' => 'Payment already Captured',
				);
			}
		}
		elseif($data['status']=='cancelled')
		{
			$message = $this->order_cancelled($data);
			$response_data = array(
				'status' => 'success',
				'message' => $message,
			);
		}
		

		$response_json = json_encode($response_data);			
		header('Content-Type: application/json');
		http_response_code(200);
		echo $response_json;
		exit;
	}

	public function order_captured($data){
		$purchase_id = $data['purchase_id'];
		global $wpdb;
        $table_name = $wpdb->prefix . 'deema_order_references';
        // Prepare and execute the SQL query to retrieve the row
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE deema_purchase_id = %s LIMIT 1", $purchase_id);
        $result = $wpdb->get_row($sql);
        if ($result) {
            // The order_id from the row is stored in the $order_id variable
            $order_id = $result->woocommerce_order_id;
            $transaction_id = $result->deema_purchase_id;
        } else {
            
            $order_id = null; // or any other default value
        }
        $order = wc_get_order($order_id);
		
		// Check if transaction id is already set mean the payment already captured and payment is complete in woocommerce
		$order_transaction_id = $order->get_transaction_id();
		if($order_transaction_id == $purchase_id)
		{
			return false;
		}
		else{
			$order->payment_complete($transaction_id);
			$order->save();
			return true;
		}
	}

	public function order_cancelled($data){
		$purchase_id = $data['purchase_id'];
		global $wpdb;
        $table_name = $wpdb->prefix . 'deema_order_references';
        // Prepare and execute the SQL query to retrieve the row
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE deema_purchase_id = %s LIMIT 1", $purchase_id);
        $result = $wpdb->get_row($sql);
        if ($result) {
            // The order_id from the row is stored in the $order_id variable
            $order_id = $result->woocommerce_order_id;
            $transaction_id = $result->deema_purchase_id;
        } else {
            
            $order_id = null; // or any other default value
        }
        $order = wc_get_order($order_id);
		
		// Check if transaction id is already set mean the payment already captured and payment is complete in woocommerce
		if ($order) {
			// Check if the order is in a cancellable state (e.g., "processing" or "on-hold")
			$current_status = $order->get_status();
			
			if (in_array($current_status, array('processing', 'on-hold'))) {
				// Update the order status to "cancelled"
				$order->update_status('cancelled');
				
				// Optionally, you can add a note to explain the reason for cancellation
				$note = 'Order cancelled from Deema';
				$order->add_order_note($note);
				
				// Save changes to the order
				$order->save();
				
				return 'Order has been cancelled.';
			} else {
				return 'Order cannot be cancelled in its current state.';
			}
		} else {
			return 'Order not found.';
		}
	}
}

new WC_Deema_Webhook_Handler();
