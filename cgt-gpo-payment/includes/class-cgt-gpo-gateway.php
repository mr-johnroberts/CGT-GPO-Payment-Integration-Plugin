<?php
if (!class_exists('WC_Payment_Gateway')) {
    return;
}

class CGT_GPO_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id                 = 'cgt_gpo';
        $this->method_title       = __('CGT GPO', 'cgt-gpo-gateway');
        $this->method_description = __('Allows GPO Webframe payments using CGT GPO Gateway.', 'cgt-gpo-gateway');
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title           = $this->get_option('title');
        $this->description     = $this->get_option('description');
        $this->enabled         = $this->get_option('enabled');
        $this->consumer_key    = $this->get_option('consumer_key');
        $this->consumer_secret = $this->get_option('consumer_secret');

        $this->supports = array('products', 'refunds', 'multiple_currencies');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_checkout_update_order_review', array($this, 'set_redirect_session'));
        add_action('woocommerce_before_checkout_form', array($this, 'maybe_redirect_on_place_order'));
        add_action('woocommerce_order_status_changed', array($this, 'update_order_origin'), 10, 4);
    }

    public function update_order_origin($order_id, $from_status, $to_status, $order)
    {
        if ($to_status === 'completed' && $order->get_payment_method() === $this->id) {
            update_post_meta($order_id, '_payment_gateway_origin', 'CGT GPO');
        }
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'cgt-gpo-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable CGT GPO Payment', 'cgt-gpo-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'cgt-gpo-gateway'),
                'type'        => 'text',
                'description' => __('Payment title displayed to the customer.', 'cgt-gpo-gateway'),
                'default'     => __('CGT GPO Payment', 'cgt-gpo-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'cgt-gpo-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description.', 'cgt-gpo-gateway'),
                'description' => __('<strong style="font-size: 15px;">Online Payment. Pay by MULTICAIXA Express or PAY ONLINE. Allow a few seconds to be redirected to the processing page after placing your order. We appreciate your patience. Thank you!</strong><br><br>The mobile number must be active in MCX Express. The confirmation/rejection of the purchase is done on MCX Express. After the introduction of the cell phone number in the solution, the customer should be aware of the notification you will receive on your phone, and that clicking on it will open the MCX Express to complete the payment.', 'cgt-gpo-gateway'),
                'desc_tip'    => true,
            ),
            'initial_token' => array(
                'title'       => __('Initial Token', 'cgt-gpo-gateway'),
                'type'        => 'text',
                'description' => __('Enter the initial token for CGT GPO Payment.', 'cgt-gpo-gateway'),
                'desc_tip'    => true,
            ),
            'webframe_url' => array(
                'title'       => __('Webframe URL', 'cgt-gpo-gateway'),
                'type'        => 'text',
                'description' => __('Enter the Webframe URL for CGT GPO Payment.', 'cgt-gpo-gateway'),
                'desc_tip'    => true,
            ),
            'consumer_key' => array(
                'title'       => __('Consumer Key', 'cgt-gpo-gateway'),
                'type'        => 'text',
                'description' => __('WooCommerce REST API consumer key.', 'cgt-gpo-gateway'),
                'desc_tip'    => true,
            ),
            'consumer_secret' => array(
                'title'       => __('Consumer Secret', 'cgt-gpo-gateway'),
                'type'        => 'text',
                'description' => __('WooCommerce REST API consumer secret.', 'cgt-gpo-gateway'),
                'desc_tip'    => true,
            ),
        );
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Log the order ID
        error_log('Order ID: ' . $order_id);

        WC()->session->set('cgt_gpo_order_id', $order_id);

        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $initial_token = $this->get_option('initial_token');
        $amount = $order->get_total();

        $request_body = array(
            'reference'   => $this->generate_gpo_reference($order_id),
            'amount'      => $amount,
            'token'       => $initial_token,
            'mobile'      => 'PAYMENT',
            'qrCode'      => 'PAYMENT',
            'callbackUrl' => home_url('/wp-json/teubiva/v1/payment-callback/'),
        );

        $response_data = $this->simulate_post_request($request_body);

        if (!empty($response_data) && isset($response_data['id'], $response_data['timeToLive'])) {
            $transaction_id = $response_data['id'];
            $time_to_live = $response_data['timeToLive'];

            // Save the GPO token for later use
            update_post_meta($order_id, '_gpo_transaction_id', $transaction_id);

            // Get the order key
            $order_key = $order->get_order_key();

            // Log the order key
            error_log('Order Key: ' . $order_key);

            error_log('GPO Callback - Transaction ID: ' . $transaction_id . ', Time to Live: ' . $time_to_live);

            $redirect_url = add_query_arg(array(
                'token' => $transaction_id,
                'key'   => $order_key,
                'order_id' => $order_id,
            ), home_url('/gpo-payment/'));

            // Redirect to the URL with the token and key parameters
            return array(
                'result'   => 'success',
                'redirect' => $redirect_url,
            );
        } else {
            // Log the response data for debugging
            error_log('Error processing payment: ' . json_encode($response_data));

            // Error handling based on the response data
            wc_add_notice('Error processing payment. Please try again.', 'error');
            return;
        }
    }


    private function simulate_post_request($request_body)
    {
        $url = 'https://pagamentonline.emis.co.ao/online-payment-gateway/webframe/v1/frameToken';

        $response = wp_safe_remote_post(
            $url,
            array(
                'body'    => wp_json_encode($request_body),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 30, // Increase timeout to 30 seconds
            )
        );

        if (is_wp_error($response)) {
            error_log('cURL error: ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $response_data = wp_remote_retrieve_body($response);
        $parsed_response = json_decode($response_data, true);

        return $parsed_response;
    }


    public function teubiva_process_payment_callback(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        // Log raw data
        $raw_data = file_get_contents('php://input');
        error_log('Payment Callback Raw Data: ' . $raw_data);

        // Log specific details from the request
        error_log('Request Method: ' . $request->get_method());
        error_log('Request Body: ' . json_encode($request->get_json_params()));
        error_log('Request Headers: ' . json_encode($request->get_headers()));
        error_log('Request Query Parameters: ' . json_encode($request->get_query_params()));

        $params = $request->get_json_params();

        $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';
        $transactionId = isset($params['id']) ? sanitize_text_field($params['id']) : '';
        $orderReference = isset($params['reference']['id']) ? sanitize_text_field($params['reference']['id']) : '';

        // Extract the order number from the GPO reference
        $order_number = intval(str_replace('twc_order_', '', $orderReference));

        // Log the extracted order number
        error_log('Extracted Order Number: ' . $order_number);

        // Get the order ID from the order number
        global $wpdb;
        $order_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_order_number' AND meta_value='%s'", $order_number));

        $gateway_instance = new CGT_GPO_Gateway();
        $order_id = $gateway_instance->get_order_id_by_reference($orderReference);

        if ($order_id) {
            $order = wc_get_order($order_id);

            error_log('Payment Callback Received - Order ID: ' . $order_id . ', Status: ' . $status);

            // Send a callback to the endpoint
            $callback_url = 'https://teubiva.com/wp-json/teubiva/v1/gpo-callback/';
            $callback_params = array(
                'status' => $status,
                'id' => $transactionId,
                'reference' => array('id' => $orderReference),
            );

            // Use wp_safe_remote_post to send the callback
            wp_safe_remote_post($callback_url, array(
                'body'    => wp_json_encode($callback_params),
                'headers' => array('Content-Type' => 'application/json'),
            ));

            if (strtolower($status) === 'accepted') {
                $gateway_instance->teubiva_process_purchase($order_id, $transactionId);

                $order->payment_complete();
                $order->update_status('completed');
            } elseif (strtolower($status) === 'rejected') {
                // Update order status to "Failed" for "REJECTED" status
                $error_message = isset($params['errorMessage']) ? sanitize_text_field($params['errorMessage']) : 'Unknown Error';
                $error_code = isset($params['errorCode']) ? sanitize_text_field($params['errorCode']) : '';
                $error_type = isset($params['errorType']) ? sanitize_text_field($params['errorType']) : '';

                $order->update_status('failed', sprintf('Payment Failed: %s (Code: %s, Type: %s)', $error_message, $error_code, $error_type));
            }
        }

        // Include the transactionId in the response
        return rest_ensure_response(array('status' => 'success', 'transactionId' => $transactionId));
    }



    private function simulate_gpo_callback_request($status, $transactionId, $orderReference)
    {
        $url = 'https://teubiva.com/wp-json/teubiva/v1/payment-callback/';

        $payload = array(
            'status' => $status,
            'id' => $transactionId,
            'reference' => array(
                'id' => $orderReference
            )
        );

        $response = wp_safe_remote_post($url, array(
            'body'    => wp_json_encode($payload),
            'headers' => array('Content-Type' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            error_log('Error in request: ' . $response->get_error_message());
        } else {
            // Log the response for debugging
            $response_data = wp_remote_retrieve_body($response);
            error_log('Response from the server: ' . $response_data);
        }
    }


    public function set_redirect_session($post_data)
    {
        if (WC()->session->get('cgt_gpo_redirected') !== 'yes') {
            WC()->session->set('cgt_gpo_redirected', 'yes');
        }
    }

    public function maybe_redirect_on_place_order()
    {
        if (is_checkout() && WC()->session->get('cgt_gpo_redirected') !== 'yes') {
?>
            <script type="text/javascript">
                jQuery(function($) {
                    $('#loading').show();
                    $.ajax({
                        url: '/get-token',
                        success: function(response) {
                            $('#loading').hide();
                            var token = response.token;
                            var url = "<?php echo esc_url(home_url('/gpo-payment/') . '?token='); ?>" + token;
                            document.cookie = 'cgt_gpo_redirected=yes;path=/;expires=' + new Date(Date.now() + 86400e3).toUTCString();
                            window.location.href = url; // Redirect to the URL with the token parameter
                        }
                    });
                });
            </script>
<?php
        }
    }


    // private function teubiva_process_purchase($order_id, $transaction_id, $status)
    // {
    //     $order = wc_get_order($order_id);

    //     // Update order status based on transaction result
    //     if (strtolower($status) === 'accepted') {
    //         // If the status is accepted, mark the order as completed
    //         if ($order->get_status() !== 'completed') {
    //             $order->update_status('completed');
    //         }
    //     } elseif (strtolower($status) === 'rejected') {
    //         // If the status is rejected, mark the order as failed
    //         if ($order->get_status() !== 'failed') {
    //             $order->update_status('failed');
    //         }
    //     }
    // }

    private function teubiva_process_purchase($order_id, $transaction_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_status() !== 'completed') {
            $order->update_status('completed');
        }
    }

    private function generate_gpo_reference($order_id)
    {
        return 'twc_order_' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
    }

    private function get_order_id_by_reference($reference)
    {
        $order_id = str_replace('twc_order_', '', $reference);
        return intval($order_id);
    }
}

?>