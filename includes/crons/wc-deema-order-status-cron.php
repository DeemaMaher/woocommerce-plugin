<?php

// Cron job for order status

function deema_fetch_orders_within_last_hour() {
	global $wpdb;


    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {

		$one_hour = strtotime('-10 hour');

		$args = array(
			'date_query' => array(
				array(
					'after' => date('Y-m-d H:i:s', $one_hour),
				),
			),
			'post_type' => 'shop_order',
			'post_status' => 'any', // You can change this to the desired order status
			'posts_per_page' => -1, // Retrieve all orders
		);
		$orders = wc_get_orders($args);
        // Process the retrieved orders as needed
        foreach ($orders as $order) {
			$orderId = $order->get_id();
			// Get the payment method code
			$payment_method = $order->get_payment_method();

			if($orderId && $payment_method == 'deema_payment')
			{
				$table_name = $wpdb->prefix . 'deema_order_references';
				// Prepare the SQL query to fetch data based on the WooCommerce order number
				$query = $wpdb->prepare(
					"SELECT * FROM $table_name WHERE woocommerce_order_number = %s",
					$orderId
				);

				// Run the SQL query
				$result = $wpdb->get_results($query);

				// Check if there are matching records
				if ($result) {
					foreach ($result as $row) {
						// Access data from the custom table
						$deemaReferenceNum = $row->deema_reference_number;
						$deemaPurchaseId = $row->deema_purchase_id;

						if($deemaReferenceNum)
						{
							$deema = new WC_Gateway_Deema();							// Retrieve the payment method configuration settings
							$deema_api_key = $deema->api_key;
							$deema_api_url = $deema->api_url.'api/merchant/v1/purchase/status?order_reference='.$deemaReferenceNum;
							$curl = curl_init();

							$headers = array(
								'Authorization: Basic ' . $deema_api_key,
							);
							curl_setopt_array($curl, array(
							CURLOPT_URL => $deema_api_url,
							CURLOPT_RETURNTRANSFER => true,
							CURLOPT_ENCODING => '',
							CURLOPT_MAXREDIRS => 10,
							CURLOPT_TIMEOUT => 0,
							CURLOPT_FOLLOWLOCATION => true,
							CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
							CURLOPT_CUSTOMREQUEST => 'GET',
							CURLOPT_HTTPHEADER => $headers,
							));

							$response = curl_exec($curl);
							echo $response;
							$responseData = json_decode($response,true);

							curl_close($curl);
							$orderData = wc_get_order($orderId);
							$current_status = $orderData->get_status();
							if($responseData['data']['status'] == 'captured'){
								if($current_status != 'completed'){
									$orderData->payment_complete($deemaPurchaseId);
									$orderData->save();
								}

							}
							elseif($responseData['data']['status'] == 'cancelled' || $responseData['data']['status'] == 'expired'){

								if (in_array($current_status, array('processing', 'on-hold'))) {
									// Update the order status to "cancelled"
									$orderData->update_status('cancelled');

									// Optionally, you can add a note to explain the reason for cancellation
									$note = 'Order cancelled from Deema';
									$orderData->add_order_note($note);

									// Save changes to the order
									$orderData->save();

									return 'Order has been cancelled.';
								} else {
									return 'Order cannot be cancelled in its current state.';
								}
							}
						}

					}
				} else {
					// No matching records found
					echo "No matching records found for order ID: $orderId";
				}

			}


        }

    }
}

function deema_schedule_custom_cron() {
    if (!wp_next_scheduled('deema_custom_cron')) {
        wp_schedule_event(time(), '5minutes', 'deema_custom_cron');
    }
}
add_action('wp', 'deema_schedule_custom_cron');


function deema_custom_cron_intervals($schedules) {
    $schedules['5minutes'] = array(
        'interval' => 300, // 300 seconds = 5 minutes
        'display'  => __('Every 5 Minutes', 'deema'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'deema_custom_cron_intervals');

add_action('deema_custom_cron', 'deema_fetch_orders_within_last_hour');
